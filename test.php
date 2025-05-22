<?php
$dsn = 'mysql:host=localhost;dbname=u1';
$username = 'root';
$password = '';
try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Ошибка подключения: " . $e->getMessage();
}


$items = [
    ['productId' => 543, 'newQty' => 14],
    ['productId' => 478, 'newQty' => 378]
    
];
displayCascadeBatchResult($items, 1 /*warehouseId*/, $pdo);

function displayCascadeBatchResult(array $items, int $warehouseId, PDO $pdo): void {
    // Вызываем функцию расчёта
    $batch = buildCascade($pdo, $items, $warehouseId);
    echo "<pre>";
    print_r($batch);
    echo "</pre>";
    $old   = $batch['old'];
    $new   = $batch['new'];

    if (empty($old)) {
        echo "Нет изменений в остатках.";
        return;
    }

    // Получаем offer_id для всех product_id одним запросом
    $productIds = array_keys($old);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, offer_id
          FROM product
         WHERE id IN ($placeholders)
    ");
    $stmt->execute($productIds);
    // Получим карту product_id => offer_id
    $offerMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Выводим построчно
    foreach ($old as $productId => $oldQty) {
        $offerId = $offerMap[$productId] ?? $productId;
        $newQty  = $new[$productId] ?? $oldQty;
        echo htmlspecialchars($offerId, ENT_QUOTES, 'UTF-8')
           . ': '
           . $oldQty
           . ' -> '
           . $newQty
           . "<br>\n";
    }
}


    // Обрабатывает push-уведомление ORDER_CREATED от Яндекс.Маркета:
    function handleYandexOrder($pdo, array $notification): array
    {

        if (($notification['notificationType'] ?? '') !== 'ORDER_CREATED') {
            return ['success' => false, 'message' => 'Unsupported notificationType'];
        }

        // 1) Находим внутренний склад
        $stmt = $pdo->prepare(
            'SELECT warehouse_id 
               FROM marketplace_warehouse 
              WHERE marketplace_warehouse_id = :campaignId'
        );
        $stmt->execute([':campaignId' => (int)$notification['campaignId']]);
        $warehouseId = $stmt->fetchColumn();
        if (!$warehouseId) {
            return ['success' => false, 'message' => 'Unknown campaignId'];
        }

        // 2) Для каждого item вычисляем новый остаток
        $itemsArray = [];
        foreach ($notification['items'] as $it) {
            $offerId = $it['offerId'];
            $count   = (int)$it['count'];

            // находим product_id
            $p = $pdo->prepare(
                'SELECT id FROM product WHERE offer_id = :off'
            );
            $p->execute([':off' => $offerId]);
            $productId = (int)$p->fetchColumn();
            if (!$productId) {
                return ['success' => false, 'message' => "Unknown offerId {$offerId}"];
            }

            // читаем старый остаток
            $s = $pdo->prepare(
                'SELECT quantity FROM stock WHERE product_id = :pid AND warehouse_id = :wid'
            );
            $s->execute([
                ':pid' => $productId,
                ':wid' => $warehouseId
            ]);
            $oldQty = (int)$s->fetchColumn();

            // вычисляем новый
            $newQty = max(0, $oldQty - $count);

            $itemsArray[] = [
                'productId' => $productId,
                'newQty'    => $newQty
            ];
        }
        echo "<pre>itemsArray:<br>";
        print_r($itemsArray);
        echo "</pre>";
        // 3) Вызываем каскадное обновление с признаком «из Яндекс.Маркета»
        return updateStock($pdo, $itemsArray, (int)$warehouseId, 3  // sourcePlatformId = 3 для Яндекс.Маркета
        );
    }

function updateStock($pdo, array $itemsArray, int $warehouseId, ?int  $sourcePlatformId = null) {
    // типы движений
    $mtMap = [ null=>2, 1=>3, 2=>4, 3=>5 ];
    $mtDirect  = $mtMap[$sourcePlatformId] ?? 2;
    $mtCascade = 6;

    // 1) каскадный batch: получаем старые и новые остатки
    ['old' => $oldMap, 'new' => $newMap] = buildCascade($pdo, $itemsArray, $warehouseId);

    echo "<pre>old:<br>";
    print_r($oldMap);
    echo "</pre>";

    echo "<pre>new:<br>";
    print_r($newMap);
    echo "</pre>";

    // 2) Вычисление реальных изменений и directPids
    $inputMap    = [];
    foreach ($itemsArray as $it) {
        $inputMap[$it['productId']] = $it['newQty'];
    }
    $directPids = [];
    $changes    = [];
    foreach ($newMap as $pid => $calcNew) {
        $oldQty = $oldMap[$pid] ?? 0;
        if ($calcNew !== $oldQty) {
            $changes[$pid] = $calcNew;
            // если каскад не тронул переданный newQty — это direct
            if (isset($inputMap[$pid]) && $inputMap[$pid] === $calcNew) {
                $directPids[] = $pid;
            }
        }
    }
    if (empty($changes)) {
        return ['success'=>true];
    }

    echo "<pre>directPids:<br>";
    print_r($directPids);
    echo "</pre>";

    echo "<pre>changes:<br>";
    print_r($changes);
    echo "</pre>";
   
    // 3) Загружаем связи склад→маркетплейсы
    $sellerId = 1;

    $stmt = $pdo->prepare("
        SELECT ms.platform_id, 
               mw.marketplace_warehouse_id, 
               ms.token, 
               ms.client_id
          FROM marketplace_warehouse mw
          JOIN marketplace_seller ms
            ON mw.marketplace_seller_id = ms.id
         WHERE mw.warehouse_id = :wid
           AND ms.seller_id = :sid
        ");
    $stmt->execute([':wid' => $warehouseId, ':sid' => $sellerId]);
    $conns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4) разбиваем на batch по connection
    $batches = [];
    foreach ($conns as $c) {
        $key = "{$c['platform_id']}|{$c['marketplace_warehouse_id']}|{$c['token']}|{$c['client_id']}";
        if (!isset($batches[$key])) {
            $batches[$key] = ['conn' => $c, 'items' => []];
        }
        foreach ($changes as $pid => $qty) {
            $batches[$key]['items'][] = ['pid'=>$pid, 'qty'=>$qty];
        }
    }

    // 5) отправка на площадки, пропуская direct для sourcePlatformId
    $completed = [];
    $error = null;
    foreach ($batches as $key => $batch) {
        $c   = $batch['conn'];
        $plat= (int)$c['platform_id'];

        // фильтруем для sourcePlatformId
        $toSend = $batch['items'];
        if ($sourcePlatformId !== null && $plat === $sourcePlatformId) {
            $toSend = array_filter($toSend, fn($it) => !in_array($it['pid'],$directPids));
        }
        if (empty($toSend)) {
            $completed[] = $key;
            continue;
        }
        
        switch ($plat) {
            case 1: // Ozon
                $payload = array_map(fn($it)=>[
                    'offer_id'     => getOfferId($pdo, $it['pid']),
                    'stock'        => $it['qty'],
                    'warehouse_id' => $c['marketplace_warehouse_id']
                ], $toSend);
                echo "<pre>Ozon:".count($payload)."</pre>";
                /*
                $resp = OzonService::updateStocks($c['client_id'], $c['token'], $payload);
                if (!isset($resp['result'])) {
                    $error = 'Ozon: '.($resp['message'] ?? 'no result');
                    break 2;
                }
                foreach ($resp['result'] as $r) {
                    if (empty($r['updated'])) {
                        $error = 'Ozon failed '.$r['offer_id'] ?? '';
                        break 2;
                    }
                }*/
                break;
            case 2: // Wildberries
                $payload = array_map(fn($it)=>[
                    'sku'    => getWbBarcode($pdo, $it['pid']),
                    'amount' => $it['qty']
                ], $toSend);
                echo "<pre>Wildberries:".count($payload)."</pre>";
                /*
                $resp = WildberriesService::updateStocks(
                    $c['token'], $c['marketplace_warehouse_id'], $payload
                );
                if (!isset($resp['http_code']) || $resp['http_code'] !== 204) {
                    $error = 'WB: '.($resp['message'] ?? $resp['detail'] ?? 'error');
                    break 2;
                }*/
                break;
            case 3: // Yandex
                $payload = array_map(fn($it)=>[
                    'sku'   => getOfferId($pdo, $it['pid']),
                    'items' => [['count'=>$it['qty']]]
                ], $toSend);

                echo "<pre>Yandex:".count($payload)."</pre>";
                /*
                $resp = YandexService::updateStocks(
                    $c['marketplace_warehouse_id'], $c['token'], $payload
                );
                if (($resp['data']['status'] ?? '') !== 'OK') {
                    $error = 'YM: '.$resp['errors']['message'] ?? 'error';
                    break 2;
                }*/
                break;
        }
        $completed [] = $key;
    }

    // 6) Откат при ошибке
    /*
    if ($error !== null) {
        foreach ($completed as $key) {
            $batch = $batches[$key];
            $c     = $batch['conn'];
            $revert = array_map(fn($it)=>[
                'offer_id'     => $this->getOfferId($it['id']),
                'stock'        => $old[$it['id']],
                'warehouse_id' => $c['marketplace_warehouse_id']
            ], $batch['items']);

            try {
                if ((int)$c['platform_id'] === 1) {
                    OzonService::updateStocks($c['client_id'], $c['token'], $revert);
                } elseif ((int)$c['platform_id'] === 2) {
                    $wb = array_map(fn($it)=>['sku'=>$it['offer_id'],'amount'=>$it['stock']], $revert);
                    WildberriesService::updateStocks($c['token'], $c['marketplace_warehouse_id'], $wb);
                } else {
                    YandexService::updateStocks($c['marketplace_warehouse_id'], $c['token'], $revert);
                }
            } catch (\Throwable $ignore) {}
        }
        return ['success'=>false,'message'=>$error];
    }*/
    
    // 7) Записываем только изменившиеся в локальную БД
    /*$this->pdo->beginTransaction();
    // 7.1) Обновление таблицы stock
    $stStock = $this->pdo->prepare("
        INSERT INTO stock (product_id,warehouse_id,quantity,updated_at)
        VALUES (:pid,:wid,:qty,NOW())
        ON DUPLICATE KEY UPDATE quantity = :qty, updated_at = NOW()
    ");
    // 7.2) Подготовка записи в stock_movement
    $stMov = $this->pdo->prepare("
        INSERT INTO stock_movement (product_id, warehouse_id, old_quantity, new_quantity, movement_type_id, created_at)
        VALUES (:pid, :wid, :old, :new, :mt, NOW())
    ");

    foreach ($changes as $pid => $newQty) {
        $mt = in_array($pid, $directPids) ? $mtDirect : $mtCascade;
        $stStock->execute([
            ':pid'=>$pid, ':wid'=>$warehouseId, ':qty'=>$newQty
        ]);
        $stMov->execute([
            ':pid'=>$pid,
            ':wid'=>$warehouseId,
            ':old'=>$oldMap[$pid] ?? 0,
            ':new'=>$newQty,
            ':mt' =>$mt
        ]);
    }

    $this->pdo->commit();*/

    return ['success'=>true];
}

/**
 * Считает «old» и «new» остатки с учётом multi-parent logic.
 *
 * @param array $items       [ ['productId'=>int, 'newQty'=>int], … ]
 * @param int   $warehouseId
 * @return array             ['old'=>[pid=>oldQty], 'new'=>[pid=>newQty]]
 */
function buildCascade($pdo, array $items, int $warehouseId): array {
    $old            = [];   // pid => oldQty
    $candidates     = [];   // pid => [candidate1, candidate2, …]
    $spuskUpParents = [];   // parentId => totalUnitsEaten

    // --- Подготовка запросов ---
    $qStock = $pdo->prepare("
        SELECT quantity
          FROM stock
         WHERE product_id = ? AND warehouse_id = ?
    ");
    $qParents = $pdo->prepare("
        SELECT ps.product_id AS parentId, ps.quantity AS coef
          FROM product p
          JOIN product_set ps ON p.product_set_id = ps.id
         WHERE p.id = ?
    ");
    $qChildren = $pdo->prepare("
        SELECT p.id        AS childId, ps.quantity AS coef
          FROM product_set ps
          JOIN product p ON p.product_set_id = ps.id
         WHERE ps.product_id = ?
    ");

    $getOld = function(int $pid) use ($qStock, $warehouseId) {
        $qStock->execute([$pid, $warehouseId]);
        return (int)$qStock->fetchColumn();
    };

    // --- 1) Входные: читаем old, ставим inputNew, считаем подъём к родителям ---
    $inputNewMap = [];
    foreach ($items as $it) {
        $pid     = (int)$it['productId'];
        $wantNew = (int)$it['newQty'];
        $inputNewMap[$pid] = $wantNew;

        $oldQty = $getOld($pid);
        $old[$pid] = $oldQty;
        $candidates[$pid][] = $wantNew;

        $delta = $oldQty - $wantNew;
        if ($delta !== 0) {
            $qParents->execute([$pid]);
            while ($r = $qParents->fetch(PDO::FETCH_ASSOC)) {
                $par  = (int)$r['parentId'];
                $coef = (int)$r['coef'];
                $spuskUpParents[$par] = ($spuskUpParents[$par] ?? 0) + $delta * $coef;
            }
        }
    }

    // --- 2) Спуск вниз для входных композитов (один уровень) ---
    foreach ($items as $it) {
        $pid     = (int)$it['productId'];
        $wantNew = $inputNewMap[$pid];

        $qChildren->execute([$pid]);
        while ($r = $qChildren->fetch(PDO::FETCH_ASSOC)) {
            $cid  = (int)$r['childId'];
            $coef = (int)$r['coef'];
            if (!isset($old[$cid])) {
                $old[$cid] = $getOld($cid);
            }
            $candidates[$cid][] = (int)floor($wantNew / $coef);
        }
    }

    // --- 3) Пересчёт пересчитанных родителей ---
    foreach ($spuskUpParents as $parId => $eaten) {
        if (!isset($old[$parId])) {
            $old[$parId] = $getOld($parId);
        }
        $newPar = $old[$parId] - $eaten;
        $candidates[$parId][] = $newPar;
    }

    // --- 4) Спуск вниз от этих родителей (один уровень) ---
    foreach (array_keys($spuskUpParents) as $parId) {
        $newPar = min($candidates[$parId]);
        $qChildren->execute([$parId]);
        while ($r = $qChildren->fetch(PDO::FETCH_ASSOC)) {
            $cid  = (int)$r['childId'];
            $coef = (int)$r['coef'];
            if (!isset($old[$cid])) {
                $old[$cid] = $getOld($cid);
            }
            $candidates[$cid][] = (int)floor($newPar / $coef);
        }
    }

    // --- 5) Multi-parent fix с защитой от пустого списка родителей ---
    foreach (array_keys($candidates) as $pid) {
        // пропускаем входные — у них уже есть inputNew
        if (isset($inputNewMap[$pid])) {
            continue;
        }

        // сначала получим всех родителей в виде массива
        $qParents->execute([$pid]);
        $parents = $qParents->fetchAll(PDO::FETCH_ASSOC);
        if (count($parents) === 0) {
            // если родителей нет — не трогаем кандидатов
            continue;
        }

        // иначе формируем новый список кандидатов только от родителей
        $parentCands = [];
        foreach ($parents as $r) {
            $par  = (int)$r['parentId'];
            $coef = (int)$r['coef'];
            // выбираем новый остаток родителя: либо пересчитан, либо старый
            $parQty = in_array($par, array_keys($spuskUpParents), true)
                ? min($candidates[$par])
                : ($old[$par] ?? $getOld($par));
            $parentCands[] = (int)floor($parQty / $coef);
        }
        $candidates[$pid] = $parentCands;
    }

    // --- 6) Финальный сбор old/new ---
    $oldFinal = [];
    $newFinal = [];
    foreach ($candidates as $pid => $list) {
        $oldFinal[$pid] = $old[$pid];
        $newFinal[$pid] = min($list);
    }

    return ['old' => $oldFinal, 'new' => $newFinal];
}

    // Хелперы для SKU/Barcode
    function getOfferId($pdo, int $pid): string {
        return $pdo
            ->query("SELECT offer_id FROM product WHERE id = {$pid}")
            ->fetchColumn();
    }
    
    function getWbBarcode($pdo, int $pid): string {
        return $pdo
            ->query("SELECT wb_barcode FROM product WHERE id = {$pid}")
            ->fetchColumn();
    }

?>
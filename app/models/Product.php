<?php
require_once __DIR__ . '/../services/OzonService.php';
require_once __DIR__ . '/../services/WildberriesService.php';
require_once __DIR__ . '/../services/YandexService.php';
require_once __DIR__ . '/../helpers/MessageHelper.php';

class Product {

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Получить список продуктов с учётом пагинации и поиска
    public function getProducts(
        int $sellerId, 
        int $limit, 
        int $offset, 
        ?string $search,
        string $sortBy,
        string $sortDir
    ): array {
        $map = [
            'id'           => 'p.id',
            'offer_id'     => 'p.offer_id',
            'name'         => 'p.name',
            'cost'         => 'p.cost',
            'volume_l'     => 'volume_l',
            'weight_kg'    => 'weight_kg',
            'total_stock'  => 'total_stock',
        ];

        $orderBy = sprintf(
            '%s %s',
            $map[$sortBy] ?? 'p.id',
            strtoupper($sortDir)
        );

        $sql = "
            SELECT 
                p.id,
                p.offer_id,
                p.name,
                -- если собственная cost=0 и есть product_set_id, считаем из компонентов, иначе p.cost
                CASE
                WHEN p.cost = 0 AND p.product_set_id IS NOT NULL THEN 
                    COALESCE((
                    SELECT SUM(ps.quantity * c.cost)
                        FROM product_set ps
                        JOIN product c ON c.id = ps.product_id
                    WHERE ps.id = p.product_set_id
                    ), 0)
                ELSE p.cost
                END AS cost,
                ROUND((p.length*p.width*p.height)/1000000,2) AS volume_l,
                ROUND(p.weight/1000,2) AS weight_kg,
                COALESCE(SUM(s.quantity),0) AS total_stock
            FROM product p
            LEFT JOIN stock s ON s.product_id = p.id
            WHERE p.seller_id = :sellerId
        ";
        if ($search) {
            $sql .= " AND (p.offer_id LIKE :search OR p.name LIKE :search) ";
        }
        $sql .= " GROUP BY p.id
                  ORDER BY {$orderBy}
                  LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sellerId', $sellerId, PDO::PARAM_INT);
        if ($search) {
            $stmt->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Получить остатки по складам для конкретного товара
    public function getStockByWarehouses(int $productId): array {
        $sql = "
            SELECT w.id AS warehouse_id, w.name AS warehouse_name, s.quantity
            FROM warehouse w
            LEFT JOIN stock s ON s.warehouse_id = w.id AND s.product_id = :productId
            WHERE w.seller_id = (SELECT seller_id FROM product WHERE id = :productId)
            ORDER BY w.name
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':productId' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductById(int $id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, offer_id, name, cost, length, width, height, weight, product_set_id
            FROM product
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Получить состав набора для товара
    public function getProductSet(int $productSetId): array {
        $sql = "
            SELECT ps.id AS set_item_id, ps.product_id AS component_id, p.name AS component_name, ps.quantity
            FROM product_set ps
            JOIN product p ON p.id = ps.product_id
            WHERE ps.id = :productSetId
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':productSetId' => $productSetId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStock(array $itemsArray, int $warehouseId, ?int  $sourcePlatformId = null): array {
        // типы движений
        $mtMap = [ null=>2, 1=>3, 2=>4, 3=>5 ];
        $mtDirect  = $mtMap[$sourcePlatformId] ?? 2;
        $mtCascade = 6;

        // 1) каскадный batch: получаем старые и новые остатки
        ['old' => $oldMap, 'new' => $newMap] = $this->buildCascade($itemsArray, $warehouseId);
        
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
    
        // 3) Загружаем связи склад→маркетплейсы
        $stmt = $this->pdo->prepare("
            SELECT ms.platform_id, 
                mw.marketplace_warehouse_id, 
                ms.token, 
                ms.client_id
            FROM marketplace_warehouse mw
            JOIN marketplace_seller ms
                ON mw.marketplace_seller_id = ms.id
            WHERE mw.warehouse_id = :wid
            ");
        $stmt->execute([':wid' => $warehouseId]);
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
                    $payload = array_values(array_map(fn($it)=>[
                        'offer_id'     => $this->getOfferId($it['pid']),
                        'stock'        => $it['qty'],
                        'warehouse_id' => $c['marketplace_warehouse_id']
                    ], $toSend));
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
                    }
                    break;
                case 2: // Wildberries
                    $payload = array_values(array_map(fn($it)=>[
                        'sku'    => $this->getWbBarcode($it['pid']),
                        'amount' => $it['qty']
                    ], $toSend));
                    $resp = WildberriesService::updateStocks(
                        $c['token'], $c['marketplace_warehouse_id'], $payload
                    );
                    if (!isset($resp['http_code']) || $resp['http_code'] !== 204) {
                        $error = 'WB: '.($resp['message'] ?? $resp['detail'] ?? 'error');
                        break 2;
                    }
                    break;
                case 3: // Yandex
                    $payload = array_values(array_map(fn($it)=>[
                        'sku'   => $this->getOfferId($it['pid']),
                        'items' => [['count'=>$it['qty']]]
                    ], $toSend));
                    $resp = YandexService::updateStocks(
                        $c['marketplace_warehouse_id'], $c['token'], $payload
                    );
                    if (($resp['data']['status'] ?? '') !== 'OK') {
                        $error = 'YM: '.$resp['errors']['message'] ?? 'error';
                        break 2;
                    }
                    break;
            }
            $completed [] = $key;
        }

        // 6) Откат при ошибке
        if ($error !== null) {
            MessageHelper::log_message("Ошибка при обновлении: $error. Начинаем откат ".count($completed)." пакетов");

            foreach ($completed as $key) {
                list($platId, $mwid, $token, $client) = explode('|', $key);
                $batchItems = $batches[$key]['items'];

                // строим revert-пayload
                $revert = [];
                foreach ($batchItems as $it) {
                    $revert[] = [
                        'offer_id'     => $this->getOfferId($it['pid']),
                        'stock'        => $oldMap[$it['pid']],
                        'warehouse_id' => $mwid
                    ];
                }

                try {
                    switch ((int)$platId) {
                        case 1: // OZON
                            MessageHelper::log_message("Revert OZON batch for warehouse $mwid: " . json_encode($revert));
                            OzonService::updateStocks($client, $token, $revert);
                            MessageHelper::log_message("Revert OZON succeeded for key $key");
                            break;

                        case 2: // Wildberries
                            // Wildberries payload отличается по ключам
                            $wbRevert = array_map(fn($r)=>[
                                'sku'    => $r['offer_id'],
                                'amount' => $r['stock']
                            ], $revert);
                            MessageHelper::log_message("Revert WB batch for warehouse $mwid: " . json_encode($wbRevert));
                            WildberriesService::updateStocks($token, $mwid, $wbRevert);
                            MessageHelper::log_message("Revert WB succeeded for key $key");
                            break;

                        case 3: // Yandex.Market
                            // Yandex тоже принимает структуру items
                            $ymRevert = array_map(fn($r)=>[
                                'sku'   => $r['offer_id'],
                                'items' => [['count' => $r['stock']]]
                            ], $revert);
                            MessageHelper::log_message("Revert YM batch for warehouse $mwid: " . json_encode($ymRevert));
                            YandexService::updateStocks($mwid, $token, $ymRevert);
                            MessageHelper::log_message("Revert YM succeeded for key $key");
                            break;
                    }
                } catch (\Throwable $ex) {
                    MessageHelper::log_message("Ошибка при откате ключа $key: " . $ex->getMessage());
                    // продолжаем откатывать остальные, не прерываем цикл
                }
            }

            return ['success' => false, 'message' => $error];
        }
        
        // 7) Записываем только изменившиеся в локальную БД
        $this->pdo->beginTransaction();
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

        $this->pdo->commit();

        return ['success'=>true];
    }

    function buildCascade(array $items, int $warehouseId): array {
        $pdo = $this->pdo;
        $old           = [];
        $candidates    = [];  // productId => [candidate1, candidate2, …]
        $spuskUpParents= [];  // parentId => totalUnitsEaten
    
        // Подготовка запросов
        $qStock = $pdo->prepare("
            SELECT quantity
              FROM stock
             WHERE product_id = ? AND warehouse_id = ?
        ");
        $qParents = $pdo->prepare("
            SELECT ps.product_id AS parentId, ps.quantity AS coef
              FROM product p
              JOIN product_set ps
                ON p.product_set_id = ps.id
             WHERE p.id = ?
        ");
        $qChildren = $pdo->prepare("
            SELECT p.id        AS childId,
                   ps.quantity AS coef
              FROM product_set ps
              JOIN product p
                ON p.product_set_id = ps.id
             WHERE ps.product_id = ?
        ");
    
        // Вспомогательная лямбда для чтения старого остатка
        $getOld = function(int $pid) use ($qStock, $warehouseId) {
            $qStock->execute([$pid, $warehouseId]);
            return (int)$qStock->fetchColumn();
        };
    
        // 1) Обрабатываем входные: запоминаем old + кандидат inputNew + считаем подъём у их родителей
        foreach ($items as $it) {
            $pid     = (int)$it['productId'];
            $wantNew = (int)$it['newQty'];
    
            $oldQty = $getOld($pid);
            $old[$pid] = $oldQty;
            // гарантируем, что inputNew защищает себя сверху
            $candidates[$pid][] = $wantNew;
    
            // подъём к родителям (если есть)
            $deltaUnits = $oldQty - $wantNew;
            if ($deltaUnits !== 0) {
                $qParents->execute([$pid]);
                while ($r = $qParents->fetch(PDO::FETCH_ASSOC)) {
                    $par  = (int)$r['parentId'];
                    $coef = (int)$r['coef'];
                    $spuskUpParents[$par] = ($spuskUpParents[$par] ?? 0) + $deltaUnits * $coef;
                }
            }
        }
    
        // 2) Спуск вниз для входных композитов (один уровень)
        foreach ($items as $it) {
            $pid     = (int)$it['productId'];
            $wantNew = (int)$it['newQty'];
    
            // если есть дети — считаем для них floor(inputNew/coef)
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
    
        // 3) Пересчитываем всех родителей, где изменился входной атомарный
        foreach ($spuskUpParents as $parId => $unitsEaten) {
            if (!isset($old[$parId])) {
                $old[$parId] = $getOld($parId);
            }
            $newPar = $old[$parId] - $unitsEaten;
            $candidates[$parId][] = $newPar;
        }
    
        // 4) Спуск вниз от пересчитанных родителей (один уровень)
        foreach (array_keys($spuskUpParents) as $parId) {
            // новый остаток родителя — минимум кандидатов
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
    
        // 5) Собираем итог: для каждого productId — old и min(candidates)
        $oldFinal = [];
        $newFinal = [];
        foreach ($candidates as $pid => $list) {
            $oldFinal[$pid] = $old[$pid];
            $newFinal[$pid] = min($list);
        }
    
        return ['old' => $oldFinal, 'new' => $newFinal];
    }

    // Хелперы для SKU/Barcode
    private function getOfferId(int $pid): string {
        return $this->pdo
            ->query("SELECT offer_id FROM product WHERE id = {$pid}")
            ->fetchColumn();
    }
    private function getWbBarcode(int $pid): string {
        return $this->pdo
            ->query("SELECT wb_barcode FROM product WHERE id = {$pid}")
            ->fetchColumn();
    }

    // Удалить товар
    public function deleteProduct(int $productId): bool {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                DELETE FROM product
                WHERE id = :pid
            ");
            $stmt->execute([':pid' => $productId]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    // Обновить основные данные товара и его состав (набор)
    public function updateProduct(int $id, array $data, array $setItems): bool {
        try {
            $this->pdo->beginTransaction();

            // 1) Обновляем основные поля товара (кроме product_set_id)
            $sql = "
                UPDATE product
                   SET offer_id = :offer,
                       name     = :name,
                       cost     = :cost,
                       length   = :length,
                       width    = :width,
                       height   = :height,
                       weight   = :weight
                 WHERE id = :id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':offer'  => $data['offer_id'],
                ':name'   => $data['name'],
                ':cost'   => $data['cost'],
                ':length' => $data['length'],
                ':width'  => $data['width'],
                ':height' => $data['height'],
                ':weight' => $data['weight'],
                ':id'     => $id,
            ]);

            // 2) Узнаём текущий product_set_id
            $stmt = $this->pdo->prepare("
                SELECT product_set_id
                  FROM product
                 WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);
            $setId = $stmt->fetchColumn(); // может быть null

            // 3) Если новый набор пустой — удаляем старые строки и сбрасываем setId
            if (empty($setItems)) {
                if ($setId) {
                    $del = $this->pdo->prepare("
                        DELETE FROM product_set
                        WHERE id = :psid
                    ");
                    $del->execute([':psid' => $setId]);
                }
                // сброс в product
                $upd = $this->pdo->prepare("
                    UPDATE product
                       SET product_set_id = NULL
                     WHERE id = :id
                ");
                $upd->execute([':id' => $id]);
            }
            else {
                // 4) Если набора ещё не было — создаём новый setId
                if (!$setId) {
                    // генерируем новый идентификатор группы
                    $row = $this->pdo
                        ->query("SELECT COALESCE(MAX(id),0)+1 AS nxt FROM product_set")
                        ->fetch(PDO::FETCH_ASSOC);
                    $setId = (int)$row['nxt'];
                }

                // 5) Загружаем существующие строки набора в map: product_id => quantity
                $stmt = $this->pdo->prepare("
                    SELECT product_id, quantity
                      FROM product_set
                     WHERE id = :psid
                ");
                $stmt->execute([':psid' => $setId]);
                $existing = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);


                // 6) Синхронизируем: обновляем и добавляем
                foreach ($setItems as $item) {
                    $comp = $item['component_id'];
                    $qty  = $item['quantity'];

                    if (isset($existing[$comp])) {
                        // обновляем, если изменилось количество
                        if ((int)$existing[$comp] !== (int)$qty) {
                            $upd = $this->pdo->prepare("
                                UPDATE product_set
                                   SET quantity = :qty
                                 WHERE id = :psid
                                   AND product_id = :comp
                            ");
                            $upd->execute([
                                ':qty'  => $qty,
                                ':psid' => $setId,
                                ':comp' => $comp,
                            ]);
                        }
                        // помечаем как "обработанное"
                        unset($existing[$comp]);
                    } else {
                        // новая строка набора
                        $ins = $this->pdo->prepare("
                            INSERT INTO product_set (id, product_id, quantity)
                            VALUES (:psid, :comp, :qty)
                        ");
                        $ins->execute([
                            ':psid' => $setId,
                            ':comp' => $comp,
                            ':qty'  => $qty,
                        ]);
                    }
                }

                // 7) Удаляем те товары, которых нет в новом списке
                if (!empty($existing)) {
                    $del = $this->pdo->prepare("
                        DELETE FROM product_set
                         WHERE id = :psid
                           AND product_id = :comp
                    ");
                    foreach (array_keys($existing) as $compToDel) {
                        $del->execute([
                            ':psid' => $setId,
                            ':comp' => $compToDel,
                        ]);
                    }
                }

                // 8) Обновляем поле product.product_set_id
                $upd = $this->pdo->prepare("
                    UPDATE product
                       SET product_set_id = :psid
                     WHERE id = :id
                ");
                $upd->execute([
                    ':psid' => $setId,
                    ':id'   => $id,
                ]);
            }

            $this->pdo->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // можно залогировать $e->getMessage()
            return false;
        }
    }

    // Подсчитать общее количество товаров для пагинации
    public function countProducts(int $sellerId, ?string $search): int {
        $sql = "SELECT COUNT(*) FROM product WHERE seller_id = :sid";
        if ($search) {
            $sql .= " AND (offer_id LIKE :search OR name LIKE :search)";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sid', $sellerId, PDO::PARAM_INT);
        if ($search) {
            $stmt->bindValue(':search', "%{$search}%", PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // Обновить товары из маркетплейсов
    public function refreshProducts(int $sellerId): array {
        try {
            $this->pdo->beginTransaction();
            // 1) Получаем связанные площадки
            $stmt = $this->pdo->prepare("
                SELECT platform_id, token, client_id 
                FROM marketplace_seller 
                WHERE seller_id = :seller_id
            ");
            $stmt->execute([':seller_id' => $sellerId]);
            $platforms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($platforms)) {
                throw new Exception("Нет подключенных маркетплейсов");
            }

            // 2) Сброс всех предыдущих подтверждений для продавца
            $stmt = $this->pdo->prepare("
                UPDATE product_confirmation pc
                JOIN product p ON pc.product_id = p.id
                SET pc.is_confirmed = 0, pc.confirmed_at = NULL
                WHERE p.seller_id = :seller_id
            ");
            $stmt->execute([':seller_id' => $sellerId]);

            // 3) Сбор данных и привязка к платформам
            $sources = [];
            $platformData = [];
            $productMap = [];

            foreach ($platforms as $platform) {
                $platformId = $platform['platform_id'];
                $data = [];
                
                switch ($platformId) {
                    case 1:
                        $data = OzonService::getProducts($platform['client_id'], $platform['token']);
                        $key = 'ozon';
                        break;
                    case 2:
                        $data = WildberriesService::getProducts($platform['token']);
                        $key = 'wildberries';
                        break;
                    case 3:
                        $data = YandexService::getProducts($platform['client_id'], $platform['token']);
                        $key = 'yandex';
                        break;
                }

                $sources[$key] = array_map(function($item) use ($platformId, &$platformData) {
                    $offerId = $item['offer_id'] ?? $item['vendorCode'] ?? $item['offer']['offerId'];
                    $platformData[$offerId][] = $platformId;
                    
                    return [
                        "name" => $item['name'] ?? $item['title'] ?? $item['offer']['name'],
                        "offer_id" => $offerId,
                        "ozon_product_id" => $item['id'] ?? '',
                        "ozon_sku" => $item['sku'] ?? '',
                        "wb_barcode" => $item['sizes'][0]['skus'][0] ?? '',
                        "length" => ($item['depth'] ?? ($item['dimensions']['length'] ?? 0) * 10 ?? $item['weightDimensions']['length'] * 10) ?: 0,
                        "width" => ($item['width'] ?? ($item['dimensions']['width'] ?? 0) * 10 ?? $item['weightDimensions']['width'] * 10) ?: 0,
                        "height" => ($item['height'] ?? ($item['dimensions']['height'] ?? 0) * 10 ?? $item['weightDimensions']['height'] * 10) ?: 0,
                        "weight" => ($item['weight'] ?? ($item['dimensions']['weightBrutto'] ?? 0) * 1000 ?? $item['weightDimensions']['weight'] * 1000) ?: 0
                    ];
                }, $data);
            }

            // 4) Объединение данных
            $combined = [];
            foreach ($sources as $sourceName => $items) {
                foreach ($items as $item) {
                    $offerId = $item['offer_id'];
                    if (!isset($combined[$offerId])) {
                        $combined[$offerId] = $item;
                        continue;
                    }

                    // Обновление числовых полей
                    foreach (['length', 'width', 'height', 'weight'] as $field) {
                        $combined[$offerId][$field] = max($combined[$offerId][$field], $item[$field]);
                    }

                    // Обновление строковых полей
                    foreach (['name', 'ozon_product_id', 'ozon_sku', 'wb_barcode'] as $field) {
                        if (empty($combined[$offerId][$field])) {
                            $combined[$offerId][$field] = $item[$field];
                        }
                    }
                }
            }

            // 5) Вставка/обновление товаров
            foreach ($combined as $offerId => $product) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO product (
                        name, offer_id, ozon_product_id, ozon_sku, wb_barcode,
                        length, width, height, weight, seller_id, created_at, updated_at
                    ) VALUES (
                        :name, :offer_id, :ozon_product_id, :ozon_sku, :wb_barcode,
                        :length, :width, :height, :weight, :seller_id, NOW(), NOW()
                    ) ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        ozon_product_id = VALUES(ozon_product_id),
                        ozon_sku = VALUES(ozon_sku),
                        wb_barcode = VALUES(wb_barcode),
                        length = VALUES(length),
                        width = VALUES(width),
                        height = VALUES(height),
                        weight = VALUES(weight),
                        updated_at = NOW()
                ");
                
                $stmt->execute([
                    ':name' => $product['name'],
                    ':offer_id' => $offerId,
                    ':ozon_product_id' => $product['ozon_product_id'],
                    ':ozon_sku' => $product['ozon_sku'],
                    ':wb_barcode' => $product['wb_barcode'],
                    ':length' => $product['length'],
                    ':width' => $product['width'],
                    ':height' => $product['height'],
                    ':weight' => $product['weight'],
                    ':seller_id' => $sellerId
                ]);
                
                $productId = $this->pdo->lastInsertId() ?: 
                    $this->pdo->query("SELECT id FROM product WHERE offer_id = '$offerId' AND seller_id = $sellerId")
                    ->fetchColumn();
                
                $productMap[$offerId] = $productId;
            }

            // 6) Вставка новых подтверждений
            foreach ($combined as $offerId => $product) {
                if (!isset($platformData[$offerId]) || !isset($productMap[$offerId])) continue;
                
                foreach ($platformData[$offerId] as $platformId) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO product_confirmation 
                        (product_id, platform_id, is_confirmed, confirmed_at)
                        VALUES (:product_id, :platform_id, 1, NOW())
                        ON DUPLICATE KEY UPDATE
                            is_confirmed = VALUES(is_confirmed),
                            confirmed_at = VALUES(confirmed_at)
                    ");
                    $stmt->execute([
                        ':product_id' => $productMap[$offerId],
                        ':platform_id' => $platformId
                    ]);
                }
            }

            $this->pdo->commit();
    
            return[
                'status' => 'success',
                'products_processed' => count($combined),
                'confirmations_added' => array_sum(array_map('count', $platformData))
            ];

        } catch (Exception $e) {
            // Откат транзакции, если нужно
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['error' => $e->getMessage()];
        }
    } 
    
    // Обновить остатки по всем складам продавца через API маркетплейсов
    public function refreshStocks(int $sellerId): array {
        $pdo = $this->pdo;
        try {
            $pdo->beginTransaction();

            // 1) Получаем все склады продавца
            $stmt = $pdo->prepare("SELECT id FROM warehouse WHERE seller_id = :sid");
            $stmt->execute([':sid' => $sellerId]);
            $sellerWarehouses = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // 2) Получаем связи склад→маркетплейс
            $stmt = $pdo->prepare("
                SELECT 
                    w.id AS seller_warehouse_id,
                    mw.marketplace_warehouse_id,
                    ms.platform_id,
                    ms.token,
                    ms.client_id
                FROM warehouse w
                JOIN marketplace_warehouse mw ON w.id = mw.warehouse_id
                JOIN marketplace_seller ms ON mw.marketplace_seller_id = ms.id
                WHERE ms.seller_id = :sid
            ");
            $stmt->execute([':sid' => $sellerId]);
            $connections = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3) Берём только подтверждённые товары
            $stmt = $pdo->prepare("
                SELECT 
                    p.id, p.ozon_sku, p.wb_barcode, p.offer_id,
                    GROUP_CONCAT(pc.platform_id) AS platforms
                FROM product p
                JOIN product_confirmation pc 
                  ON p.id = pc.product_id AND pc.is_confirmed = 1
                WHERE p.seller_id = :sid
                GROUP BY p.id
            ");
            $stmt->execute([':sid' => $sellerId]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 4) Готовим запросы к API по каждому соединению
            $platformRequests = [];
            foreach ($connections as $conn) {
                $ids = [];
                foreach ($products as $prod) {
                    if (str_contains($prod['platforms'], (string)$conn['platform_id'])) {
                        $field = match((int)$conn['platform_id']) {
                            1 => 'ozon_sku',
                            2 => 'wb_barcode',
                            3 => 'offer_id',
                        };
                        $ids[] = $prod[$field];
                    }
                }
                if ($ids) {
                    $platformRequests[] = [
                        'connection'  => $conn,
                        'identifiers'=> array_unique($ids)
                    ];
                }
            }

            // 5) Выполняем API-вызовы и собираем $platformStocks
            $platformStocks = [];
            foreach ($platformRequests as $req) {
                $c = $req['connection'];
                switch ($c['platform_id']) {
                    case 1:
                        $stocks = OzonService::getStocks(
                            $c['client_id'], $c['token'], $req['identifiers']
                        );
                        foreach ($stocks as $st) {
                            if ($st['warehouse_id'] == $c['marketplace_warehouse_id']) {
                                $platformStocks['ozon'][$st['sku']][$c['seller_warehouse_id']]
                                    = $st['present'] - $st['reserved'];
                            }
                        }
                        break;
                    case 2:
                        $stocks = WildberriesService::getStocks(
                            $c['token'], $c['marketplace_warehouse_id'], $req['identifiers']
                        );
                        foreach ($stocks as $st) {
                            $platformStocks['wb'][$st['sku']][$c['seller_warehouse_id']]
                                = $st['amount'];
                        }
                        break;
                    case 3:
                        $stocks = YandexService::getStocks(
                            $c['marketplace_warehouse_id'], $c['token'], $req['identifiers']
                        );
                        foreach ($stocks as $st) {
                            $key = array_search("AVAILABLE", array_column($st['stocks'], "type"));
                            $count = $key !== false ? $st['stocks'][$key]['count'] : 0;
                            $platformStocks['yandex'][$st['offerId']][$c['seller_warehouse_id']] = $count;
                        }
                        break;
                }
            }

            // 6) Обновляем таблицы stock и stock_movement
            $priorities = ['1'=>'ozon','2'=>'wb','3'=>'yandex'];
            foreach ($sellerWarehouses as $whId) {
                foreach ($products as $prod) {
                    // выбираем остаток по приоритету площадок
                    $finalStock = 0;
                    foreach ($priorities as $pid => $key) {
                        if (str_contains($prod['platforms'], (string)$pid)
                            && isset($platformStocks[$key][$prod[ match((int)$pid){1=>'ozon_sku',2=>'wb_barcode',3=>'offer_id'} ]][$whId])
                        ) {
                            $finalStock = $platformStocks[$key][$prod[ match((int)$pid){1=>'ozon_sku',2=>'wb_barcode',3=>'offer_id'} ]][$whId];
                            break;
                        }
                    }
                    // текущее значение
                    $stmt = $pdo->prepare("
                        SELECT quantity FROM stock
                        WHERE product_id = :pid AND warehouse_id = :wid
                    ");
                    $stmt->execute([':pid'=>$prod['id'],':wid'=>$whId]);
                    $current = (int)$stmt->fetchColumn();

                    if ($finalStock !== $current) {
                        // обновляем stock
                        $stmt = $pdo->prepare("
                            INSERT INTO stock (product_id, warehouse_id, quantity, updated_at)
                            VALUES (:pid, :wid, :qty, NOW())
                            ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = NOW()
                        ");
                        $stmt->execute([':pid'=>$prod['id'],':wid'=>$whId,':qty'=>$finalStock]);
                        // логируем движение
                        $stmt = $pdo->prepare("
                            INSERT INTO stock_movement
                                (product_id, warehouse_id, old_quantity, new_quantity, movement_type_id, created_at)
                            VALUES (:pid,:wid,:old,:new,1,NOW())
                        ");
                        $stmt->execute([
                            ':pid' => $prod['id'],
                            ':wid' => $whId,
                            ':old' => $current,
                            ':new' => $finalStock,
                        ]);
                    }
                }
            }

            $pdo->commit();
            return [
                'status'     => 'success',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => $e->getMessage()];
        }
    }

    // Сгенерировать product_set для товаров вида base xN
    public function generateProductSets(int $sellerId): array {
        $pdo = $this->pdo;
        try {
            // 1) Получаем все товары продавца
            $stmt = $pdo->prepare(
                "SELECT id, offer_id 
                   FROM product 
                  WHERE seller_id = :sid 
               ORDER BY id ASC"
            );
            $stmt->execute([':sid' => $sellerId]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2) Парсим артикул вида base или basexN
            $parsed = [];
            foreach ($products as $prod) {
                if (preg_match('/^(.*?)(?:x(\d+))?$/i', $prod['offer_id'], $m)) {
                    $qty = isset($m[2]) ? (int)$m[2] : 1;
                    if ($qty > 1) {
                        $parsed[] = [
                            'id'       => $prod['id'],
                            'original' => $prod['offer_id'],
                            'base'     => $m[1],
                            'quantity' => $qty
                        ];
                    }
                }
            }

            $pdo->beginTransaction();
            $count = 0;
            foreach ($parsed as $item) {
                // 3) Ищем базовый товар по offer_id = base или base x1
                $baseId = null;
                $q = $pdo->prepare("SELECT id FROM product WHERE offer_id = ?");
                $q->execute([$item['base']]);
                if ($r = $q->fetch()) {
                    $baseId = $r['id'];
                } else {
                    $q->execute([$item['base'].'x1']);
                    if ($r = $q->fetch()) {
                        $baseId = $r['id'];
                    }
                }
                if (!$baseId) {
                    continue; // пропускаем, если не найден базовый товар
                }

                // 4) Вставляем запись в product_set
                $ins = $pdo->prepare(
                    "INSERT INTO product_set (product_id, quantity) VALUES (:pid, :qty)"
                );
                $ins->execute([
                    ':pid' => $baseId,
                    ':qty' => $item['quantity']
                ]);                                                   
                $setId = $pdo->lastInsertId();                        

                // 5) Обновляем поле product.product_set_id
                $upd = $pdo->prepare(
                    "UPDATE product 
                        SET product_set_id = :psid 
                      WHERE id = :id"
                );
                $upd->execute([
                    ':psid' => $setId,
                    ':id'   => $item['id']
                ]);
                $count++;
            }
            $pdo->commit();

            return ['status'=>'success', 'generated'=>$count];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error'=>$e->getMessage()];
        }
    }

    // Поиск товаров по артикулу или названию
    public function searchProducts(int $sellerId, string $query, int $limit = 10): array {
        $sql = "
            SELECT id, offer_id, name
              FROM product
             WHERE seller_id = :sid
               AND product_set_id IS NULL
               AND (offer_id LIKE :q OR name LIKE :q)
             ORDER BY offer_id ASC
             LIMIT :limit
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sid', $sellerId, PDO::PARAM_INT);
        $stmt->bindValue(':q',   '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
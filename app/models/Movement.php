<?php

class Movement {

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Список складов, в которых у продавца были движения.
    public function getWarehouses(int $sellerId): array {
        $sql = "
            SELECT DISTINCT w.id, w.name
              FROM stock_movement sm
              JOIN product p ON p.id = sm.product_id
              JOIN warehouse w ON w.id = sm.warehouse_id
             WHERE p.seller_id = :sid
             ORDER BY w.name
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sid'=>$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Список всех типов движений.
    public function getMovementTypes(): array {
        $sql = "SELECT id, name FROM movement_type ORDER BY name";
        return $this->pdo->query($sql, PDO::FETCH_ASSOC)->fetchAll();
    }

    // Список всех товаров (id, offer_id, name), у которых были движения у этого продавца
    public function getProducts(int $sellerId): array {
        $sql = "
            SELECT DISTINCT p.id, p.offer_id, p.name
              FROM stock_movement sm
              JOIN product p ON p.id = sm.product_id
             WHERE p.seller_id = :sid
             ORDER BY p.offer_id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sid'=>$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Считает общее число записей движений с учётом поиска.
    public function count(
        int    $sellerId,
        string $search = '',
        ?int   $wh     = null,
        ?int   $type   = null,
        ?string $from  = null,
        ?string $to    = null,
        ?array $prodIds= null
    ): int {
        $sql = "
            SELECT COUNT(*) 
              FROM stock_movement sm
              JOIN product p ON p.id = sm.product_id
             WHERE p.seller_id = :sid
        ";
        $params = [':sid'=>$sellerId];

        if ($search!=='') {
            $sql .= " AND (p.offer_id LIKE :s OR p.name LIKE :s)";
            $params[':s'] = "%{$search}%";
        }
        if ($wh !== null) {
            $sql .= " AND sm.warehouse_id = :wid";
            $params[':wid'] = $wh;
        }
        if ($type !== null) {
            $sql .= " AND sm.movement_type_id = :mt";
            $params[':mt'] = $type;
        }
        if ($from !== null) {
            $sql .= " AND DATE(sm.created_at) >= :df";
            $params[':df'] = $from;
        }
        if ($to !== null) {
            $sql .= " AND DATE(sm.created_at) <= :dt";
            $params[':dt'] = $to;
        }
        if ($prodIds) {
            $in  = implode(',', array_fill(0, count($prodIds), '?'));
            $sql .= " AND p.id IN ($in)";
            $params = array_merge($params, array_values($prodIds));
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // Возвращает страницу движений (поиск/сортировка/фильтрация/пагинация).
    public function fetchPage(
        int    $sellerId,
        int    $limit,
        int    $offset,
        string $search,
        string $sortBy,
        string $sortDir,
        ?int   $wh,
        ?int   $type,
        ?string $from,
        ?string $to,
        ?array $prodIds
    ): array {
        // 1) Мапинг удобных имён столбцов на реальные колонки
        $map = [
            'id'            => 'sm.id',
            'offer_id'      => 'p.offer_id',
            'name'          => 'p.name',
            'warehouse'     => 'w.name',
            'movement_type' => 'mt.name',
            'created_at'    => 'sm.created_at',
        ];
        $order = ($map[$sortBy] ?? 'sm.created_at') . ' ' . strtoupper($sortDir);

        // 2) Базовый SELECT с JOIN-ами
        $sql = "
            SELECT
            sm.id,
            p.offer_id,
            p.name,
            w.name        AS warehouse,
            sm.old_quantity,
            sm.new_quantity,
            mt.name       AS movement_type,
            sm.created_at
            FROM stock_movement sm
            JOIN product       p  ON p.id = sm.product_id
            JOIN warehouse     w  ON w.id = sm.warehouse_id
            JOIN movement_type mt ON mt.id = sm.movement_type_id
            WHERE p.seller_id = :sid
        ";
        $params = [':sid' => $sellerId];

        // 3) Динамические фильтры
        if ($search !== '') {
            $sql    .= " AND (p.offer_id LIKE :s OR p.name LIKE :s)";
            $params[':s'] = "%{$search}%";
        }
        if ($wh !== null) {
            $sql    .= " AND sm.warehouse_id = :wid";
            $params[':wid'] = $wh;
        }
        if ($type !== null) {
            $sql    .= " AND sm.movement_type_id = :mt";
            $params[':mt'] = $type;
        }
        if ($from !== null) {
            $sql    .= " AND DATE(sm.created_at) >= :df";
            $params[':df'] = $from;
        }
        if ($to !== null) {
            $sql    .= " AND DATE(sm.created_at) <= :dt";
            $params[':dt'] = $to;
        }
        if (!empty($prodIds)) {
            // IN-плейсхолдеры: ?,?,?
            $in  = implode(',', array_fill(0, count($prodIds), '?'));
            $sql .= " AND p.id IN ($in)";
            // Смешиваем числовые индексы PDO => пусть execute примет их
            $params = array_merge($params, array_values($prodIds));
        }

        // 4) ORDER BY + LIMIT/OFFSET
        $sql .= " ORDER BY {$order} LIMIT :l OFFSET :o";

        // 5) Подготовка и бинд параметров
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            // Если ключ — строковый (начинается с ':'), определяем тип
            if (is_string($key) && $key[0] === ':') {
                $type = PDO::PARAM_STR;
                if (in_array($key, [':sid', ':wid', ':mt'], true)) {
                    $type = PDO::PARAM_INT;
                }
                $stmt->bindValue($key, $val, $type);
            }
        }
        // Последние два параметра — limit и offset
        $stmt->bindValue(':l', $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);

        // 6) Выполнение и возврат результата
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
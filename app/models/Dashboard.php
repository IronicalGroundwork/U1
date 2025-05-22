<?php
class Dashboard {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Получение данных для виджетов
    public function getWidgetData($sellerId) {
        return [
            'totalProducts' => $this->getTotalProducts($sellerId),
            'totalStock' => $this->getTotalStock($sellerId),
            'marketplacesCount' => $this->getMarketplacesCount($sellerId),
            'totalInventoryValue' => $this->getInventoryValue($sellerId)
        ];
    }

    // Получение данных для графиков
    public function getChartsData($sellerId) {
        return [
            'movements' => $this->getMovementData($sellerId),
            'warehouses' => $this->getWarehouseDistribution($sellerId),
            'platforms' => $this->getPlatformDistribution($sellerId)
        ];
    }

    // 1. Общее количество товаров
    private function getTotalProducts($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM product 
            WHERE seller_id = ?
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchColumn() ?? 0;
    }

    // 2. Общее количество на складах
    private function getTotalStock($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(s.quantity), 0) 
            FROM stock s
            JOIN product p ON s.product_id = p.id 
            WHERE p.seller_id = ?
            AND p.product_set_id IS NULL
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchColumn() ?? 0;
    }

    // 3. Количество подключенных площадок
    private function getMarketplacesCount($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM marketplace_seller 
            WHERE seller_id = ?
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchColumn() ?? 0;
    }

    // 4. Стоимость запасов
    private function getInventoryValue($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(s.quantity * p.cost), 0) 
            FROM stock s
            JOIN product p ON s.product_id = p.id 
            WHERE p.seller_id = ?
            AND p.product_set_id IS NULL
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchColumn() ?? 0;
    }

    // Данные для графика активности
    private function getMovementData($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM stock_movement 
            WHERE product_id IN (
                SELECT id 
                FROM product 
                WHERE seller_id = ?
            )
            AND created_at >= CURDATE() - INTERVAL 7 DAY
            GROUP BY DATE(created_at)
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Распределение по складам
    private function getWarehouseDistribution($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT w.name, SUM(s.quantity) as total 
            FROM stock s
            JOIN warehouse w ON s.warehouse_id = w.id
            WHERE product_id IN (
                SELECT id 
                FROM product 
                WHERE seller_id = ?
                AND product_set_id IS NULL
            )
            GROUP BY w.id
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Подтвержденные товары по платформам
    private function getPlatformDistribution($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT p.name, COUNT(pc.product_id) as total 
            FROM product_confirmation pc
            JOIN platform p ON pc.platform_id = p.id
            WHERE pc.product_id IN (
                SELECT id 
                FROM product 
                WHERE seller_id = ?
            )
            GROUP BY p.id
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
?>
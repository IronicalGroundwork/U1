<?php
// Модель для работы со складами и их привязками к маркетплейсам
class Warehouse {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getSellerWarehouses($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                w.id as warehouse_id,
                w.name as warehouse_name,
                mw.name as marketplace_wh_name,
                mw.marketplace_warehouse_id,
                p.id as platform_id,
                marketplace_seller_id
            FROM warehouse w
            LEFT JOIN marketplace_warehouse mw ON w.id = mw.warehouse_id
            LEFT JOIN marketplace_seller ms ON mw.marketplace_seller_id = ms.id
            LEFT JOIN platform p ON ms.platform_id = p.id
            WHERE ms.seller_id = ?
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveWarehouse($name, $marketplaces, $sellerId) {    
        try {
            $this->pdo->beginTransaction();
             // Создание основного склада
            $stmt = $this->pdo->prepare("INSERT INTO warehouse (name, seller_id) VALUES (?, ?)");
            $stmt->execute([$name, $sellerId]);
            $warehouseId = $this->pdo->lastInsertId();

            foreach ($marketplaces as $msId => $value) {
                if (!empty($value)) {
                    [$marketplaceSellerId, $mWhId, $mWhName] = explode('_', $value);

                    // Проверка уникальности
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) 
                        FROM marketplace_warehouse 
                        WHERE marketplace_seller_id = ? 
                        AND marketplace_warehouse_id = ?
                    ");
                    $stmt->execute([$marketplaceSellerId, $mWhId]);

                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception("Склад уже привязан");
                    }
                    
                    // Сохранение
                    $stmt = $this->pdo->prepare("
                        INSERT INTO marketplace_warehouse 
                        (marketplace_warehouse_id, name, warehouse_id, marketplace_seller_id)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$mWhId, $mWhName, $warehouseId, $marketplaceSellerId]);
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deleteWarehouse($warehouseId, $sellerId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM warehouse 
            WHERE id = ? 
            AND EXISTS (
                SELECT 1 
                FROM marketplace_warehouse mw
                JOIN marketplace_seller ms ON mw.marketplace_seller_id = ms.id
                WHERE mw.warehouse_id = ?
                AND ms.seller_id = ?
            )
        ");

        $stmt->execute([$warehouseId, $warehouseId, $sellerId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Склад не найден или нет прав доступа");
        }
    }  

    public function getUsedWarehouses($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT marketplace_warehouse_id, marketplace_seller_id 
            FROM marketplace_warehouse 
            WHERE marketplace_seller_id IN (
                SELECT id FROM marketplace_seller WHERE seller_id = ?
            )
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
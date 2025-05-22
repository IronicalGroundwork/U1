<?php
class Marketplace {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getMarketplaceSeller($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT ms.*, p.id as platform_id 
            FROM marketplace_seller ms
            JOIN platform p ON ms.platform_id = p.id
            WHERE ms.seller_id = ?
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Получение подключенных маркетплейсов
    public function getConnectedMarketplaces($sellerId) {
        $stmt = $this->pdo->prepare("
            SELECT ms.*, p.name as platform_name 
            FROM marketplace_seller ms
            LEFT JOIN platform p ON ms.platform_id = p.id
            WHERE ms.seller_id = ?
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Сохранение подключения
    public function saveConnection($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO marketplace_seller 
            (seller_id, platform_id, name, client_id, token, connected_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            client_id = VALUES(client_id),
            token = VALUES(token),
            connected_at = NOW()
        ");
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

     // Удаление подключения
    public function deleteConnection($sellerId, $platformId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM marketplace_seller 
            WHERE seller_id = ? AND platform_id = ?
        ");
        return $stmt->execute([$sellerId, $platformId]);
    }
}
?>
<?php
// app/services/OrderProcessor.php

require_once __DIR__ . '/../helpers/MessageHelper.php';
require_once __DIR__ . '/../models/Product.php';

class OrderProcessor
{
    private PDO $db;
    private Product $productModel;

    public function __construct(PDO $db)
    {
        $this->db           = $db;
        $this->productModel = new Product($db);
    }

    /**
     * Обрабатывает push-уведомление ORDER_CREATED от Яндекс.Маркета:
     * 1) Находит внутренний warehouse_id
     * 2) Вычисляет newQty для каждого товара
     * 3) Вызывает updateStock
     * Все сообщения об ошибках и статусах пишутся в лог, не возвращаются.
     *
     * @param array $notification
     */
    public function handleYandexOrder(array $notification)
    {
        // проверяем тип уведомления
        if (($notification['notificationType'] ?? '') !== 'ORDER_CREATED') {
            MessageHelper::log_message("OrderProcessor::Unsupported notificationType: " . ($notification['notificationType'] ?? ''));
        }

        // 1) находим внутренний склад
        try {
            $stmt = $this->db->prepare(
                'SELECT warehouse_id 
                   FROM marketplace_warehouse 
                  WHERE marketplace_warehouse_id = :campaignId'
            );
            $stmt->execute([':campaignId' => (int)$notification['campaignId']]);
            $warehouseId = (int)$stmt->fetchColumn();

            if (!$warehouseId) {
                MessageHelper::log_message("OrderProcessor::Unknown campaignId: " . $notification['campaignId']);
            }
        } catch (\Throwable $ex) {
            MessageHelper::log_message("OrderProcessor::DB error finding warehouse for campaignId {$notification['campaignId']}: " . $ex->getMessage());
        }

        // 2) вычисляем новый остаток для каждого offerId
        $itemsArray = [];
        foreach ($notification['items'] as $it) {
            $offerId = $it['offerId'];
            $count   = (int)$it['count'];

            try {
                // product_id
                $p = $this->db->prepare(
                    'SELECT id FROM product WHERE offer_id = :off'
                );
                $p->execute([':off' => $offerId]);
                $productId = (int)$p->fetchColumn();

                if (!$productId) {
                    MessageHelper::log_message("OrderProcessor::Unknown offerId in order: {$offerId}");
                }

                // старый остаток
                $s = $this->db->prepare(
                    'SELECT quantity FROM stock WHERE product_id = :pid AND warehouse_id = :wid'
                );
                $s->execute([
                    ':pid' => $productId,
                    ':wid' => $warehouseId
                ]);
                $oldQty = (int)$s->fetchColumn();

                $newQty = max(0, $oldQty - $count);
                $itemsArray[] = [
                    'productId' => $productId,
                    'newQty'    => $newQty
                ];
            } catch (\Throwable $ex) {
                MessageHelper::log_message("OrderProcessor::Error computing newQty for offer {$offerId}: " . $ex->getMessage());
            }
        }

        $result = $this->productModel->updateStock(
            $itemsArray,
            $warehouseId,
            3 // sourcePlatformId = 3 (Яндекс.Маркет)
        );

        if (empty($result['success'])) {
            MessageHelper::log_message("OrderProcessor::updateStock failed for YM order {$notification['orderId']}: " . ($result['message'] ?? 'unknown'));
        }

        MessageHelper::log_message("OrderProcessor::Successfully processed YM order {$notification['orderId']}");
    }
    
    /**
     * Обрабатывает уведомление от OZON о новой поставке (TYPE_NEW_POSTING):
     * 1) Находит внутренний warehouse_id и seller_id по marketplace_warehouse_id и client_id
     * 2) Считывает старый остаток для каждого SKU, вычисляет newQty = oldQty - quantity
     * 3) Вызывает updateStockCascade с sourcePlatformId = 1 (OZON)
     *
     * @param array $notification
     */
    public function handleOzonOrder(array $notification){
        // 1) Проверяем тип сообщения
        if (($notification['message_type'] ?? '') !== 'TYPE_NEW_POSTING') {
            MessageHelper::log_message("OZON push: unsupported message_type: " . ($notification['message_type'] ?? ''));
            return ['success' => false];
        }

        // 2) Находим внутренний склад и seller_id
        try {
            $stmt = $this->db->prepare("
                SELECT
                  mw.warehouse_id,
                  ms.seller_id
                FROM marketplace_warehouse mw
                JOIN marketplace_seller ms
                  ON mw.marketplace_seller_id = ms.id
                WHERE mw.marketplace_warehouse_id = :mwid
                  AND ms.client_id              = :clientId
            ");
            $stmt->execute([
                ':mwid'     => (int)$notification['warehouse_id'],
                ':clientId' => (int)$notification['seller_id']
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                MessageHelper::log_message("OZON push: unknown warehouse_id {$notification['warehouse_id']} or seller_id {$notification['seller_id']}");
                return ['success' => false];
            }

            $warehouseId = (int)$row['warehouse_id'];
            $sellerId    = (int)$row['seller_id'];

        } catch (Throwable $ex) {
            MessageHelper::log_message("OZON push: DB error locating warehouse/seller: " . $ex->getMessage());
            return ['success' => false];
        }

        // 3) Строим itemsArray с newQty
        $itemsArray = [];
        foreach ($notification['products'] as $prod) {
            $sku      = $prod['sku'];
            $quantity = (int)$prod['quantity'];

            try {
                // Находим internal product_id
                $p = $this->db->prepare(
                    'SELECT id FROM product WHERE ozon_sku = :sku AND seller_id = :sid'
                );
                $p->execute([':sku' => $sku, ':sid' => $sellerId]);
                $productId = (int)$p->fetchColumn();

                if (!$productId) {
                    log_message("OZON push: unknown SKU in product table: {$sku}");
                    return ['success' => false];
                }

                // Читаем старый остаток
                $s = $this->db->prepare(
                    'SELECT quantity FROM stock 
                       WHERE product_id = :pid 
                         AND warehouse_id = :wid'
                );
                $s->execute([
                    ':pid' => $productId,
                    ':wid' => $warehouseId
                ]);
                $oldQty = (int)$s->fetchColumn();

                // Вычисляем новый
                $newQty = max(0, $oldQty - $quantity);

                $itemsArray[] = [
                    'productId' => $productId,
                    'newQty'    => $newQty
                ];

            } catch (Throwable $ex) {
                MessageHelper::log_message("OZON push: error computing newQty for SKU {$sku}: " . $ex->getMessage());
                return ['success' => false];
            }
        }

        // 4) Вызываем каскадное обновление
        $result = $this->productModel->updateStockCascade(
            $itemsArray,
            $warehouseId,
            1 // sourcePlatformId = 1 для OZON
        );

        if (empty($result['success'])) {
            MessageHelper::log_message("OZON push: updateStockCascade failed: " . ($result['message'] ?? ''));
            return ['success' => false];
        }

        MessageHelper::log_message("OZON push: successfully processed posting {$notification['posting_number']}");
        return ['success' => true];
    }
}
?>
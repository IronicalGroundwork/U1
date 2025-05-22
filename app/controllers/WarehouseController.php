<?php
require_once __DIR__ . '/../models/Warehouse.php';
require_once __DIR__ . '/../models/Marketplace.php';

class WarehouseController {
 
    public function __construct() {
        $db = Database::getInstance()->getConnection();
        $this->warehouseModel = new Warehouse($db);
        $this->marketplaceModel = new Marketplace($db);
    }

    public function index() {
        AuthHelper::checkAuth();

        $sidenav_link_4 = 'active';
        
        $warehouses = $this->warehouseModel->getSellerWarehouses($_SESSION['seller']['id']);
        $grouped_warehouses = $this->groupWarehouses($warehouses);
        $connected_mps = $this->marketplaceModel->getMarketplaceSeller($_SESSION['seller']['id']);
        

        require_once __DIR__ . '/../views/warehouses/index.php';
    }

    public function save() {
        header('Content-Type: application/json');

        try {
            $this->warehouseModel->saveWarehouse($_POST['warehouse_name'], $_POST['marketplaces'], $_SESSION['seller']['id']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function delete() {
        header('Content-Type: application/json');
        try {
            $this->warehouseModel->deleteWarehouse($_POST['warehouse_id'], $_SESSION['seller']['id']);
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function groupWarehouses($warehouses) {
        $grouped = [];
        foreach ($warehouses as $wh) {
            $key = $wh['warehouse_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $wh['warehouse_name'],
                    'marketplaces' => []
                ];
            }
            if ($wh['platform_id']) {
                $grouped[$key]['marketplaces'][] = [
                    'name' => $wh['marketplace_wh_name'],
                    'id' => $wh['marketplace_warehouse_id'],
                    'platform' => $wh['platform_id']
                ];
            }
        }
        return $grouped;
    }
}
?>
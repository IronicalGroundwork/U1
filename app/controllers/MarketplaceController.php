<?php
require_once __DIR__ . '/../models/Marketplace.php';

class MarketplaceController {
 
    public function __construct() {
        $db = Database::getInstance()->getConnection();
        $this->marketplaceModel = new Marketplace($db);
    }

    public function index() {
        AuthHelper::checkAuth();

        $sidenav_link_5 = 'active';

        $connected_marketplaces = $this->marketplaceModel->getConnectedMarketplaces($_SESSION['seller']['id']);

        require_once __DIR__ . '/../views/marketplaces/index.php';
    }

    public function disconnect() {
        try {
            $this->marketplaceModel->deleteConnection(
                $_SESSION['seller']['id'],
                $_POST['platform_id']
            );
            $success = match($_POST['platform_id']) {
                '1' => 'OZON отключен!',
                '2' => 'Wildberries отключен!',
                '3' => 'Яндекс Маркет отключен!'
            };
            MessageHelper::showSuccess($success);

        } catch (Exception $e) {
            MessageHelper::showError($e->getMessage());      
        }
        
        header('Location: index.php?action=marketplaces');
    }

}
?>
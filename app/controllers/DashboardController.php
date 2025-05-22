<?php
require_once __DIR__ . '/../models/Dashboard.php';

class DashboardController {

    public function __construct() {
        $db = Database::getInstance()->getConnection();
        $this->dashboardModel = new Dashboard($db);
    }

    public function index() {
        AuthHelper::checkAuth();
        
        $sidenav_link_1 = 'active';
        
        $data = [
            'widgets' => $this->dashboardModel->getWidgetData($_SESSION['seller']['id']),
            'charts' => $this->dashboardModel->getChartsData($_SESSION['seller']['id'])
        ];

        require_once __DIR__ . '/../views/dashboard/index.php';
    }
}
?>
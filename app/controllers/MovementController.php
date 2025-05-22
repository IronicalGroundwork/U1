<?php
require_once __DIR__.'/../models/Movement.php';

class MovementController {

    public function __construct() {
        $db = Database::getInstance()->getConnection();
        $this->movementModel = new Movement($db);
        $this->sellerId = $_SESSION['seller']['id']?? 0;
    }

    public function index(): void {
        AuthHelper::checkAuth();
        $sidenav_link_3 = 'active';

        // 1) Параметры пагинации
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = (int)($_GET['limit'] ?? 20);
        if (!in_array($limit, [10,20,50,100], true)) {
            $limit = 20;
        }
        $offset = ($page - 1) * $limit;

        // 2) Параметры сортировки
        $sortBy  = $_GET['sort_by'] ?? 'created_at';
        $sortDir = strtolower($_GET['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        // 3) Фильтры
        $search  = trim($_GET['search']    ?? '');
        $wh      = isset($_GET['warehouse']) && $_GET['warehouse'] !== '' ? (int)$_GET['warehouse'] : null;
        $type    = isset($_GET['type']) && $_GET['type'] !== '' ? (int)$_GET['type'] : null;
        $from    = !empty($_GET['date_from']) ? $_GET['date_from'] : null;
        $to      = !empty($_GET['date_to']) ? $_GET['date_to'] : null;

        // 4) Мультиселект товаров
        $prodIds = isset($_GET['products'])? array_map('intval', (array)$_GET['products']) : null;

        // 5) Списки для фильтров
        $warehouses = $this->movementModel->getWarehouses($this->sellerId);
        $types      = $this->movementModel->getMovementTypes();
        $products   = $this->movementModel->getProducts($this->sellerId);

        // 6) Данные по движению
        $total      = $this->movementModel->count($this->sellerId, $search, $wh, $type, $from, $to, $prodIds);
        $pages     = $total > 0 ? (int)ceil($total / $limit) : 1;
        $movements  = $this->movementModel->fetchPage($this->sellerId, $limit, $offset, $search, $sortBy, $sortDir, $wh, $type, $from, $to, $prodIds);

        function query(array $ov = []): string {
            $p = $_GET;
            $p['action'] = 'movements';
            foreach ($ov as $k => $v) {
                $p[$k] = $v;
            }
            return '?' . http_build_query($p);
        }

        // 7) Рендерим view
        include __DIR__ . '/../views/movements/index.php';
    }

}
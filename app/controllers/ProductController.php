<?php
require_once __DIR__.'/../models/Product.php';

class ProductController {

    public function __construct() {
        $db = Database::getInstance()->getConnection();
        $this->productModel = new Product($db);
        $this->sellerId = $_SESSION['seller']['id']?? 0;
    }

    public function index() {
        AuthHelper::checkAuth();
        $sidenav_link_2 = 'active';

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = (int)($_GET['limit'] ?? 20);
        $allowedLimits = [10,20,50,100];
        if (!in_array($limit, $allowedLimits, true)) {
            $limit = 20;
        }
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        $allowedSort = ['id','offer_id','name','cost','volume_l','weight_kg','total_stock'];
        $sortBy  = $_GET['sort_by']  ?? 'id';
        $sortDir = ($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'id';
        }

        $total  = $this->productModel->countProducts($this->sellerId, $search);
        $pages  = ceil($total / $limit);

        $products = $this->productModel->getProducts(
            $this->sellerId, 
            $limit, 
            $offset, 
            $search,
            $sortBy,
            $sortDir
        );
        
        require_once __DIR__.'/../views/products/index.php';
    }

    // Получить остатки по складам
    public function stockAjax(): void {
        $pid = (int)$_POST['product_id'];
        header('Content-Type: application/json');
        echo json_encode($this->productModel->getStockByWarehouses($pid));
    }

    // Обновить остаток
    public function updateStockAjax(): void {
        $pid = (int)$_POST['product_id'];
        $wid = (int)$_POST['warehouse_id'];
        $qty = (int)$_POST['quantity'];
        header('Content-Type: application/json');
        $itemsArray[] = [
            'productId' => $pid,
            'newQty'    => $qty
        ];
        $success = $this->productModel->updateStock($itemsArray, $wid);
        if ($success['success']) {
            MessageHelper::showSuccess('Остаток успешно обнавлён!');
        }
        else {
            MessageHelper::showError($success['message']);    
        }
        echo json_encode($success);
    }

    // Вернуть JSON с данными товара + его набором
    public function getProductAjax(): void {
        $pid = (int)$_GET['product_id'];
        $prod = $this->productModel->getProductById($pid);
        $prod['set'] = $this->productModel->getProductSet($prod['product_set_id'] ?? 0); 
        header('Content-Type: application/json');
        echo json_encode($prod);
    }

    // Обновить данные товара и набор
    public function updateProductAjax(): void {
        $data = $_POST['product'];
        $setItems = json_decode($_POST['set'], true);
        $ok = $this->productModel->updateProduct((int)$data['id'], $data, $setItems);
        header('Content-Type: application/json');
        echo json_encode(['success'=>$ok]);
    }

    // Удалить товар
    public function deleteProductAjax(): void {
    $pid = (int)($_POST['product_id'] ?? 0);
    header('Content-Type: application/json; charset=utf-8');
    $ok = $this->productModel->deleteProduct($pid);
    echo json_encode([
        'success' => $ok,
        'message' => $ok ? '' : 'Не удалось удалить товар'
    ], JSON_UNESCAPED_UNICODE);
    }

    // Обновить товары
    public function refreshProductsAjax(): void {
        header('Content-Type: application/json; charset=utf-8');
        $result = $this->productModel->refreshProducts($this->sellerId);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    // Обновить остатки
    public function refreshStocksAjax(): void {
        header('Content-Type: application/json; charset=utf-8');
        $res = $this->productModel->refreshStocks($this->sellerId);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    // Сгенерировать наборы
    public function generateSetsAjax(): void {
        header('Content-Type: application/json; charset=utf-8');
        $res = $this->productModel->generateProductSets($this->sellerId);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    // Автодополнение товаров по артикулу/названию
    public function searchProductsAjax(): void {
        $q = trim($_GET['query'] ?? '');
        header('Content-Type: application/json; charset=utf-8');

        if ($q === '') {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            return;
        }

        $results = $this->productModel->searchProducts($this->sellerId, $q, 10);
        // Отдаём массив {id, offer_id, name}
        echo json_encode($results, JSON_UNESCAPED_UNICODE);
    }
}
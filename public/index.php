<?php
require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? 'home';

switch ($action) {
    case 'login':
        $controller = new AuthController();
        $controller->loginForm();
        break;
    case 'do-login':
        $controller = new AuthController();
        $controller->login();
        break;
    case 'register':
        $controller = new AuthController();
        $controller->registerForm();
        break;
    case 'do-register':
        $controller = new AuthController();
        $controller->register();
        break;
    case 'forgot-password':
        $controller = new AuthController();
        $controller->forgotPasswordForm();
        break;
    case 'logout':
        $controller = new AuthController();
        $controller->logout();
        break;
    // Сводка    
    case 'dashboard':
        $controller = new DashboardController();
        $controller->index();
        break;
    // Товары    
    case 'products':
        $controller = new ProductController();
        $controller->index();
        break;
    case 'refresh-products':
        $controller = new ProductController();
        $controller->refreshProductsAjax();
        break; 
    case 'refresh-stocks':
        $controller = new ProductController();
        $controller->refreshStocksAjax();
        break;    
    case 'generate-sets':
        $controller = new ProductController();
        $controller->generateSetsAjax();
        break;
    case 'get-product-stock':
        $controller = new ProductController(); 
        $controller->stockAjax();
    break;
    case 'update-product-stock':
        $controller = new ProductController();
        $controller->updateStockAjax();
    break;        
    case 'get-product':
        $controller = new ProductController();
        $controller->getProductAjax();
    break;
    case 'update-product':
        $controller = new ProductController();
        $controller->updateProductAjax();
    break; 
    case 'delete-product':
        $controller = new ProductController();
        $controller->deleteProductAjax();
    break;   
    case 'search-product':
        $controller = new ProductController();
        $controller->searchProductsAjax();
    break;
    // Движения по товарам
    case 'movements':
        $controller = new MovementController();
        $controller->index();
    break;    
    // Склады
    case 'warehouses':
        $controller = new WarehouseController();
        $controller->index();
        break;
    case 'save-warehouses':
        $controller = new WarehouseController();
        $controller->save();
        break; 
    case 'delete-warehouses':
        $controller = new WarehouseController();
        $controller->delete();
        break; 
    case 'get-marketplace-warehouses':
        $controller = new ApiController();
        $controller->getMarketplaceWarehouses();
        break; 
    // Маркетплейсы
    case 'marketplaces':
        $controller = new MarketplaceController();
        $controller->index();
        break;
    case 'disconnect-marketplaces':
        $controller = new MarketplaceController();
        $controller->disconnect();
        break;  
    case 'connect-marketplaces':
        $controller = new ApiController();
        $controller->connectMarketplace();
        break;
    // Настройки
    case 'settings':
        $controller = new SettingsController();
        $controller->index();
        break;
    case 'do-settings':
        $controller = new SettingsController();
        $controller->update();
        break;
    // Профиль
    case 'profile':
        $controller = new ProfileController ();
        $controller->index();
        break;
    case 'update-profile':
        $controller = new ProfileController ();
        $controller->profileUpdate();
        break;
    case 'update-profile-image':
        $controller = new ProfileController ();
        $controller->imageUpload();
        break;          
    default:
        // Редирект или вывод домашней страницы
        header('Location: ?action=login');
        exit;
}
?>
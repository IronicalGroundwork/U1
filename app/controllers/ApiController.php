<?php
require_once __DIR__ . '/../services/OzonService.php';
require_once __DIR__ . '/../services/WildberriesService.php';
require_once __DIR__ . '/../services/YandexService.php';

require_once __DIR__ . '/../models/Warehouse.php';
require_once __DIR__ . '/../models/Marketplace.php';

class ApiController {
    private $warehouseModel;
    private $marketplaceModel;

    public function __construct() {
        $db = Database::getInstance()->getConnection();
        $this->warehouseModel = new Warehouse($db);
        $this->marketplaceModel = new Marketplace($db);
    }
    
    public function getMarketplaceWarehouses() {
        header('Content-Type: application/json');
        try {
            $seller_id = $_SESSION['seller']['id'];
            $result = [];
            // Получение подключенных маркетплейсов
            $mps = $this->marketplaceModel->getMarketplaceSeller($seller_id);
            // Получение уже использованных складов
            $used = $this->warehouseModel->getSellerWarehouses($seller_id);

            // Запрос складов с маркетплейсов
            foreach ($mps as $mp) {
                $warehouses = [];
                switch ($mp['platform_id']) {
                    case 1: // Ozon
                        $data = OzonService::getWarehouses($mp['client_id'], $mp['token']);
                        foreach ($data['data']['result'] as $wh) {
                            if ($wh['status'] === 'created') {
                                $warehouses[] = [
                                    'id' => $wh['warehouse_id'],
                                    'name' => $wh['name'],
                                    'marketplace_seller_id' => $mp['id']
                                ];
                            }
                        }
                        break;
                        
                    case 2: // Wildberries
                        $data = WildberriesService::getWarehouses($mp['token']);
                        foreach ($data['data'] as $wh) {
                            $warehouses[] = [
                                'id' => $wh['id'],
                                'name' => $wh['name'],
                                'marketplace_seller_id' => $mp['id']
                            ];
                        }
                        break;
                        
                    case 3: // Яндекс
                        $data = YandexService::getCampaigns($mp['token']);
                        foreach ($data['data']['campaigns'] as $wh) {
                            if ($wh['placementType'] === 'FBS') {
                                $warehouses[] = [
                                    'id' => $wh['id'],
                                    'name' => $wh['domain'],
                                    'marketplace_seller_id' => $mp['id']
                                ];
                            }
                        }
                        break;
                }

                // Фильтрация использованных
                $result[$mp['platform_id']] = array_filter($warehouses, function($wh) use ($used) {
                    foreach ($used as $u) {
                        if ($u['marketplace_seller_id'] == $wh['marketplace_seller_id'] 
                            && $u['marketplace_warehouse_id'] == $wh['id']) {
                            return false;
                        }
                    }
                    return true;
                });
            }

            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function verifyOzon($data) {
        $result = OzonService::getWarehouses($data['client_id'], $data['api_key']);
        return [
            'success' => $result['http_code'] == 200,
            'name' => $data['name'],
            'error' => $result['data']['message'] ?? null
        ];
    }

    private function verifyWildberries($data) {
        $result = WildberriesService::getSellerInfo($data['api_key']);
        return [
            'success' => $result['http_code'] == 200,
            'name' => trim($result['data']['tradeMark'] ?? ''),
            'error' => $result['data']['detail'] ?? null
        ];
    }

    private function verifyYandex($data) {
        $result = YandexService::getBusinessesSettings($data['client_id'], $data['api_key']);
        return [
            'success' => $result['http_code'] == 200,
            'name' => trim($result['data']['result']['info']['name'] ?? ''),
            'error' => $result['data']['errors'][0]['message'] ?? null
        ];
    }

    public function connectMarketplace() {
        try {
            $data = $_POST;

            switch($data['platform_id']) {
                case "1": $response = $this->verifyOzon($data); break;
                case "2": $response = $this->verifyWildberries($data); break;
                case "3": $response = $this->verifyYandex($data); break;
                default: throw new Exception('Неизвестная платформа');
            }
            
            if ($response['success']) {
                $this->marketplaceModel->saveConnection([
                    $_SESSION['seller']['id'],
                    $data['platform_id'],
                    $response['name'],
                    $data['client_id'] ?? null,
                    $data['api_key']
                ]);
                $success = match($_POST['platform_id']) {
                    '1' => 'OZON успешно подключён!',
                    '2' => 'Wildberries успешно подключён!',
                    '3' => 'Яндекс Маркет успешно подключён!'
                };
                MessageHelper::showSuccess($success); 
            } else {
                MessageHelper::showError('Ошибка подключения: '.$response['error']); 
            }
        }
        catch (Exception $e) {
            MessageHelper::showError($e->getMessage()); 
        }
        
        header('Location: index.php?action=marketplaces');
    }
   
}
?>
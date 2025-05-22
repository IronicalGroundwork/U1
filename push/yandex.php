<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/services/OrderProcessor.php';

// Настройки
$ALLOWED_IPS = ['5.45.207.0/25', '141.8.142.0/25', '5.255.253.0/25'];
$LOG_FILE = 'yandex_notifications.log';
$VERSION = '1.2.0'; // Версия интеграции
$INTEGRATION_NAME = 'U1 Integration'; // Название интеграции

// Проверка IP
function is_ip_allowed($ip) {
    global $ALLOWED_IPS;
    foreach ($ALLOWED_IPS as $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        if ((ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == (ip2long($subnet) & ~((1 << (32 - $mask)) - 1))) {
            return true;
        }
    }
    return false;
}

// Генерация ответа
function generate_response($data = []) {
    global $VERSION, $INTEGRATION_NAME;
    return json_encode(array_merge([
        'version' => $VERSION,
        'name' => $INTEGRATION_NAME,
        'time' => date('c')
    ], $data));
}

// Основная обработка
header('Content-Type: application/json; charset=utf-8');

try {
    // Проверка IP
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_ip_allowed($client_ip)) {
        throw new Exception("Forbidden IP: $client_ip", 403);
    }

    // Получение тела запроса
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception("Empty request body", 400);
    }

    // Парсинг JSON
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg(), 400);
    }

    // Валидация обязательных полей
    if (!isset($data['notificationType'])) {
        throw new Exception("Missing required fields", 400);
    }

    // Обработка PING-запроса
    if ($data['notificationType'] === 'PING') {
        MessageHelper::log_message("Yandex push: PING received. Time: " . $data['time']);
        
        // Возвращаем успешный ответ
        http_response_code(200);
        echo generate_response();
        exit;
    }

    // Обработка создания заказа
    if ($data['notificationType'] === 'ORDER_CREATED') {
        $campaignId = $data['campaignId'] ?? null;
        $orderId = $data['orderId'] ?? null;
        
        if (!$campaignId || !$orderId) {
            throw new Exception("Missing order fields", 400);
        }

        $db = Database::getInstance()->getConnection();
        $processor = new OrderProcessor($db);
        $processor->handleYandexOrder($data);
        
        MessageHelper::log_message("Yandex push: New order. Campaign $campaignId, Order $orderId");
        
        http_response_code(200);
        echo generate_response(['processed' => true]);
        exit;
    }

    // Неподдерживаемый тип уведомления
    throw new Exception("Unsupported notification type: " . $data['notificationType'], 400);

} catch (Exception $e) {
    // Обработка ошибок
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    MessageHelper::log_message("Yandex webhook error [{$code}]: {$e->getMessage()} - Input: " . ($input ?? 'NULL'));
    
    $error_type = match(true) {
        $code === 400 => 'INVALID_DATA',
        $code === 403 => 'ACCESS_DENIED',
        default => 'UNKNOWN'
    };
    
    echo json_encode([
        'error' => [
            'type' => $error_type,
            'message' => $e->getMessage()
        ]
    ]);
}
?>
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/services/OrderProcessor.php';

// Получаем данные из тела запроса
$request = file_get_contents('php://input');
$data = json_decode($request, true);

// 3) Если не получилось распарсить JSON — ошибка
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    MessageHelper::log_message("ozon.php: invalid JSON: " . $input);
    ERROR_PARAMETER_VALUE_MISSED();
    exit;
}

// Проверяем, что уведомление было получено
if (isset($data['message_type']))
{
    $messageType = $data['message_type'];

    switch ($messageType) {
        case 'TYPE_PING':
            if (isset($data['time'])) {
                MessageHelper::log_message("Ozon PING received at {$data['time']}");
                RECEIVED_SUCCESSFULLY();
            } else {
                MessageHelper::log_message("Ozon PING missing time");
                ERROR_PARAMETER_VALUE_MISSED();
            }
            break;

        case 'TYPE_NEW_POSTING':
            if (
                isset($data['posting_number'], $data['products'], $data['in_process_at'],
                      $data['warehouse_id'], $data['seller_id'])
            ) {
                RECEIVED_SUCCESSFULLY();

                MessageHelper::log_message("OZON new posting {$data['posting_number']} received");

                try {
                    $processor = new OrderProcessor($db);
                    $res = $processor->handleOzonOrder($data);
                    if (empty($res['success'])) {
                        MessageHelper::log_message("OZON order processing failed: " . ($res['message'] ?? 'unknown'));
                    } else {
                        MessageHelper::log_message("OZON order {$data['posting_number']} processed successfully");
                    }
                } catch (Throwable $ex) {
                    MessageHelper::log_message("Exception in OzonOrderProcessor: " . $ex->getMessage());
                }
            } else {
                MessageHelper::log_message("OZON TYPE_NEW_POSTING missing parameters: " . json_encode($data));
                ERROR_PARAMETER_VALUE_MISSED();
            }
            break;

        default:
            MessageHelper::log_message("push.php: unknown message_type {$messageType}");
            ERROR_UNKNOWN();
            break;
     }

}
else
{
    MessageHelper::log_message("push.php: message_type not set");
    ERROR_PARAMETER_VALUE_MISSED();
}

// --------------------------------------------------
// Функции ответа OZON
// --------------------------------------------------

function RECEIVED_SUCCESSFULLY()
{
    // Ответ при успешной обработ
    $response = ['version' => '1.0', // Версия приложения
    'name' => 'MyApp', // Название приложения
    'time' => gmdate('Y-m-d\TH:i:s\Z') // Время начала обработки (текущая дата и время в UTC)
    ];

    // Возвращаем ответ с кодом 200 и JSON
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode($response);
}

function ERROR_PARAMETER_VALUE_MISSED()
{
    // Если отсутствуют необходимые параметры, возвращаем ошибку
    $errorResponse = ['error' => ['code' => 'ERROR_PARAMETER_VALUE_MISSED', 'message' => 'Отсутствует один или несколько обязательных параметров', 'details' => null]];

    // Возвращаем ошибку с кодом 400 (Bad Request)
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode($errorResponse);
}

function ERROR_UNKNOWN()
{
    // Если тип уведомления неизвестен, возвращаем ошибку
    $errorResponse = ['error' => ['code' => 'ERROR_UNKNOWN', 'message' => 'Неизвестный тип уведомления', 'details' => null]];

    // Возвращаем ошибку с кодом 400 (Bad Request)
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode($errorResponse);
}

?>

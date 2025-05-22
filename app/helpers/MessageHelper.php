<?php
class MessageHelper {
    public static function showSuccess($message) {
        $_SESSION['success'] = $message;
    }

    public static function showError($message) {
        $_SESSION['error'] = $message;
    }

    public static function display() {
        if (!empty($_SESSION['error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'.htmlspecialchars($_SESSION['error']).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'.htmlspecialchars($_SESSION['success']).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['success']);
        }
    }

    public static function log_message(string $message){
        // Путь к файлу лога — папка logs/ должна существовать и быть доступной для записи
        $logFile = __DIR__ . '/../../logs/app.log';

        // Форматируем: [YYYY-MM-DD HH:MM:SS] сообщение
        $timestamp = (new DateTime())->format('Y-m-d H:i:s');
        $line = sprintf("[%s] %s%s", $timestamp, $message, PHP_EOL);

        // Добавляем запись в конец файла
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
?>
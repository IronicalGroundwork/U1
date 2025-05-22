<?php
class AuthHelper {
    public static function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        if (!isset($_SESSION['seller']['id'])) {
            MessageHelper::showError('Требуется авторизация');
            header('Location: /index.php?action=login');
            exit;
        }
    }
}
?>
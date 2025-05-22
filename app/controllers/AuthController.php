<?php
require_once __DIR__ . '/../models/Seller.php';
require_once __DIR__ . '/../models/Database.php';

// Контроллер для управления аутентификацией пользователей
class AuthController {
    private $sellerModel;

    // Инициализация модели работы с продавцами
    public function __construct() {
        $this->sellerModel = new Seller(Database::getInstance()->getConnection());
    }

    // Выход
    public function logout() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        header('Location: index.php?action=login');
        exit;
    }

    // Отображение формы входа
    public function loginForm() {
        require_once __DIR__ . '/../views/auth/login.php';
    }

    // Обработка формы входа
    public function login() {        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=login');
            exit;
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $user = $this->sellerModel->findByEmail($email);
            
            if (!$user) throw new Exception('Пользователь не найден');
            if (!password_verify($password, $user['password_hash'])) throw new Exception('Неверный пароль');
            if (!$user['is_confirmed']) throw new Exception('Аккаунт не подтверждён');

            $_SESSION['seller'] = $user;
            header('Location: index.php?action=dashboard');
            exit;
        } catch (Exception $e) {
            MessageHelper::showError($e->getMessage());
            header('Location: index.php?action=login');
            exit;
        }
    }

    // Отображение формы регистрации
    public function registerForm() {
        require_once __DIR__ . '/../views/auth/register.php';
    }

    // Обработка формы регистрации
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=register');
            exit;
        }

        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? ''
        ];

        try {

            if (!$data['first_name']) throw new Exception('Имя обязательно');
            if (!$data['last_name']) throw new Exception('Фамилия обязательна');
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Некорректный email');
            if ($this->sellerModel->findByEmail($data['email'])) throw new Exception('Email уже занят');
            if (strlen($data['password']) < 6) throw new Exception('Пароль менее 6 символов');
            if ($data['password'] !== $data['confirm_password']) throw new Exception('Пароли не совпадают');

            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $data['confirmation_code'] = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            $this->sellerModel->create($data);
            MessageHelper::showSuccess('Регистрация успешна! Проверьте email');
            header('Location: index.php?action=login');
            exit;
        } catch (Exception $e) {
            MessageHelper::showError($e->getMessage());
            header('Location: index.php?action=register');
            exit;
        }
    }

    // Отображение формы восстановления пароля
    public function forgotPasswordForm() {
        require_once __DIR__ . '/../views/auth/forgot-password.php';
    }
}
?>
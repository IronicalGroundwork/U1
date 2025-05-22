<?php
session_start();
require_once __DIR__ . '/app/models/Database.php';
require_once __DIR__ . '/app/models/Seller.php';
require_once __DIR__ . '/app/helpers/MessageHelper.php';
require_once __DIR__ . '/app/helpers/AuthHelper.php';

// Автозагрузка классов
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/app/controllers/',
        __DIR__ . '/app/models/'
    ];
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Инициализация подключения к БД
Database::init('localhost', 'u1', 'root', '');
?>
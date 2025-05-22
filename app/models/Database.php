<?php
class Database {
    private static $instance = null;
    private $pdo;

    // Приватный конструктор
    private function __construct($host, $dbname, $user, $pass) {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    // Метод для получения экземпляра класса
    public static function getInstance() {
        if (self::$instance === null) {
            throw new Exception("Database not initialized. Call Database::init() first.");
        }
        return self::$instance;
    }

    // Инициализация подключения
    public static function init($host, $dbname, $user, $pass) {
        if (self::$instance === null) {
            self::$instance = new self($host, $dbname, $user, $pass);
        }
    }

    // Получить PDO-объект
    public function getConnection() {
        return $this->pdo;
    }
}
?>
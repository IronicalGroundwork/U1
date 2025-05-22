<?php
class Seller {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM seller WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO seller 
            (first_name, last_name, email, password_hash, confirmation_code) 
            VALUES (?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['password_hash'],
            $data['confirmation_code']
        ]);
    }

    public function getSellerById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM seller WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile($userId, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE seller SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone = ?, 
            birthday = ? 
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'],
            $data['birthday'],
            $userId
        ]);
    }

    public function updateProfileImage($userId, $filename) {
        $stmt = $this->pdo->prepare("UPDATE seller SET image = ? WHERE id = ?");
        return $stmt->execute([$filename, $userId]);
    }
}
?>
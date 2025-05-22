<?php
require_once __DIR__.'/../helpers/FileUploaderHelper.php';

class ProfileController {

    public function __construct() {
        $db = Database::getInstance()->getConnection();
        $this->sellerModel = new Seller($db);
        $this->uploader = new FileUploaderHelper('assets/img/illustrations/profiles/');
    }

    public function index() {
        AuthHelper::checkAuth();

        $seller = $_SESSION['seller'];

        require_once __DIR__.'/../views/profile/index.php';
    }

    public function handleRequest() {
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if (isset($_POST['upload_image'])) {
                    $this->handleImageUpload();
                } elseif (isset($_POST['update_profile'])) {
                    $this->handleProfileUpdate();
                }
            }
            $this->showProfile();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /profile");
        }
    }

    public function imageUpload() {
        try {
            $userId = $_SESSION['seller']['id'];
            $oldImage = $_SESSION['seller']['image'];
            
            $newFilename = $this->uploader->upload($_FILES['profile_image'], [
                'max_size' => 5 * 1024 * 1024,
                'allowed_types' => ['image/jpeg', 'image/png']
            ]);

            if ($this->sellerModel->updateProfileImage($userId, $newFilename)) {
                if ($oldImage && $oldImage != 'default.png') {
                    $this->uploader->delete($oldImage);
                }
                $_SESSION['seller']['image'] = $newFilename;
                MessageHelper::showSuccess('Изображение успешно обновлено!');
            }
        } catch (Exception $e) {
            MessageHelper::showError($e->getMessage()); 
        }

        header('Location: index.php?action=profile'); 
    }

    public function profileUpdate() {
        try {
            $data = [
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'birthday' => $_POST['birthday']
            ];

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Некорректный email');
            }

            if ($this->sellerModel->updateProfile($_SESSION['seller']['id'], $data)) {
                $_SESSION['seller'] = array_merge($_SESSION['seller'], $data);
                MessageHelper::showSuccess('Данные профиля успешно обновлены!');
            }
                        
        } catch (Exception $e) {
            MessageHelper::showError($e->getMessage()); 
        }

        header('Location: index.php?action=profile');        
    }
}
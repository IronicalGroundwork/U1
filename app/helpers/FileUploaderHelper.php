<?php
class FileUploaderHelper {
    private $uploadPath;

    public function __construct($uploadPath) {
        $this->uploadPath = rtrim($uploadPath, '/') . '/';
    }

    public function upload($file, $options = []) {
        if (empty($file['name'])) {
            throw new Exception('Выберите файл для загрузки');
        }

        if (!in_array($file['type'], $options['allowed_types'] ?? [])) {
            throw new Exception('Недопустимый тип файла');
        }

        if ($file['size'] > ($options['max_size'] ?? 5242880)) {
            throw new Exception('Превышен максимальный размер файла');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = uniqid() . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $this->uploadPath . $newFilename)) {
            throw new Exception('Ошибка загрузки файла');
        }

        return $newFilename;
    }

    public function delete($filename) {
        $filePath = $this->uploadPath . $filename;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}
<?php
class Settings {
    private $pdo;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /** Получает настройки (или дефолт, если их ещё нет) */
    public function getSettings(int $sellerId): array {
        $stmt = $this->db->prepare("
            SELECT ozon_enabled, yandex_enabled, wb_enabled, wb_frequency, wb_task_id
              FROM update_settings
             WHERE seller_id = :sid
        ");
        $stmt->execute([':sid'=>$sellerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'ozon_enabled'   => 0,
                'yandex_enabled' => 0,
                'wb_enabled'     => 0,
                'wb_frequency'   => 60,
                'wb_task_id'     => null,
            ];
        }
        return $row;
    }

    /** Сохраняет (вставка или обновление) */
    public function saveSettings(int  $sellerId, bool $ozon, bool $yandex, bool $wbEnabled,int  $wbFreq): void {
        $stmt = $this->db->prepare("
            INSERT INTO update_settings
              (seller_id, ozon_enabled, yandex_enabled, wb_enabled, wb_frequency)
            VALUES
              (:sid, :oz, :ya, :wbEn, :wbFr)
            ON DUPLICATE KEY UPDATE
              ozon_enabled   = VALUES(ozon_enabled),
              yandex_enabled = VALUES(yandex_enabled),
              wb_enabled     = VALUES(wb_enabled),
              wb_frequency   = VALUES(wb_frequency)
        ");
        $stmt->execute([
            ':sid'   => $sellerId,
            ':oz'    => $ozon       ? 1 : 0,
            ':ya'    => $yandex     ? 1 : 0,
            ':wbEn'  => $wbEnabled  ? 1 : 0,
            ':wbFr'  => $wbFreq
        ]);
    }

    public function setWbTaskId(int $sellerId, string $taskId): void {
        $stmt = $this->db->prepare("
            UPDATE update_settings
               SET wb_task_id = :tid
             WHERE seller_id = :sid
        ");
        $stmt->execute([':tid'=>$taskId,':sid'=>$sellerId]);
    }
}
?>
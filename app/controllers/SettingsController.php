<?php
require_once __DIR__ . '/../models/Settings.php';

class SettingsController {
 
    public function __construct() {
        $db = Database::getInstance()->getConnection();
        $this->settingsModel = new Settings($db);
        $this->sellerId = $_SESSION['seller']['id']?? 0;
    }

    public function index(): void  {
        AuthHelper::checkAuth();

        $sidenav_link_6 = 'active';

        $freqOptions = [
            15   => 'Каждые 15 минут',
            30   => 'Каждые 30 минут',
            60   => 'Каждый час',
            360  => 'Каждые 6 часов',
            1440 => 'Каждый день',
        ];
        
        $settings = $this->settingsModel->getSettings($this->sellerId);
        include __DIR__ . '/../views/settings/index.php';
    }

    public function update(): void {
        // 1) Считываем старые настройки
        $old = $this->settingsModel->getSettings($this->sellerId);

        // 2) Читаем новые из формы
        $ozon      = isset($_POST['ozon_enabled']);
        $yandex    = isset($_POST['yandex_enabled']);
        $wbEnabled = isset($_POST['wb_enabled']);
        $wbFreq    = (int)($_POST['wb_frequency'] ?? 60);
        $allowed   = [15,30,60,360,1440];
        if (!in_array($wbFreq, $allowed, true)) {
            $wbFreq = 60;
        }

        $this->settingsModel->saveSettings($this->sellerId, $ozon, $yandex, $wbEnabled, $wbFreq);

        // 4) Управление cron-задачей для Wildberries
        $taskId = $old['wb_task_id'];
        if ($wbEnabled) {
            // включаем или создаём
            $schedule = sprintf(
                "BEGIN:VEVENT\nDTSTART:%sZ\nRRULE:FREQ=MINUTELY;INTERVAL=%d\nEND:VEVENT",
                gmdate('Ymd\THis'),
                $wbFreq
            );
            if ($taskId) {
                // обновляем существующую задачу
                automations.update([
                    'jawbone_id' => $taskId,
                    'schedule'   => $schedule,
                    'is_enabled' => true
                ]);
            } else {
                // создаём новую задачу
                $resp = automations.create({
                    'title'    : 'Загрузить заказы Wildberries',
                    'prompt'   : 'Fetch new orders from Wildberries',
                    'schedule' : $schedule
                });
                $taskId = $resp.jawbone.id;
                $this->settingsModel->setWbTaskId($this->sellerId, $taskId);
            }
        } elseif ($taskId) {
            // выключаем существующую задачу
            automations.update([
                'jawbone_id' => $taskId,
                'is_enabled' => false
            ]);
        }

        // 5) Редирект обратно на форму
        header('Location: ?action=settings');
        exit;
    }
}
?>
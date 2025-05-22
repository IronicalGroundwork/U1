<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Настройки - U1</title>
    <?php require __DIR__ . '/../layouts/head.php'; ?>
</head>
<body class="nav-fixed">
    <?php require __DIR__ . '/../layouts/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <div id="layoutSidenav_content">
            <main>
                <div class="container-xl px-4 mt-5">
                    <!-- Заголовок -->
                    <div class="d-flex justify-content-between align-items-sm-center flex-column flex-sm-row mb-4">
                        <div class="me-4 mb-3 mb-sm-0">
                            <h1 class="mb-0">Настройки</h1>
                            <?php setlocale(LC_TIME, 'ru_RU.UTF-8');?>
                            <div class="small">
                                <span class="fw-500 text-primary"><?= strftime('%A') ?></span>&middot; <?= strftime('%d.%m.%Y') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Вывод системных сообщений -->
                    <?php MessageHelper::display(); ?>

                    <div class="row">
                        <div class="col-xl-12">
                           <form method="post" action="?action=do-settings" class="mt-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="ozon_enabled" name="ozon_enabled" <?= $settings['ozon_enabled'] ? 'checked' : '' ?> >
                                    <label class="form-check-label" for="ozon_enabled">
                                        Принимать уведомления от OZON
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="yandex_enabled" name="yandex_enabled" <?= $settings['yandex_enabled'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="yandex_enabled">
                                        Принимать уведомления от Яндекс.Маркета
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="wb_enabled" name="wb_enabled" <?= $settings['wb_enabled'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="wb_enabled">
                                        Принимать (или отключить) обновления из Wildberries
                                    </label>
                                </div>

                                <div class="mb-4">
                                    <label for="wb_frequency" class="form-label">
                                        Частота обновления данных из Wildberries
                                    </label>
                                    <select id="wb_frequency" name="wb_frequency" class="form-select w-auto" >
                                        <?php foreach($freqOptions as $val => $lbl): ?>
                                        <option
                                            value="<?= $val ?>"
                                            <?= $settings['wb_frequency']==$val ? 'selected' : '' ?>
                                        ><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </form>
                        </div>
                    </div>
                  
                </div>
            </main>
            <?php require __DIR__ . '/../layouts/footer.php'; ?>
        </div>
    </div>   
    <?php require __DIR__ . '/../layouts/scripts.php'; ?>
</body>
</html>
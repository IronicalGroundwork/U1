<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Маркетплейсы - U1</title>
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
                            <h1 class="mb-0">Маркетплейсы</h1>
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
                            <div class="card mb-4">
                                <div class="card-header">OZON</div>
                                <div class="card-body">
                                    <?php $ozon = array_filter($connected_marketplaces, fn($m) => $m['platform_id'] == 1);
                                          $ozon = reset($ozon);
                                          if ($ozon): ?>
                                    <form method="POST" action="index.php?action=disconnect-marketplaces">
                                        <input type="hidden" name="platform_id" value="1">
                                        <div class="mb-3">
                                            <label class="small mb-1">Название</label>
                                            <input class="form-control" type="text" value="<?= htmlspecialchars($ozon['name']) ?>" disabled/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="small mb-1">Client ID</label>
                                            <input class="form-control" type="text" value="<?= htmlspecialchars($ozon['client_id']) ?>" disabled/>
                                        </div>
                                        <button type="submit" class="btn btn-danger">Отключить</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" action="index.php?action=connect-marketplaces">
                                        <input type="hidden" name="platform_id" value="1">
                                        <div class="mb-3">
                                            <label class="small mb-1">Название</label>
                                            <input class="form-control" name="name" type="text" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="small mb-1">Client ID</label>
                                            <input class="form-control" name="client_id" type="text" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="small mb-1">API Key</label>
                                            <input class="form-control" name="api_key" type="text" required/>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Подключить</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card mb-4">
                                <div class="card-header">Wildberries</div>
                                <div class="card-body">
                                    <?php $wildberries = array_filter($connected_marketplaces, fn($m) => $m['platform_id'] == 2);
                                          $wildberries = reset($wildberries);
                                          if ($wildberries): ?>
                                    <form method="POST" action="index.php?action=disconnect-marketplaces">
                                        <input type="hidden" name="platform_id" value="2">                                       
                                        <div class="mb-3">
                                            <label class="small mb-1">Название</label>
                                            <input class="form-control" type="text" value="<?= htmlspecialchars($wildberries['name']) ?>" disabled/>
                                        </div>
                                        <button type="submit" class="btn btn-danger">Отключить</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" action="index.php?action=connect-marketplaces">
                                        <input type="hidden" name="platform_id" value="2"> 
                                        <div class="mb-3">
                                            <label class="small mb-1">Токен</label>
                                            <input class="form-control" name="api_key" type="text" />
                                        </div>
                                        <button type="submit" class="btn btn-primary">Подключить</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card mb-4">
                                <div class="card-header">Яндекс Маркет</div>
                                <div class="card-body">
                                    <?php $yandex = array_filter($connected_marketplaces, fn($m) => $m['platform_id'] == 3);
                                          $yandex = reset($yandex);
                                          if ($yandex): ?>
                                    <form method="POST" action="index.php?action=disconnect-marketplaces">
                                        <input type="hidden" name="platform_id" value="3">
                                        <div class="mb-3">
                                            <label class="small mb-1">Название</label>
                                            <input class="form-control" type="text" value="<?= htmlspecialchars($yandex['name']) ?>" disabled/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="small mb-1">ID кабинета</label>
                                            <input class="form-control" type="text" value="<?= htmlspecialchars($yandex['client_id']) ?>" disabled/>
                                        </div>
                                        <button type="submit" class="btn btn-danger">Отключить</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" action="index.php?action=connect-marketplaces">
                                        <input type="hidden" name="platform_id" value="3">
                                        <div class="mb-3">
                                            <label class="small mb-1">ID кабинета</label>
                                            <input class="form-control" name="client_id" type="text" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="small mb-1">Токен</label>
                                            <input class="form-control" name="api_key" type="text" />
                                        </div>
                                        <button type="submit" class="btn btn-primary">Подключить</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                      </div>
                    <div>
                </div>
            </main>
            <?php require __DIR__ . '/../layouts/footer.php'; ?>
        </div>
    </div>   
    <?php require __DIR__ . '/../layouts/scripts.php'; ?>
</body>
</html>
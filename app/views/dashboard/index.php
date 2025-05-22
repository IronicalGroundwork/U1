<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Сводка - U1</title>
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
                            <h1 class="mb-0">Сводка</h1>
                            <?php setlocale(LC_TIME, 'ru_RU.UTF-8');?>
                            <div class="small">
                                <span class="fw-500 text-primary"><?= strftime('%A') ?></span>&middot; <?= strftime('%d.%m.%Y') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Вывод системных сообщений -->
                    <?php MessageHelper::display(); ?>

                    <!-- Вывод виджетов -->
                    <div class="row">
                        <!-- Товары -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-start-lg border-start-primary h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small fw-bold text-primary mb-1">Товары</div>
                                            <div class="h5"><?= $data['widgets']['totalProducts'] ?? 0 ?></div>
                                        </div>
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- На складах -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-start-lg border-start-warning h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small fw-bold text-warning mb-1">На складах</div>
                                            <div class="h5"><?= $data['widgets']['totalStock'] ?? 0 ?></div>
                                        </div>
                                        <i class="fas fa-pallet fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Площадки -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-start-lg border-start-info h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small fw-bold text-info mb-1">Площадки</div>
                                            <div class="h5"><?= $data['widgets']['marketplacesCount'] ?? 0 ?></div>
                                        </div>
                                        <i class="fas fa-store-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Стоимость запасов -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-start-lg border-start-danger h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small fw-bold text-danger mb-1">Стоимость запасов</div>
                                            <div class="h5">
                                                <?= number_format($data['widgets']['totalInventoryValue'] ?? 0, 0, '', ' ') ?> ₽
                                            </div>
                                        </div>
                                        <i class="fas fa-coins fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                     
                    <!-- Графики -->
                    <div class="row">
                        <div class="col-xl-12">
                            <div class="card mb-4">
                                <div class="card-header">Активность за 7 дней</div>
                                <div class="card-body">
                                    <div class="chart-area">
                                        <canvas id="movementChart" width="100%" height="30"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header">Подтвержденные товары</div>
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="chart-bar"><canvas id="platformChart" width="100%" height="30"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header">Распределение по складам</div>
                                <div class="card-body">
                                    <div class="chart-pie mb-4"><canvas id="warehouseChart" width="100%" height="50"></canvas></div>
                                    <div class="list-group list-group-flush">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>                        
                </div>
            </main>
            <?php require __DIR__ . '/../layouts/footer.php'; ?>
        </div>
    </div>
    <script>
        window.chartData = {
            movement: <?= json_encode([
                'labels' => array_column($data['charts']['movements'], 'date'),
                'values' => array_column($data['charts']['movements'], 'count')
            ]) ?>,
            warehouse: <?= json_encode([
                'labels' => array_column($data['charts']['warehouses'], 'name'),
                'values' => array_column($data['charts']['warehouses'], 'total'),
                'data' => $data['charts']['warehouses']
            ]) ?>,
            platform: <?= json_encode([
                'labels' => array_column($data['charts']['platforms'], 'name'),
                'values' => array_column($data['charts']['platforms'], 'total')
            ]) ?>
        };
    </script>
    <?php require __DIR__ . '/../layouts/scripts.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js" crossorigin="anonymous"></script>
    <script src="assets/js/dashboard-charts.js"></script>
</body>
</html>
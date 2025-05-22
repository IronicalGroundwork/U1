<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Склады - U1</title>
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
                            <h1 class="mb-0">Склады</h1>
                            <?php setlocale(LC_TIME, 'ru_RU.UTF-8');?>
                            <div class="small">
                                <span class="fw-500 text-primary"><?= strftime('%A') ?></span>&middot; <?= strftime('%d.%m.%Y') ?>
                            </div>
                        </div>
                        <div>
                            <button class="btn btn-primary mb-4" id="addWarehouseBtn">
                                <i data-feather="plus" class="me-2"></i>Добавить
                            </button>
                        </div>
                    </div>

                    <!-- Вывод системных сообщений -->
                    <?php MessageHelper::display(); ?>

                    <div class="row">
                        <div class="col-xl-12">
                            <div id="warehouseFormsContainer"></div>
                            <!-- Список складов -->

                            <?php 
                            foreach ($grouped_warehouses as $id => $wh): ?>
                            <div class="card mb-4" data-warehouse-id="<?= $id ?>">
                                <div class="card-header"><?= htmlspecialchars($wh['name']) ?></div>
                                <div class="card-body">
                                    <?php foreach ($wh['marketplaces'] as $mp): ?>
                                        <div class="mb-3">
                                            <label class="small mb-1">
                                                <?= match($mp['platform']) {
                                                    1 => 'OZON',
                                                    2 => 'Wildberries',
                                                    3 => 'Яндекс'
                                                } ?>
                                            </label>
                                            <input class="form-control" type="text" value="<?= htmlspecialchars($mp['name']) ?> (<?= htmlspecialchars($mp['id']) ?>)" disabled/>
                                        </div>    
                                    <?php endforeach; ?>
                                    <button type="button" class="btn btn-danger delete-warehouse">Удалить</button>
                                    
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                  
                </div>
            </main>
            <?php require __DIR__ . '/../layouts/footer.php'; ?>
        </div>
    </div>

    <!-- Шаблон формы -->
    <template id="warehouseFormTemplate">
        <div class="card mb-4 warehouse-card">
            <div class="card-header">Новый склад</div>
            <div class="card-body">
                <form class="warehouse-form">
                    <div class="mb-3">
                        <label>Название склада</label>
                        <input type="text" class="form-control" name="warehouse_name" required>
                    </div>
                    
                    <?php 
                    foreach ($connected_mps as $mp): ?>
                    <div class="mb-3 platform-select" data-platform="<?= $mp['platform_id'] ?>">
                        <label><?= match($mp['platform_id']) { 
                            1 => 'OZON', 
                            2 => 'Wildberries', 
                            3 => 'Яндекс' 
                        } ?></label>
                        <select class="form-control" name="marketplaces[<?= $mp['id'] ?>]">
                            <option value="">Не привязывать</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <button type="button" class="btn btn-danger ms-2 remove-card">Отмена</button>
                </form>
            </div>
        </div>
    </template>
    
    <?php require __DIR__ . '/../layouts/scripts.php'; ?>
    <script src="assets/js/warehouses.js"></script>
</body>
</html>
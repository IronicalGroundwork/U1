<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Товары - U1</title>
    <?php require __DIR__ . '/../layouts/head.php'; ?>
</head>
<body class="nav-fixed">
    <?php require __DIR__ . '/../layouts/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-xl px-4 mt-5">
                    <div class="d-flex justify-content-between align-items-sm-center flex-column flex-sm-row mb-4">
                        <div class="me-4 mb-3 mb-sm-0">
                            <h1 class="mb-0">Товары</h1>
                            <?php setlocale(LC_TIME, 'ru_RU.UTF-8'); ?>
                            <div class="small">
                                <span class="fw-500 text-primary"><?= strftime('%A') ?></span>
                                    &middot; <?= strftime('%d.%m.%Y') ?>
                            </div>
                        </div>
                        <!-- Блок кнопок действий -->
                        <div>
                            <button id="btn-refresh-stock" class="btn btn-outline-primary me-2">Обновить остатки</button>
                            <button id="btn-generate-sets" class="btn btn-outline-primary me-2">Сгенерировать наборы</button>
                            <button id="btn-refresh-products" class="btn btn-outline-success">Обновить товары</button>
                        </div>
                    </div>
                    <!-- Вывод системных сообщений -->
                    <?php MessageHelper::display(); ?>
                    <div class="row">
                        <!-- Поиск по артикулу и наименованию -->
                        <form method="get" action="index.php" class="d-flex mb-3">
                            <div class="position-relative w-100">
                                <input type="hidden" name="action" value="products">
                                <input
                                    type="text"
                                    id="search-input"
                                    name="search"
                                    value="<?=htmlspecialchars($search)?>"
                                    class="form-control pe-5"
                                    placeholder="Артикул или название"
                                    autocomplete="off"
                                    >
                                    <span
                                    id="clear-search"
                                    class="position-absolute top-50 end-0 translate-middle-y pe-3"
                                    style="cursor: pointer; display: <?= $search!=='' ? 'block' : 'none' ?>"
                                    title="Очистить поиск"
                                    >&times;</span>
                            </div>
                        </form>
                        <div class="col-xl-12">
                            <div class="card mb-4">
                                <div class="table-responsive">
                                    <?php
                                        // Переменные из контроллера:
                                        // $page, $pages, $limit, $search, $sortBy, $sortDir, $products
                                        // Функция-утилита для генерации URL сортировки:
                                        function sortUrl($col, $currentSortBy, $currentSortDir, $page, $limit, $search) {
                                            $dir = ($col === $currentSortBy && $currentSortDir === 'asc') ? 'desc' : 'asc';
                                            return "index.php"
                                                ."?action=products"
                                                ."&page=1"    // сбрасываем на первую страницу при сортировке
                                                ."&limit={$limit}"
                                                ."&search=" . urlencode($search)
                                                ."&sort_by={$col}"
                                                ."&sort_dir={$dir}";
                                        }
                                    ?>
                                    <table class="table table-hover align-middle" id="products-table">
                                        <thead class="table-light align-middle">
                                            <tr>
                                                <th>#</th>
                                                 <th class="border-end">
                                                    <a href="<?=sortUrl('offer_id',$sortBy,$sortDir,$page,$limit,$search)?>">
                                                        Артикул
                                                        <?php if($sortBy==='offer_id'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                                <th>
                                                    <a href="<?=sortUrl('name',$sortBy,$sortDir,$page,$limit,$search)?>">
                                                        Наименование
                                                        <?php if($sortBy==='name'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                                <th class="text-end">
                                                    <a href="<?=sortUrl('cost',$sortBy,$sortDir,$page,$limit,$search)?>">
                                                        Себестоимость
                                                        <?php if($sortBy==='cost'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                                <th class="table-fixed-header">
                                                    <a href="<?=sortUrl('volume_l',$sortBy,$sortDir,$page,$limit,$search)?>">
                                                        Объём, л
                                                        <?php if($sortBy==='volume_l'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                                <th class="table-fixed-header">
                                                    <a href="<?=sortUrl('weight_kg',$sortBy,$sortDir,$page,$limit,$search)?>">
                                                        Вес, кг
                                                        <?php if($sortBy==='weight_kg'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th> 
                                                <th class="text-end">
                                                    <a href="<?=sortUrl('total_stock',$sortBy,$sortDir,$page,$limit,$search)?>">
                                                        Остаток
                                                        <?php if($sortBy==='total_stock'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                                <th class="text-center"><span><i data-feather="settings"></i></span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($products as $i => $p): ?>
                                            <tr >
                                                <td><?=($offset + $i + 1)?></td>
                                                <td class="border-end">
                                                    <span class="offer-cell " style="cursor: pointer;" title="Клик — копировать">
                                                        <?= htmlspecialchars($p['offer_id']) ?>
                                                    </span>
                                                </td>
                                                <td><?=htmlspecialchars($p['name'])?></td>
                                                <td><?=$p['cost']?> ₽</td>
                                                <td><?=$p['volume_l']?></td>
                                                <td><?=$p['weight_kg']?></td>
                                                <td class="dropdown">
                                                    <button
                                                        class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                        type="button"
                                                        id="stockDropdown<?=$p['id']?>"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false"
                                                        data-product-id="<?=$p['id']?>">
                                                        <?=$p['total_stock']?>
                                                    </button>
                                                    <div
                                                        class="dropdown-menu p-3"
                                                        aria-labelledby="stockDropdown<?=$p['id']?>"
                                                        style="min-width:220px;">
                                                        <div class="stock-content"></div>
                                                    </div>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <button
                                                        class="btn btn-xs btn-outline-link"
                                                        type="button"
                                                        id="actionDropdown<?=$p['id']?>"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false">
                                                        <i data-feather="more-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="actionDropdown<?=$p['id']?>">
                                                        <li><a class="dropdown-item edit-product" href="#" data-product-id="<?=$p['id']?>">Редактировать</a></li>
                                                        <li><a class="dropdown-item delete-product text-danger" href="#" data-product-id="<?=$p['id']?>">Удалить</a></li>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="d-flex justify-content-between align-items-center my-3 px-3">
                                        <!-- Селектор размера страницы -->
                                        <form method="get" action="index.php?action" class="d-flex align-items-center flex-nowrap">
                                            <input type="hidden" name="action" value="products">
                                            <input type="hidden" name="search" value="<?=htmlspecialchars($search)?>">
                                            <label class="me-2 mb-0 text-nowrap">Строк на странице</label>
                                            <select
                                            name="limit"
                                            class="form-select form-select-sm me-3 flex-shrink-0 w-auto"
                                            onchange="this.form.submit()"
                                            >
                                            <?php foreach ([10,20,50,100] as $opt): ?>
                                                <option value="<?=$opt?>" <?=$opt === $limit ? 'selected' : ''?>>
                                                <?=$opt?>
                                                </option>
                                            <?php endforeach; ?>
                                            </select>
                                        </form>

                                        <!-- Пагинация -->
                                        <nav aria-label="Страницы">
                                            <ul class="pagination pagination-sm mb-0">
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" 
                                                    href="index.php?action=products&page=<?= max(1, $page-1) ?>&search=<?=urlencode($search)?>&limit=<?=$limit?>"
                                                    aria-label="Назад">
                                                    &laquo;
                                                </a>
                                            </li>
                                            <?php for($p = 1; $p <= $pages; $p++): ?>
                                                <?php if (abs($p - $page) <= 2 || $p == 1 || $p == $pages): ?>
                                                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                                    <a class="page-link" 
                                                        href="index.php?action=products&page=<?=$p?>&search=<?=urlencode($search)?>&limit=<?=$limit?>">
                                                        <?=$p?>
                                                    </a>
                                                    </li>
                                                <?php elseif (abs($p - $page) === 3): ?>
                                                    <li class="page-item disabled">
                                                    <span class="page-link">…</span>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endfor; ?>        
                                            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                                <a class="page-link" 
                                                    href="index.php?action=products&page=<?= min($pages, $page+1) ?>&search=<?=urlencode($search)?>&limit=<?=$limit?>"
                                                    aria-label="Вперёд">
                                                    &raquo;
                                                </a>
                                            </li>
                                            </ul>
                                        </nav>
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
    
    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактировать товар</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="product-form">
                <div class="modal-body">
                <input type="hidden" name="product[id]" id="prod-id">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-main" type="button">Основное</button>
                    </li>
                    <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-set" type="button">Состав набора</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <!-- Основное -->
                    <div class="tab-pane fade show active" id="tab-main">
                    <div class="mb-3">
                        <label for="prod-offer" class="form-label">Артикул</label>
                        <input type="text" class="form-control" id="prod-offer" name="product[offer_id]">
                    </div>
                    <div class="mb-3">
                        <label for="prod-name" class="form-label">Наименование</label>
                        <input type="text" class="form-control" id="prod-name" name="product[name]">
                    </div>
                    <div class="mb-3">
                        <label for="prod-cost" class="form-label">Себестоимость</label>
                        <input type="number" class="form-control" id="prod-cost" name="product[cost]">
                    </div>
                    <div class="mb-3">
                        <label for="prod-length" class="form-label">Длина (мм)</label>
                        <input type="number" class="form-control" id="prod-length" name="product[length]">
                    </div>
                    <div class="mb-3">
                        <label for="prod-width" class="form-label">Ширина (мм)</label>
                        <input type="number" class="form-control" id="prod-width" name="product[width]">
                    </div>
                    <div class="mb-3">
                        <label for="prod-height" class="form-label">Высота (мм)</label>
                        <input type="number" class="form-control" id="prod-height" name="product[height]">
                    </div>
                    <div class="mb-3">
                        <label for="prod-weight" class="form-label">Вес (г)</label>
                        <input type="number" class="form-control" id="prod-weight" name="product[weight]">
                    </div>
                    </div>
                    <!-- Состав набора -->
                    <div class="tab-pane fade" id="tab-set">
                    <div class="table-responsive position-relative">
                        <table class="table align-middle" id="set-table">
                            <thead>
                                <tr><th>Товар</th><th>Количество</th><th></th></tr>
                            </thead>
                            <tbody id="set-table-body"></tbody>
                            <tfoot>
                                <tr>
                                <td colspan="3">
                                    <input type="text" id="set-search-input" class="form-control" placeholder="Введите артикул или название и нажмите Enter">
                                </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    </div>
                </div>
                </div>
                <div class="modal-footer">
                <button type="submit" class="btn btn-light" id="btn-save-product" disabled>Сохранить</button>    
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                </div>
            </form>
            <div id="set-search-results" class="list-group" style="display:none; position:absolute; z-index:1050;"></div>
            </div>
        </div>
    </div>


    <div id="page-overlay" style="
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        text-align: center;
        ">
        <div class="spinner-border text-light" role="status"
            style="
            position: absolute;
            top: 50%; left: 50%;
            width: 3rem; height: 3rem;
            margin-top: -1.5rem; margin-left: -1.5rem;
            ">
        <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <?php require __DIR__ . '/../layouts/scripts.php'; ?>
    <script src="assets/js/products.js"></script>
</body>
</html>
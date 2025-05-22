<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Движения по товарам - U1</title>
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
                            <h1 class="mb-0">Движения по товарам</h1>
                            <?php setlocale(LC_TIME, 'ru_RU.UTF-8'); ?>
                            <div class="small">
                                <span class="fw-500 text-primary"><?= strftime('%A') ?></span>
                                    &middot; <?= strftime('%d.%m.%Y') ?>
                            </div>
                        </div>
                    </div>
                    <!-- Вывод системных сообщений -->
                    <?php MessageHelper::display(); ?>
                    <div class="row">
                        <!-- Поиск по артикулу и наименованию -->
                        <form id="mov-form" method="get" action="?action=movements" class="row d-flex mb-3 filters">
                            <input type="hidden" name="action"   value="movements">
                            <input type="hidden" name="limit"    value="<?=htmlspecialchars($limit)?>">
                            <input type="hidden" name="sort_by"  value="<?=htmlspecialchars($sortBy)?>">
                            <input type="hidden" name="sort_dir" value="<?=htmlspecialchars($sortDir)?>">
                            <input type="hidden" name="page"     value="1">

                            <div class="col-md-4 position-relative">
                                <input
                                type="text"
                                id="search-input"
                                name="search"
                                value="<?=htmlspecialchars($search)?>"
                                class="form-control pe-5"
                                placeholder="Артикул или название"
                                >
                                <span
                                id="clear-search"
                                class="position-absolute top-50 end-0 translate-middle-y pe-3"
                                style="cursor:pointer; display:<?= $search!=='' ? 'block' : 'none' ?>"
                                title="Очистить поиск"
                                >&times;</span>
                            </div>

                            <div class="col-md-2">
                            <select name="warehouse" class="form-select">
                                <option value="">Все склады</option>
                                <?php foreach($warehouses as $w): ?>
                                <option value="<?=$w['id']?>" <?= $wh === $w['id'] ? 'selected' : ''?>>
                                    <?=htmlspecialchars($w['name'])?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            </div>

                            <div class="col-md-2">
                            <select name="type" class="form-select">
                                <option value="">Все типы</option>
                                <?php foreach($types as $t): ?>
                                <option value="<?=$t['id']?>" <?= $type === $t['id'] ? 'selected' : ''?>>
                                    <?=htmlspecialchars($t['name'])?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            </div>

                            <div class="col-md-2">
                            <input
                                type="date"
                                name="date_from"
                                value="<?=htmlspecialchars($from)?>"
                                class="form-control"
                            >
                            </div>

                            <div class="col-md-2">
                            <input
                                type="date"
                                name="date_to"
                                value="<?=htmlspecialchars($to)?>"
                                class="form-control"
                            >
                            </div>
                        </form>
                        <div class="col-xl-12">
                            <div class="card mb-4">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="movements-table">
                                        <thead class="table-light align-middle table-fixed-header">
                                            <tr>
                                                <th>#</th>
                                                <th class="border-end">
                                                    <a href="<?=query(['sort_by'=>'offer_id','sort_dir'=>($sortBy==='offer_id' && $sortDir==='asc'?'desc':'asc'), 'page'=>1])?>">
                                                        Артикул 
                                                        <?php if($sortBy==='offer_id'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                                <th>
                                                    <a href="<?=query(['sort_by'=>'name', 'sort_dir'=>($sortBy==='name' && $sortDir==='asc'?'desc':'asc'), 'page'=>1])?>">
                                                        Наименование
                                                        <?php if($sortBy==='name'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                                <th class="text-center" style="width:5%">
                                                    <a href="<?=query(['sort_by'=>'warehouse', 'sort_dir'=>($sortBy==='warehouse' && $sortDir==='asc'?'desc':'asc'), 'page'=>1])?>">
                                                        Склад
                                                        <?php if($sortBy==='warehouse'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                                <th class="text-center" style="width:5%">Движение</th>
                                                <th class="text-center" style="width:15%">
                                                    <a href="<?=query(['sort_by'=>'movement_type', 'sort_dir'=>($sortBy==='movement_type' && $sortDir==='asc'?'desc':'asc'), 'page'=>1])?>">
                                                        Тип
                                                        <?php if($sortBy==='movement_type'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                                <th class="text-center" style="width:5%">
                                                    <a href="<?=query(['sort_by'=>'created_at', 'sort_dir'=>($sortBy==='created_at' && $sortDir==='asc'?'desc':'asc'), 'page'=>1])?>">
                                                        Дата
                                                        <?php if($sortBy==='created_at'): ?>
                                                            <i data-feather="<?= $sortDir==='asc'?'chevron-up':'chevron-down' ?>"></i>
                                                        <?php endif;?>
                                                    </a>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tbody>
                                            <?php foreach($movements as $i => $m): 
                                                $old  = $m['old_quantity'];
                                                $new  = $m['new_quantity'];
                                                $cls= $new>$old ? 'text-success' : ($new<$old?'text-danger':'');
                                                $dt   = new DateTime($m['created_at']);
                                                $date = $dt->format('d.m.Y');
                                                $time = $dt->format('H:i:s');
                                            ?>
                                            <tr>
                                                <td><?= ($page-1)*$limit + $i +1 ?></td>
                                                <td class="border-end"><?= htmlspecialchars($m['offer_id']) ?></td>
                                                <td><?= htmlspecialchars($m['name']) ?></td>
                                                <td class="text-center"><?= htmlspecialchars($m['warehouse']) ?></td>
                                                <td class="<?=$cls?> no-wrap"><?=$old?> → <?=$new?></td>
                                                <td><?= htmlspecialchars($m['movement_type']) ?></td>
                                                <td class="text-center">
                                                    <div><?=$date?></div>
                                                    <div><?=$time?></div>
                                                </td>
                                            </tr>
                                            <?php endforeach;?>
                                        </tbody>
                                    </table>
                                    <div class="d-flex justify-content-between align-items-center my-3 px-3">
                                        <div class="d-flex align-items-center flex-nowrap">
                                            <label class="me-2 mb-0 text-nowrap">Строк на странице:</label>
                                            <select id="mov-limit" class="form-select form-select-sm me-3 flex-shrink-0 d-inline-block w-auto">
                                                <?php foreach([10,20,50,100] as $l): ?>
                                                <option value="<?=$l?>" <?=$limit==$l?'selected':''?>><?=$l?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <nav aria-label="Страницы">
                                            <ul class="pagination pagination-sm mb-0">
                                                <li class="page-item <?= $page<=1?'disabled':'' ?>">
                                                    <a class="page-link" href="<?= $page>1 ? query(['page'=>$page-1]) : 'javascript:void(0)' ?>">
                                                    &laquo;
                                                    </a>
                                                </li>
                                                <?php for($p = 1; $p <= $pages; $p++): ?>
                                                    <?php if (abs($p - $page) <= 2 || $p == 1 || $p == $pages): ?>
                                                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                                            <a class="page-link" href="<?=query(['page'=>$p])?>"><?=$p?></a>
                                                        </li>
                                                    <?php elseif (abs($p - $page) === 3): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">…</span>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endfor; ?>   
                                                <li class="page-item <?= $page>=$pages?'disabled':'' ?>">
                                                    <a class="page-link" href="<?= $page<$pages ? query(['page'=>$page+1]) : 'javascript:void(0)' ?>">
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

    <?php require __DIR__ . '/../layouts/scripts.php'; ?>
    <script>
        // Чтобы JS знал текущие GET-параметры
        window.movParams = <?= json_encode($_GET, JSON_HEX_TAG) ?>;
    </script>
    <script src="assets/js/movements.js"></script>
</body>
</html>
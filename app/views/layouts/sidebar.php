<div id="layoutSidenav_nav">
    <nav class="sidenav shadow-right sidenav-light">
        <div class="sidenav-menu">
            <div class="nav accordion" id="accordionSidenav">
            <div class="sidenav-menu-heading d-sm-none">Профиль</div>
            <div class="sidenav-menu-heading">Главное</div>
            <a class="nav-link <?= htmlspecialchars($sidenav_link_1) ?>" href="index.php?action=dashboard">
                <div class="nav-link-icon"><i data-feather="activity"></i></div>
                Сводка
            </a>
            <a class="nav-link <?= htmlspecialchars($sidenav_link_2) ?>" href="index.php?action=products">
                <div class="nav-link-icon"><i data-feather="shopping-bag"></i></div>
                Товары
            </a>
            <div class="sidenav-menu-heading">Отчёты</div>
            <a class="nav-link <?= htmlspecialchars($sidenav_link_3) ?>" href="index.php?action=movements">
                <div class="nav-link-icon"><i data-feather="trending-up"></i></div>
                Движения по товарам
            </a>
            <div class="sidenav-menu-heading">Настройки</div>
            <a class="nav-link <?= htmlspecialchars($sidenav_link_4) ?>" href="index.php?action=warehouses">
                <div class="nav-link-icon"><i data-feather="package"></i></div>
                Склады
            </a>
            <a class="nav-link <?= htmlspecialchars($sidenav_link_5) ?>" href="index.php?action=marketplaces">
                <div class="nav-link-icon"><i data-feather="shopping-cart"></i></div>
                Маркетплейсы
            </a>
            <a class="nav-link <?= htmlspecialchars($sidenav_link_6) ?>" href="index.php?action=settings">
                <div class="nav-link-icon"><i data-feather="refresh-cw"></i></div>
                Обновления
            </a>
            </div>
        </div>
        <div class="sidenav-footer">
            <div class="sidenav-footer-content">
            <div class="sidenav-footer-subtitle">Вы вошли как:</div>
            <div class="sidenav-footer-title">
                <?= htmlspecialchars($_SESSION['seller']['first_name'].' '.$_SESSION['seller']['last_name']) ?>
            </div>
            </div>
        </div>
    </nav>
</div>
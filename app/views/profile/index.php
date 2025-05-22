<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Профиль - U1</title>
    <?php require __DIR__ . '/../layouts/head.php'; ?>
</head>
<body class="nav-fixed">
    <?php require __DIR__ . '/../layouts/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <div id="layoutSidenav_content">
            <main>
                <div class="container-xl px-4 mt-4">
                    <nav class="nav nav-borders">
                        <a class="nav-link active ms-0" href="account-profile.html">Профиль</a>
                        <a class="nav-link" href="account-billing.html">Тарифы</a>
                        <a class="nav-link" href="account-security.html">Безопасность</a>
                        <a class="nav-link" href="account-notifications.html">Уведомления</a>
                    </nav>
                    <hr class="mt-0 mb-4" />
                    <div class="row">
                        <div class="col-xl-4">
                            <div class="card mb-4 mb-xl-0">
                                <div class="card-header">Изображение профиля</div>
                                <div class="card-body text-center">
                                    <form method="post" enctype="multipart/form-data" action="index.php?action=update-profile-image">
                                        <img class="img-account-profile rounded-circle mb-2" src="assets/img/illustrations/profiles/<?= htmlspecialchars($seller['image']) ?>" alt="Profile Image" />
                                        <div class="small font-italic text-muted mb-4">JPG или PNG размером не более 5 МБ</div>
                                        <div class="input-group">
                                            <input type="file" name="profile_image" class="form-control" accept="image/jpeg, image/png">
                                            <button type="submit" name="upload_image" class="btn btn-primary">
                                                <i class="fas fa-upload me-2"></i>Загрузить
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <div class="card mb-4">
                                <div class="card-header">Детали профиля</div>
                                <div class="card-body">
                                    <form method="post" action="index.php?action=update-profile">
                                        <div class="row gx-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="small mb-1" for="inputFirstName">Имя</label>
                                                <input class="form-control" id="inputFirstName" name="first_name" type="text" placeholder="Введите свое имя" value="<?= htmlspecialchars($seller['first_name']) ?>" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="small mb-1" for="inputLastName">Фамилия</label>
                                                <input class="form-control" id="inputLastName" name="last_name" type="text" placeholder="Введите свою фамилию" value="<?= htmlspecialchars($seller['last_name']) ?>" />
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="small mb-1" for="inputEmailAddress">Адрес электронной почты</label>
                                            <input class="form-control" id="inputEmailAddress" name="email" type="email" placeholder="Введите свой адрес электронной почты" value="<?= htmlspecialchars($seller['email']) ?>" />
                                        </div>
                                        <div class="row gx-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="small mb-1" for="inputPhone">Номер телефона</label>
                                                <input class="form-control" id="inputPhone" name="phone" type="tel" placeholder="Введите свой номер телефона" value="<?= htmlspecialchars($seller['phone']) ?>" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="small mb-1" for="inputBirthday">День рождения</label>
                                                <input class="form-control" id="inputBirthday" name="birthday" type="text" name="birthday" placeholder="Введите свой день рождения" value="<?= htmlspecialchars($seller['birthday']) ?>" />
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit" name="update_profile">Сохранить изменения</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php MessageHelper::display(); ?>
                    </div>
                </div>
            </main>
            <?php require __DIR__ . '/../layouts/footer.php'; ?>
        </div>
    </div>   
    <?php require __DIR__ . '/../layouts/scripts.php'; ?>
</body>
</html>
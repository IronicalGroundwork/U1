<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Регистрация - U1</title>
        <link href="assets/css/styles.css" rel="stylesheet" />
        <link rel="icon" type="image/x-icon" href="assets/img/favicon.png" />
        <script data-search-pseudo-elements defer src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/js/all.min.js" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.28.0/feather.min.js" crossorigin="anonymous"></script>
    </head>
    <body class="bg-primary">
        <div id="layoutAuthentication">
            <div id="layoutAuthentication_content">
                <main>
                    <div class="container-xl px-4">
                        <div class="row justify-content-center">
                            <div class="col-xl-8 col-lg-9">
                                <div class="card my-5">
                                    <div class="card-body p-5 text-center">
                                        <div class="h3 fw-light mb-3">Создать аккаунт</div>
                                        <div class="small text-muted mb-2">Войти в систему, используя...</div>
                                        <a class="btn btn-icon btn-facebook mx-1" href="#!"><i class="fab fa-facebook-f fa-fw fa-sm"></i></a>
                                        <a class="btn btn-icon btn-github mx-1" href="#!"><i class="fab fa-github fa-fw fa-sm"></i></a>
                                        <a class="btn btn-icon btn-google mx-1" href="#!"><i class="fab fa-google fa-fw fa-sm"></i></a>
                                        <a class="btn btn-icon btn-twitter mx-1" href="#!"><i class="fab fa-twitter fa-fw fa-sm text-white"></i></a>
                                    </div>
                                    <hr class="my-0" />
                                    <div class="card-body p-5">
                                        <div class="text-center small text-muted mb-4">... или введите свою информацию ниже.</div>
                                        <?php 
                                        MessageHelper::display(); 
                                        ?>
                                        <form method="POST" action="index.php?action=do-register">
                                            <div class="row gx-3">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="text-gray-600 small" for="firstNameExample">Имя</label>
                                                        <input class="form-control form-control-solid" name="first_name" type="text" placeholder="" aria-label="First Name" aria-describedby="firstNameExample" />
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="text-gray-600 small" for="lastNameExample">Фамилия</label>
                                                        <input class="form-control form-control-solid" name="last_name" type="text" placeholder="" aria-label="Last Name" aria-describedby="lastNameExample" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-gray-600 small" for="emailExample">Адрес электронной почты</label>
                                                <input class="form-control form-control-solid" name="email" type="text" placeholder="" aria-label="Email Address" aria-describedby="emailExample" />
                                            </div>
                                            <div class="row gx-3">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="text-gray-600 small" for="passwordExample">Пароль</label>
                                                        <input class="form-control form-control-solid" name="password" type="password" placeholder="" aria-label="Password" aria-describedby="passwordExample" />
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="text-gray-600 small" for="confirmPasswordExample">Подтвердите пароль</label>
                                                        <input class="form-control form-control-solid" name="confirm_password" type="password" placeholder="" aria-label="Confirm Password" aria-describedby="confirmPasswordExample" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="form-check">
                                                    <input class="form-check-input" id="checkTerms" type="checkbox" value="" />
                                                    <label class="form-check-label" for="checkTerms">
                                                        Я принимаю
                                                        <a href="#!">правила &amp; условия</a>
                                                        .
                                                    </label>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Создать аккаунт</button>
                                            </div>
                                        </form>
                                    </div>
                                    <hr class="my-0" />
                                    <div class="card-body px-5 py-4">
                                        <div class="small text-center">
                                            Уже есть аккаунт?
                                            <a href="index.php?action=login">Войдите в систему!</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
            <div id="layoutAuthentication_footer">
                <footer class="footer-admin mt-auto footer-dark">
                    <div class="container-xl px-4">
                        <div class="row">
                            <div class="col-md-6 small">Copyright &copy; U1 2025</div>
                            <div class="col-md-6 text-md-end small">
                                <a href="#!">Privacy Policy</a>
                                &middot;
                                <a href="#!">Terms &amp; Conditions</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
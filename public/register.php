<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$error = null;
$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'city' => 'Москва',
    'address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'full_name' => trim((string)($_POST['full_name'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'city' => trim((string)($_POST['city'] ?? '')),
        'address' => trim((string)($_POST['address'] ?? '')),
    ];
    $password = trim((string)($_POST['password'] ?? ''));
    $errors = [];

    if ($form['full_name'] === '') {
        $errors[] = 'Заполните ФИО.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    }
    if (!preg_match('/^\+?[0-9\s()\-]{7,20}$/', $form['phone'])) {
        $errors[] = 'Введите телефон в корректном формате.';
    }
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{6,}$/', $password)) {
        $errors[] = 'Пароль должен быть не короче 6 символов и содержать латинскую букву и цифру.';
    }
    if (!$errors && spice_user_by_email($form['email'])) {
        $errors[] = 'Пользователь с таким email уже зарегистрирован.';
    }

    if (!$errors) {
        try {
            $user = spice_create_user($form + ['password' => $password]);

            if ($user) {
                set_current_user($user);
                stats_log_auth_event('register', (int)$user['user_id'], (string)$user['email']);
                header('Location: /account.php');
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Spice Market register error: ' . $exception->getMessage());
            $error = 'Не удалось создать пользователя. Попробуйте ещё раз.';
        }
    } else {
        $error = implode(' ', $errors);
    }

    $error ??= 'Проверьте заполнение формы.';
}

page_start('Регистрация', 'account');
?>

<section class="auth-layout">
    <form class="form-card" method="post" action="/register.php">
        <h1 class="page-title">Регистрация</h1>
        <p class="lead">Создайте аккаунт, чтобы сохранять адрес доставки и историю заказов.</p>
        <?php if ($error): ?>
            <div class="message error"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="form-grid">
            <label>
                ФИО
                <input class="input" type="text" name="full_name" value="<?= e($form['full_name']) ?>" pattern="[A-Za-zА-Яа-яЁё\s.\-]{2,}" title="Введите имя буквами" required>
            </label>
            <label>
                Email
                <input class="input" type="email" name="email" value="<?= e($form['email']) ?>" required>
            </label>
            <label>
                Телефон
                <input class="input" type="tel" name="phone" value="<?= e($form['phone']) ?>" pattern="^\+?[0-9\s()\-]{7,20}$" placeholder="+7 (999) 123-45-67" required>
            </label>
            <label>
                Город
                <input class="input" type="text" name="city" value="<?= e($form['city']) ?>">
            </label>
            <label>
                Адрес
                <textarea class="textarea" name="address"><?= e($form['address']) ?></textarea>
            </label>
            <label>
                Пароль
                <input class="input" type="password" name="password" minlength="6" pattern="(?=.*[A-Za-z])(?=.*\d).{6,}" title="Минимум 6 символов, латинская буква и цифра" required>
            </label>
            <button class="btn" type="submit">Зарегистрироваться</button>
            <a class="chip" href="/login.php">Уже есть аккаунт</a>
        </div>
    </form>

    <div class="content-card">
        <h2>Что будет в профиле</h2>
        <p>ФИО, email, телефон, город и адрес доставки для будущих заказов.</p>
    </div>
</section>

<?php page_end(); ?>

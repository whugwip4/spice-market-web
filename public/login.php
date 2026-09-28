<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$error = null;
$next = safe_next_url((string)($_GET['next'] ?? $_POST['next'] ?? '/account.php'), '/account.php');
$adminRequired = isset($_GET['admin_required']) || isset($_POST['admin_required']);

if (isset($_GET['logout'])) {
    if (is_logged_in()) {
        stats_log_auth_event('logout', current_user_id(), current_user_email());
    }
    logout_current_user();
    $redirect = '/login.php';
    $query = [];
    if ($adminRequired) {
        $query['admin_required'] = '1';
    }
    if ($next !== '/account.php') {
        $query['next'] = $next;
    }
    if ($query) {
        $redirect .= '?' . http_build_query($query);
    }
    header('Location: ' . $redirect);
    exit;
}

$email = trim((string)($_POST['email'] ?? current_user_email()));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim((string)($_POST['password'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email.';
    } elseif ($password === '') {
        $error = 'Введите пароль.';
    } else {
        $user = spice_authenticate_user($email, $password);

        if ($user) {
            set_current_user($user);
            stats_log_auth_event('login_success', (int)$user['user_id'], $email);

            if (str_starts_with($next, '/admin') && !is_admin()) {
                header('Location: /account.php?admin_denied=1');
                exit;
            }

            header('Location: ' . $next);
            exit;
        }

        stats_log_auth_event('login_failed', null, $email);
        $error = 'Неверный email или пароль.';
    }
}

page_start('Вход', 'account');
?>

<section class="auth-layout">
    <form class="form-card" method="post" action="/login.php">
        <h1 class="page-title">Вход</h1>
        <p class="lead">Войдите, чтобы открыть личный кабинет, историю заказов и избранные специи.</p>
        <?php if ($adminRequired): ?>
            <div class="message error">Для доступа к административной панели войдите под аккаунтом администратора.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="form-grid">
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <?php if ($adminRequired): ?>
                <input type="hidden" name="admin_required" value="1">
            <?php endif; ?>
            <label>
                Email
                <input class="input" type="email" name="email" value="<?= e($email) ?>" required>
            </label>
            <label>
                Пароль
                <input class="input" type="password" name="password" required>
            </label>
            <button class="btn" type="submit">Войти</button>
            <a class="chip" href="/register.php">Создать аккаунт</a>
        </div>
    </form>

    <div class="content-card">
        <h2>Личный кабинет</h2>
        <p>В аккаунте доступны контактные данные, история заказов и любимые товары для повторной покупки.</p>
    </div>
</section>

<?php page_end(); ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/data.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(mixed $value): string
{
    return number_format((float)$value, 0, ',', ' ') . ' ₽';
}

function active_class(string $active, string $key): string
{
    return $active === $key ? ' is-active' : '';
}

function is_logged_in(): bool
{
    return !empty($_SESSION['spice_user_id']);
}

function current_user_id(): int
{
    return (int)($_SESSION['spice_user_id'] ?? 0);
}

function current_user_name(): string
{
    return (string)($_SESSION['spice_user_name'] ?? '');
}

function current_user_email(): string
{
    return (string)($_SESSION['spice_user_email'] ?? '');
}

function current_user_role(): string
{
    return (string)($_SESSION['spice_user_role'] ?? '');
}

function is_admin(): bool
{
    return is_logged_in() && current_user_role() === 'admin';
}

function set_current_user(array $user): void
{
    $_SESSION['spice_user_id'] = (int)$user['user_id'];
    $_SESSION['spice_user_name'] = (string)$user['full_name'];
    $_SESSION['spice_user_email'] = (string)$user['email'];
    $_SESSION['spice_user_role'] = (string)$user['role'];
}

function logout_current_user(): void
{
    unset(
        $_SESSION['spice_user_id'],
        $_SESSION['spice_user_name'],
        $_SESSION['spice_user_email'],
        $_SESSION['spice_user_role']
    );
}

function safe_next_url(string $value, string $fallback = '/account.php'): string
{
    if ($value === '' || !str_starts_with($value, '/') || str_starts_with($value, '//')) {
        return $fallback;
    }

    return $value;
}

function require_admin(): void
{
    $currentPath = (string)($_SERVER['REQUEST_URI'] ?? '/admin/');

    if (!is_logged_in()) {
        header('Location: /login.php?admin_required=1&next=' . rawurlencode(safe_next_url($currentPath, '/admin/')));
        exit;
    }

    if (is_admin()) {
        return;
    }

    http_response_code(403);
    page_start('Доступ запрещён', 'account');
    ?>
    <section class="auth-layout">
        <div class="content-card">
            <h1 class="page-title">Доступ запрещён</h1>
            <p class="lead">Административная панель доступна только пользователю с ролью admin.</p>
            <a class="btn" href="/login.php?logout=1&admin_required=1&next=/admin/">Войти как администратор</a>
        </div>
        <div class="content-card">
            <h2>Текущий аккаунт</h2>
            <p><?= e(current_user_email()) ?> вошёл как <?= e(current_user_role()) ?>. Для просмотра таблиц БД нужен администратор.</p>
        </div>
    </section>
    <?php
    page_end();
    exit;
}

function page_start(string $title, string $active = ''): void
{
    $bodyClass = $active === 'admin' ? ' class="admin-page"' : '';
    $mainClass = 'page-shell' . ($active === 'admin' ? ' page-shell-admin' : '');
    ?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | Spice Market</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script defer src="/assets/js/cart.js"></script>
</head>
<body<?= $bodyClass ?>>
<header class="site-header">
    <a class="brand" href="/">
        <img src="/assets/img/Group%2091.png" alt="Spice Market" width="644" height="198">
    </a>
    <nav class="main-nav" aria-label="Основная навигация">
        <a class="<?= active_class($active, 'home') ?>" href="/">Главная</a>
        <a class="<?= active_class($active, 'catalog') ?>" href="/catalog.php">Каталог</a>
        <a class="<?= active_class($active, 'recipes') ?>" href="/recipes.php">Рецепты</a>
        <a class="<?= active_class($active, 'delivery') ?>" href="/delivery.php">Доставка</a>
        <a class="<?= active_class($active, 'reviews') ?>" href="/reviews.php">Отзывы</a>
        <a class="<?= active_class($active, 'contacts') ?>" href="/contacts.php">Контакты</a>
        <a class="<?= active_class($active, 'cart') ?>" href="/cart.php">Корзина <span class="cart-count" data-cart-count>0</span></a>
        <a class="<?= active_class($active, 'account') ?>" href="<?= is_logged_in() ? '/account.php' : '/login.php' ?>"><?= is_logged_in() ? 'Аккаунт' : 'Войти' ?></a>
        <?php if (is_admin()): ?>
            <a class="<?= active_class($active, 'admin') ?>" href="/admin/">Админка</a>
        <?php endif; ?>
    </nav>
</header>
<?php if (!spice_db_available()): ?>
    <div class="db-notice">
        MySQL сейчас недоступен. Страницы используют демо-данные, а после запуска XAMPP будут читать БД <b>spice_market</b>.
    </div>
<?php endif; ?>
<main class="<?= e($mainClass) ?>">
<?php
}

function page_end(): void
{
    ?>
</main>
<footer class="site-footer">
    <div>
        <strong>Spice Market</strong>
        <span>Натуральные специи, пряные смеси и травы для домашней кухни.</span>
    </div>
    <?php if (is_admin()): ?>
        <a href="/admin/">Административная панель</a>
    <?php else: ?>
        <a href="/login.php?admin_required=1&next=/admin/">Вход для администратора</a>
    <?php endif; ?>
</footer>
</body>
</html>
<?php
}

function product_visual(array $product, string $size = ''): string
{
    $label = e($product['name'] ?? 'Специя');
    $src = e($product['image_src'] ?? spice_product_image_url('', ''));
    $sizeClass = trim('product-photo ' . $size);

    return '
        <div class="' . e($sizeClass) . '">
            <img src="' . $src . '" alt="' . $label . '" loading="lazy" onerror="this.closest(\'.product-photo\').classList.add(\'image-failed\'); this.remove();">
        </div>
    ';
}

function product_card(array $product, string $extraClass = ''): void
{
    $stock = (int)$product['stock_quantity'];
    ?>
    <article class="product-card<?= $extraClass !== '' ? ' ' . e($extraClass) : '' ?>">
        <a class="product-link" href="/product.php?slug=<?= urlencode((string)$product['slug']) ?>">
            <?= product_visual($product) ?>
            <h3><?= e($product['name']) ?></h3>
            <p><?= e($product['weight_grams']) ?> г · <?= e($product['category_name'] ?? 'натуральная смесь') ?></p>
        </a>
        <div class="product-card-bottom">
            <strong><?= money($product['price']) ?></strong>
            <button
                class="btn btn-small js-add-cart"
                type="button"
                data-slug="<?= e($product['slug']) ?>"
                data-name="<?= e($product['name']) ?>"
                <?= $stock <= 0 ? 'disabled' : '' ?>
            >
                <?= $stock > 0 ? 'В корзину' : 'Нет в наличии' ?>
            </button>
        </div>
    </article>
    <?php
}

function status_label(string $status): string
{
    return match ($status) {
        'new' => 'Новый',
        'processing' => 'В обработке',
        'completed' => 'Выполнен',
        'cancelled' => 'Отменен',
        default => $status,
    };
}

function method_label(string $method): string
{
    return match ($method) {
        'courier' => 'Курьер',
        'pickup' => 'Самовывоз',
        'cash' => 'Наличными при получении',
        'card_on_delivery' => 'Картой при получении',
        default => $method,
    };
}

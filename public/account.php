<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

if (!is_logged_in()) {
    header('Location: /login.php?next=/account.php');
    exit;
}

$user = spice_user_by_id(current_user_id());

if (!$user) {
    logout_current_user();
    header('Location: /login.php?next=/account.php');
    exit;
}

$orders = spice_orders_for_user(current_user_id());
$favorites = spice_favorites_for_user(current_user_id());
$adminDenied = isset($_GET['admin_denied']);

page_start('Личный кабинет', 'account');
?>

<section>
    <h1 class="page-title">Личный кабинет</h1>
    <p class="lead">Профиль покупателя, история заказов и избранные товары.</p>

    <?php if ($adminDenied): ?>
        <div class="message error">Вы вошли как покупатель. Админка доступна только аккаунту с ролью admin.</div>
    <?php endif; ?>

    <div class="two-column">
        <div>
            <div class="content-card">
                <h2><?= e($user['full_name'] ?? current_user_name()) ?></h2>
                <ul class="info-list">
                    <li><span>Email</span><strong><?= e($user['email'] ?? '') ?></strong></li>
                    <li><span>Телефон</span><strong><?= e($user['phone'] ?? '') ?></strong></li>
                    <li><span>Город</span><strong><?= e($user['city'] ?? '') ?></strong></li>
                    <li><span>Роль</span><strong><?= e($user['role'] ?? 'customer') ?></strong></li>
                </ul>
            </div>

            <div class="section-head">
                <div>
                    <h2>Избранное</h2>
                    <p>Товары, сохраненные пользователем.</p>
                </div>
            </div>
            <div class="product-grid">
                <?php foreach ($favorites as $product): ?>
                    <?php product_card($product); ?>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="summary-card">
            <h2>Заказы</h2>
            <div class="orders">
                <?php foreach ($orders as $order): ?>
                    <article class="order-card">
                        <div>
                            <h3><?= e($order['order_number']) ?></h3>
                            <p><?= e(status_label((string)$order['status'])) ?> · <?= e(method_label((string)$order['delivery_method'])) ?></p>
                            <p><?= e(date('d.m.Y H:i', strtotime((string)$order['created_at']))) ?></p>
                        </div>
                        <strong><?= money($order['total_amount']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>
            <a class="btn btn-secondary" href="/login.php?logout=1">Сменить пользователя</a>
        </aside>
    </div>
</section>

<?php page_end(); ?>

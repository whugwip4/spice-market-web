<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

page_start('Корзина', 'cart');
?>

<section>
    <h1 class="page-title">Корзина</h1>
    <p class="lead">Проверьте состав заказа, измените количество и перейдите к оформлению доставки.</p>

    <div class="two-column">
        <div class="cart-list" data-cart-list></div>

        <aside class="summary-card">
            <h2>Итого</h2>
            <div class="summary-line">
                <span>Товаров</span>
                <strong data-cart-items-count>0</strong>
            </div>
            <div class="summary-line total">
                <span>Сумма</span>
                <strong data-cart-total>0 ₽</strong>
            </div>
            <a class="btn" href="/checkout.php">Оформить заказ</a>
            <a class="btn btn-secondary" href="/catalog.php">Продолжить покупки</a>
        </aside>
    </div>
</section>

<?php page_end(); ?>

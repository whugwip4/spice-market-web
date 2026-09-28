<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

// оформление заказа доступно только авторизованному покупателю; корзина хранится в браузере и не теряется
if (!is_logged_in()) {
    header('Location: /login.php?next=' . rawurlencode('/checkout.php'));
    exit;
}

$user = spice_user_by_id(current_user_id());
$message = null;
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cartPayload = json_decode((string)($_POST['cart_payload'] ?? '{}'), true);
    $cart = is_array($cartPayload) ? $cartPayload : [];
    $customer = [
        'user_id' => current_user_id(),
        'customer_name' => trim((string)($_POST['customer_name'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'delivery_address' => trim((string)($_POST['delivery_address'] ?? '')),
        'delivery_method' => (string)($_POST['delivery_method'] ?? 'courier'),
        'payment_method' => (string)($_POST['payment_method'] ?? 'cash'),
    ];
    $errors = [];

    if ($customer['customer_name'] === '') {
        $errors[] = 'Укажите ФИО получателя.';
    }
    if (!preg_match('/^\+?[0-9\s()\-]{7,20}$/', $customer['phone'])) {
        $errors[] = 'Укажите корректный телефон.';
    }
    if ($customer['delivery_address'] === '') {
        $errors[] = 'Укажите адрес доставки.';
    }

    if ($errors) {
        $message = implode(' ', $errors);
        $messageType = 'error';
    } else {
        $result = spice_create_order($customer, $cart);
        $message = $result['message'];
        $messageType = $result['ok'] ? 'ok' : 'error';
    }
}

page_start('Оформление заказа', 'cart');
?>

<section>
    <h1 class="page-title">Оформление заказа</h1>
    <p class="lead">Укажите контакты и адрес. Онлайн-оплаты нет, оплатить можно при получении.</p>

    <?php if ($message !== null): ?>
        <div class="message <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="checkout-layout">
        <form class="form-card" method="post" action="/checkout.php">
            <input type="hidden" name="cart_payload" value="{}">
            <div class="form-grid">
                <label>
                    ФИО получателя
                    <input class="input" type="text" name="customer_name" value="<?= e($user['full_name'] ?? '') ?>" pattern="[A-Za-zА-Яа-яЁё\s.\-]{2,}" required>
                </label>
                <label>
                    Телефон
                    <input class="input" type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>" pattern="^\+?[0-9\s()\-]{7,20}$" placeholder="+7 (999) 123-45-67" required>
                </label>
                <label>
                    Адрес доставки
                    <textarea class="textarea" name="delivery_address" required><?= e($user['address'] ?? '') ?></textarea>
                </label>
                <label>
                    Способ доставки
                    <select class="select" name="delivery_method">
                        <option value="courier">Курьером</option>
                        <option value="pickup">Самовывоз</option>
                    </select>
                </label>
                <label>
                    Оплата
                    <select class="select" name="payment_method">
                        <option value="cash">Наличными при получении</option>
                        <option value="card_on_delivery">Картой при получении</option>
                    </select>
                </label>
                <button class="btn" type="submit">Подтвердить заказ</button>
            </div>
        </form>

        <aside class="summary-card">
            <h2>Состав заказа</h2>
            <div data-checkout-summary></div>
        </aside>
    </div>
</section>

<?php page_end(); ?>

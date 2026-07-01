<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

page_start('Контакты', 'contacts');
?>

<section>
    <h1 class="page-title">Контакты</h1>
    <p class="lead">Контактная страница для покупателя: адрес, телефон, email, график и форма сообщения.</p>

    <div class="checkout-layout">
        <div class="content-card">
            <h2>Spice Market</h2>
            <ul class="info-list">
                <li><span>Адрес</span><strong>Москва, ул. Ароматная, 7</strong></li>
                <li><span>Телефон</span><strong>+7 (999) 100-10-10</strong></li>
                <li><span>Email</span><strong>hello@spice-market.local</strong></li>
                <li><span>График</span><strong>Ежедневно 10:00-20:00</strong></li>
            </ul>
        </div>

        <form class="form-card" method="post" action="/contacts.php">
            <h2>Сообщение</h2>
            <div class="form-grid">
                <label>
                    Имя
                    <input class="input" type="text" name="name" required>
                </label>
                <label>
                    Email
                    <input class="input" type="email" name="email" required>
                </label>
                <label>
                    Текст
                    <textarea class="textarea" name="message" required></textarea>
                </label>
                <button class="btn" type="submit">Отправить</button>
            </div>
        </form>
    </div>
</section>

<?php page_end(); ?>

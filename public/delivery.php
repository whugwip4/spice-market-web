<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

page_start('Доставка и оплата', 'delivery');
?>

<section>
    <h1 class="page-title">Доставка и оплата</h1>
    <p class="lead">Страница условий для веб-ресурса магазина специй. Онлайн-оплата на этом этапе не подключается.</p>

    <div class="content-grid">
        <article class="content-card">
            <h2>Курьерская доставка</h2>
            <p>Доставка по Москве и ближайшим городам. Менеджер подтверждает заказ и согласует удобный интервал.</p>
            <ul class="info-list">
                <li><span>Срок</span><strong>1-2 дня</strong></li>
                <li><span>Стоимость</span><strong>от 250 ₽</strong></li>
            </ul>
        </article>
        <article class="content-card">
            <h2>Самовывоз</h2>
            <p>Заказ можно забрать из магазина после подтверждения. Специи резервируются до конца следующего дня.</p>
            <ul class="info-list">
                <li><span>Адрес</span><strong>ул. Ароматная, 7</strong></li>
                <li><span>График</span><strong>10:00-20:00</strong></li>
            </ul>
        </article>
        <article class="content-card">
            <h2>Оплата</h2>
            <p>Для учебного проекта доступны наличные или карта при получении. Платежный шлюз не используется.</p>
            <ul class="info-list">
                <li><span>Наличные</span><strong>Да</strong></li>
                <li><span>Карта курьеру</span><strong>Да</strong></li>
            </ul>
        </article>
    </div>
</section>

<?php page_end(); ?>

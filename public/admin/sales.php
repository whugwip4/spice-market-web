<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/stats_view.php';

require_admin();

$period = stats_period($_GET);
$sales = stats_sales_summary($period);
$salesBefore = stats_sales_summary($period, true);
$daily = stats_business_by_day($period);
$reviews = stats_reviews_summary($period);
$lowStock = stats_low_stock(MONITOR_LOW_STOCK);
$searches = stats_search_queries($period);
$emptySearches = stats_search_queries($period, 10, true);
$charts = stats_sales_charts($period);

$tiles = [
    ['label' => 'Заказы', 'value' => stats_format_number($sales['orders']), 'delta' => stats_delta($sales['orders'], $salesBefore['orders']), 'spark' => $daily['orders']],
    ['label' => 'Выручка', 'value' => stats_format_money($sales['revenue']), 'delta' => stats_delta($sales['revenue'], $salesBefore['revenue']), 'spark' => $daily['revenue']],
    ['label' => 'Средний чек', 'value' => stats_format_money($sales['avg_check']), 'delta' => stats_delta($sales['avg_check'], $salesBefore['avg_check'])],
    ['label' => 'Отменённые заказы', 'value' => stats_format_number($sales['cancel_rate'], 1) . "\u{00A0}%", 'delta' => $salesBefore['orders'] > 0 ? stats_delta($sales['cancel_rate'], $salesBefore['cancel_rate'], true) : null, 'delta_unit' => 'п.п.', 'good' => 'down'],
    ['label' => 'Покупатели', 'value' => stats_format_number($sales['buyers']), 'delta' => stats_delta($sales['buyers'], $salesBefore['buyers'])],
    ['label' => 'Повторные покупатели', 'value' => stats_format_number($sales['repeat_rate'], 1) . "\u{00A0}%", 'delta' => $salesBefore['buyers'] > 0 ? stats_delta($sales['repeat_rate'], $salesBefore['repeat_rate'], true) : null, 'delta_unit' => 'п.п.'],
];

$catalogTiles = [
    ['label' => 'Средний рейтинг', 'value' => $reviews['avg_rating'] > 0 ? stats_format_number($reviews['avg_rating'], 2) . "\u{00A0}из 5" : '—', 'note' => 'отзывов за период: ' . stats_format_number($reviews['count'])],
    ['label' => 'Отзывы на модерации', 'value' => stats_format_number($reviews['pending']), 'note' => 'статус «new», ждут проверки'],
    ['label' => 'Мало на складе', 'value' => stats_format_number(count($lowStock)), 'note' => 'товаров с остатком меньше ' . MONITOR_LOW_STOCK . ' шт.'],
];

page_start('Продажи', 'admin');
?>

<section class="stats-page">
    <?php admin_nav('sales'); ?>

    <div class="admin-title-row">
        <div>
            <h1 class="page-title">Продажи и покупатели</h1>
            <p class="lead">Заказы, выручка, покупатели, отзывы и поиск по каталогу за выбранный период.</p>
        </div>
    </div>

    <?php stats_render_filters('/admin/sales.php', $period); ?>

    <h2 class="stats-section-title">Заказы и выручка</h2>
    <?php stats_render_tiles($tiles); ?>

    <div class="chart-grid">
        <?php
        stats_chart_card('sales', $period, 'orders_by_day', 'Заказы по дням', 'Количество оформленных заказов во всех статусах', 'chart-card-wide', 240);
        stats_chart_card('sales', $period, 'revenue_by_day', 'Выручка по дням', 'Сумма заказов за день без отменённых, в рублях', 'chart-card-wide', 260);
        stats_chart_card('sales', $period, 'order_statuses', 'Статусы заказов', 'Сколько заказов сейчас в каждом статусе');
        stats_chart_card('sales', $period, 'funnel', 'Путь до заказа', 'Сколько визитов дошло до каждого шага и доля от всех визитов');
        stats_chart_card('sales', $period, 'delivery_methods', 'Способы доставки', 'Курьер или самовывоз', '', 220);
        stats_chart_card('sales', $period, 'payment_methods', 'Способы оплаты', 'Наличными или картой при получении', '', 220);
        stats_chart_card('sales', $period, 'top_products_revenue', 'Товары-лидеры по выручке', 'Выручка по товарам без отменённых заказов', 'chart-card-wide', 300);
        stats_chart_card('sales', $period, 'categories', 'Выручка по категориям', 'Сумма продаж по категориям каталога и доля от всей выручки');
        stats_chart_card('sales', $period, 'cities', 'Города покупателей', 'Сколько покупателей сделали заказ из каждого города');
        ?>
    </div>

    <h2 class="stats-section-title">Пользователи и каталог</h2>
    <?php stats_render_tiles($catalogTiles); ?>

    <div class="chart-grid">
        <?php
        stats_chart_card('sales', $period, 'users_growth', 'Рост числа пользователей', 'Сколько зарегистрированных пользователей было на конец каждого дня', 'chart-card-wide', 260);
        stats_chart_card('sales', $period, 'ratings', 'Оценки в отзывах', 'Количество отзывов с каждой оценкой от 1 до 5');
        ?>
        <div class="chart-card">
            <h3 class="stats-card-title">Заканчиваются на складе</h3>
            <?php if (!$lowStock): ?>
                <div class="empty-state">Все товары в наличии в достаточном количестве.</div>
            <?php else: ?>
                <div class="table-wrap stats-table">
                    <table>
                        <thead><tr><th>Товар</th><th>Категория</th><th>Остаток, шт.</th></tr></thead>
                        <tbody>
                            <?php foreach ($lowStock as $product): ?>
                                <tr>
                                    <td><?= e($product['name']) ?></td>
                                    <td><?= e($product['category_name']) ?></td>
                                    <td><?= (int)$product['stock_quantity'] === 0 ? '<span class="badge badge-critical">нет</span>' : e($product['stock_quantity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h2 class="stats-section-title">Поиск по каталогу</h2>
    <div class="chart-grid">
        <div class="chart-card">
            <h3 class="stats-card-title">Частые запросы</h3>
            <div class="table-wrap stats-table">
                <table>
                    <thead><tr><th>Запрос</th><th>Поисков</th><th>Найдено товаров</th></tr></thead>
                    <tbody>
                        <?php foreach ($searches as $row): ?>
                            <tr><td><?= e($row['query_text']) ?></td><td><?= e($row['searches']) ?></td><td><?= e($row['results']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="chart-card">
            <h3 class="stats-card-title">Запросы без результатов</h3>
            <p class="stats-note">Этих товаров ищут, но в каталоге их нет.</p>
            <div class="table-wrap stats-table">
                <table>
                    <thead><tr><th>Запрос</th><th>Поисков</th></tr></thead>
                    <tbody>
                        <?php foreach ($emptySearches as $row): ?>
                            <tr><td><?= e($row['query_text']) ?></td><td><?= e($row['searches']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <details class="stats-method" open>
        <summary>Как считаются показатели</summary>
        <div class="table-wrap stats-table">
            <table>
                <thead><tr><th>Показатель</th><th>Формула и правило расчёта</th><th>Источник данных</th></tr></thead>
                <tbody>
                    <tr><td>Заказы</td><td>Количество заказов за период во всех статусах.</td><td><code>orders</code></td></tr>
                    <tr><td>Выручка</td><td><code>SUM(total_amount)</code> заказов, кроме отменённых.</td><td><code>orders</code></td></tr>
                    <tr><td>Средний чек</td><td>Выручка / количество неотменённых заказов.</td><td><code>orders</code></td></tr>
                    <tr><td>Отменённые заказы</td><td>Заказы со статусом <code>cancelled</code> / все заказы × 100 %.</td><td><code>orders.status</code></td></tr>
                    <tr><td>Покупатели</td><td>Количество разных <code>user_id</code> среди заказов периода.</td><td><code>orders.user_id</code></td></tr>
                    <tr><td>Повторные покупатели</td><td>Покупатели с двумя заказами и больше / все покупатели × 100 %.</td><td><code>orders</code></td></tr>
                    <tr><td>Путь до заказа</td><td>Количество визитов, в которых была открыта карточка товара, корзина и страница оформления; последний шаг – число заказов.</td><td><code>site_visits</code>, <code>orders</code></td></tr>
                    <tr><td>Товары и категории</td><td><code>SUM(line_total)</code> и <code>SUM(quantity)</code> по позициям неотменённых заказов.</td><td><code>order_items</code>, <code>products</code>, <code>categories</code></td></tr>
                    <tr><td>Города покупателей</td><td>Количество разных покупателей по полю «город» из профиля.</td><td><code>users.city</code></td></tr>
                    <tr><td>Рост пользователей</td><td>Пользователи, зарегистрированные до начала периода, плюс регистрации по дням (накопительный итог).</td><td><code>users.created_at</code></td></tr>
                    <tr><td>Средний рейтинг</td><td><code>AVG(rating)</code> опубликованных отзывов за период.</td><td><code>reviews</code></td></tr>
                    <tr><td>Поиск</td><td>Запросы из строки поиска каталога, число поисков и сколько товаров нашлось.</td><td><code>search_queries</code></td></tr>
                    <tr><td>Мало на складе</td><td>Активные товары с остатком меньше <?= MONITOR_LOW_STOCK ?> шт.</td><td><code>products.stock_quantity</code></td></tr>
                </tbody>
            </table>
        </div>
    </details>
</section>

<?php stats_render_charts_data($charts); ?>

<?php page_end(); ?>

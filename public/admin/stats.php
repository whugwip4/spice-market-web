<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/stats_view.php';

require_admin();

$period = stats_period($_GET);
$traffic = stats_traffic_summary($period);
$trafficBefore = stats_traffic_summary($period, true);
$business = stats_business_summary($period);
$businessBefore = stats_business_summary($period, true);
$daily = stats_traffic_by_day($period);
$businessDaily = stats_business_by_day($period);
$referrers = stats_referrer_hosts($period);
$recentVisits = stats_recent_visits();

$conversion = $traffic['sessions'] > 0 ? $business['orders'] / $traffic['sessions'] * 100 : 0.0;
$conversionBefore = $trafficBefore['sessions'] > 0 ? $businessBefore['orders'] / $trafficBefore['sessions'] * 100 : 0.0;

$trafficTiles = [
    ['label' => 'Просмотры страниц', 'value' => stats_format_number($traffic['page_views']), 'delta' => stats_delta($traffic['page_views'], $trafficBefore['page_views']), 'spark' => $daily['page_views']],
    ['label' => 'Посетители', 'value' => stats_format_number($traffic['visitors']), 'delta' => stats_delta($traffic['visitors'], $trafficBefore['visitors']), 'spark' => $daily['visitors']],
    ['label' => 'Визиты', 'value' => stats_format_number($traffic['sessions']), 'delta' => stats_delta($traffic['sessions'], $trafficBefore['sessions']), 'spark' => $daily['sessions']],
    ['label' => 'Отказы', 'value' => stats_format_number($traffic['bounce_rate'], 1) . "\u{00A0}%", 'delta' => $trafficBefore['sessions'] > 0 ? stats_delta($traffic['bounce_rate'], $trafficBefore['bounce_rate'], true) : null, 'delta_unit' => 'п.п.', 'good' => 'down'],
    ['label' => 'Глубина просмотра', 'value' => stats_format_number($traffic['pages_per_session'], 2) . "\u{00A0}стр.", 'delta' => stats_delta($traffic['pages_per_session'], $trafficBefore['pages_per_session'])],
    ['label' => 'Время на сайте', 'value' => stats_format_duration($traffic['avg_duration']), 'delta' => stats_delta($traffic['avg_duration'], $trafficBefore['avg_duration'])],
];

$shopTiles = [
    ['label' => 'Заказы', 'value' => stats_format_number($business['orders']), 'delta' => stats_delta($business['orders'], $businessBefore['orders']), 'spark' => $businessDaily['orders']],
    ['label' => 'Выручка', 'value' => stats_format_money($business['revenue']), 'delta' => stats_delta($business['revenue'], $businessBefore['revenue']), 'spark' => $businessDaily['revenue']],
    ['label' => 'Средний чек', 'value' => stats_format_money($business['avg_check']), 'delta' => stats_delta($business['avg_check'], $businessBefore['avg_check'])],
    ['label' => 'Конверсия в заказ', 'value' => stats_format_number($conversion, 2) . "\u{00A0}%", 'delta' => $trafficBefore['sessions'] > 0 ? stats_delta($conversion, $conversionBefore, true) : null, 'delta_unit' => 'п.п.'],
    ['label' => 'Новые пользователи', 'value' => stats_format_number($business['new_users']), 'delta' => stats_delta($business['new_users'], $businessBefore['new_users']), 'spark' => $businessDaily['new_users']],
    ['label' => 'Всего пользователей', 'value' => stats_format_number($business['total_users']), 'delta' => stats_delta($business['total_users'], $businessBefore['total_users'])],
];

$charts = stats_traffic_charts($period);

$sourceTypeLabels = [
    'direct' => 'Прямой',
    'internal' => 'Внутренний',
    'search' => 'Поиск',
    'social' => 'Соцсеть',
    'external' => 'Сайт',
];
$deviceLabels = ['desktop' => 'Компьютер', 'mobile' => 'Смартфон', 'tablet' => 'Планшет'];

page_start('Статистика', 'admin');
?>

<section class="stats-page">
    <?php admin_nav('stats'); ?>

    <div class="admin-title-row">
        <div>
            <h1 class="page-title">Статистика сайта</h1>
            <p class="lead">Посещаемость и ключевые показатели магазина за выбранный период.</p>
        </div>
    </div>

    <?php stats_render_filters('/admin/stats.php', $period); ?>

    <h2 class="stats-section-title">Посещаемость</h2>
    <?php stats_render_tiles($trafficTiles); ?>

    <div class="chart-grid">
        <?php
        stats_chart_card(
            'traffic', $period,
            'traffic_by_day',
            'Посещаемость по дням',
            'Просмотры страниц и уникальные посетители за каждый день периода'
                . ($period['to'] === date('Y-m-d') ? '. Последняя точка – сегодняшний день, он еще не закончился' : ''),
            'chart-card-wide',
            300
        );
        stats_chart_card('traffic', $period, 'top_pages', 'Популярные страницы', 'Количество просмотров каждой страницы за период');
        stats_chart_card('traffic', $period, 'sources', 'Источники визитов', 'Откуда пришёл посетитель: по адресу первого перехода визита');
        stats_chart_card('traffic', $period, 'devices', 'Устройства', 'Доля визитов с компьютеров, смартфонов и планшетов');
        stats_chart_card('traffic', $period, 'browsers', 'Браузеры', 'Количество визитов по браузерам посетителей');
        stats_chart_card('traffic', $period, 'hourly', 'Активность по часам', 'Сколько страниц в среднем просматривают в каждый час суток', 'chart-card-wide', 240);
        stats_chart_card('traffic', $period, 'top_products', 'Популярные товары', 'Просмотры карточек товаров за период', 'chart-card-wide', 300);
        ?>
    </div>

    <?php if ($referrers): ?>
        <h3 class="stats-subtitle">Сайты-источники переходов</h3>
        <div class="table-wrap stats-table">
            <table>
                <thead>
                    <tr><th>Сайт</th><th>Тип источника</th><th>Визиты</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($referrers as $row): ?>
                        <tr>
                            <td><?= e($row['referrer_host']) ?></td>
                            <td><?= e($sourceTypeLabels[$row['referrer_type']] ?? $row['referrer_type']) ?></td>
                            <td><?= e(stats_format_number((float)$row['sessions'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 class="stats-section-title">Магазин</h2>
    <?php stats_render_tiles($shopTiles); ?>

    <h2 class="stats-section-title">Последние посещения</h2>
    <p class="stats-note">Последние 12 записей журнала <code>site_visits</code> без роботов и страниц админ-панели. Полный журнал — в разделе «Таблицы БД».</p>
    <?php if (!$recentVisits): ?>
        <div class="empty-state">Посещений пока нет — откройте любую страницу сайта, и она появится в журнале.</div>
    <?php else: ?>
        <div class="table-wrap stats-table">
            <table>
                <thead>
                    <tr>
                        <th>Время</th><th>Страница</th><th>Источник</th><th>Устройство и браузер</th>
                        <th>Код</th><th>Ответ, мс</th><th>Пользователь</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentVisits as $visit): ?>
                        <tr>
                            <td><?= e(date('d.m.Y H:i:s', strtotime((string)$visit['created_at']))) ?><?= (int)$visit['is_demo'] === 1 ? ' <span class="badge">демо</span>' : '' ?></td>
                            <td><?= e(stats_page_label((string)$visit['page'])) ?><?= $visit['query_string'] ? '<br><small>' . e('?' . $visit['query_string']) . '</small>' : '' ?></td>
                            <td><?= e($sourceTypeLabels[$visit['referrer_type']] ?? $visit['referrer_type']) ?></td>
                            <td><?= e(($deviceLabels[$visit['device_type']] ?? $visit['device_type']) . ', ' . $visit['browser']) ?></td>
                            <td><?= e($visit['http_status']) ?></td>
                            <td><?= e(stats_format_number((float)$visit['response_time_ms'], 1)) ?></td>
                            <td><?= e($visit['email'] ?? 'гость') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <details class="stats-method" open>
        <summary>Как считаются показатели</summary>
        <div class="table-wrap stats-table">
            <table>
                <thead>
                    <tr><th>Показатель</th><th>Формула и правило расчёта</th><th>Источник данных</th></tr>
                </thead>
                <tbody>
                    <tr><td>Просмотры страниц</td><td>Количество записей журнала за период: <code>COUNT(*)</code>. Запись создаётся при каждой отрисовке страницы, редиректы не учитываются.</td><td><code>site_visits</code></td></tr>
                    <tr><td>Посетители</td><td>Количество уникальных браузеров: <code>COUNT(DISTINCT visitor_id)</code>. Идентификатор хранится в cookie <code>sm_vid</code> 1 год.</td><td><code>site_visits.visitor_id</code></td></tr>
                    <tr><td>Визиты</td><td>Количество уникальных <code>session_id</code>. Визит заканчивается, если между просмотрами прошло больше 30 минут (cookie <code>sm_sid</code>).</td><td><code>site_visits.session_id</code></td></tr>
                    <tr><td>Отказы</td><td>Визиты с одним просмотром / все визиты × 100 %.</td><td><code>site_visits</code></td></tr>
                    <tr><td>Глубина просмотра</td><td>Просмотры / визиты.</td><td><code>site_visits</code></td></tr>
                    <tr><td>Время на сайте</td><td>Среднее по визитам: время последнего просмотра − время первого просмотра.</td><td><code>site_visits.created_at</code></td></tr>
                    <tr><td>Источник визита</td><td>Тип адреса перехода (referrer) у первого просмотра визита: поисковые системы, социальные сети, другие сайты или прямой заход.</td><td><code>site_visits.referrer_type</code></td></tr>
                    <tr><td>Устройство, браузер</td><td>Определяются по заголовку User-Agent; считается количество визитов.</td><td><code>site_visits.device_type</code>, <code>browser</code></td></tr>
                    <tr><td>Активность по часам</td><td>Просмотры в каждый час суток / количество дней периода.</td><td><code>HOUR(site_visits.created_at)</code></td></tr>
                    <tr><td>Заказы</td><td>Количество заказов за период во всех статусах.</td><td><code>orders</code></td></tr>
                    <tr><td>Выручка</td><td><code>SUM(total_amount)</code> заказов, кроме отменённых (<code>status &lt;&gt; 'cancelled'</code>).</td><td><code>orders</code></td></tr>
                    <tr><td>Средний чек</td><td>Выручка / количество неотменённых заказов.</td><td><code>orders</code></td></tr>
                    <tr><td>Конверсия в заказ</td><td>Заказы / визиты × 100 %.</td><td><code>orders</code>, <code>site_visits</code></td></tr>
                    <tr><td>Новые пользователи</td><td>Количество регистраций за период.</td><td><code>users.created_at</code></td></tr>
                    <tr><td>Изменение к прошлому периоду</td><td>(текущее − прошлое) / прошлое × 100 %; для долей (отказы, конверсия) — разность в процентных пунктах. Прошлый период имеет ту же длину и идёт перед текущим; если период включает сегодняшний день, оба периода берутся до текущего времени суток.</td><td>—</td></tr>
                    <tr><td>Что исключается</td><td>Поисковые роботы (по User-Agent) и страницы административной панели.</td><td><code>is_bot</code>, <code>is_admin_area</code></td></tr>
                </tbody>
            </table>
        </div>
    </details>
</section>

<?php stats_render_charts_data($charts); ?>

<?php page_end(); ?>

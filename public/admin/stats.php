<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/layout.php';
require_once __DIR__ . '/../../src/stats.php';

require_admin();

function stats_sparkline_svg(array $values): string
{
    $count = count($values);
    if ($count < 2) {
        return '';
    }

    $width = 120;
    $height = 32;
    $padding = 4;
    $min = min(0.0, (float)min($values));
    $range = max(0.000001, (float)max($values) - $min);
    $points = [];

    foreach (array_values($values) as $index => $value) {
        $x = $padding + ($width - 2 * $padding) * $index / ($count - 1);
        $y = $height - $padding - ($height - 2 * $padding) * (((float)$value - $min) / $range);
        $points[] = round($x, 1) . ',' . round($y, 1);
    }

    [$lastX, $lastY] = explode(',', (string)end($points));

    return '<svg class="kpi-spark" viewBox="0 0 ' . $width . ' ' . $height . '" aria-hidden="true" focusable="false">'
        . '<polyline points="' . implode(' ', $points) . '"></polyline>'
        . '<circle cx="' . $lastX . '" cy="' . $lastY . '" r="3"></circle>'
        . '</svg>';
}

function stats_render_tile(array $tile): void
{
    $delta = $tile['delta'] ?? null;
    $unit = $tile['delta_unit'] ?? '%';
    $state = '';

    if ($delta !== null && $delta != 0.0) {
        $isUp = $delta > 0;
        $state = $isUp === (($tile['good'] ?? 'up') === 'up') ? 'is-good' : 'is-bad';
    }
    ?>
    <article class="kpi-tile">
        <span class="kpi-label"><?= e($tile['label']) ?></span>
        <strong class="kpi-value"><?= e($tile['value']) ?></strong>
        <?php if ($delta === null): ?>
            <span class="kpi-delta">нет данных за прошлый период</span>
        <?php else: ?>
            <span class="kpi-delta <?= $state ?>">
                <span aria-hidden="true"><?= $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '●') ?></span>
                <?= e(($delta > 0 ? '+' : '') . stats_format_number($delta, 1) . "\u{00A0}" . $unit) ?>
                <span class="kpi-delta-note">к прошлому периоду</span>
            </span>
        <?php endif; ?>
        <?= isset($tile['spark']) ? stats_sparkline_svg($tile['spark']) : '' ?>
    </article>
    <?php
}

function stats_chart_card(string $key, string $title, string $subtitle, string $size = '', int $height = 260): void
{
    ?>
    <figure class="chart-card<?= $size !== '' ? ' ' . e($size) : '' ?>">
        <figcaption>
            <h3><?= e($title) ?></h3>
            <p><?= e($subtitle) ?></p>
        </figcaption>
        <div class="chart-box" style="height: <?= $height ?>px">
            <canvas data-chart="<?= e($key) ?>" role="img" aria-label="<?= e($title) ?>"></canvas>
        </div>
        <details class="chart-table">
            <summary>Таблица данных</summary>
            <div class="table-wrap" data-chart-table="<?= e($key) ?>"></div>
        </details>
    </figure>
    <?php
}

$period = stats_period($_GET);
$traffic = stats_traffic_summary($period);
$trafficBefore = stats_traffic_summary($period, true);
$business = stats_business_summary($period);
$businessBefore = stats_business_summary($period, true);
$daily = stats_traffic_by_day($period);
$businessDaily = stats_business_by_day($period);
$topPages = stats_top_pages($period);
$sources = stats_traffic_sources($period);
$devices = stats_devices($period);
$browsers = stats_browsers($period);
$hourly = stats_hourly_activity($period);
$topProducts = stats_top_products_by_views($period);
$referrers = stats_referrer_hosts($period);
$recentVisits = stats_recent_visits();
$hasDemo = stats_has_demo_data();

$conversion = $traffic['sessions'] > 0 ? $business['orders'] / $traffic['sessions'] * 100 : 0.0;
$conversionBefore = $trafficBefore['sessions'] > 0 ? $businessBefore['orders'] / $trafficBefore['sessions'] * 100 : 0.0;
$sourcesTotal = max(1, array_sum(array_column($sources, 'sessions')));
$devicesTotal = max(1, array_sum(array_column($devices, 'sessions')));

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

$charts = [
    'traffic_by_day' => [
        'type' => 'line',
        'unit' => 'count',
        'labels' => $daily['labels'],
        'series' => [
            ['name' => 'Просмотры', 'values' => $daily['page_views']],
            ['name' => 'Посетители', 'values' => $daily['visitors']],
        ],
        'table' => [
            'columns' => ['Дата', 'Просмотры', 'Посетители', 'Визиты'],
            'rows' => array_map(static fn(int $i): array => [
                stats_format_date($daily['dates'][$i]),
                stats_format_number($daily['page_views'][$i]),
                stats_format_number($daily['visitors'][$i]),
                stats_format_number($daily['sessions'][$i]),
            ], array_keys($daily['dates'])),
        ],
    ],
    'top_pages' => [
        'type' => 'hbar',
        'unit' => 'count',
        'labels' => array_column($topPages, 'label'),
        'series' => [['name' => 'Просмотры', 'values' => array_column($topPages, 'views')]],
        'table' => [
            'columns' => ['Страница', 'Адрес', 'Просмотры', 'Посетители'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], $row['page'], stats_format_number($row['views']), stats_format_number($row['visitors']),
            ], $topPages),
        ],
    ],
    'sources' => [
        'type' => 'hbar',
        'unit' => 'count',
        'show_share' => true,
        'labels' => array_column($sources, 'label'),
        'series' => [['name' => 'Визиты', 'values' => array_column($sources, 'sessions')]],
        'table' => [
            'columns' => ['Источник', 'Визиты', 'Доля'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], stats_format_number($row['sessions']), stats_format_number($row['sessions'] / $sourcesTotal * 100, 1) . ' %',
            ], $sources),
        ],
    ],
    'devices' => [
        'type' => 'doughnut',
        'unit' => 'count',
        'labels' => array_column($devices, 'label'),
        'series' => [['name' => 'Визиты', 'values' => array_column($devices, 'sessions')]],
        'table' => [
            'columns' => ['Устройство', 'Визиты', 'Доля'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], stats_format_number($row['sessions']), stats_format_number($row['sessions'] / $devicesTotal * 100, 1) . ' %',
            ], $devices),
        ],
    ],
    'browsers' => [
        'type' => 'hbar',
        'unit' => 'count',
        'labels' => array_column($browsers, 'browser'),
        'series' => [['name' => 'Визиты', 'values' => array_column($browsers, 'sessions')]],
        'table' => [
            'columns' => ['Браузер', 'Визиты'],
            'rows' => array_map(static fn(array $row): array => [$row['browser'], stats_format_number($row['sessions'])], $browsers),
        ],
    ],
    'hourly' => [
        'type' => 'bar',
        'unit' => 'decimal',
        'labels' => $hourly['labels'],
        'series' => [['name' => 'Просмотров в среднем за день', 'values' => $hourly['page_views']]],
        'table' => [
            'columns' => ['Час', 'Просмотров в среднем за день'],
            'rows' => array_map(static fn(string $label, float $value): array => [$label, stats_format_number($value, 1)], $hourly['labels'], $hourly['page_views']),
        ],
    ],
    'top_products' => [
        'type' => 'hbar',
        'unit' => 'count',
        'labels' => array_column($topProducts, 'name'),
        'series' => [['name' => 'Просмотры карточки', 'values' => array_column($topProducts, 'views')]],
        'table' => [
            'columns' => ['Товар', 'Просмотры карточки', 'Посетители'],
            'rows' => array_map(static fn(array $row): array => [
                $row['name'], stats_format_number($row['views']), stats_format_number($row['visitors']),
            ], $topProducts),
        ],
    ],
];

$query = static fn(array $overrides): string => '/admin/stats.php?' . http_build_query(array_merge([
    'period' => $period['preset'],
    'from' => $period['preset'] === 'custom' ? $period['from'] : null,
    'to' => $period['preset'] === 'custom' ? $period['to'] : null,
    'demo' => $period['include_demo'] ? '1' : '0',
], $overrides));

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

    <div class="stats-filters">
        <div class="chips">
            <?php foreach ([7 => '7 дней', 30 => '30 дней', 90 => '90 дней'] as $days => $label): ?>
                <a class="chip<?= $period['preset'] === (string)$days ? ' is-active' : '' ?>" href="<?= e($query(['period' => $days, 'from' => null, 'to' => null])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <form class="stats-range" method="get" action="/admin/stats.php">
            <input type="hidden" name="period" value="custom">
            <input type="hidden" name="demo" value="<?= $period['include_demo'] ? '1' : '0' ?>">
            <label>С <input class="input" type="date" name="from" value="<?= e($period['from']) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
            <label>по <input class="input" type="date" name="to" value="<?= e($period['to']) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
            <button class="btn btn-small<?= $period['preset'] === 'custom' ? '' : ' btn-secondary' ?>" type="submit">Показать</button>
        </form>
        <a class="chip<?= $period['include_demo'] ? '' : ' is-active' ?>" href="<?= e($query(['demo' => $period['include_demo'] ? '0' : '1'])) ?>">
            <?= $period['include_demo'] ? 'Только реальные данные' : 'Показать с демо-данными' ?>
        </a>
    </div>

    <p class="stats-period">
        Период: <strong><?= e(stats_format_date($period['from'])) ?> – <?= e(stats_format_date($period['to'])) ?></strong>
        (<?= e($period['days']) ?> дн.), сравнение с периодом
        <?= e(stats_format_date($period['previous_from'])) ?> – <?= e(stats_format_date($period['previous_to'])) ?><?= $period['includes_today'] ? ' до того же времени суток' : '' ?>.
    </p>

    <?php if ($hasDemo && $period['include_demo']): ?>
        <div class="message info">
            В статистику включены демонстрационные данные: история посещений и заказов, сгенерированная скриптом
            <code>tools/seed_stats.php</code> (отмечены <code>is_demo = 1</code>, заказы <code>DM-…</code>, пользователи <code>@demo.local</code>).
            Реальные посещения сайта записываются трекером параллельно.
        </div>
    <?php elseif (!$period['include_demo']): ?>
        <div class="message ok">Показаны только реальные данные, собранные трекером сайта, без демонстрационных записей.</div>
    <?php endif; ?>

    <h2 class="stats-section-title">Посещаемость</h2>
    <div class="kpi-grid">
        <?php foreach ($trafficTiles as $tile): ?>
            <?php stats_render_tile($tile); ?>
        <?php endforeach; ?>
    </div>

    <div class="chart-grid">
        <?php
        stats_chart_card(
            'traffic_by_day',
            'Посещаемость по дням',
            'Просмотры страниц и уникальные посетители за каждый день периода'
                . ($period['to'] === date('Y-m-d') ? '. Последняя точка — сегодняшний день, он ещё не закончился' : ''),
            'chart-card-wide',
            300
        );
        stats_chart_card('top_pages', 'Популярные страницы', 'Количество просмотров каждой страницы за период');
        stats_chart_card('sources', 'Источники визитов', 'Откуда пришёл посетитель: по адресу первого перехода визита');
        stats_chart_card('devices', 'Устройства', 'Доля визитов с компьютеров, смартфонов и планшетов');
        stats_chart_card('browsers', 'Браузеры', 'Количество визитов по браузерам посетителей');
        stats_chart_card('hourly', 'Активность по часам', 'Сколько страниц в среднем просматривают в каждый час суток', 'chart-card-wide', 240);
        stats_chart_card('top_products', 'Популярные товары', 'Просмотры карточек товаров за период', 'chart-card-wide', 300);
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
    <div class="kpi-grid">
        <?php foreach ($shopTiles as $tile): ?>
            <?php stats_render_tile($tile); ?>
        <?php endforeach; ?>
    </div>

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

<script type="application/json" id="stats-data"><?= stats_json($charts) ?></script>
<script src="/assets/vendor/chart.umd.min.js"></script>
<script src="/assets/js/admin-stats.js"></script>

<?php page_end(); ?>

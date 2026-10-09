<?php

declare(strict_types=1);

require_once __DIR__ . '/monitoring.php';

/*
 * Описания графиков для Chart.js. У каждого графика есть таблица с теми же данными:
 * она показывается под графиком и выгружается в CSV (admin/export.php).
 * type: line | bar | hbar | doughnut; unit: count | decimal | money | percent | ms | kb.
 */

function stats_chart(string $type, string $unit, array $labels, array $series, array $table, array $options = []): array
{
    return ['type' => $type, 'unit' => $unit, 'labels' => array_values($labels), 'series' => $series, 'table' => $table] + $options;
}

function stats_series(string $name, array $values): array
{
    // null – за этот день нет данных, на графике будет разрыв линии
    return ['name' => $name, 'values' => array_map(static fn($value): ?float => $value === null ? null : round((float)$value, 2), array_values($values))];
}

function stats_share(float $value, float $total): string
{
    return stats_format_number($total > 0 ? $value / $total * 100 : 0, 1);
}

function stats_daily_rows(array $daily, array $columns): array
{
    $rows = [];
    foreach ($daily['dates'] as $index => $date) {
        $row = [stats_format_date($date)];
        foreach ($columns as $field => $decimals) {
            $value = $daily[$field][$index];
            $row[] = $value === null ? '—' : stats_format_number((float)$value, $decimals);
        }
        $rows[] = $row;
    }

    return $rows;
}

function stats_traffic_charts(array $period): array
{
    $daily = stats_traffic_by_day($period);
    $topPages = stats_top_pages($period);
    $sources = stats_traffic_sources($period);
    $devices = stats_devices($period);
    $browsers = stats_browsers($period);
    $hourly = stats_hourly_activity($period);
    $topProducts = stats_top_products_by_views($period);
    $sourcesTotal = (float)array_sum(array_column($sources, 'sessions'));
    $devicesTotal = (float)array_sum(array_column($devices, 'sessions'));

    return [
        'traffic_by_day' => stats_chart('line', 'count', $daily['labels'], [
            stats_series('Просмотры', $daily['page_views']),
            stats_series('Посетители', $daily['visitors']),
        ], [
            'columns' => ['Дата', 'Просмотры', 'Посетители', 'Визиты'],
            'rows' => stats_daily_rows($daily, ['page_views' => 0, 'visitors' => 0, 'sessions' => 0]),
        ]),
        'top_pages' => stats_chart('hbar', 'count', array_column($topPages, 'label'), [
            stats_series('Просмотры', array_column($topPages, 'views')),
        ], [
            'columns' => ['Страница', 'Адрес', 'Просмотры', 'Посетители'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], $row['page'], stats_format_number($row['views']), stats_format_number($row['visitors']),
            ], $topPages),
        ]),
        'sources' => stats_chart('hbar', 'count', array_column($sources, 'label'), [
            stats_series('Визиты', array_column($sources, 'sessions')),
        ], [
            'columns' => ['Источник', 'Визиты', 'Доля, %'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], stats_format_number($row['sessions']), stats_share($row['sessions'], $sourcesTotal),
            ], $sources),
        ], ['show_share' => true]),
        'devices' => stats_chart('doughnut', 'count', array_column($devices, 'label'), [
            stats_series('Визиты', array_column($devices, 'sessions')),
        ], [
            'columns' => ['Устройство', 'Визиты', 'Доля, %'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], stats_format_number($row['sessions']), stats_share($row['sessions'], $devicesTotal),
            ], $devices),
        ]),
        'browsers' => stats_chart('hbar', 'count', array_column($browsers, 'browser'), [
            stats_series('Визиты', array_column($browsers, 'sessions')),
        ], [
            'columns' => ['Браузер', 'Визиты'],
            'rows' => array_map(static fn(array $row): array => [$row['browser'], stats_format_number($row['sessions'])], $browsers),
        ]),
        'hourly' => stats_chart('bar', 'decimal', $hourly['labels'], [
            stats_series('Просмотров в среднем за день', $hourly['page_views']),
        ], [
            'columns' => ['Час', 'Просмотров в среднем за день'],
            'rows' => array_map(static fn(string $label, float $value): array => [$label, stats_format_number($value, 1)], $hourly['labels'], $hourly['page_views']),
        ]),
        'top_products' => stats_chart('hbar', 'count', array_column($topProducts, 'name'), [
            stats_series('Просмотры карточки', array_column($topProducts, 'views')),
        ], [
            'columns' => ['Товар', 'Просмотры карточки', 'Посетители'],
            'rows' => array_map(static fn(array $row): array => [
                $row['name'], stats_format_number($row['views']), stats_format_number($row['visitors']),
            ], $topProducts),
        ]),
    ];
}

function stats_sales_charts(array $period): array
{
    $daily = stats_business_by_day($period);
    $statuses = stats_order_statuses($period);
    $delivery = stats_order_methods($period, 'delivery_method');
    $payment = stats_order_methods($period, 'payment_method');
    $products = stats_top_products_by_revenue($period);
    $categories = stats_revenue_by_category($period);
    $funnel = stats_funnel($period);
    $growth = stats_users_growth($period);
    $cities = stats_buyers_by_city($period);
    $reviews = stats_reviews_summary($period);
    $statusTotal = (float)array_sum(array_column($statuses, 'orders'));
    $categoriesTotal = (float)array_sum(array_column($categories, 'revenue'));
    $funnelStart = (float)($funnel[0]['value'] ?? 0);
    $ordersTotal = (float)array_sum(array_column($delivery, 'orders'));
    $reviewsTotal = (float)array_sum($reviews['distribution']);

    return [
        'orders_by_day' => stats_chart('bar', 'count', $daily['labels'], [
            stats_series('Заказы', $daily['orders']),
        ], [
            'columns' => ['Дата', 'Заказы', 'Выручка, ₽'],
            'rows' => stats_daily_rows($daily, ['orders' => 0, 'revenue' => 0]),
        ]),
        'revenue_by_day' => stats_chart('line', 'money', $daily['labels'], [
            stats_series('Выручка', $daily['revenue']),
        ], [
            'columns' => ['Дата', 'Выручка, ₽'],
            'rows' => stats_daily_rows($daily, ['revenue' => 0]),
        ]),
        'order_statuses' => stats_chart('hbar', 'count', array_column($statuses, 'label'), [
            stats_series('Заказы', array_column($statuses, 'orders')),
        ], [
            'columns' => ['Статус', 'Заказы', 'Доля, %'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], stats_format_number($row['orders']), stats_share($row['orders'], $statusTotal),
            ], $statuses),
        ], ['show_share' => true]),
        'delivery_methods' => stats_chart('doughnut', 'count', array_column($delivery, 'label'), [
            stats_series('Заказы', array_column($delivery, 'orders')),
        ], [
            'columns' => ['Способ доставки', 'Заказы', 'Доля, %'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], stats_format_number($row['orders']), stats_share($row['orders'], $ordersTotal),
            ], $delivery),
        ]),
        'payment_methods' => stats_chart('doughnut', 'count', array_column($payment, 'label'), [
            stats_series('Заказы', array_column($payment, 'orders')),
        ], [
            'columns' => ['Способ оплаты', 'Заказы', 'Доля, %'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], stats_format_number($row['orders']), stats_share($row['orders'], $ordersTotal),
            ], $payment),
        ]),
        'top_products_revenue' => stats_chart('hbar', 'money', array_column($products, 'name'), [
            stats_series('Выручка', array_column($products, 'revenue')),
        ], [
            'columns' => ['Товар', 'Продано, шт.', 'Заказы', 'Выручка, ₽'],
            'rows' => array_map(static fn(array $row): array => [
                $row['name'], stats_format_number($row['quantity']), stats_format_number($row['orders']), stats_format_number($row['revenue']),
            ], $products),
        ]),
        'categories' => stats_chart('hbar', 'money', array_column($categories, 'name'), [
            stats_series('Выручка', array_column($categories, 'revenue')),
        ], [
            'columns' => ['Категория', 'Продано, шт.', 'Выручка, ₽', 'Доля, %'],
            'rows' => array_map(static fn(array $row): array => [
                $row['name'], stats_format_number($row['quantity']), stats_format_number($row['revenue']), stats_share($row['revenue'], $categoriesTotal),
            ], $categories),
        ], ['show_share' => true]),
        'funnel' => stats_chart('hbar', 'count', array_column($funnel, 'label'), [
            stats_series('Визиты', array_column($funnel, 'value')),
        ], [
            'columns' => ['Шаг', 'Количество', 'От всех визитов, %'],
            'rows' => array_map(static fn(array $row): array => [
                $row['label'], stats_format_number($row['value']), stats_share($row['value'], $funnelStart),
            ], $funnel),
        ], ['show_share' => true, 'share_base' => 'first']),
        'users_growth' => stats_chart('line', 'count', $growth['labels'], [
            stats_series('Всего пользователей', $growth['total']),
        ], [
            'columns' => ['Дата', 'Новые пользователи', 'Всего пользователей'],
            'rows' => stats_daily_rows($growth, ['new_users' => 0, 'total' => 0]),
        ]),
        'cities' => stats_chart('hbar', 'count', array_column($cities, 'city'), [
            stats_series('Покупатели', array_column($cities, 'buyers')),
        ], [
            'columns' => ['Город', 'Покупатели', 'Заказы'],
            'rows' => array_map(static fn(array $row): array => [
                $row['city'], stats_format_number($row['buyers']), stats_format_number($row['orders']),
            ], $cities),
        ]),
        'ratings' => stats_chart('bar', 'count', ['1 ★', '2 ★', '3 ★', '4 ★', '5 ★'], [
            stats_series('Отзывы', $reviews['distribution']),
        ], [
            'columns' => ['Оценка', 'Отзывы', 'Доля, %'],
            'rows' => array_map(static fn(int $rating, int $count): array => [
                (string)$rating, stats_format_number($count), stats_share($count, $reviewsTotal),
            ], [1, 2, 3, 4, 5], $reviews['distribution']),
        ]),
    ];
}

function monitor_charts(array $period): array
{
    $response = monitor_response_by_day($period);
    $http = monitor_http_errors_by_day($period);
    $errors = monitor_errors_by_day($period);
    $auth = monitor_auth_by_day($period);
    $api = monitor_api_by_day($period);
    $uptime = monitor_uptime_by_day($period);
    $tables = array_slice(monitor_table_sizes(), 0, 10);

    return [
        'response_by_day' => stats_chart('line', 'ms', $response['labels'], [
            stats_series('Среднее время', $response['avg_ms']),
            stats_series('95-й перцентиль', $response['p95_ms']),
        ], [
            'columns' => ['Дата', 'Страниц', 'Среднее, мс', '95-й перцентиль, мс'],
            'rows' => stats_daily_rows($response, ['hits' => 0, 'avg_ms' => 1, 'p95_ms' => 1]),
        ]),
        'http_errors' => stats_chart('bar', 'count', $http['labels'], [
            stats_series('404 – не найдено', $http['s404']),
            stats_series('403 – нет доступа', $http['s403']),
            stats_series('500 – ошибка сервера', $http['s500']),
        ], [
            'columns' => ['Дата', '404', '403', '500'],
            'rows' => stats_daily_rows($http, ['s404' => 0, 's403' => 0, 's500' => 0]),
        ], ['stacked' => true]),
        'php_errors' => stats_chart('bar', 'count', $errors['labels'], [
            stats_series('Замечания', $errors['notices']),
            stats_series('Предупреждения', $errors['warnings']),
            stats_series('Ошибки', $errors['errors']),
        ], [
            'columns' => ['Дата', 'Замечания', 'Предупреждения', 'Ошибки'],
            'rows' => stats_daily_rows($errors, ['notices' => 0, 'warnings' => 0, 'errors' => 0]),
        ], ['stacked' => true]),
        'auth_by_day' => stats_chart('bar', 'count', $auth['labels'], [
            stats_series('Успешные входы', $auth['success']),
            stats_series('Неудачные входы', $auth['failed']),
        ], [
            'columns' => ['Дата', 'Успешные входы', 'Неудачные входы'],
            'rows' => stats_daily_rows($auth, ['success' => 0, 'failed' => 0]),
        ]),
        'api_by_day' => stats_chart('line', 'ms', $api['labels'], [
            stats_series('Среднее время ответа', $api['avg_ms']),
        ], [
            'columns' => ['Дата', 'Запросы', 'Успешно, %', 'Среднее время, мс'],
            'rows' => stats_daily_rows($api, ['requests' => 0, 'success_rate' => 1, 'avg_ms' => 0]),
        ]),
        'uptime_by_day' => stats_chart('line', 'percent', $uptime['labels'], [
            stats_series('Сайт', $uptime['site_uptime']),
            stats_series('База данных', $uptime['db_uptime']),
            stats_series('API', $uptime['api_uptime']),
        ], [
            'columns' => ['Дата', 'Сайт, %', 'База данных, %', 'API, %'],
            'rows' => stats_daily_rows($uptime, ['site_uptime' => 2, 'db_uptime' => 2, 'api_uptime' => 2]),
        ], ['suggested_min' => 80, 'max' => 100]),
        'table_sizes' => stats_chart('hbar', 'kb', array_column($tables, 'table'), [
            stats_series('Размер', array_column($tables, 'size_kb')),
        ], [
            'columns' => ['Таблица', 'Строк (оценка)', 'Размер, КБ'],
            'rows' => array_map(static fn(array $row): array => [
                $row['table'], stats_format_number($row['rows']), stats_format_number($row['size_kb'], 1),
            ], $tables),
        ]),
    ];
}

function stats_all_charts(string $group, array $period): array
{
    return match ($group) {
        'traffic' => stats_traffic_charts($period),
        'sales' => stats_sales_charts($period),
        'monitoring' => monitor_charts($period),
        default => [],
    };
}

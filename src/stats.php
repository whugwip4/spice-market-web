<?php

declare(strict_types=1);

require_once __DIR__ . '/data.php';

const STATS_PERIOD_PRESETS = [7, 30, 90];
const STATS_MAX_PERIOD_DAYS = 366;

/**
 * Период отчёта: 7/30/90 дней до сегодняшнего дня включительно или свои даты (?period=custom&from=…&to=…).
 * Границы — полуинтервал [start; end). Прошлый период сдвинут назад на ту же длину.
 * Если период заканчивается сегодня, он длится до текущего момента, а прошлый период обрезается тем же временем суток,
 * поэтому неполный сегодняшний день не занижает изменения к прошлому периоду.
 */
function stats_period(array $query): array
{
    $today = new DateTimeImmutable('today');
    $preset = (string)($query['period'] ?? '30');
    $from = null;
    $to = null;

    if ($preset === 'custom') {
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($query['from'] ?? '')) ?: null;
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($query['to'] ?? '')) ?: null;
    }

    if (!$from || !$to || $from > $to) {
        $days = in_array((int)$preset, STATS_PERIOD_PRESETS, true) ? (int)$preset : 30;
        $preset = (string)$days;
        $to = $today;
        $from = $today->modify('-' . ($days - 1) . ' days');
    }

    if ($to > $today) {
        $to = $today;
    }
    if ($from > $to) {
        $from = $to;
    }
    if ($from->diff($to)->days + 1 > STATS_MAX_PERIOD_DAYS) {
        $from = $to->modify('-' . (STATS_MAX_PERIOD_DAYS - 1) . ' days');
    }

    $days = $from->diff($to)->days + 1;
    $includesToday = $to == $today;
    $end = $includesToday ? new DateTimeImmutable('now') : $to->modify('+1 day');
    $previousStart = $from->modify('-' . $days . ' days');
    $previousEnd = $end->modify('-' . $days . ' days');

    return [
        'preset' => $preset,
        'days' => $days,
        'includes_today' => $includesToday,
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
        'start' => $from->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'previous_from' => $previousStart->format('Y-m-d'),
        'previous_to' => $previousEnd->modify('-1 second')->format('Y-m-d'),
        'previous_start' => $previousStart->format('Y-m-d H:i:s'),
        'previous_end' => $previousEnd->format('Y-m-d H:i:s'),
        'include_demo' => (string)($query['demo'] ?? '1') !== '0',
    ];
}

function stats_bounds(array $period, bool $previous = false): array
{
    return $previous
        ? ['start' => $period['previous_start'], 'end' => $period['previous_end']]
        : ['start' => $period['start'], 'end' => $period['end']];
}

// Общее условие для журнала посещений: период, без поисковых роботов и без страниц админ-панели
function stats_visits_where(array $period, string $alias = 'v'): string
{
    $where = "$alias.created_at >= :start AND $alias.created_at < :end AND $alias.is_bot = 0 AND $alias.is_admin_area = 0";

    return $period['include_demo'] ? $where : $where . " AND $alias.is_demo = 0";
}

function stats_orders_demo_filter(array $period, string $alias = 'o'): string
{
    return $period['include_demo'] ? '' : " AND $alias.order_number NOT LIKE 'DM-%'";
}

function stats_users_demo_filter(array $period, string $alias = 'u'): string
{
    return $period['include_demo'] ? '' : " AND $alias.email NOT LIKE '%@demo.local'";
}

function stats_has_demo_data(): bool
{
    $row = spice_fetch_one('SELECT EXISTS(SELECT 1 FROM site_visits WHERE is_demo = 1) AS has_demo');

    return (bool)($row['has_demo'] ?? false);
}

function stats_traffic_summary(array $period, bool $previous = false): array
{
    $where = stats_visits_where($period);
    $params = stats_bounds($period, $previous);

    $totals = spice_fetch_one(
        "
        SELECT
            COUNT(*) AS page_views,
            COUNT(DISTINCT v.visitor_id) AS visitors,
            COUNT(DISTINCT v.session_id) AS sessions
        FROM site_visits v
        WHERE $where
        ",
        $params
    ) ?? [];

    // визит с одним просмотром считается отказом; длительность визита — от первого до последнего просмотра
    $sessions = spice_fetch_one(
        "
        SELECT
            COUNT(*) AS sessions,
            COALESCE(SUM(s.hits = 1), 0) AS bounces,
            COALESCE(AVG(TIMESTAMPDIFF(SECOND, s.started_at, s.ended_at)), 0) AS avg_duration
        FROM (
            SELECT v.session_id, COUNT(*) AS hits, MIN(v.created_at) AS started_at, MAX(v.created_at) AS ended_at
            FROM site_visits v
            WHERE $where
            GROUP BY v.session_id
        ) s
        ",
        $params
    ) ?? [];

    $pageViews = (int)($totals['page_views'] ?? 0);
    $sessionCount = (int)($sessions['sessions'] ?? 0);

    return [
        'page_views' => $pageViews,
        'visitors' => (int)($totals['visitors'] ?? 0),
        'sessions' => $sessionCount,
        'bounce_rate' => $sessionCount > 0 ? round((int)$sessions['bounces'] / $sessionCount * 100, 1) : 0.0,
        'pages_per_session' => $sessionCount > 0 ? round($pageViews / $sessionCount, 2) : 0.0,
        'avg_duration' => (int)round((float)($sessions['avg_duration'] ?? 0)),
    ];
}

function stats_business_summary(array $period, bool $previous = false): array
{
    $params = stats_bounds($period, $previous);
    $ordersFilter = stats_orders_demo_filter($period);
    $usersFilter = stats_users_demo_filter($period);

    // выручка и средний чек считаются без отменённых заказов
    $orders = spice_fetch_one(
        "
        SELECT
            COUNT(*) AS orders_count,
            COALESCE(SUM(o.status <> 'cancelled'), 0) AS completed_count,
            COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS revenue
        FROM orders o
        WHERE o.created_at >= :start AND o.created_at < :end $ordersFilter
        ",
        $params
    ) ?? [];

    $newUsers = spice_fetch_one(
        "SELECT COUNT(*) AS users_count FROM users u WHERE u.created_at >= :start AND u.created_at < :end $usersFilter",
        $params
    ) ?? [];

    $totalUsers = spice_fetch_one(
        "SELECT COUNT(*) AS users_count FROM users u WHERE u.created_at < :end $usersFilter",
        ['end' => $params['end']]
    ) ?? [];

    $validOrders = (int)($orders['completed_count'] ?? 0);
    $revenue = (float)($orders['revenue'] ?? 0);

    return [
        'orders' => (int)($orders['orders_count'] ?? 0),
        'revenue' => $revenue,
        'avg_check' => $validOrders > 0 ? round($revenue / $validOrders, 2) : 0.0,
        'new_users' => (int)($newUsers['users_count'] ?? 0),
        'total_users' => (int)($totalUsers['users_count'] ?? 0),
    ];
}

// Строит непрерывный ряд по дням периода: дни без данных получают 0, иначе линия графика «перескакивает» через пропуски
function stats_fill_days(array $period, array $rows, array $fields): array
{
    $byDay = [];
    foreach ($rows as $row) {
        $byDay[(string)$row['day']] = $row;
    }

    $result = ['dates' => [], 'labels' => []];
    foreach ($fields as $field) {
        $result[$field] = [];
    }

    $day = new DateTimeImmutable($period['from']);
    $last = new DateTimeImmutable($period['to']);

    while ($day <= $last) {
        $key = $day->format('Y-m-d');
        $result['dates'][] = $key;
        $result['labels'][] = $day->format('d.m');

        foreach ($fields as $field) {
            $result[$field][] = isset($byDay[$key]) ? (float)$byDay[$key][$field] : 0.0;
        }

        $day = $day->modify('+1 day');
    }

    return $result;
}

function stats_traffic_by_day(array $period): array
{
    $where = stats_visits_where($period);
    $rows = spice_fetch_all(
        "
        SELECT
            DATE(v.created_at) AS day,
            COUNT(*) AS page_views,
            COUNT(DISTINCT v.visitor_id) AS visitors,
            COUNT(DISTINCT v.session_id) AS sessions
        FROM site_visits v
        WHERE $where
        GROUP BY DATE(v.created_at)
        ORDER BY day
        ",
        stats_bounds($period)
    );

    return stats_fill_days($period, $rows, ['page_views', 'visitors', 'sessions']);
}

function stats_business_by_day(array $period): array
{
    $params = stats_bounds($period);
    $ordersFilter = stats_orders_demo_filter($period);
    $usersFilter = stats_users_demo_filter($period);

    $orders = spice_fetch_all(
        "
        SELECT
            DATE(o.created_at) AS day,
            COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS revenue
        FROM orders o
        WHERE o.created_at >= :start AND o.created_at < :end $ordersFilter
        GROUP BY DATE(o.created_at)
        ",
        $params
    );

    $users = spice_fetch_all(
        "
        SELECT DATE(u.created_at) AS day, COUNT(*) AS new_users
        FROM users u
        WHERE u.created_at >= :start AND u.created_at < :end $usersFilter
        GROUP BY DATE(u.created_at)
        ",
        $params
    );

    $series = stats_fill_days($period, $orders, ['orders', 'revenue']);
    $series['new_users'] = stats_fill_days($period, $users, ['new_users'])['new_users'];

    return $series;
}

function stats_page_label(string $page): string
{
    return [
        '/' => 'Главная',
        '/catalog.php' => 'Каталог',
        '/product.php' => 'Карточка товара',
        '/recipes.php' => 'Рецепты',
        '/reviews.php' => 'Отзывы',
        '/delivery.php' => 'Доставка и оплата',
        '/contacts.php' => 'Контакты',
        '/cart.php' => 'Корзина',
        '/checkout.php' => 'Оформление заказа',
        '/login.php' => 'Вход',
        '/register.php' => 'Регистрация',
        '/account.php' => 'Личный кабинет',
        '/admin/' => 'Админка: главная',
        '/admin/table.php' => 'Админка: таблица БД',
        '/admin/stats.php' => 'Админка: посещаемость',
        '/admin/sales.php' => 'Админка: продажи',
        '/admin/monitoring.php' => 'Админка: мониторинг',
    ][$page] ?? $page;
}

function stats_top_pages(array $period, int $limit = 8): array
{
    $where = stats_visits_where($period);
    $rows = spice_fetch_all(
        "
        SELECT v.page, COUNT(*) AS views, COUNT(DISTINCT v.visitor_id) AS visitors
        FROM site_visits v
        WHERE $where
        GROUP BY v.page
        ORDER BY views DESC
        LIMIT " . max(1, $limit),
        stats_bounds($period)
    );

    return array_map(static fn(array $row): array => [
        'page' => (string)$row['page'],
        'label' => stats_page_label((string)$row['page']),
        'views' => (int)$row['views'],
        'visitors' => (int)$row['visitors'],
    ], $rows);
}

function stats_top_products_by_views(array $period, int $limit = 8): array
{
    $where = stats_visits_where($period);

    return array_map(static fn(array $row): array => [
        'name' => (string)$row['name'],
        'views' => (int)$row['views'],
        'visitors' => (int)$row['visitors'],
    ], spice_fetch_all(
        "
        SELECT p.name, COUNT(*) AS views, COUNT(DISTINCT v.visitor_id) AS visitors
        FROM site_visits v
        JOIN products p ON p.product_id = v.product_id
        WHERE $where AND v.page = '/product.php'
        GROUP BY p.product_id, p.name
        ORDER BY views DESC
        LIMIT " . max(1, $limit),
        stats_bounds($period)
    ));
}

// Источник визита определяется по первому просмотру визита (минимальный visit_id внутри session_id)
function stats_traffic_sources(array $period): array
{
    $where = stats_visits_where($period);
    $rows = spice_fetch_all(
        "
        SELECT v.referrer_type, COUNT(*) AS sessions
        FROM site_visits v
        JOIN (
            SELECT MIN(v.visit_id) AS first_visit_id
            FROM site_visits v
            WHERE $where
            GROUP BY v.session_id
        ) first_hits ON first_hits.first_visit_id = v.visit_id
        GROUP BY v.referrer_type
        ",
        stats_bounds($period)
    );

    $counts = array_column($rows, 'sessions', 'referrer_type');
    $labels = [
        'direct' => 'Прямые заходы',
        'search' => 'Поисковые системы',
        'social' => 'Социальные сети',
        'external' => 'Ссылки на сайтах',
        'internal' => 'Внутренние переходы',
    ];

    $result = [];
    foreach ($labels as $type => $label) {
        if ($type === 'internal' && empty($counts[$type])) {
            continue;
        }
        $result[] = ['type' => $type, 'label' => $label, 'sessions' => (int)($counts[$type] ?? 0)];
    }

    return $result;
}

function stats_referrer_hosts(array $period, int $limit = 8): array
{
    $where = stats_visits_where($period);

    return spice_fetch_all(
        "
        SELECT v.referrer_type, v.referrer_host, COUNT(*) AS sessions
        FROM site_visits v
        JOIN (
            SELECT MIN(v.visit_id) AS first_visit_id
            FROM site_visits v
            WHERE $where
            GROUP BY v.session_id
        ) first_hits ON first_hits.first_visit_id = v.visit_id
        WHERE v.referrer_host IS NOT NULL
        GROUP BY v.referrer_type, v.referrer_host
        ORDER BY sessions DESC
        LIMIT " . max(1, $limit),
        stats_bounds($period)
    );
}

// Устройства в фиксированном порядке: цвет закреплён за типом устройства, а не за местом в рейтинге
function stats_devices(array $period): array
{
    $where = stats_visits_where($period);
    $counts = array_column(spice_fetch_all(
        "
        SELECT v.device_type, COUNT(DISTINCT v.session_id) AS sessions
        FROM site_visits v
        WHERE $where
        GROUP BY v.device_type
        ",
        stats_bounds($period)
    ), 'sessions', 'device_type');

    $result = [];
    foreach (['mobile' => 'Смартфоны', 'desktop' => 'Компьютеры', 'tablet' => 'Планшеты'] as $type => $label) {
        $result[] = ['type' => $type, 'label' => $label, 'sessions' => (int)($counts[$type] ?? 0)];
    }

    return $result;
}

function stats_browsers(array $period, int $limit = 7): array
{
    $where = stats_visits_where($period);

    return array_map(static fn(array $row): array => [
        'browser' => (string)$row['browser'],
        'sessions' => (int)$row['sessions'],
    ], spice_fetch_all(
        "
        SELECT v.browser, COUNT(DISTINCT v.session_id) AS sessions
        FROM site_visits v
        WHERE $where
        GROUP BY v.browser
        ORDER BY sessions DESC
        LIMIT " . max(1, $limit),
        stats_bounds($period)
    ));
}

function stats_hourly_activity(array $period): array
{
    $where = stats_visits_where($period);
    $counts = array_column(spice_fetch_all(
        "
        SELECT HOUR(v.created_at) AS hour_of_day, COUNT(*) AS page_views
        FROM site_visits v
        WHERE $where
        GROUP BY HOUR(v.created_at)
        ",
        stats_bounds($period)
    ), 'page_views', 'hour_of_day');

    $result = ['labels' => [], 'page_views' => []];
    for ($hour = 0; $hour < 24; $hour++) {
        $result['labels'][] = sprintf('%02d:00', $hour);
        // среднее число просмотров в этот час за один день периода
        $result['page_views'][] = round((int)($counts[$hour] ?? 0) / max(1, $period['days']), 1);
    }

    return $result;
}

function stats_recent_visits(int $limit = 12): array
{
    return spice_fetch_all(
        '
        SELECT
            v.created_at, v.page, v.query_string, v.http_status, v.device_type, v.browser,
            v.referrer_type, v.response_time_ms, v.is_demo, u.email
        FROM site_visits v
        LEFT JOIN users u ON u.user_id = v.user_id
        WHERE v.is_bot = 0 AND v.is_admin_area = 0
        ORDER BY v.visit_id DESC
        LIMIT ' . max(1, $limit)
    );
}

// ---------- Продажи, покупатели, каталог ----------

function stats_orders_where(array $period, string $alias = 'o'): string
{
    return "$alias.created_at >= :start AND $alias.created_at < :end" . stats_orders_demo_filter($period, $alias);
}

function stats_order_status_label(string $status): string
{
    return ['new' => 'Новые', 'processing' => 'В обработке', 'completed' => 'Выполненные', 'cancelled' => 'Отменённые'][$status] ?? $status;
}

function stats_sales_summary(array $period, bool $previous = false): array
{
    $where = stats_orders_where($period);
    $row = spice_fetch_one(
        "
        SELECT
            COUNT(*) AS orders_count,
            COALESCE(SUM(o.status = 'cancelled'), 0) AS cancelled_count,
            COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS revenue,
            COUNT(DISTINCT o.user_id) AS buyers
        FROM orders o
        WHERE $where
        ",
        stats_bounds($period, $previous)
    ) ?? [];

    // покупатель считается повторным, если за период у него два заказа и больше
    $repeat = spice_fetch_one(
        "
        SELECT COALESCE(SUM(buyer.orders_count >= 2), 0) AS repeat_buyers
        FROM (SELECT o.user_id, COUNT(*) AS orders_count FROM orders o WHERE $where GROUP BY o.user_id) buyer
        ",
        stats_bounds($period, $previous)
    ) ?? [];

    $ordersCount = (int)($row['orders_count'] ?? 0);
    $cancelled = (int)($row['cancelled_count'] ?? 0);
    $buyers = (int)($row['buyers'] ?? 0);
    $revenue = (float)($row['revenue'] ?? 0);

    return [
        'orders' => $ordersCount,
        'revenue' => $revenue,
        'avg_check' => $ordersCount - $cancelled > 0 ? round($revenue / ($ordersCount - $cancelled), 2) : 0.0,
        'cancel_rate' => $ordersCount > 0 ? round($cancelled / $ordersCount * 100, 1) : 0.0,
        'buyers' => $buyers,
        'repeat_rate' => $buyers > 0 ? round((int)($repeat['repeat_buyers'] ?? 0) / $buyers * 100, 1) : 0.0,
    ];
}

function stats_order_statuses(array $period): array
{
    $counts = array_column(spice_fetch_all(
        'SELECT o.status, COUNT(*) AS orders_count FROM orders o WHERE ' . stats_orders_where($period) . ' GROUP BY o.status',
        stats_bounds($period)
    ), 'orders_count', 'status');

    $result = [];
    foreach (['new', 'processing', 'completed', 'cancelled'] as $status) {
        $result[] = ['status' => $status, 'label' => stats_order_status_label($status), 'orders' => (int)($counts[$status] ?? 0)];
    }

    return $result;
}

function stats_order_methods(array $period, string $column): array
{
    $labels = $column === 'delivery_method'
        ? ['courier' => 'Курьер', 'pickup' => 'Самовывоз']
        : ['card_on_delivery' => 'Картой при получении', 'cash' => 'Наличными'];
    $column = $column === 'delivery_method' ? 'delivery_method' : 'payment_method';

    $counts = array_column(spice_fetch_all(
        "SELECT o.$column AS method, COUNT(*) AS orders_count FROM orders o WHERE " . stats_orders_where($period) . " GROUP BY o.$column",
        stats_bounds($period)
    ), 'orders_count', 'method');

    $result = [];
    foreach ($labels as $method => $label) {
        $result[] = ['label' => $label, 'orders' => (int)($counts[$method] ?? 0)];
    }

    return $result;
}

function stats_top_products_by_revenue(array $period, int $limit = 8): array
{
    return array_map(static fn(array $row): array => [
        'name' => (string)$row['name'],
        'quantity' => (int)$row['quantity'],
        'orders' => (int)$row['orders_count'],
        'revenue' => (float)$row['revenue'],
    ], spice_fetch_all(
        '
        SELECT p.name, SUM(oi.quantity) AS quantity, COUNT(DISTINCT o.order_id) AS orders_count, SUM(oi.line_total) AS revenue
        FROM order_items oi
        JOIN orders o ON o.order_id = oi.order_id
        JOIN products p ON p.product_id = oi.product_id
        WHERE ' . stats_orders_where($period) . " AND o.status <> 'cancelled'
        GROUP BY p.product_id, p.name
        ORDER BY revenue DESC
        LIMIT " . max(1, $limit),
        stats_bounds($period)
    ));
}

function stats_revenue_by_category(array $period): array
{
    return array_map(static fn(array $row): array => [
        'name' => (string)$row['name'],
        'quantity' => (int)$row['quantity'],
        'revenue' => (float)$row['revenue'],
    ], spice_fetch_all(
        '
        SELECT c.name, SUM(oi.quantity) AS quantity, SUM(oi.line_total) AS revenue
        FROM order_items oi
        JOIN orders o ON o.order_id = oi.order_id
        JOIN products p ON p.product_id = oi.product_id
        JOIN categories c ON c.category_id = p.category_id
        WHERE ' . stats_orders_where($period) . " AND o.status <> 'cancelled'
        GROUP BY c.category_id, c.name
        ORDER BY revenue DESC
        ",
        stats_bounds($period)
    ));
}

// Воронка: сколько визитов дошло до карточки товара, корзины и оформления, сколько оформлено заказов
function stats_funnel(array $period): array
{
    $row = spice_fetch_one(
        "
        SELECT
            COUNT(DISTINCT v.session_id) AS visits,
            COUNT(DISTINCT CASE WHEN v.page = '/product.php' THEN v.session_id END) AS product_views,
            COUNT(DISTINCT CASE WHEN v.page = '/cart.php' THEN v.session_id END) AS cart_views,
            COUNT(DISTINCT CASE WHEN v.page = '/checkout.php' THEN v.session_id END) AS checkout_views
        FROM site_visits v
        WHERE " . stats_visits_where($period),
        stats_bounds($period)
    ) ?? [];

    return [
        ['label' => 'Визиты', 'value' => (int)($row['visits'] ?? 0)],
        ['label' => 'Открыли товар', 'value' => (int)($row['product_views'] ?? 0)],
        ['label' => 'Открыли корзину', 'value' => (int)($row['cart_views'] ?? 0)],
        ['label' => 'Начали оформление', 'value' => (int)($row['checkout_views'] ?? 0)],
        ['label' => 'Оформили заказ', 'value' => stats_sales_summary($period)['orders']],
    ];
}

function stats_buyers_by_city(array $period, int $limit = 8): array
{
    return array_map(static fn(array $row): array => [
        'city' => (string)$row['city'],
        'buyers' => (int)$row['buyers'],
        'orders' => (int)$row['orders_count'],
    ], spice_fetch_all(
        '
        SELECT u.city, COUNT(DISTINCT o.user_id) AS buyers, COUNT(*) AS orders_count
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        WHERE ' . stats_orders_where($period) . '
        GROUP BY u.city
        ORDER BY buyers DESC
        LIMIT ' . max(1, $limit),
        stats_bounds($period)
    ));
}

function stats_users_growth(array $period): array
{
    $usersFilter = stats_users_demo_filter($period);
    $before = spice_fetch_one(
        "SELECT COUNT(*) AS users_count FROM users u WHERE u.created_at < :start $usersFilter",
        ['start' => $period['start']]
    );
    $daily = stats_business_by_day($period);

    // накопительный итог: пользователи до начала периода плюс регистрации по дням
    $total = (int)($before['users_count'] ?? 0);
    $cumulative = [];
    foreach ($daily['new_users'] as $count) {
        $total += (int)$count;
        $cumulative[] = $total;
    }

    return ['dates' => $daily['dates'], 'labels' => $daily['labels'], 'new_users' => $daily['new_users'], 'total' => $cumulative];
}

function stats_reviews_summary(array $period): array
{
    $demoFilter = stats_users_demo_filter($period);
    $params = stats_bounds($period);

    $summary = spice_fetch_one(
        "
        SELECT
            COUNT(*) AS reviews_count,
            COALESCE(AVG(CASE WHEN r.status = 'published' THEN r.rating END), 0) AS avg_rating,
            COALESCE(SUM(r.status = 'new'), 0) AS pending
        FROM reviews r
        JOIN users u ON u.user_id = r.user_id
        WHERE r.created_at >= :start AND r.created_at < :end $demoFilter
        ",
        $params
    ) ?? [];

    $ratings = array_column(spice_fetch_all(
        "
        SELECT r.rating, COUNT(*) AS reviews_count
        FROM reviews r
        JOIN users u ON u.user_id = r.user_id
        WHERE r.created_at >= :start AND r.created_at < :end $demoFilter
        GROUP BY r.rating
        ",
        $params
    ), 'reviews_count', 'rating');

    $distribution = [];
    for ($rating = 1; $rating <= 5; $rating++) {
        $distribution[] = (int)($ratings[$rating] ?? 0);
    }

    return [
        'count' => (int)($summary['reviews_count'] ?? 0),
        'avg_rating' => round((float)($summary['avg_rating'] ?? 0), 2),
        'pending' => (int)($summary['pending'] ?? 0),
        'distribution' => $distribution,
    ];
}

function stats_search_queries(array $period, int $limit = 10, bool $onlyEmpty = false): array
{
    $where = 's.created_at >= :start AND s.created_at < :end' . ($period['include_demo'] ? '' : ' AND s.is_demo = 0');

    return spice_fetch_all(
        "
        SELECT s.query_text, COUNT(*) AS searches, MAX(s.results_count) AS results
        FROM search_queries s
        WHERE $where
        GROUP BY s.query_text
        " . ($onlyEmpty ? 'HAVING MAX(s.results_count) = 0' : '') . '
        ORDER BY searches DESC
        LIMIT ' . max(1, $limit),
        stats_bounds($period)
    );
}

function stats_low_stock(int $threshold = 10): array
{
    return spice_fetch_all(
        '
        SELECT p.name, c.name AS category_name, p.stock_quantity, p.price
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        WHERE p.is_active = 1 AND p.stock_quantity < :threshold
        ORDER BY p.stock_quantity, p.name
        ',
        ['threshold' => $threshold]
    );
}

// Изменение к предыдущему периоду: для количеств — в процентах, для долей — в процентных пунктах
function stats_delta(float $current, float $previous, bool $inPoints = false): ?float
{
    if ($inPoints) {
        return round($current - $previous, 1);
    }

    if ($previous == 0.0) {
        return null;
    }

    return round(($current - $previous) / $previous * 100, 1);
}

// Склонение после числа: 1 проверка, 2 проверки, 5 проверок
function stats_plural(int $count, string $one, string $few, string $many): string
{
    $mod100 = $count % 100;
    $mod10 = $count % 10;

    if ($mod100 >= 11 && $mod100 <= 14) {
        return $many;
    }

    return match (true) {
        $mod10 === 1 => $one,
        $mod10 >= 2 && $mod10 <= 4 => $few,
        default => $many,
    };
}

function stats_format_number(float $value, int $decimals = 0): string
{
    return number_format($value, $decimals, ',', "\u{00A0}");
}

function stats_format_money(float $value): string
{
    return stats_format_number($value) . "\u{00A0}₽";
}

function stats_format_duration(int $seconds): string
{
    $minutes = intdiv($seconds, 60);
    $rest = $seconds % 60;

    return $minutes > 0 ? "{$minutes}\u{00A0}мин {$rest}\u{00A0}с" : "{$rest}\u{00A0}с";
}

function stats_format_date(string $date): string
{
    return (new DateTimeImmutable($date))->format('d.m.Y');
}

function stats_json(mixed $data): string
{
    return json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?: '{}';
}

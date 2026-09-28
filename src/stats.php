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

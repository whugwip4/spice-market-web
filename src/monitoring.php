<?php

declare(strict_types=1);

require_once __DIR__ . '/stats.php';

const MONITOR_SLOW_RESPONSE_MS = 500;
const MONITOR_FAILED_LOGINS_LIMIT = 5;
const MONITOR_LOW_STOCK = 10;
const MONITOR_PENDING_CHECKS = __DIR__ . '/../storage/monitor/pending_checks.jsonl';

function monitor_demo_filter(array $period, string $alias): string
{
    return $period['include_demo'] ? '' : " AND $alias.is_demo = 0";
}

function monitor_db_status(): array
{
    $startedAt = microtime(true);
    $pdo = spice_pdo();

    if (!$pdo) {
        return ['ok' => false, 'error' => spice_db_error() ?? 'Нет подключения к MySQL'];
    }

    $pdo->query('SELECT 1')->fetchColumn();
    $pingMs = (microtime(true) - $startedAt) * 1000;

    $status = array_column(
        spice_fetch_all("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime', 'Threads_connected', 'Questions', 'Slow_queries')"),
        'Value',
        'Variable_name'
    );
    $size = spice_fetch_one(
        'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
    );

    return [
        'ok' => true,
        'ping_ms' => round($pingMs, 2),
        'version' => (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        'uptime' => (int)($status['Uptime'] ?? 0),
        'connections' => (int)($status['Threads_connected'] ?? 0),
        'queries' => (int)($status['Questions'] ?? 0),
        'slow_queries' => (int)($status['Slow_queries'] ?? 0),
        'size_mb' => round((int)($size['bytes'] ?? 0) / 1024 / 1024, 2),
    ];
}

function monitor_server_info(): array
{
    $root = dirname(__DIR__);
    $free = @disk_free_space($root) ?: 0;
    $total = @disk_total_space($root) ?: 0;

    return [
        'php' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'os' => php_uname('s') . ' ' . php_uname('r'),
        'memory_limit' => (string)ini_get('memory_limit'),
        'disk_free_gb' => round($free / 1024 ** 3, 1),
        'disk_total_gb' => round($total / 1024 ** 3, 1),
        'disk_free_percent' => $total > 0 ? round($free / $total * 100, 1) : 0.0,
    ];
}

function monitor_table_sizes(): array
{
    return array_map(static fn(array $row): array => [
        'table' => (string)$row['TABLE_NAME'],
        'rows' => (int)$row['TABLE_ROWS'],
        'size_kb' => round((int)$row['bytes'] / 1024, 1),
    ], spice_fetch_all(
        "
        SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH + INDEX_LENGTH AS bytes
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY bytes DESC
        "
    ));
}

// Время генерации страниц по дням: среднее и 95-й перцентиль (95 % страниц открылись быстрее этого значения)
function monitor_response_by_day(array $period): array
{
    $where = "v.created_at >= :start AND v.created_at < :end AND v.is_bot = 0" . monitor_demo_filter($period, 'v');
    $params = stats_bounds($period);

    $avg = spice_fetch_all(
        "SELECT DATE(v.created_at) AS day, AVG(v.response_time_ms) AS avg_ms, COUNT(*) AS hits FROM site_visits v WHERE $where GROUP BY DATE(v.created_at)",
        $params
    );
    $p95 = spice_fetch_all(
        "
        SELECT DISTINCT DATE(v.created_at) AS day,
            PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY v.response_time_ms) OVER (PARTITION BY DATE(v.created_at)) AS p95_ms
        FROM site_visits v
        WHERE $where
        ",
        $params
    );

    $series = stats_fill_days($period, $avg, ['avg_ms', 'hits']);
    $series['p95_ms'] = stats_fill_days($period, $p95, ['p95_ms'])['p95_ms'];

    return $series;
}

function monitor_response_summary(array $period): array
{
    $row = spice_fetch_one(
        "
        SELECT AVG(v.response_time_ms) AS avg_ms, COALESCE(SUM(v.response_time_ms > :slow), 0) AS slow_hits, COUNT(*) AS hits
        FROM site_visits v
        WHERE v.created_at >= :start AND v.created_at < :end AND v.is_bot = 0" . monitor_demo_filter($period, 'v'),
        stats_bounds($period) + ['slow' => MONITOR_SLOW_RESPONSE_MS]
    ) ?? [];

    return [
        'avg_ms' => round((float)($row['avg_ms'] ?? 0), 1),
        'slow_hits' => (int)($row['slow_hits'] ?? 0),
        'hits' => (int)($row['hits'] ?? 0),
    ];
}

function monitor_slow_pages(array $period, int $limit = 8): array
{
    return spice_fetch_all(
        "
        SELECT v.page, COUNT(*) AS hits, AVG(v.response_time_ms) AS avg_ms, MAX(v.response_time_ms) AS max_ms
        FROM site_visits v
        WHERE v.created_at >= :start AND v.created_at < :end AND v.is_bot = 0" . monitor_demo_filter($period, 'v') . '
        GROUP BY v.page
        HAVING COUNT(*) >= 5
        ORDER BY avg_ms DESC
        LIMIT ' . max(1, $limit),
        stats_bounds($period)
    );
}

// Ответы с ошибками по дням: 404 – страница не найдена, 403 – нет доступа, 500 – ошибка сервера
function monitor_http_errors_by_day(array $period): array
{
    $rows = spice_fetch_all(
        "
        SELECT DATE(v.created_at) AS day,
            SUM(v.http_status = 404) AS s404,
            SUM(v.http_status = 403) AS s403,
            SUM(v.http_status >= 500) AS s500
        FROM site_visits v
        WHERE v.created_at >= :start AND v.created_at < :end AND v.http_status >= 400" . monitor_demo_filter($period, 'v') . '
        GROUP BY DATE(v.created_at)
        ',
        stats_bounds($period)
    );

    return stats_fill_days($period, $rows, ['s404', 's403', 's500']);
}

function monitor_errors_by_day(array $period): array
{
    $rows = spice_fetch_all(
        "
        SELECT DATE(e.created_at) AS day,
            SUM(e.level = 'notice') AS notices,
            SUM(e.level = 'warning') AS warnings,
            SUM(e.level IN ('error', 'exception', 'fatal')) AS errors
        FROM app_errors e
        WHERE e.created_at >= :start AND e.created_at < :end" . monitor_demo_filter($period, 'e') . '
        GROUP BY DATE(e.created_at)
        ',
        stats_bounds($period)
    );

    return stats_fill_days($period, $rows, ['notices', 'warnings', 'errors']);
}

function monitor_recent_errors(array $period, int $limit = 8): array
{
    return spice_fetch_all(
        'SELECT e.level, e.message, e.file, e.line, e.page, e.created_at, e.is_demo
         FROM app_errors e
         WHERE e.created_at >= :start AND e.created_at < :end' . monitor_demo_filter($period, 'e') . '
         ORDER BY e.error_id DESC
         LIMIT ' . max(1, $limit),
        stats_bounds($period)
    );
}

function monitor_auth_by_day(array $period): array
{
    $rows = spice_fetch_all(
        "
        SELECT DATE(a.created_at) AS day,
            SUM(a.event_type = 'login_success') AS success,
            SUM(a.event_type = 'login_failed') AS failed
        FROM auth_events a
        WHERE a.created_at >= :start AND a.created_at < :end" . monitor_demo_filter($period, 'a') . '
        GROUP BY DATE(a.created_at)
        ',
        stats_bounds($period)
    );

    return stats_fill_days($period, $rows, ['success', 'failed']);
}

function monitor_auth_summary(array $period): array
{
    $counts = array_column(spice_fetch_all(
        'SELECT a.event_type, COUNT(*) AS events FROM auth_events a
         WHERE a.created_at >= :start AND a.created_at < :end' . monitor_demo_filter($period, 'a') . '
         GROUP BY a.event_type',
        stats_bounds($period)
    ), 'events', 'event_type');

    return [
        'login_success' => (int)($counts['login_success'] ?? 0),
        'login_failed' => (int)($counts['login_failed'] ?? 0),
        'register' => (int)($counts['register'] ?? 0),
        'admin_denied' => (int)($counts['admin_denied'] ?? 0),
    ];
}

// Подбор пароля: 5 и больше неудачных входов с одного адреса в одном 10-минутном интервале
function monitor_suspicious_logins(array $period): array
{
    return spice_fetch_all(
        "
        SELECT a.ip_hash, COUNT(*) AS attempts, MIN(a.created_at) AS first_at, MAX(a.created_at) AS last_at,
            GROUP_CONCAT(DISTINCT a.email_masked SEPARATOR ', ') AS emails
        FROM auth_events a
        WHERE a.event_type = 'login_failed' AND a.created_at >= :start AND a.created_at < :end" . monitor_demo_filter($period, 'a') . '
        GROUP BY a.ip_hash, FLOOR(UNIX_TIMESTAMP(a.created_at) / 600)
        HAVING COUNT(*) >= ' . MONITOR_FAILED_LOGINS_LIMIT . '
        ORDER BY first_at DESC
        LIMIT 10
        ',
        stats_bounds($period)
    );
}

function monitor_api_by_day(array $period): array
{
    $rows = spice_fetch_all(
        "
        SELECT DATE(r.created_at) AS day,
            COUNT(*) AS requests,
            AVG(CASE WHEN r.is_success = 1 THEN r.response_time_ms END) AS avg_ms,
            AVG(r.is_success) * 100 AS success_rate
        FROM api_requests r
        WHERE r.created_at >= :start AND r.created_at < :end" . monitor_demo_filter($period, 'r') . '
        GROUP BY DATE(r.created_at)
        ',
        stats_bounds($period)
    );

    return monitor_null_empty_days(stats_fill_days($period, $rows, ['requests', 'avg_ms', 'success_rate']), 'requests', ['avg_ms', 'success_rate']);
}

function monitor_api_summary(array $period): array
{
    $row = spice_fetch_one(
        "
        SELECT COUNT(*) AS requests,
            COALESCE(AVG(r.is_success) * 100, 0) AS success_rate,
            COALESCE(AVG(CASE WHEN r.is_success = 1 THEN r.response_time_ms END), 0) AS avg_ms,
            COALESCE(SUM(r.used_fallback), 0) AS fallbacks
        FROM api_requests r
        WHERE r.created_at >= :start AND r.created_at < :end" . monitor_demo_filter($period, 'r'),
        stats_bounds($period)
    ) ?? [];

    return [
        'requests' => (int)($row['requests'] ?? 0),
        'success_rate' => round((float)($row['success_rate'] ?? 0), 1),
        'avg_ms' => round((float)($row['avg_ms'] ?? 0), 1),
        'fallbacks' => (int)($row['fallbacks'] ?? 0),
    ];
}

// Доступность по проверкам: успешные проверки / все проверки за день × 100 %
function monitor_uptime_by_day(array $period): array
{
    $rows = spice_fetch_all(
        "
        SELECT DATE(h.created_at) AS day,
            COUNT(*) AS checks,
            AVG(h.site_ok) * 100 AS site_uptime,
            AVG(h.db_ok) * 100 AS db_uptime,
            AVG(h.api_ok) * 100 AS api_uptime
        FROM health_checks h
        WHERE h.created_at >= :start AND h.created_at < :end" . monitor_demo_filter($period, 'h') . '
        GROUP BY DATE(h.created_at)
        ',
        stats_bounds($period)
    );

    return monitor_null_empty_days(
        stats_fill_days($period, $rows, ['checks', 'site_uptime', 'db_uptime', 'api_uptime']),
        'checks',
        ['site_uptime', 'db_uptime', 'api_uptime']
    );
}

// В дни без проверок и запросов процент не считается: значение null вместо ложного нуля
function monitor_null_empty_days(array $series, string $countField, array $fields): array
{
    foreach ($series[$countField] as $index => $count) {
        if ((int)$count === 0) {
            foreach ($fields as $field) {
                $series[$field][$index] = null;
            }
        }
    }

    return $series;
}

function monitor_uptime_summary(array $period): array
{
    $row = spice_fetch_one(
        "
        SELECT COUNT(*) AS checks,
            COALESCE(AVG(h.site_ok) * 100, 0) AS site_uptime,
            COALESCE(AVG(h.db_ok) * 100, 0) AS db_uptime,
            COALESCE(AVG(h.api_ok) * 100, 0) AS api_uptime,
            COALESCE(SUM(h.db_ok = 0), 0) AS db_failures,
            COALESCE(SUM(h.api_ok = 0), 0) AS api_failures
        FROM health_checks h
        WHERE h.created_at >= :start AND h.created_at < :end" . monitor_demo_filter($period, 'h'),
        stats_bounds($period)
    ) ?? [];

    return [
        'checks' => (int)($row['checks'] ?? 0),
        'site_uptime' => round((float)($row['site_uptime'] ?? 0), 2),
        'db_uptime' => round((float)($row['db_uptime'] ?? 0), 2),
        'api_uptime' => round((float)($row['api_uptime'] ?? 0), 2),
        'db_failures' => (int)($row['db_failures'] ?? 0),
        'api_failures' => (int)($row['api_failures'] ?? 0),
    ];
}

function monitor_last_check(): ?array
{
    return spice_fetch_one('SELECT * FROM health_checks ORDER BY created_at DESC, check_id DESC LIMIT 1');
}

// Сейчас на сайте: уникальные посетители за последние 5 минут
function monitor_online_now(): array
{
    $row = spice_fetch_one(
        "
        SELECT
            COUNT(DISTINCT CASE WHEN v.created_at >= NOW() - INTERVAL 5 MINUTE THEN v.visitor_id END) AS online,
            COUNT(*) AS views_30m
        FROM site_visits v
        WHERE v.created_at >= NOW() - INTERVAL 30 MINUTE AND v.is_bot = 0 AND v.is_admin_area = 0
        "
    ) ?? [];

    return [
        'online' => (int)($row['online'] ?? 0),
        'views_30m' => (int)($row['views_30m'] ?? 0),
        'checked_at' => date('H:i:s'),
    ];
}

/**
 * Предупреждения для администратора. Уровни: critical – нужно срочно разобраться,
 * serious – заметный сбой, warning – стоит обратить внимание.
 */
function monitor_alerts(array $period): array
{
    $alerts = [];

    foreach (monitor_suspicious_logins($period) as $row) {
        $alerts[] = [
            'level' => 'critical',
            'title' => 'Похоже на подбор пароля',
            'text' => sprintf(
                '%d %s с одного адреса с %s по %s (%s).',
                $row['attempts'],
                stats_plural((int)$row['attempts'], 'неудачный вход', 'неудачных входа', 'неудачных входов'),
                date('d.m.Y H:i', strtotime((string)$row['first_at'])),
                date('H:i', strtotime((string)$row['last_at'])),
                (string)$row['emails']
            ),
        ];
    }

    $uptime = monitor_uptime_summary($period);
    if ($uptime['db_failures'] > 0) {
        $alerts[] = [
            'level' => 'serious',
            'title' => 'База данных была недоступна',
            'text' => sprintf(
                '%d %s из %d без подключения к MySQL, доступность БД %s %%.',
                $uptime['db_failures'],
                stats_plural($uptime['db_failures'], 'проверка', 'проверки', 'проверок'),
                $uptime['checks'],
                stats_format_number($uptime['db_uptime'], 2)
            ),
        ];
    }

    $api = monitor_api_summary($period);
    if ($api['requests'] > 0 && $api['success_rate'] < 97) {
        $alerts[] = [
            'level' => 'warning',
            'title' => 'Сбои внешнего API',
            'text' => sprintf(
                'Успешно только %s %% запросов к TheMealDB, резервный рецепт показан %d %s.',
                stats_format_number($api['success_rate'], 1),
                $api['fallbacks'],
                stats_plural($api['fallbacks'], 'раз', 'раза', 'раз')
            ),
        ];
    }

    $serverErrors = spice_fetch_one(
        'SELECT COUNT(*) AS hits FROM site_visits v WHERE v.http_status >= 500 AND v.created_at >= :start AND v.created_at < :end' . monitor_demo_filter($period, 'v'),
        stats_bounds($period)
    );
    if ((int)($serverErrors['hits'] ?? 0) > 0) {
        $alerts[] = [
            'level' => 'serious',
            'title' => 'Ошибки сервера 500',
            'text' => sprintf(
                'Ошибкой сервера завершились %d %s, подробности в журнале ошибок.',
                (int)$serverErrors['hits'],
                stats_plural((int)$serverErrors['hits'], 'страница', 'страницы', 'страниц')
            ),
        ];
    }

    $lowStock = stats_low_stock(MONITOR_LOW_STOCK);
    if ($lowStock) {
        $empty = array_filter($lowStock, static fn(array $row): bool => (int)$row['stock_quantity'] === 0);
        $alerts[] = [
            'level' => 'warning',
            'title' => 'Заканчиваются товары',
            'text' => sprintf(
                'Меньше %d шт. на складе у %d %s, нет в наличии: %d (%s).',
                MONITOR_LOW_STOCK,
                count($lowStock),
                stats_plural(count($lowStock), 'товара', 'товаров', 'товаров'),
                count($empty),
                implode(', ', array_slice(array_column($lowStock, 'name'), 0, 4))
            ),
        ];
    }

    return $alerts;
}

/**
 * Проверка доступности: сайт (HTTP-запрос к главной), база данных (подключение и SELECT 1),
 * API TheMealDB и свободное место на диске. Результат пишется в health_checks.
 * Если база недоступна, результат сохраняется в файл и переносится в базу при следующей удачной проверке.
 */
function monitor_run_health_check(?string $siteUrl): array
{
    $check = [
        'site_ok' => 1,
        'site_status' => 200,
        'site_response_ms' => null,
        'db_ok' => 0,
        'db_response_ms' => null,
        'api_ok' => 0,
        'api_response_ms' => null,
        'disk_free_mb' => (int)round((@disk_free_space(dirname(__DIR__)) ?: 0) / 1024 / 1024),
        'created_at' => date('Y-m-d H:i:s'),
    ];

    if ($siteUrl !== null) {
        [$status, $ms] = monitor_http_probe($siteUrl, 5);
        $check['site_ok'] = $status >= 200 && $status < 400 ? 1 : 0;
        $check['site_status'] = $status ?: null;
        $check['site_response_ms'] = round($ms, 2);
    } else {
        // проверка запущена со страницы мониторинга: сайт отвечает, раз эта страница открылась
        $check['site_response_ms'] = stats_request_time_ms();
    }

    $config = spice_config()['db'];
    $startedAt = microtime(true);
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']),
            $config['user'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
        );
        $pdo->query('SELECT 1')->fetchColumn();
        $check['db_ok'] = 1;
        $check['db_response_ms'] = round((microtime(true) - $startedAt) * 1000, 2);
    } catch (Throwable) {
        $pdo = null;
    }

    [$apiStatus, $apiMs] = monitor_http_probe('https://www.themealdb.com/api/json/v1/1/lookup.php?i=52771', 3);
    $check['api_ok'] = $apiStatus === 200 ? 1 : 0;
    $check['api_response_ms'] = round($apiMs, 2);

    if (!$pdo) {
        monitor_queue_check($check);
        return $check;
    }

    foreach (array_merge(monitor_take_queued_checks(), [$check]) as $row) {
        $pdo->prepare(
            'INSERT INTO health_checks (site_ok, site_status, site_response_ms, db_ok, db_response_ms, api_ok, api_response_ms, disk_free_mb, created_at)
             VALUES (:site_ok, :site_status, :site_response_ms, :db_ok, :db_response_ms, :api_ok, :api_response_ms, :disk_free_mb, :created_at)'
        )->execute($row);
    }

    return $check;
}

function monitor_http_probe(string $url, int $timeout): array
{
    if (!function_exists('curl_init')) {
        return [0, 0.0];
    }

    $startedAt = microtime(true);
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'SpiceMarket-HealthCheck/1.0 (monitor)',
    ]);
    curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return [$status, (microtime(true) - $startedAt) * 1000];
}

function monitor_queue_check(array $check): void
{
    $directory = dirname(MONITOR_PENDING_CHECKS);
    if (!is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }

    @file_put_contents(MONITOR_PENDING_CHECKS, json_encode($check) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function monitor_take_queued_checks(): array
{
    if (!is_file(MONITOR_PENDING_CHECKS)) {
        return [];
    }

    $lines = file(MONITOR_PENDING_CHECKS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    @unlink(MONITOR_PENDING_CHECKS);

    return array_values(array_filter(array_map(static fn(string $line): ?array => json_decode($line, true), $lines)));
}

function monitor_format_uptime(int $seconds): string
{
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    return $days > 0 ? "{$days}\u{00A0}дн. {$hours}\u{00A0}ч" : "{$hours}\u{00A0}ч {$minutes}\u{00A0}мин";
}

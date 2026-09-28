<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Посетитель — браузер с cookie sm_vid (1 год). Визит — серия просмотров,
// между которыми проходит не больше 30 минут (cookie sm_sid продлевается при каждом просмотре).
const STATS_VISITOR_COOKIE = 'sm_vid';
const STATS_SESSION_COOKIE = 'sm_sid';
const STATS_VISITOR_LIFETIME = 60 * 60 * 24 * 365;
const STATS_SESSION_TIMEOUT = 60 * 30;

function stats_tracker_state(?array $update = null): array
{
    static $state = [
        'started' => false,
        'page_rendered' => false,
        'visitor_id' => '',
        'session_id' => '',
        'new_session' => false,
        'product_id' => null,
    ];

    if ($update !== null) {
        $state = array_merge($state, $update);
    }

    return $state;
}

function stats_tracker_boot(): void
{
    if (PHP_SAPI === 'cli' || stats_tracker_state()['started']) {
        return;
    }

    $visitorId = stats_cookie_id(STATS_VISITOR_COOKIE);
    $sessionId = stats_cookie_id(STATS_SESSION_COOKIE);
    $newSession = $sessionId === '';

    if ($visitorId === '') {
        $visitorId = bin2hex(random_bytes(16));
    }

    if ($newSession) {
        $sessionId = bin2hex(random_bytes(16));
    }

    if (!headers_sent()) {
        $cookie = ['path' => '/', 'httponly' => true, 'samesite' => 'Lax'];
        setcookie(STATS_VISITOR_COOKIE, $visitorId, $cookie + ['expires' => time() + STATS_VISITOR_LIFETIME]);
        setcookie(STATS_SESSION_COOKIE, $sessionId, $cookie + ['expires' => time() + STATS_SESSION_TIMEOUT]);
    }

    stats_tracker_state([
        'started' => true,
        'visitor_id' => $visitorId,
        'session_id' => $sessionId,
        'new_session' => $newSession,
    ]);

    set_error_handler('stats_handle_error');
    set_exception_handler('stats_handle_exception');
    register_shutdown_function('stats_tracker_shutdown');
}

// Вызывается из page_start(): просмотр засчитывается только для отрисованных страниц, редиректы не учитываются.
function stats_tracker_page(): void
{
    stats_tracker_state(['page_rendered' => true]);
}

function stats_tracker_context(array $context): void
{
    stats_tracker_state(array_intersect_key($context, ['product_id' => true]));
}

function stats_tracker_shutdown(): void
{
    $state = stats_tracker_state();
    $lastError = error_get_last();
    $isFatal = $lastError !== null
        && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);

    if ($isFatal) {
        stats_log_error('fatal', (string)$lastError['message'], (string)$lastError['file'], (int)$lastError['line']);
    }

    if (!$state['page_rendered'] || !$state['started']) {
        return;
    }

    try {
        $queries = spice_db_query_count();
        $memoryKb = (int)round(memory_get_peak_usage(true) / 1024);
        $client = stats_parse_user_agent((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        [$referrerType, $referrerHost] = stats_classify_referrer((string)($_SERVER['HTTP_REFERER'] ?? ''));
        $page = stats_normalize_page((string)($_SERVER['REQUEST_URI'] ?? '/'));
        $queryString = (string)($_SERVER['QUERY_STRING'] ?? '');
        $userId = (int)($_SESSION['spice_user_id'] ?? 0);

        // визит, начатый со своей же страницы (вкладка простояла больше 30 минут), считается прямым заходом
        if ($state['new_session'] && $referrerType === 'internal') {
            $referrerType = 'direct';
            $referrerHost = null;
        }

        spice_execute(
            '
            INSERT INTO site_visits (
                visitor_id, session_id, user_id, page, query_string, product_id, http_status,
                referrer_type, referrer_host, device_type, browser, os, is_bot, is_admin_area,
                ip_hash, response_time_ms, memory_kb, db_queries
            )
            VALUES (
                :visitor_id, :session_id, :user_id, :page, :query_string, :product_id, :http_status,
                :referrer_type, :referrer_host, :device_type, :browser, :os, :is_bot, :is_admin_area,
                :ip_hash, :response_time_ms, :memory_kb, :db_queries
            )
            ',
            [
                'visitor_id' => $state['visitor_id'],
                'session_id' => $state['session_id'],
                'user_id' => $userId > 0 ? $userId : null,
                'page' => $page,
                'query_string' => $queryString !== '' ? mb_substr($queryString, 0, 255) : null,
                'product_id' => $state['product_id'],
                'http_status' => $isFatal ? 500 : (int)http_response_code(),
                'referrer_type' => $referrerType,
                'referrer_host' => $referrerHost,
                'device_type' => $client['device'],
                'browser' => $client['browser'],
                'os' => $client['os'],
                'is_bot' => $client['is_bot'] ? 1 : 0,
                'is_admin_area' => str_starts_with($page, '/admin') ? 1 : 0,
                'ip_hash' => stats_ip_hash(),
                'response_time_ms' => stats_request_time_ms(),
                'memory_kb' => $memoryKb,
                'db_queries' => min($queries, 65535),
            ]
        );
    } catch (Throwable $error) {
        stats_log_to_file('site_visits: ' . $error->getMessage());
    }
}

function stats_cookie_id(string $name): string
{
    $value = (string)($_COOKIE[$name] ?? '');

    return preg_match('/^[a-f0-9]{32}$/', $value) ? $value : '';
}

function stats_normalize_page(string $uri): string
{
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
    $path = preg_replace('#/index\.php$#', '/', $path) ?? $path;

    return mb_substr($path, 0, 120);
}

function stats_request_time_ms(): float
{
    $start = (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

    return round((microtime(true) - $start) * 1000, 2);
}

// IP-адрес не хранится: в журнал пишется только SHA-256 от «соль + IP»
function stats_ip_hash(): string
{
    $salt = (string)(spice_config()['stats']['salt'] ?? 'spice-market');

    return hash('sha256', $salt . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? 'cli'));
}

function stats_mask_email(string $email): string
{
    $email = mb_strtolower(trim($email));
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $masked = mb_substr($name, 0, 2) . '***';

    return mb_substr($domain !== '' ? $masked . '@' . $domain : $masked, 0, 120);
}

function stats_parse_user_agent(string $userAgent): array
{
    $isBot = $userAgent === ''
        || (bool)preg_match('/bot|crawl|spider|slurp|curl|wget|python|java\/|httpclient|headless|phantom|lighthouse|monitor|preview|scanner/i', $userAgent);

    $os = match (true) {
        str_contains($userAgent, 'Windows') => 'Windows',
        (bool)preg_match('/iPhone|iPod/', $userAgent) => 'iOS',
        str_contains($userAgent, 'iPad') => 'iPadOS',
        str_contains($userAgent, 'Android') => 'Android',
        str_contains($userAgent, 'Macintosh') => 'macOS',
        str_contains($userAgent, 'Linux') => 'Linux',
        default => 'Другая',
    };

    $browser = match (true) {
        str_contains($userAgent, 'YaBrowser') => 'Яндекс Браузер',
        str_contains($userAgent, 'Edg/') => 'Edge',
        str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
        str_contains($userAgent, 'SamsungBrowser') => 'Samsung Internet',
        str_contains($userAgent, 'Firefox/') || str_contains($userAgent, 'FxiOS') => 'Firefox',
        str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS') => 'Chrome',
        str_contains($userAgent, 'Safari/') => 'Safari',
        default => 'Другой',
    };

    $device = match (true) {
        (bool)preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $userAgent) => 'tablet',
        (bool)preg_match('/Mobile|iPhone|Android/i', $userAgent) => 'mobile',
        default => 'desktop',
    };

    return ['device' => $device, 'browser' => $browser, 'os' => $os, 'is_bot' => $isBot];
}

function stats_classify_referrer(string $referrer): array
{
    $host = strtolower((string)parse_url($referrer, PHP_URL_HOST));

    if ($referrer === '' || $host === '') {
        return ['direct', null];
    }

    $ownHost = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    if ($host === $ownHost) {
        return ['internal', $host];
    }

    $host = mb_substr((string)preg_replace('/^www\./', '', $host), 0, 120);
    $groups = [
        'search' => ['yandex.', 'ya.ru', 'google.', 'bing.com', 'go.mail.ru', 'duckduckgo.com', 'rambler.ru'],
        'social' => ['vk.com', 'ok.ru', 't.me', 'telegram.', 'dzen.ru', 'youtube.com', 'pinterest.', 'instagram.com', 'facebook.com'],
    ];

    foreach ($groups as $type => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($host, $needle)) {
                return [$type, $host];
            }
        }
    }

    return ['external', $host];
}

function stats_log_auth_event(string $type, ?int $userId, string $email = ''): void
{
    try {
        spice_execute(
            '
            INSERT INTO auth_events (event_type, user_id, email_masked, ip_hash, user_agent)
            VALUES (:event_type, :user_id, :email_masked, :ip_hash, :user_agent)
            ',
            [
                'event_type' => $type,
                'user_id' => $userId ?: null,
                'email_masked' => $email !== '' ? stats_mask_email($email) : null,
                'ip_hash' => stats_ip_hash(),
                'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]
        );
    } catch (Throwable $error) {
        stats_log_to_file('auth_events: ' . $error->getMessage());
    }
}

function stats_log_search(string $query, int $resultsCount, string $category = ''): void
{
    $query = mb_substr(mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', $query))), 0, 120);

    if ($query === '') {
        return;
    }

    try {
        $userId = (int)($_SESSION['spice_user_id'] ?? 0);
        spice_execute(
            '
            INSERT INTO search_queries (query_text, results_count, category_slug, visitor_id, user_id)
            VALUES (:query_text, :results_count, :category_slug, :visitor_id, :user_id)
            ',
            [
                'query_text' => $query,
                'results_count' => max(0, $resultsCount),
                'category_slug' => $category !== '' ? mb_substr($category, 0, 100) : null,
                'visitor_id' => stats_tracker_state()['visitor_id'] ?: null,
                'user_id' => $userId > 0 ? $userId : null,
            ]
        );
    } catch (Throwable $error) {
        stats_log_to_file('search_queries: ' . $error->getMessage());
    }
}

function stats_log_api_request(
    string $service,
    string $endpoint,
    ?int $httpStatus,
    float $responseTimeMs,
    bool $isSuccess,
    bool $usedFallback,
    string $errorMessage = ''
): void {
    try {
        spice_execute(
            '
            INSERT INTO api_requests (
                service, endpoint, http_status, response_time_ms, is_success, used_fallback, error_message
            )
            VALUES (
                :service, :endpoint, :http_status, :response_time_ms, :is_success, :used_fallback, :error_message
            )
            ',
            [
                'service' => $service,
                'endpoint' => mb_substr($endpoint, 0, 255),
                'http_status' => $httpStatus ?: null,
                'response_time_ms' => round($responseTimeMs, 2),
                'is_success' => $isSuccess ? 1 : 0,
                'used_fallback' => $usedFallback ? 1 : 0,
                'error_message' => $errorMessage !== '' ? mb_substr($errorMessage, 0, 255) : null,
            ]
        );
    } catch (Throwable $error) {
        stats_log_to_file('api_requests: ' . $error->getMessage());
    }
}

function stats_handle_error(int $level, string $message, string $file = '', int $line = 0): bool
{
    // ошибки, подавленные оператором @, не журналируются
    if (!(error_reporting() & $level)) {
        return false;
    }

    $type = match ($level) {
        E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED => 'notice',
        E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'warning',
        default => 'error',
    };

    stats_log_error($type, $message, $file, $line);

    // стандартный обработчик PHP продолжает работу как обычно
    return false;
}

function stats_handle_exception(Throwable $exception): void
{
    stats_log_error(
        'exception',
        get_class($exception) . ': ' . $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );

    // запрос, завершившийся исключением, тоже попадает в журнал просмотров с кодом 500
    stats_tracker_state(['page_rendered' => true]);

    if (!headers_sent()) {
        http_response_code(500);
    }

    echo '<p style="font-family: sans-serif; padding: 24px;">Внутренняя ошибка сервера. Попробуйте обновить страницу позже.</p>';
}

function stats_log_error(string $level, string $message, string $file = '', int $line = 0): void
{
    static $busy = false;

    if ($busy) {
        return;
    }

    $busy = true;
    $root = dirname(__DIR__);
    $file = str_replace('\\', '/', str_starts_with($file, $root) ? substr($file, strlen($root)) : $file);
    $page = PHP_SAPI === 'cli' ? 'cli' : (string)($_SERVER['REQUEST_URI'] ?? '');

    try {
        $saved = spice_db_available() && spice_execute(
            '
            INSERT INTO app_errors (level, message, file, line, page)
            VALUES (:level, :message, :file, :line, :page)
            ',
            [
                'level' => $level,
                'message' => mb_substr($message, 0, 1000),
                'file' => $file !== '' ? mb_substr($file, 0, 255) : null,
                'line' => $line > 0 ? $line : null,
                'page' => $page !== '' ? mb_substr($page, 0, 255) : null,
            ]
        );

        if (!$saved) {
            stats_log_to_file("[$level] $message ($file:$line) $page");
        }
    } catch (Throwable $error) {
        stats_log_to_file("[$level] $message ($file:$line) $page; app_errors: " . $error->getMessage());
    } finally {
        $busy = false;
    }
}

// Резервный журнал на случай, когда база данных недоступна
function stats_log_to_file(string $line): void
{
    $directory = dirname(__DIR__) . '/storage/logs';

    if (!is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }

    @file_put_contents(
        $directory . '/monitoring.log',
        date('Y-m-d H:i:s') . ' ' . str_replace(["\r", "\n"], ' ', $line) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

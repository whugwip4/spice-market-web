<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/layout.php';
require_once __DIR__ . '/../../../src/monitoring.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Нужен вход под администратором'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = monitor_db_status();

echo json_encode(monitor_online_now() + [
    'db_ok' => $db['ok'],
    'db_ping_ms' => $db['ping_ms'] ?? null,
], JSON_UNESCAPED_UNICODE);

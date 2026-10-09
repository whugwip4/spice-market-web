<?php

declare(strict_types=1);

/*
 * Проверка доступности Spice Market для мониторинга.
 *
 *   php tools/health_check.php                                 проверить http://127.0.0.1:8081/
 *   php tools/health_check.php --url=http://localhost/spice/   другой адрес сайта
 *
 * Для постоянного мониторинга команду запускают по расписанию, например раз в 15 минут
 * через планировщик заданий Windows. Результат записывается в таблицу health_checks.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/monitoring.php';

$options = getopt('', ['url::']);
$url = (string)($options['url'] ?? 'http://127.0.0.1:8081/');

$check = monitor_run_health_check($url);
$mark = static fn(int $ok): string => $ok === 1 ? 'OK  ' : 'FAIL';

echo $check['created_at'] . PHP_EOL;
printf("  сайт         %s  код %s, %s мс\n", $mark($check['site_ok']), $check['site_status'] ?? '-', $check['site_response_ms'] ?? '-');
printf("  база данных  %s  %s мс\n", $mark($check['db_ok']), $check['db_response_ms'] ?? '-');
printf("  API          %s  %s мс\n", $mark($check['api_ok']), $check['api_response_ms'] ?? '-');
printf("  диск         свободно %s МБ\n", $check['disk_free_mb']);

if ($check['db_ok'] !== 1) {
    echo 'База данных недоступна: результат сохранен в storage/monitor и будет записан при следующей проверке.' . PHP_EOL;
}

exit($check['site_ok'] === 1 && $check['db_ok'] === 1 ? 0 : 1);

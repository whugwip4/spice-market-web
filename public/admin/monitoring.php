<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/stats_view.php';

require_admin();

$period = stats_period($_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'health_check') {
    if (csrf_valid($_POST['csrf'] ?? null)) {
        monitor_run_health_check(null);
        header('Location: ' . stats_page_url('/admin/monitoring.php', $period, ['checked' => 1]) . '#health');
    } else {
        header('Location: ' . stats_page_url('/admin/monitoring.php', $period));
    }
    exit;
}

$db = monitor_db_status();
$server = monitor_server_info();
$online = monitor_online_now();
$uptime = monitor_uptime_summary($period);
$response = monitor_response_summary($period);
$responseBefore = monitor_response_summary(['start' => $period['previous_start'], 'end' => $period['previous_end']] + $period);
$api = monitor_api_summary($period);
$auth = monitor_auth_summary($period);
$alerts = monitor_alerts($period);
$slowPages = monitor_slow_pages($period);
$recentErrors = monitor_recent_errors($period);
$suspicious = monitor_suspicious_logins($period);
$lastCheck = monitor_last_check();
$charts = monitor_charts($period);

$tiles = [
    ['label' => 'Сейчас на сайте', 'value' => stats_format_number($online['online']), 'live' => 'online', 'note' => 'посетителей за последние 5 минут'],
    ['label' => 'База данных', 'value' => $db['ok'] ? 'работает' : 'недоступна', 'status' => $db['ok'] ? 'good' : 'critical', 'note' => $db['ok'] ? 'ответ ' . stats_format_number($db['ping_ms'], 1) . ' мс, MariaDB ' . explode('-', $db['version'])[0] : 'нет подключения к MySQL'],
    ['label' => 'Доступность сайта', 'value' => $uptime['checks'] > 0 ? stats_format_number($uptime['site_uptime'], 2) . "\u{00A0}%" : '—', 'note' => 'проверок за период: ' . stats_format_number($uptime['checks'])],
    ['label' => 'Доступность базы данных', 'value' => $uptime['checks'] > 0 ? stats_format_number($uptime['db_uptime'], 2) . "\u{00A0}%" : '—', 'note' => 'неудачных проверок: ' . stats_format_number($uptime['db_failures'])],
    ['label' => 'Среднее время ответа', 'value' => stats_format_number($response['avg_ms'], 1) . "\u{00A0}мс", 'delta' => stats_delta($response['avg_ms'], $responseBefore['avg_ms']), 'good' => 'down'],
    ['label' => 'API TheMealDB', 'value' => $api['requests'] > 0 ? stats_format_number($api['success_rate'], 1) . "\u{00A0}%" : '—', 'note' => 'успешных запросов, в среднем ' . stats_format_number($api['avg_ms']) . ' мс'],
];

page_start('Мониторинг', 'admin');
?>

<section class="stats-page">
    <?php admin_nav('monitoring'); ?>

    <div class="admin-title-row">
        <div>
            <h1 class="page-title">Мониторинг сайта</h1>
            <p class="lead">Работает ли сайт, база данных и внешний API, как быстро открываются страницы и какие были ошибки.</p>
        </div>
    </div>

    <?php stats_render_filters('/admin/monitoring.php', $period); ?>

    <h2 class="stats-section-title">Состояние сейчас</h2>
    <?php stats_render_tiles($tiles); ?>
    <p class="stats-note">Число посетителей на сайте обновляется само каждые 30 секунд. Последнее обновление: <span data-live="checked_at"><?= e($online['checked_at']) ?></span>.</p>

    <h2 class="stats-section-title">Предупреждения</h2>
    <?php if (!$alerts): ?>
        <div class="alert-item status-good"><span class="alert-icon" aria-hidden="true">✓</span><div><strong>Норма</strong><p>За выбранный период проблем не найдено.</p></div></div>
    <?php else: ?>
        <div class="alert-list">
            <?php foreach ($alerts as $alert): ?>
                <?php [$icon, $levelLabel] = stats_alert_label($alert['level']); ?>
                <div class="alert-item status-<?= e($alert['level']) ?>">
                    <span class="alert-icon" aria-hidden="true"><?= e($icon) ?></span>
                    <div>
                        <strong><?= e($levelLabel) ?>: <?= e($alert['title']) ?></strong>
                        <p><?= e($alert['text']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="stats-section-title">Скорость и доступность</h2>
    <div class="chart-grid">
        <?php
        stats_chart_card('monitoring', $period, 'response_by_day', 'Время ответа сервера', 'Сколько миллисекунд PHP формирует страницу: среднее и 95-й перцентиль за день', 'chart-card-wide', 280);
        stats_chart_card('monitoring', $period, 'uptime_by_day', 'Доступность по дням', 'Доля успешных проверок сайта, базы данных и API за каждый день', 'chart-card-wide', 260);
        ?>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h3 class="stats-card-title">Самые медленные страницы</h3>
            <div class="table-wrap stats-table">
                <table>
                    <thead><tr><th>Страница</th><th>Открытий</th><th>Среднее, мс</th><th>Максимум, мс</th></tr></thead>
                    <tbody>
                        <?php foreach ($slowPages as $row): ?>
                            <tr>
                                <td><?= e(stats_page_label((string)$row['page'])) ?></td>
                                <td><?= e(stats_format_number((float)$row['hits'])) ?></td>
                                <td><?= e(stats_format_number((float)$row['avg_ms'], 1)) ?></td>
                                <td><?= e(stats_format_number((float)$row['max_ms'], 1)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="chart-card" id="health">
            <h3 class="stats-card-title">Последняя проверка доступности</h3>
            <?php if (isset($_GET['checked'])): ?>
                <div class="message success">Проверка выполнена, результат записан в журнал health_checks.</div>
            <?php endif; ?>
            <?php if ($lastCheck): ?>
                <div class="table-wrap stats-table">
                    <table>
                        <tbody>
                            <tr><th>Время</th><td><?= e(date('d.m.Y H:i:s', strtotime((string)$lastCheck['created_at']))) ?><?= (int)$lastCheck['is_demo'] === 1 ? ' <span class="badge">демо</span>' : '' ?></td></tr>
                            <tr><th>Сайт</th><td><?= (int)$lastCheck['site_ok'] === 1 ? '✓ отвечает' : '✕ не отвечает' ?>, <?= e(stats_format_number((float)$lastCheck['site_response_ms'], 1)) ?> мс</td></tr>
                            <tr><th>База данных</th><td><?= (int)$lastCheck['db_ok'] === 1 ? '✓ работает, ' . e(stats_format_number((float)$lastCheck['db_response_ms'], 1)) . ' мс' : '✕ нет подключения' ?></td></tr>
                            <tr><th>API TheMealDB</th><td><?= (int)$lastCheck['api_ok'] === 1 ? '✓ отвечает' : '✕ не отвечает' ?>, <?= e(stats_format_number((float)$lastCheck['api_response_ms'])) ?> мс</td></tr>
                            <tr><th>Свободно на диске</th><td><?= e(stats_format_number((float)$lastCheck['disk_free_mb'] / 1024, 1)) ?> ГБ</td></tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">Проверок еще не было.</div>
            <?php endif; ?>
            <form method="post" action="<?= e(stats_page_url('/admin/monitoring.php', $period)) ?>" class="health-form">
                <input type="hidden" name="action" value="health_check">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-small" type="submit">Проверить сейчас</button>
            </form>
            <p class="stats-note">Автоматически проверка запускается командой <code>php tools/health_check.php</code> (например, раз в 15 минут через планировщик Windows).</p>
        </div>
    </div>

    <h2 class="stats-section-title">Ошибки</h2>
    <div class="chart-grid">
        <?php
        stats_chart_card('monitoring', $period, 'http_errors', 'Ошибки HTTP', 'Страницы, открытые с кодом 404, 403 или 500');
        stats_chart_card('monitoring', $period, 'php_errors', 'Ошибки PHP', 'Записи журнала ошибок по уровням');
        ?>
    </div>
    <h3 class="stats-subtitle">Последние ошибки PHP</h3>
    <?php if (!$recentErrors): ?>
        <div class="empty-state">Ошибок за период нет.</div>
    <?php else: ?>
        <div class="table-wrap stats-table">
            <table>
                <thead><tr><th>Время</th><th>Уровень</th><th>Сообщение</th><th>Файл</th><th>Страница</th></tr></thead>
                <tbody>
                    <?php foreach ($recentErrors as $error): ?>
                        <tr>
                            <td><?= e(date('d.m.Y H:i', strtotime((string)$error['created_at']))) ?><?= (int)$error['is_demo'] === 1 ? ' <span class="badge">демо</span>' : '' ?></td>
                            <td><?= e($error['level']) ?></td>
                            <td><?= e($error['message']) ?></td>
                            <td><?= e($error['file'] . ($error['line'] ? ':' . $error['line'] : '')) ?></td>
                            <td><?= e($error['page']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 class="stats-section-title">Безопасность и внешний API</h2>
    <div class="kpi-grid">
        <?php
        stats_render_tile(['label' => 'Успешные входы', 'value' => stats_format_number($auth['login_success']), 'note' => 'регистраций: ' . stats_format_number($auth['register'])]);
        stats_render_tile(['label' => 'Неудачные входы', 'value' => stats_format_number($auth['login_failed']), 'note' => 'неверный e-mail или пароль']);
        stats_render_tile(['label' => 'Отказы в доступе к админке', 'value' => stats_format_number($auth['admin_denied']), 'note' => 'вход в /admin без роли admin']);
        ?>
    </div>
    <div class="chart-grid">
        <?php
        stats_chart_card('monitoring', $period, 'auth_by_day', 'Входы на сайт', 'Успешные и неудачные попытки входа по дням');
        stats_chart_card('monitoring', $period, 'api_by_day', 'Время ответа API TheMealDB', 'Среднее время успешных запросов со страницы рецептов');
        ?>
    </div>
    <h3 class="stats-subtitle">Подозрительные входы</h3>
    <?php if (!$suspicious): ?>
        <div class="empty-state">Подбора пароля не обнаружено.</div>
    <?php else: ?>
        <div class="table-wrap stats-table">
            <table>
                <thead><tr><th>Адрес (хэш)</th><th>Попыток</th><th>Начало</th><th>Конец</th><th>E-mail</th></tr></thead>
                <tbody>
                    <?php foreach ($suspicious as $row): ?>
                        <tr>
                            <td><code><?= e(substr((string)$row['ip_hash'], 0, 12)) ?>…</code></td>
                            <td><?= e($row['attempts']) ?></td>
                            <td><?= e(date('d.m.Y H:i:s', strtotime((string)$row['first_at']))) ?></td>
                            <td><?= e(date('H:i:s', strtotime((string)$row['last_at']))) ?></td>
                            <td><?= e($row['emails']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 class="stats-section-title">Сервер и база данных</h2>
    <div class="chart-grid">
        <div class="chart-card">
            <h3 class="stats-card-title">Сервер</h3>
            <div class="table-wrap stats-table">
                <table>
                    <tbody>
                        <tr><th>Версия PHP</th><td><?= e($server['php']) ?> (<?= e($server['sapi']) ?>)</td></tr>
                        <tr><th>Операционная система</th><td><?= e($server['os']) ?></td></tr>
                        <tr><th>Лимит памяти PHP</th><td><?= e($server['memory_limit']) ?></td></tr>
                        <tr><th>Свободно на диске</th><td><?= e(stats_format_number($server['disk_free_gb'], 1)) ?> ГБ из <?= e(stats_format_number($server['disk_total_gb'], 1)) ?> ГБ (<?= e(stats_format_number($server['disk_free_percent'], 1)) ?> %)</td></tr>
                        <?php if ($db['ok']): ?>
                            <tr><th>Версия СУБД</th><td><?= e($db['version']) ?></td></tr>
                            <tr><th>MySQL работает без перезапуска</th><td><?= e(monitor_format_uptime($db['uptime'])) ?></td></tr>
                            <tr><th>Подключений сейчас</th><td><?= e($db['connections']) ?></td></tr>
                            <tr><th>Размер базы spice_market</th><td><?= e(stats_format_number($db['size_mb'], 2)) ?> МБ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php stats_chart_card('monitoring', $period, 'table_sizes', 'Размер таблиц', 'Сколько места занимают данные и индексы самых больших таблиц'); ?>
    </div>

    <details class="stats-method" open>
        <summary>Как считаются показатели</summary>
        <div class="table-wrap stats-table">
            <table>
                <thead><tr><th>Показатель</th><th>Формула и правило расчёта</th><th>Источник данных</th></tr></thead>
                <tbody>
                    <tr><td>Сейчас на сайте</td><td>Уникальные посетители (<code>visitor_id</code>) за последние 5 минут, без роботов и админки. Страница запрашивает число заново каждые 30 секунд.</td><td><code>site_visits</code></td></tr>
                    <tr><td>Время ответа</td><td>Время от начала запроса до конца формирования страницы (<code>microtime</code> в PHP). Среднее – <code>AVG</code>, 95-й перцентиль – <code>PERCENTILE_CONT(0.95)</code>: 95 % страниц открылись быстрее.</td><td><code>site_visits.response_time_ms</code></td></tr>
                    <tr><td>Доступность</td><td>Успешные проверки / все проверки × 100 %. Проверка открывает главную страницу, подключается к MySQL и делает запрос к API.</td><td><code>health_checks</code></td></tr>
                    <tr><td>Ошибки HTTP</td><td>Количество страниц с кодом ответа 404, 403 и 500 по дням.</td><td><code>site_visits.http_status</code></td></tr>
                    <tr><td>Ошибки PHP</td><td>Предупреждения и ошибки перехватываются обработчиками <code>set_error_handler</code> и <code>set_exception_handler</code> и пишутся в журнал.</td><td><code>app_errors</code></td></tr>
                    <tr><td>Подбор пароля</td><td>5 и больше неудачных входов с одного адреса за один 10-минутный интервал. Адрес хранится только как хэш.</td><td><code>auth_events</code></td></tr>
                    <tr><td>API TheMealDB</td><td>Успешные запросы / все запросы × 100 % и среднее время успешного ответа.</td><td><code>api_requests</code></td></tr>
                    <tr><td>Размер таблиц</td><td><code>DATA_LENGTH + INDEX_LENGTH</code> из служебной базы <code>information_schema</code>.</td><td><code>information_schema.TABLES</code></td></tr>
                </tbody>
            </table>
        </div>
    </details>
</section>

<?php stats_render_charts_data($charts); ?>
<script src="/assets/js/admin-monitoring.js"></script>

<?php page_end(); ?>

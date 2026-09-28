<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/layout.php';
require_once __DIR__ . '/../../src/stats.php';

require_admin();

$todayPeriod = stats_period(['period' => 'custom', 'from' => date('Y-m-d'), 'to' => date('Y-m-d')]);
$todayTraffic = stats_traffic_summary($todayPeriod);
$todayBusiness = stats_business_summary($todayPeriod);

$groups = [
    'Таблицы' => spice_admin_base_tables(),
    'Представления' => spice_admin_views(),
    'Журналы статистики и мониторинга' => spice_admin_log_tables(),
];

page_start('Админ-панель', 'admin');
?>

<section>
    <?php admin_nav('tables'); ?>

    <h1 class="page-title">Админ-панель</h1>
    <p class="lead">Просмотр справочников, товаров, заказов, представлений и журналов статистики.</p>

    <div class="admin-summary">
        <a class="admin-card" href="/admin/stats.php?period=7">
            <strong><?= e(stats_format_number($todayTraffic['page_views'])) ?></strong>
            <span>просмотров страниц сегодня</span>
        </a>
        <a class="admin-card" href="/admin/stats.php?period=7">
            <strong><?= e(stats_format_number($todayTraffic['visitors'])) ?></strong>
            <span>посетителей сегодня</span>
        </a>
        <a class="admin-card" href="/admin/stats.php?period=7">
            <strong><?= e(stats_format_number($todayBusiness['orders'])) ?></strong>
            <span>заказов сегодня</span>
        </a>
        <a class="admin-card" href="/admin/stats.php?period=7">
            <strong><?= e(stats_format_money($todayBusiness['revenue'])) ?></strong>
            <span>выручка сегодня</span>
        </a>
    </div>

    <?php foreach ($groups as $title => $tables): ?>
        <h2 class="admin-section-title"><?= e($title) ?></h2>
        <div class="admin-grid">
            <?php foreach ($tables as $table): ?>
                <?php $count = spice_admin_table_count($table); ?>
                <a class="admin-card" href="/admin/table.php?table=<?= urlencode($table) ?>">
                    <strong><?= e($table) ?></strong>
                    <span><?= e(stats_format_number($count)) ?> строк</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>

<?php page_end(); ?>

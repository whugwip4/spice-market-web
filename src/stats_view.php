<?php

declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/stats_charts.php';

function stats_sparkline_svg(array $values): string
{
    $values = array_map('floatval', array_filter(array_values($values), static fn($value): bool => $value !== null));
    $count = count($values);
    if ($count < 2) {
        return '';
    }

    $width = 120;
    $height = 32;
    $padding = 4;
    $min = min(0.0, min($values));
    $range = max(0.000001, max($values) - $min);
    $points = [];

    foreach ($values as $index => $value) {
        $x = $padding + ($width - 2 * $padding) * $index / ($count - 1);
        $y = $height - $padding - ($height - 2 * $padding) * (($value - $min) / $range);
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
        $state = ($delta > 0) === (($tile['good'] ?? 'up') === 'up') ? 'is-good' : 'is-bad';
    }
    ?>
    <article class="kpi-tile<?= isset($tile['status']) ? ' kpi-status-' . e($tile['status']) : '' ?>">
        <span class="kpi-label"><?= e($tile['label']) ?></span>
        <strong class="kpi-value"<?= isset($tile['live']) ? ' data-live="' . e($tile['live']) . '"' : '' ?>>
            <?php if (isset($tile['status'])): ?><span class="kpi-status-icon" aria-hidden="true"><?= $tile['status'] === 'good' ? '✓' : '✕' ?></span><?php endif; ?>
            <?= e($tile['value']) ?>
        </strong>
        <?php if (isset($tile['note'])): ?>
            <span class="kpi-delta"><?= e($tile['note']) ?></span>
        <?php elseif ($delta === null): ?>
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

function stats_page_url(string $page, array $period, array $overrides = []): string
{
    return $page . '?' . http_build_query(array_merge([
        'period' => $period['preset'],
        'from' => $period['preset'] === 'custom' ? $period['from'] : null,
        'to' => $period['preset'] === 'custom' ? $period['to'] : null,
        'demo' => $period['include_demo'] ? '1' : '0',
    ], $overrides));
}

function stats_chart_card(string $group, array $period, string $key, string $title, string $subtitle, string $size = '', int $height = 260): void
{
    $exportUrl = stats_page_url('/admin/export.php', $period, ['group' => $group, 'dataset' => $key]);
    ?>
    <figure class="chart-card<?= $size !== '' ? ' ' . e($size) : '' ?>" id="chart-<?= e($key) ?>">
        <figcaption>
            <div>
                <h3><?= e($title) ?></h3>
                <p><?= e($subtitle) ?></p>
            </div>
            <a class="chip chip-small" href="<?= e($exportUrl) ?>" title="Скачать данные графика в CSV">CSV</a>
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

function stats_render_filters(string $page, array $period): void
{
    ?>
    <div class="stats-filters">
        <div class="chips">
            <?php foreach ([7 => '7 дней', 30 => '30 дней', 90 => '90 дней'] as $days => $label): ?>
                <a class="chip<?= $period['preset'] === (string)$days ? ' is-active' : '' ?>" href="<?= e(stats_page_url($page, $period, ['period' => $days, 'from' => null, 'to' => null])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <form class="stats-range" method="get" action="<?= e($page) ?>">
            <input type="hidden" name="period" value="custom">
            <input type="hidden" name="demo" value="<?= $period['include_demo'] ? '1' : '0' ?>">
            <label>С <input class="input" type="date" name="from" value="<?= e($period['from']) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
            <label>по <input class="input" type="date" name="to" value="<?= e($period['to']) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
            <button class="btn btn-small<?= $period['preset'] === 'custom' ? '' : ' btn-secondary' ?>" type="submit">Показать</button>
        </form>
        <a class="chip<?= $period['include_demo'] ? '' : ' is-active' ?>" href="<?= e(stats_page_url($page, $period, ['demo' => $period['include_demo'] ? '0' : '1'])) ?>">
            <?= $period['include_demo'] ? 'Только реальные данные' : 'Показать с демо-данными' ?>
        </a>
    </div>

    <p class="stats-period">
        Период: <strong><?= e(stats_format_date($period['from'])) ?> – <?= e(stats_format_date($period['to'])) ?></strong>
        (<?= e($period['days']) ?> дн.), сравнение с периодом
        <?= e(stats_format_date($period['previous_from'])) ?> – <?= e(stats_format_date($period['previous_to'])) ?><?= $period['includes_today'] ? ' до того же времени суток' : '' ?>.
    </p>

    <?php if ($period['include_demo'] && stats_has_demo_data()): ?>
        <div class="message info">
            В статистику включены демонстрационные данные: история посещений и заказов, сгенерированная скриптом
            <code>tools/seed_stats.php</code> (отмечены <code>is_demo = 1</code>, заказы <code>DM-…</code>, пользователи <code>@demo.local</code>).
            Реальные посещения сайта записываются трекером параллельно.
        </div>
    <?php elseif (!$period['include_demo']): ?>
        <div class="message ok">Показаны только реальные данные, собранные трекером сайта, без демонстрационных записей.</div>
    <?php endif; ?>
    <?php
}

function stats_render_tiles(array $tiles): void
{
    echo '<div class="kpi-grid">';
    foreach ($tiles as $tile) {
        stats_render_tile($tile);
    }
    echo '</div>';
}

function stats_render_charts_data(array $charts): void
{
    ?>
    <script type="application/json" id="stats-data"><?= stats_json($charts) ?></script>
    <script src="/assets/vendor/chart.umd.min.js"></script>
    <script src="/assets/js/admin-stats.js"></script>
    <?php
}

function stats_alert_label(string $level): array
{
    return match ($level) {
        'critical' => ['!', 'Критично'],
        'serious' => ['▲', 'Сбой'],
        'warning' => ['●', 'Внимание'],
        default => ['✓', 'Норма'],
    };
}

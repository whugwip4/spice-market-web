<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/layout.php';
require_once __DIR__ . '/../../src/stats_charts.php';

require_admin();

// Выгрузка таблицы любого графика в CSV: те же данные, что под графиком на странице
$group = (string)($_GET['group'] ?? '');
$dataset = (string)($_GET['dataset'] ?? '');
$period = stats_period($_GET);
$charts = stats_all_charts($group, $period);

if (!isset($charts[$dataset])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Набор данных не найден.';
    exit;
}

$table = $charts[$dataset]['table'];
$filename = sprintf('spice-market_%s_%s_%s.csv', $dataset, $period['from'], $period['to']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// BOM и точка с запятой нужны, чтобы Excel сразу открыл файл с русскими буквами по столбцам
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, $table['columns'], ';');
foreach ($table['rows'] as $row) {
    fputcsv($output, array_map(static fn($cell): string => str_replace(["\u{00A0}", "\u{202F}"], '', (string)$cell), $row), ';');
}
fclose($output);

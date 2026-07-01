<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/data.php';

header('Content-Type: application/json; charset=utf-8');

$slugs = array_filter(array_map('trim', explode(',', (string)($_GET['slugs'] ?? ''))));
$products = spice_products_by_slugs($slugs);

echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

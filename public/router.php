<?php

declare(strict_types=1);

$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/assets/')) {
    $assetPath = rawurldecode($path);
    $file = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $assetPath);

    if (is_file($file)) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $types = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
        ];

        header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
        readfile($file);
        return true;
    }

    http_response_code(404);
    return true;
}

$target = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($path));

if (is_file($target) || is_file(rtrim($target, '\\/') . DIRECTORY_SEPARATOR . 'index.php')) {
    return false;
}

// отсутствующие файлы (favicon.ico, *.map и т.п.) получают простой ответ 404 и не считаются просмотрами
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($extension !== '' && $extension !== 'php') {
    http_response_code(404);
    return true;
}

require __DIR__ . '/404.php';
return true;

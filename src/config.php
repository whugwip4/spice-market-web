<?php

declare(strict_types=1);

const SPICE_SITE_NAME = 'Spice Market';

return [
    'db' => [
        'host' => getenv('SPICE_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('SPICE_DB_PORT') ?: '3307',
        'name' => getenv('SPICE_DB_NAME') ?: 'spice_market',
        'user' => getenv('SPICE_DB_USER') ?: 'root',
        'password' => getenv('SPICE_DB_PASSWORD') ?: '',
    ],
    'stats' => [
        // соль для хэширования IP-адресов в журналах статистики
        'salt' => getenv('SPICE_STATS_SALT') ?: 'spice-market-stats-2026',
    ],
];


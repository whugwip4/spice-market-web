<?php

declare(strict_types=1);

function spice_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function spice_pdo(): ?PDO
{
    static $pdo = null;
    static $attempted = false;
    static $lastError = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if ($attempted) {
        return null;
    }

    $attempted = true;
    $db = spice_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $db['host'],
        $db['port'],
        $db['name']
    );

    try {
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 1,
        ]);
    } catch (Throwable $error) {
        $lastError = $error->getMessage();
        return null;
    }

    return $pdo;
}

function spice_db_error(): ?string
{
    $reflection = new ReflectionFunction('spice_pdo');
    $statics = $reflection->getStaticVariables();

    return $statics['lastError'] ?? null;
}

function spice_db_available(): bool
{
    return spice_pdo() instanceof PDO;
}

function spice_fetch_all(string $sql, array $params = []): array
{
    $pdo = spice_pdo();

    if (!$pdo) {
        return [];
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function spice_fetch_one(string $sql, array $params = []): ?array
{
    $rows = spice_fetch_all($sql, $params);

    return $rows[0] ?? null;
}

function spice_execute(string $sql, array $params = []): bool
{
    $pdo = spice_pdo();

    if (!$pdo) {
        return false;
    }

    $statement = $pdo->prepare($sql);

    return $statement->execute($params);
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/layout.php';

require_admin();

$tables = spice_admin_tables();

page_start('Админ-панель', 'admin');
?>

<section>
    <h1 class="page-title">Админ-панель</h1>
    <p class="lead">Просмотр справочников, товаров, заказов и представлений для демонстрации заполненной базы.</p>

    <div class="admin-grid">
        <?php foreach ($tables as $table): ?>
            <?php $count = spice_admin_table_count($table); ?>
            <a class="admin-card" href="/admin/table.php?table=<?= urlencode($table) ?>">
                <strong><?= e($table) ?></strong>
                <span><?= e($count) ?> строк</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php page_end(); ?>

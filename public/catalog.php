<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$search = trim((string)($_GET['search'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$categories = spice_categories();
$products = spice_products([
    'search' => $search,
    'category' => $category,
]);

if ($search !== '' && spice_db_available()) {
    stats_log_search($search, count($products), $category);
}

page_start('Каталог', 'catalog');
?>

<section>
    <h1 class="page-title">Каталог специй</h1>
    <p class="lead">Выберите специи по категории, найдите нужный аромат и добавьте товары в корзину.</p>

    <form class="filters" method="get" action="/catalog.php">
        <div class="field-row">
            <label class="field">
                Поиск по названию
                <input class="input" type="search" name="search" value="<?= e($search) ?>" placeholder="Например, паприка">
            </label>
            <?php if ($category !== ''): ?>
                <input type="hidden" name="category" value="<?= e($category) ?>">
            <?php endif; ?>
            <button class="btn" type="submit">Найти</button>
        </div>

        <div class="chips" aria-label="Категории">
            <a class="chip<?= $category === '' ? ' is-active' : '' ?>" href="/catalog.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>">Все</a>
            <?php foreach ($categories as $item): ?>
                <?php if (($item['slug'] ?? '') === '') {
                    continue;
                } ?>
                <?php
                $href = '/catalog.php?category=' . urlencode((string)$item['slug']);
                if ($search !== '') {
                    $href .= '&search=' . urlencode($search);
                }
                ?>
                <a class="chip<?= $category === ($item['slug'] ?? '') ? ' is-active' : '' ?>" href="<?= e($href) ?>"><?= e($item['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </form>

    <?php if (!$products): ?>
        <div class="empty-state">По заданным условиям товары не найдены.</div>
    <?php else: ?>
        <div class="catalog-grid">
            <?php foreach ($products as $index => $product): ?>
                <?php product_card($product, $index >= 8 ? 'catalog-extra is-hidden' : ''); ?>
            <?php endforeach; ?>
        </div>
        <?php if (count($products) > 8): ?>
            <div class="catalog-actions">
                <button class="btn btn-secondary" type="button" data-show-more-products>
                    Показать еще специи
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php page_end(); ?>

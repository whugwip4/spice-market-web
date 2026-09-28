<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$slug = trim((string)($_GET['slug'] ?? 'smoked-paprika'));
$product = spice_product_by_slug($slug);

if ($product) {
    stats_tracker_context(['product_id' => (int)$product['product_id']]);
} else {
    http_response_code(404);
}

page_start($product ? (string)$product['name'] : 'Товар не найден', 'catalog');
?>

<?php if (!$product): ?>
    <section>
        <h1 class="page-title">Товар не найден</h1>
        <p class="lead">Проверьте ссылку или вернитесь в каталог.</p>
        <a class="btn" href="/catalog.php">В каталог</a>
    </section>
<?php else: ?>
    <section class="product-detail">
        <div>
            <?= product_visual($product, 'large') ?>
        </div>
        <div>
            <h1><?= e($product['name']) ?></h1>
            <p class="description"><?= e($product['description']) ?></p>
            <strong class="price-big"><?= money($product['price']) ?></strong>
            <button
                class="btn js-add-cart"
                type="button"
                data-slug="<?= e($product['slug']) ?>"
                data-name="<?= e($product['name']) ?>"
                <?= (int)$product['stock_quantity'] <= 0 ? 'disabled' : '' ?>
            >
                <?= (int)$product['stock_quantity'] > 0 ? 'Добавить в корзину' : 'Нет в наличии' ?>
            </button>

            <div class="info-card">
                <ul class="info-list">
                    <li><span>Категория</span><strong><?= e($product['category_name']) ?></strong></li>
                    <li><span>Страна происхождения</span><strong><?= e($product['country_name']) ?></strong></li>
                    <li><span>Вес упаковки</span><strong><?= e($product['weight_grams']) ?> г</strong></li>
                    <li><span>Острота</span><strong><?= e($product['heat_level']) ?>/5</strong></li>
                    <li><span>Наличие</span><strong><?= e($product['stock_quantity']) ?> шт.</strong></li>
                </ul>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php page_end(); ?>

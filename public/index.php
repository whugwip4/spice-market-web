<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$featuredProducts = spice_featured_products(4);
$recipes = array_slice(spice_recipes(), 0, 3);

page_start('Главная', 'home');
?>

<section class="hero">
    <div>
        <h1>Spice Market</h1>
        <p>Магазин натуральных специй, пряных смесей и трав для домашней кухни, выпечки и рецептов со всего мира.</p>
        <a class="btn" href="/catalog.php">Перейти в каталог</a>
        <a class="btn btn-secondary" href="/recipes.php">Смотреть рецепты</a>
    </div>
    <div class="hero-panel" aria-label="Композиция специй">
        <div class="hero-label">
            <strong>Базовая полка специй</strong>
            <span>Соберите набор для дома: паприка, перец, куркума, корица, пряные травы и готовые смеси.</span>
        </div>
    </div>
</section>

<section>
    <div class="section-head">
        <div>
            <h2>Популярные специи</h2>
            <p>Ароматные позиции, которые чаще всего выбирают для домашней кухни.</p>
        </div>
        <a class="chip" href="/catalog.php">Все товары</a>
    </div>

    <div class="product-grid">
        <?php foreach ($featuredProducts as $product): ?>
            <?php product_card($product); ?>
        <?php endforeach; ?>
    </div>
</section>

<section>
    <div class="section-head">
        <div>
            <h2>Идеи для кухни</h2>
            <p>Блюда, где специи раскрывают вкус мяса, овощей, выпечки и напитков.</p>
        </div>
        <a class="chip" href="/recipes.php">Все рецепты</a>
    </div>

    <div class="content-grid">
        <?php foreach ($recipes as $recipe): ?>
            <article class="content-card">
                <h3><?= e($recipe['title']) ?></h3>
                <p><?= e($recipe['description']) ?></p>
                <ul class="info-list">
                    <li><span>Кухня</span><strong><?= e($recipe['cuisine_name']) ?></strong></li>
                    <li><span>Время</span><strong><?= e($recipe['cooking_time_minutes']) ?> мин</strong></li>
                </ul>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php page_end(); ?>

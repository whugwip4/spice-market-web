<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$recipes = spice_recipes();
$apiRecipe = spice_api_recipe();

page_start('Рецепты', 'recipes');
?>

<section>
    <h1 class="page-title">Рецепты</h1>
    <p class="lead">Подборка блюд для разных кухонь, где специи помогают собрать полноценный вкус.</p>

    <div class="content-grid">
        <?php foreach ($recipes as $recipe): ?>
            <article class="content-card">
                <h2><?= e($recipe['title']) ?></h2>
                <p><?= e($recipe['description']) ?></p>
                <ul class="info-list">
                    <li><span>Кухня</span><strong><?= e($recipe['cuisine_name']) ?></strong></li>
                    <li><span>Время</span><strong><?= e($recipe['cooking_time_minutes']) ?> мин</strong></li>
                    <li><span>Специи</span><strong><?= e($recipe['products_list'] ?? 'Подборка специй') ?></strong></li>
                </ul>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section>
    <div class="section-head">
        <div>
            <h2>Рецепт из API</h2>
            <p>Идея блюда загружается из TheMealDB и подходит к разделу с рецептами.</p>
        </div>
        <a class="chip" href="<?= e($apiRecipe['api_url']) ?>" target="_blank" rel="noreferrer">TheMealDB</a>
    </div>

    <article class="api-recipe-card">
        <div class="product-photo large">
            <img src="<?= e($apiRecipe['image']) ?>" alt="<?= e($apiRecipe['title']) ?>" loading="lazy">
        </div>
        <div class="content-card">
            <h2><?= e($apiRecipe['title']) ?></h2>
            <p>Внешний рецепт можно использовать как вдохновение для подбора специй и трав из каталога.</p>
            <ul class="info-list">
                <li><span>Источник</span><strong><?= e($apiRecipe['source']) ?></strong></li>
                <li><span>Кухня</span><strong><?= e($apiRecipe['area']) ?></strong></li>
                <li><span>Категория</span><strong><?= e($apiRecipe['category']) ?></strong></li>
                <li><span>Ингредиенты</span><strong><?= e($apiRecipe['ingredients']) ?></strong></li>
            </ul>
        </div>
    </article>
</section>

<?php page_end(); ?>

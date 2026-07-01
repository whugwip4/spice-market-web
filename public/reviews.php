<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

$reviews = spice_reviews();

page_start('Отзывы', 'reviews');
?>

<section>
    <h1 class="page-title">Отзывы покупателей</h1>
    <p class="lead">Покупатели рассказывают, какие специи стали постоянными на их кухне.</p>

    <div class="content-grid">
        <?php foreach ($reviews as $review): ?>
            <article class="content-card">
                <h2><?= str_repeat('★', (int)$review['rating']) ?></h2>
                <p><?= e($review['review_text']) ?></p>
                <ul class="info-list">
                    <li><span>Покупатель</span><strong><?= e($review['full_name']) ?></strong></li>
                    <li><span>Товар</span><strong><a href="/product.php?slug=<?= e($review['product_slug']) ?>"><?= e($review['product_name']) ?></a></strong></li>
                    <li><span>Дата</span><strong><?= e(date('d.m.Y', strtotime((string)$review['created_at']))) ?></strong></li>
                </ul>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php page_end(); ?>

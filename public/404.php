<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

http_response_code(404);
page_start('Страница не найдена');
?>

<section>
    <h1 class="page-title">Страница не найдена</h1>
    <p class="lead">Такой страницы нет на сайте. Проверьте адрес или перейдите в каталог специй.</p>
    <div class="chips">
        <a class="btn" href="/catalog.php">В каталог</a>
        <a class="chip" href="/">На главную</a>
    </div>
</section>

<?php page_end(); ?>

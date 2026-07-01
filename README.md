# Spice Market Web

Учебный веб-проект для задания 3 по теме «Магазин специй».

## Запуск

1. Запустить MySQL в XAMPP.
2. Импортировать `..\db\spice_market.sql` в phpMyAdmin.
3. Запустить сайт:

```powershell
& "C:\Program Files\xampp\php\php.exe" -S 127.0.0.1:8081 -t public public/router.php
```

## Основные страницы

- `http://127.0.0.1:8081/` — главная страница.
- `/catalog.php` — каталог с поиском и категориями.
- `/product.php?slug=smoked-paprika` — карточка товара.
- `/recipes.php` — рецепты.
- `/reviews.php` — отзывы.
- `/cart.php` — корзина через `localStorage`.
- `/checkout.php` — оформление заказа.
- `/login.php` и `/register.php` — авторизация и регистрация.
- `/account.php` — личный кабинет.
- `/delivery.php` — доставка и оплата.
- `/contacts.php` — контакты.
- `/admin/` — просмотр таблиц и представлений БД.

Подключение к базе настраивается в `src/config.php`.

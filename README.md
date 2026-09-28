# Spice Market Web

Учебный веб-проект для задания 3 по теме "Магазин специй".

## Запуск

1. Запустить Apache и MySQL в XAMPP.
2. Импортировать `..\db\spice_market.sql` в phpMyAdmin.
3. Запустить сайт из папки проекта:

```powershell
& "C:\Program Files\xampp\php\php.exe" `
  -S 127.0.0.1:8081 `
  -t public public/router.php
```

4. Открыть в браузере:

```text
http://127.0.0.1:8081/
```

## Основные страницы

- `/` - главная страница.
- `/catalog.php` - каталог с поиском и категориями.
- `/product.php?slug=smoked-paprika` - карточка товара.
- `/recipes.php` - рецепты и проверка API TheMealDB.
- `/reviews.php` - отзывы покупателей.
- `/cart.php` - корзина через localStorage.
- `/checkout.php` - оформление заказа.
- `/login.php` и `/register.php` - авторизация и регистрация.
- `/account.php` - личный кабинет.
- `/delivery.php` - доставка и оплата.
- `/contacts.php` - контакты.
- `/admin/` - просмотр таблиц и представлений БД.

## Доступ администратора

Административная панель открывается только после входа под пользователем с ролью администратора.

Демонстрационные учётные записи из `db\spice_market.sql`:

```text
Администратор: sultan@example.com / Admin2026
Покупатели:    anna.petrova@example.com, ivan.smirnov@example.com,
               maria.kuznetsova@example.com, dmitry.orlov@example.com / Spice2026
```

Оформление заказа доступно только после входа в личный кабинет.

## База данных

Подключение к MySQL/MariaDB настраивается в `src/config.php`.

По умолчанию используется:

```text
host: 127.0.0.1
port: 3307
database: spice_market
user: root
password: пустой
```

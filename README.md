# Spice Market Web

Учебный веб-проект для задания 3 по теме "Магазин специй".

## Запуск

1. Запустить Apache и MySQL в XAMPP.
2. Импортировать `..\db\spice_market.sql` в phpMyAdmin, затем `database\stats_monitoring.sql` (таблицы статистики).
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
- `/admin/` - просмотр таблиц, представлений и журналов БД.
- `/admin/stats.php` - посещаемость: просмотры, посетители, источники, устройства.
- `/admin/sales.php` - продажи: заказы, выручка, путь до заказа, отзывы, поиск.
- `/admin/monitoring.php` - мониторинг: доступность, время ответа, ошибки, входы, API.

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

## Статистика и мониторинг

Каждая отрисованная страница сайта записывается в журнал `site_visits` (трекер `src/tracker.php`):
страница, посетитель (cookie `sm_vid`, 1 год), визит (cookie `sm_sid`, 30 минут бездействия),
источник перехода, устройство, браузер, код ответа и время генерации страницы.
IP-адрес не хранится — только его хэш SHA-256 с солью.

Дополнительно журналируются входы и регистрации (`auth_events`), поиск в каталоге (`search_queries`),
запросы к API TheMealDB (`api_requests`) и ошибки PHP (`app_errors`). Если база данных недоступна,
ошибки пишутся в `storage/logs/monitoring.log`.

Показатели считаются в `src/stats.php` и выводятся на странице `/admin/stats.php` с графиками Chart.js 4
(`assets/vendor/chart.umd.min.js`). Методика расчёта каждого показателя приведена внизу этой страницы.

Демонстрационная история за 90 дней создаётся генератором (записи помечаются `is_demo = 1`,
заказы `DM-…`, пользователи `@demo.local`):

```powershell
& "C:\Program Files\xampp\php\php.exe" tools/seed_stats.php            # создать заново
& "C:\Program Files\xampp\php\php.exe" tools/seed_stats.php --purge    # удалить демо-данные
```

Проверка доступности сайта, MySQL и API TheMealDB (результат пишется в `health_checks`,
для постоянного мониторинга команду запускают по расписанию, например раз в 15 минут):

```powershell
& "C:\Program Files\xampp\php\php.exe" tools/health_check.php
```

Данные любого графика выгружаются в CSV кнопкой «CSV» в его карточке (`/admin/export.php`).

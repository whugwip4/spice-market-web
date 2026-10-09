<?php

declare(strict_types=1);

/*
 * Генератор демонстрационных данных для модуля статистики и мониторинга Spice Market.
 *
 * Запуск из папки проекта:
 *   php tools/seed_stats.php              удалить старые демо-данные и создать историю за 90 дней
 *   php tools/seed_stats.php --days=60    другая длина истории (от 7 до 365 дней)
 *   php tools/seed_stats.php --purge      только удалить демо-данные
 *
 * Все созданные записи помечены: is_demo = 1 в журналах, номера заказов DM-…, e-mail пользователей @demo.local.
 * Генерация детерминирована (--seed=2026): при одной и той же дате запуска получаются одинаковые данные.
 *
 * Модель посещаемости:
 *   - визитов в день: от 70 в начале истории до 120 в конце, недельная сезонность и случайный разброс ±10 %;
 *   - время начала визита распределено по часам суток (пики в обед и вечером);
 *   - устройства: 58 % смартфоны, 36 % компьютеры, 6 % планшеты;
 *   - путь по сайту — цепь переходов между страницами с вероятностями выхода (отказы 30–60 % в зависимости от входа);
 *   - оформление заказа: корзина → вход или регистрация → оформление, успешно в 62 % случаев;
 *   - смоделированы события мониторинга: рассылка во ВКонтакте, подбор пароля, сбои API и базы данных.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/data.php';

final class StatsDemoSeeder
{
    // Сколько дней назад произошли смоделированные события
    private const PROMO_DAY = 35;
    private const BRUTE_FORCE_DAY = 24;
    private const DB_OUTAGE_DAY = 19;
    private const SESSION_ERRORS_DAY = 17;
    private const API_OUTAGE_DAY = 9;
    private const MEMORY_ERROR_DAY = 41;

    private const WEEKDAY_FACTOR = [1 => 0.95, 2 => 0.97, 3 => 1.0, 4 => 1.03, 5 => 0.92, 6 => 1.08, 7 => 1.15];
    private const HOUR_WEIGHTS = [
        0 => 1.2, 1 => 0.6, 2 => 0.35, 3 => 0.25, 4 => 0.2, 5 => 0.3, 6 => 0.8, 7 => 1.8,
        8 => 2.8, 9 => 3.6, 10 => 4.2, 11 => 4.8, 12 => 5.6, 13 => 5.8, 14 => 5.0, 15 => 4.6,
        16 => 4.7, 17 => 5.2, 18 => 6.0, 19 => 7.2, 20 => 7.8, 21 => 7.1, 22 => 5.0, 23 => 2.6,
    ];
    private const DEVICES = ['mobile' => 58, 'desktop' => 36, 'tablet' => 6];
    private const BROWSERS = [
        'desktop' => ['Chrome' => 50, 'Яндекс Браузер' => 24, 'Edge' => 11, 'Firefox' => 8, 'Opera' => 5, 'Safari' => 2],
        'mobile' => ['Chrome' => 44, 'Safari' => 31, 'Яндекс Браузер' => 18, 'Samsung Internet' => 5, 'Opera' => 2],
        'tablet' => ['Safari' => 48, 'Chrome' => 36, 'Яндекс Браузер' => 16],
    ];
    private const SOURCES = ['direct' => 36, 'search' => 35, 'social' => 17, 'external' => 12];
    private const PROMO_SOURCES = ['social' => 52, 'direct' => 24, 'search' => 15, 'external' => 9];
    private const REFERRER_HOSTS = [
        'search' => ['yandex.ru' => 64, 'google.com' => 28, 'go.mail.ru' => 5, 'bing.com' => 3],
        'social' => ['vk.com' => 55, 't.me' => 30, 'ok.ru' => 10, 'dzen.ru' => 5],
        'external' => ['eda.ru' => 30, 'povarenok.ru' => 25, 'otzovik.com' => 20, 'pikabu.ru' => 15, 'irecommend.ru' => 10],
    ];
    private const ENTRY_PAGES = [
        'direct' => ['/' => 60, '/catalog.php' => 25, '/account.php' => 8, '/delivery.php' => 4, '/contacts.php' => 3],
        'search' => ['/product.php' => 45, '/' => 25, '/catalog.php' => 18, '/recipes.php' => 8, '/delivery.php' => 4],
        'social' => ['/' => 40, '/product.php' => 30, '/recipes.php' => 20, '/catalog.php' => 10],
        'external' => ['/recipes.php' => 35, '/product.php' => 35, '/' => 20, '/reviews.php' => 10],
    ];
    private const BOUNCE_RATE = [
        '/' => 0.3, '/catalog.php' => 0.26, '/product.php' => 0.42, '/recipes.php' => 0.48,
        '/delivery.php' => 0.55, '/contacts.php' => 0.6, '/reviews.php' => 0.4, '/account.php' => 0.2,
    ];
    private const TRANSITIONS = [
        '/' => ['/catalog.php' => 45, '/product.php' => 20, '/recipes.php' => 10, '/delivery.php' => 6, '/reviews.php' => 6, '/contacts.php' => 3, '/register.php' => 2, 'exit' => 10],
        '/catalog.php' => ['/product.php' => 55, '/catalog.php' => 14, '/cart.php' => 4, '/' => 4, 'exit' => 23],
        '/product.php' => ['/product.php' => 26, '/catalog.php' => 21, '/cart.php' => 11, '/reviews.php' => 4, 'exit' => 38],
        '/cart.php' => ['/checkout.php' => 38, '/catalog.php' => 20, '/product.php' => 9, 'exit' => 33],
        '/recipes.php' => ['/product.php' => 32, '/catalog.php' => 18, '/recipes.php' => 10, '/' => 5, 'exit' => 35],
        '/delivery.php' => ['/catalog.php' => 30, '/contacts.php' => 15, '/' => 10, 'exit' => 45],
        '/contacts.php' => ['/' => 20, '/catalog.php' => 15, 'exit' => 65],
        '/reviews.php' => ['/product.php' => 35, '/catalog.php' => 20, 'exit' => 45],
        '/account.php' => ['/catalog.php' => 35, '/' => 15, '/account.php' => 10, 'exit' => 40],
        '/login.php' => ['/account.php' => 70, 'exit' => 30],
        '/register.php' => ['/account.php' => 75, 'exit' => 25],
    ];
    // Среднее время на странице (с) и базовое время ответа сервера (мс)
    private const DWELL_SECONDS = [
        '/' => 30, '/catalog.php' => 45, '/product.php' => 60, '/recipes.php' => 90, '/cart.php' => 50,
        '/checkout.php' => 120, '/login.php' => 25, '/register.php' => 75, '/account.php' => 45,
        '/delivery.php' => 50, '/contacts.php' => 30, '/reviews.php' => 60,
    ];
    private const RESPONSE_MS = [
        '/' => 28, '/catalog.php' => 34, '/product.php' => 26, '/recipes.php' => 30, '/cart.php' => 18,
        '/checkout.php' => 32, '/login.php' => 16, '/register.php' => 18, '/account.php' => 30,
        '/delivery.php' => 12, '/contacts.php' => 11, '/reviews.php' => 22, '/admin/' => 45,
        '/admin/table.php' => 38, '/admin/stats.php' => 85,
    ];
    private const DB_QUERIES = [
        '/' => 2, '/catalog.php' => 3, '/product.php' => 2, '/recipes.php' => 1, '/cart.php' => 0,
        '/checkout.php' => 2, '/login.php' => 1, '/register.php' => 2, '/account.php' => 4,
        '/delivery.php' => 0, '/contacts.php' => 0, '/reviews.php' => 1, '/admin/' => 22,
        '/admin/table.php' => 5, '/admin/stats.php' => 34,
    ];
    private const SEARCH_TERMS = [
        'паприка' => 12, 'куркума' => 10, 'корица' => 10, 'перец' => 9, 'карри' => 8, 'хмели-сунели' => 6,
        'кардамон' => 6, 'чеснок' => 5, 'имбирь' => 4, 'аджика' => 4, 'базилик' => 4, 'зира' => 4,
        'кориандр' => 3, 'мускатный орех' => 3, 'ваниль' => 3, 'шафран' => 3, 'приправа для курицы' => 3,
        'гвоздика' => 2, 'лавровый лист' => 2, 'орегано' => 2, 'розмарин' => 2, 'гарам масала' => 2,
        'сумах' => 2, 'бадьян' => 2, 'тмин' => 2, 'тимьян' => 2, 'смесь для плова' => 2,
        'паприка копченая' => 2, 'мята' => 1, 'кари' => 1,
    ];
    private const POPULAR_SLUGS = [
        'smoked-paprika', 'ground-turmeric', 'ceylon-cinnamon', 'khmeli-suneli', 'ground-chili-pepper',
        'madras-curry', 'black-peppercorns', 'dried-garlic', 'ground-cardamom', 'dry-adjika',
        'garam-masala', 'cumin-seeds', 'coriander-seeds', 'sweet-paprika', 'nutmeg-ground',
    ];
    private const CITIES = [
        'Москва' => 55, 'Химки' => 6, 'Балашиха' => 6, 'Подольск' => 5, 'Мытищи' => 5, 'Люберцы' => 4,
        'Королёв' => 4, 'Красногорск' => 4, 'Одинцово' => 3, 'Санкт-Петербург' => 4, 'Казань' => 2, 'Нижний Новгород' => 2,
    ];
    private const STREETS = [
        'ул. Садовая', 'ул. Лесная', 'пр-т Мира', 'ул. Центральная', 'ул. Советская', 'ул. Молодёжная',
        'ул. Школьная', 'ул. Парковая', 'ул. Гагарина', 'ул. Пушкина', 'ул. Новая', 'ул. Ленина',
    ];
    private const MALE_NAMES = ['Алексей', 'Дмитрий', 'Иван', 'Сергей', 'Андрей', 'Максим', 'Николай', 'Павел', 'Артём', 'Егор', 'Михаил', 'Роман', 'Кирилл', 'Олег'];
    private const FEMALE_NAMES = ['Анна', 'Мария', 'Екатерина', 'Ольга', 'Наталья', 'Елена', 'Татьяна', 'Ирина', 'Светлана', 'Юлия', 'Дарья', 'Полина', 'Ксения', 'Алина'];
    private const SURNAMES = ['Иванов', 'Смирнов', 'Кузнецов', 'Попов', 'Васильев', 'Петров', 'Соколов', 'Михайлов', 'Новиков', 'Фёдоров', 'Морозов', 'Волков', 'Алексеев', 'Лебедев', 'Семёнов', 'Егоров', 'Павлов', 'Козлов', 'Степанов', 'Николаев', 'Орлов', 'Андреев', 'Макаров', 'Никитин', 'Захаров'];
    private const REVIEW_TEXTS = [
        5 => ['Отличный аромат, буду заказывать ещё.', 'Свежая специя, упаковка удобная.', 'Очень понравилось, добавляю почти во все блюда.', 'Насыщенный вкус, рекомендую.'],
        4 => ['Хорошее качество, но упаковка могла бы быть больше.', 'Вкус понравился, доставка немного задержалась.', 'Приятный аромат, цена адекватная.'],
        3 => ['Нормально, но ожидал более яркого вкуса.', 'Средне: аромат слабоват.'],
        2 => ['Аромат быстро выдыхается после вскрытия.', 'Не понравился помол, слишком крупный.'],
        1 => ['Пришла повреждённая упаковка.', 'Вкус не соответствует описанию.'],
    ];
    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    private array $products = [];
    private array $productWeights = [];
    private array $productIndex = [];
    private array $visitors = [];
    private array $customers = [];
    private array $hits = [];
    private array $authEvents = [];
    private array $searches = [];
    private array $apiRequests = [];
    private array $errors = [];
    private array $healthChecks = [];
    private array $created = [];
    private int $userCounter = 0;
    private int $orderCounter = 0;
    private int $adminUserId;
    private int $now;
    private int $limit = 0;
    private string $passwordHash;
    private float $diskFreeMb;

    public function __construct(private readonly PDO $pdo, private readonly int $days, int $seed)
    {
        mt_srand($seed);
        $this->now = time();
        // демо-пользователи не могут войти: пароль случайный и нигде не сохраняется
        $this->passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $this->diskFreeMb = (float)(disk_free_space(dirname(__DIR__)) ?: 50_000 * 1024 * 1024) / 1024 / 1024;
        $this->adminUserId = (int)($pdo->query("SELECT user_id FROM users WHERE role = 'admin' ORDER BY user_id LIMIT 1")->fetchColumn() ?: 0);
        $this->loadProducts();
    }

    public function purge(): array
    {
        $deleted = [];
        $statements = [
            'site_visits' => 'DELETE FROM site_visits WHERE is_demo = 1',
            'auth_events' => 'DELETE FROM auth_events WHERE is_demo = 1',
            'search_queries' => 'DELETE FROM search_queries WHERE is_demo = 1',
            'api_requests' => 'DELETE FROM api_requests WHERE is_demo = 1',
            'app_errors' => 'DELETE FROM app_errors WHERE is_demo = 1',
            'health_checks' => 'DELETE FROM health_checks WHERE is_demo = 1',
            // позиции заказов, отзывы и избранное удаляются каскадно по внешним ключам
            'orders' => "DELETE FROM orders WHERE order_number LIKE 'DM-%'",
            'users' => "DELETE FROM users WHERE email LIKE '%@demo.local'",
        ];

        foreach ($statements as $table => $sql) {
            $deleted[$table] = $this->pdo->exec($sql);
        }

        return $deleted;
    }

    public function run(): array
    {
        $today = strtotime('today');

        for ($daysAgo = $this->days - 1; $daysAgo >= 0; $daysAgo--) {
            $dayStart = strtotime("-$daysAgo days", $today);
            $this->limit = min($dayStart + 86400, $this->now);

            $this->pdo->beginTransaction();
            $this->simulateDay($dayStart, $daysAgo);
            $this->flush();
            $this->pdo->commit();
        }

        return $this->created;
    }

    private function loadProducts(): void
    {
        $rows = $this->pdo->query(
            'SELECT product_id, slug, name, price, stock_quantity FROM products WHERE is_active = 1 ORDER BY product_id'
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            throw new RuntimeException('В таблице products нет активных товаров.');
        }

        $ranked = [];
        foreach ($rows as $row) {
            $rank = array_search($row['slug'], self::POPULAR_SLUGS, true);
            $ranked[] = ['rank' => $rank === false ? count(self::POPULAR_SLUGS) + (int)$row['product_id'] : $rank] + $row;
        }
        usort($ranked, static fn(array $a, array $b): int => $a['rank'] <=> $b['rank']);

        foreach ($ranked as $position => $product) {
            $this->products[(int)$product['product_id']] = $product;
            // популярность товаров убывает по закону, близкому к закону Ципфа
            $this->productWeights[(int)$product['product_id']] = 1 / (($position + 1) ** 0.85);
            $this->productIndex[] = [
                'id' => (int)$product['product_id'],
                'name' => str_replace('ё', 'е', mb_strtolower((string)$product['name'])),
            ];
        }
    }

    private function simulateDay(int $dayStart, int $daysAgo): void
    {
        $progress = 1 - $daysAgo / max(1, $this->days - 1);
        $factor = self::WEEKDAY_FACTOR[(int)date('N', $dayStart)] * max(0.6, $this->gauss(1.0, 0.1));

        if ($daysAgo === self::PROMO_DAY) {
            $factor *= 1.9;
        } elseif ($daysAgo === self::PROMO_DAY - 1) {
            $factor *= 1.35;
        }

        $sessions = (int)round((70 + 50 * $progress) * $factor);
        for ($i = 0; $i < $sessions; $i++) {
            $start = $dayStart + $this->randomSecondOfDay();
            if ($start < $this->limit) {
                $this->simulateSession($start, $daysAgo === self::PROMO_DAY);
            }
        }

        if ($this->rnd() < 0.15) {
            $this->simulateAdminDenied($dayStart + $this->randomSecondOfDay());
        }

        $this->simulateBots($dayStart);
        $this->simulateAdminWork($dayStart);
        $this->simulateIncidents($dayStart, $daysAgo);
        $this->simulateHealthChecks($dayStart, $daysAgo);
    }

    private function simulateSession(int $start, bool $promo): void
    {
        $visitor = $this->pickVisitor($start);
        $sessionId = $this->hexId();
        $source = $this->pick($promo ? self::PROMO_SOURCES : self::SOURCES);
        $host = $source === 'direct' ? null : $this->pick(self::REFERRER_HOSTS[$source]);
        $page = $this->pick(self::ENTRY_PAGES[$source]);
        $loggedIn = $visitor['user_id'] !== null && $this->rnd() < 0.55;
        $time = $start;
        $hitsCount = 0;
        $viewed = [];

        $hit = function (string $page, array $extra = []) use (&$time, &$hitsCount, &$source, &$host, $sessionId, &$visitor, &$loggedIn): void {
            if ($time >= $this->limit) {
                return;
            }
            $this->addHit($visitor, $sessionId, $page, $time, $hitsCount === 0 ? $source : 'internal', $hitsCount === 0 ? $host : '127.0.0.1', $loggedIn ? $visitor['user_id'] : null, $extra);
            $hitsCount++;
        };

        $bounce = $this->rnd() < (self::BOUNCE_RATE[$page] ?? 0.4);

        if ($page === '/account.php' && !$loggedIn) {
            $page = '/login.php';
        }

        while ($time < $this->limit && $hitsCount < 14) {
            if ($page === '/checkout.php') {
                $this->checkoutFlow($visitor, $time, $loggedIn, $viewed, $hit);
                break;
            }

            if ($page === '/login.php') {
                if ($visitor['user_id'] === null) {
                    $hit('/login.php');
                    break;
                }
                $this->loginFlow($visitor, $time, $hit);
                $loggedIn = true;
            } elseif ($page === '/register.php') {
                if ($visitor['user_id'] !== null) {
                    $page = '/catalog.php';
                    continue;
                }
                $hit('/register.php');
                $time += $this->dwell('/register.php');
                $this->registerVisitor($visitor, $time);
                $loggedIn = true;
            } elseif ($page === '/product.php') {
                $productId = $this->pickProduct();
                $viewed[] = $productId;
                $hit('/product.php', ['query_string' => 'slug=' . $this->products[$productId]['slug'], 'product_id' => $productId]);
                if ($loggedIn && $this->rnd() < 0.03) {
                    $this->addFavorite((int)$visitor['user_id'], $productId, $time);
                }
            } elseif ($page === '/catalog.php') {
                $this->catalogHit($visitor, $time, $loggedIn, $hit);
            } elseif ($page === '/recipes.php') {
                $apiMs = $this->apiRequest($time);
                $hit('/recipes.php', ['response_extra_ms' => $apiMs]);
            } else {
                $hit($page);
            }

            if ($hitsCount === 1 && $this->rnd() < 0.006) {
                $time += $this->dwell($page);
                $this->brokenLinkHit($hit);
            }

            if ($bounce && $hitsCount === 1) {
                break;
            }

            $next = $this->pick($this->transitions($page, $visitor['user_id'] !== null));
            if ($next === 'exit') {
                break;
            }

            // вернувшийся покупатель часто сразу входит в личный кабинет
            if ($visitor['user_id'] !== null && !$loggedIn && $hitsCount === 1 && $this->rnd() < 0.3) {
                $next = '/login.php';
            }

            if ($next === '/account.php' && !$loggedIn) {
                $next = '/login.php';
            }

            $time += $this->dwell($page);
            $page = $next;
        }

        if ($loggedIn && $hitsCount > 1 && $this->rnd() < 0.06) {
            $time += $this->dwell('/account.php');
            $this->addAuthEvent('logout', $visitor, $time);
            $hit('/login.php');
        }
    }

    private function checkoutFlow(array &$visitor, int &$time, bool &$loggedIn, array $viewed, callable $hit): void
    {
        if (!$loggedIn) {
            if ($visitor['user_id'] !== null) {
                $this->loginFlow($visitor, $time, $hit);
            } else {
                $hit('/register.php');
                $time += $this->dwell('/register.php');
                // каждый четвёртый новый посетитель не заканчивает регистрацию
                if ($this->rnd() < 0.25) {
                    return;
                }
                $this->registerVisitor($visitor, $time);
            }
            $loggedIn = true;
            $time += $this->dwell('/login.php');
        }

        $hit('/checkout.php');
        $time += $this->dwell('/checkout.php');

        if ($time >= $this->limit || $this->rnd() >= 0.6) {
            return;
        }

        $this->createOrder($visitor, $time, $viewed);
        $hit('/checkout.php', ['query_string' => null]);

        if ($this->rnd() < 0.7) {
            $time += $this->dwell('/checkout.php');
            $hit('/account.php');
        }
    }

    private function loginFlow(array $visitor, int &$time, callable $hit): void
    {
        $hit('/login.php');
        $time += $this->dwell('/login.php');

        // часть покупателей ошибается в пароле с первой попытки
        if ($this->rnd() < 0.07) {
            $this->addAuthEvent('login_failed', $visitor, $time, false);
            $hit('/login.php');
            $time += $this->dwell('/login.php');
        }

        $this->addAuthEvent('login_success', $visitor, $time);
    }

    // Покупатели с аккаунтом добавляют товары в корзину примерно вдвое чаще новых посетителей
    private function transitions(string $page, bool $isCustomer): array
    {
        $weights = self::TRANSITIONS[$page] ?? ['exit' => 1];

        if ($isCustomer && isset($weights['/cart.php'])) {
            $weights['/cart.php'] *= 2.3;
        }

        return $weights;
    }

    private function catalogHit(array $visitor, int $time, bool $loggedIn, callable $hit): void
    {
        $roll = $this->rnd();

        if ($roll < 0.28) {
            $term = $this->pick(self::SEARCH_TERMS);
            $results = $this->countSearchResults($term);
            $hit('/catalog.php', ['query_string' => 'search=' . rawurlencode($term)]);
            $this->searches[] = [
                'query_text' => $term,
                'results_count' => $results,
                'category_slug' => null,
                'visitor_id' => $visitor['visitor_id'],
                'user_id' => $loggedIn ? $visitor['user_id'] : null,
                'is_demo' => 1,
                'created_at' => $this->timestamp($time),
            ];
            return;
        }

        if ($roll < 0.42) {
            $categories = ['hot-spices', 'herbs', 'spice-mixes', 'baking-desserts', 'universal-spices'];
            $hit('/catalog.php', ['query_string' => 'category=' . $categories[mt_rand(0, count($categories) - 1)]]);
            return;
        }

        $hit('/catalog.php');
    }

    private function brokenLinkHit(callable $hit): void
    {
        if ($this->rnd() < 0.5) {
            $hit('/product.php', ['query_string' => 'slug=zira', 'http_status' => 404]);
        } else {
            $hit('/sale.php', ['http_status' => 404]);
        }
    }

    private function addHit(array $visitor, string $sessionId, string $page, int $time, string $referrerType, ?string $referrerHost, ?int $userId, array $extra = []): void
    {
        $baseMs = self::RESPONSE_MS[$page] ?? 15;
        $responseMs = $baseMs * $this->lognormal(1.0, 0.35);
        // редкие медленные ответы сервера, время ожидания API добавляется отдельно
        if ($this->rnd() < 0.015) {
            $responseMs *= 4 + 4 * $this->rnd();
        }
        $responseMs += (float)($extra['response_extra_ms'] ?? 0);

        $this->hits[] = [
            'visitor_id' => $visitor['visitor_id'],
            'session_id' => $sessionId,
            'user_id' => $userId,
            'page' => $page,
            'query_string' => $extra['query_string'] ?? null,
            'product_id' => $extra['product_id'] ?? null,
            'http_status' => $extra['http_status'] ?? 200,
            'referrer_type' => $referrerType,
            'referrer_host' => $referrerHost,
            'device_type' => $visitor['device'],
            'browser' => $visitor['browser'],
            'os' => $visitor['os'],
            'is_bot' => $extra['is_bot'] ?? 0,
            'is_admin_area' => str_starts_with($page, '/admin') ? 1 : 0,
            'ip_hash' => $visitor['ip_hash'],
            'response_time_ms' => round($responseMs, 2),
            'memory_kb' => $this->rnd() < 0.9 ? 2048 : 4096,
            'db_queries' => self::DB_QUERIES[$page] ?? 0,
            'is_demo' => 1,
            'created_at' => $this->timestamp($time),
        ];
    }

    private function pickVisitor(int $time): array
    {
        // около трети визитов делают вернувшиеся посетители, почти половина из них — покупатели с аккаунтом
        if ($this->visitors && $this->rnd() < 0.33) {
            $pool = $this->customers && $this->rnd() < 0.45 ? $this->customers : $this->visitors;
            $ids = array_keys($pool);

            return $pool[$ids[mt_rand(0, count($ids) - 1)]];
        }

        $device = $this->pick(self::DEVICES);
        $browser = $this->pick(self::BROWSERS[$device]);
        $os = match ($device) {
            'desktop' => $browser === 'Safari' ? 'macOS' : $this->pick(['Windows' => 84, 'macOS' => 11, 'Linux' => 5]),
            'mobile' => $browser === 'Safari' ? 'iOS' : ($browser === 'Chrome' && $this->rnd() < 0.12 ? 'iOS' : 'Android'),
            default => $browser === 'Safari' ? 'iPadOS' : 'Android',
        };

        $visitor = [
            'visitor_id' => $this->hexId(),
            'device' => $device,
            'browser' => $browser,
            'os' => $os,
            'ip_hash' => hash('sha256', 'demo|' . $this->hexId()),
            'user_id' => null,
            'user' => null,
        ];

        if (count($this->visitors) > 1500) {
            array_shift($this->visitors);
        }
        $this->visitors[$visitor['visitor_id']] = $visitor;

        return $visitor;
    }

    private function registerVisitor(array &$visitor, int $time): void
    {
        $this->userCounter++;
        $female = $this->rnd() < 0.62;
        $firstName = $female ? self::FEMALE_NAMES[mt_rand(0, count(self::FEMALE_NAMES) - 1)] : self::MALE_NAMES[mt_rand(0, count(self::MALE_NAMES) - 1)];
        $surname = self::SURNAMES[mt_rand(0, count(self::SURNAMES) - 1)] . ($female ? 'а' : '');
        $email = sprintf(
            '%s.%s.%d@demo.local',
            $this->translit(mb_substr($firstName, 0, 1)),
            $this->translit($surname),
            $this->userCounter
        );
        $city = $this->pick(self::CITIES);
        $user = [
            'full_name' => $firstName . ' ' . $surname,
            'email' => $email,
            'phone' => sprintf('+7 (9%02d) %03d-%02d-%02d', mt_rand(10, 99), mt_rand(100, 999), mt_rand(0, 99), mt_rand(0, 99)),
            'city' => $city,
            'address' => sprintf('%s, д. %d, кв. %d', self::STREETS[mt_rand(0, count(self::STREETS) - 1)], mt_rand(1, 60), mt_rand(1, 180)),
        ];

        $statement = $this->pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, city, address, created_at)
             VALUES (:full_name, :email, :phone, :password_hash, "customer", :city, :address, :created_at)'
        );
        $statement->execute($user + ['password_hash' => $this->passwordHash, 'created_at' => $this->timestamp($time)]);

        $visitor['user_id'] = (int)$this->pdo->lastInsertId();
        $visitor['user'] = $user;
        unset($this->visitors[$visitor['visitor_id']]);
        $this->customers[$visitor['visitor_id']] = $visitor;
        $this->count('users');
        $this->addAuthEvent('register', $visitor, $time);
    }

    private function createOrder(array $visitor, int $time, array $viewed): void
    {
        $inStock = array_keys(array_filter($this->products, static fn(array $p): bool => (int)$p['stock_quantity'] > 0));
        $items = [];

        foreach (array_reverse($viewed) as $productId) {
            if (count($items) < 2 && in_array($productId, $inStock, true) && $this->rnd() < 0.8) {
                $items[$productId] = true;
            }
        }

        $wanted = min((int)$this->pick([1 => 42, 2 => 33, 3 => 17, 4 => 8]), count($inStock));
        while (count($items) < $wanted) {
            $productId = $this->pickProduct();
            if (in_array($productId, $inStock, true)) {
                $items[$productId] = true;
            }
        }

        $lines = [];
        $total = 0.0;
        foreach (array_keys($items) as $productId) {
            $quantity = $this->pick([1 => 75, 2 => 20, 3 => 5]);
            $price = (float)$this->products[$productId]['price'];
            $lines[] = ['product_id' => $productId, 'quantity' => $quantity, 'unit_price' => $price, 'line_total' => $price * $quantity];
            $total += $price * $quantity;
        }

        $ageDays = ($this->now - $time) / 86400;
        $status = match (true) {
            $ageDays <= 1 => $this->pick(['new' => 65, 'processing' => 30, 'cancelled' => 5]),
            $ageDays <= 4 => $this->pick(['new' => 10, 'processing' => 55, 'completed' => 28, 'cancelled' => 7]),
            default => $this->pick(['completed' => 88, 'cancelled' => 8, 'processing' => 4]),
        };

        $this->orderCounter++;
        $user = $visitor['user'] ?? $this->loadUser((int)$visitor['user_id']);
        $delivery = $this->pick(['courier' => 62, 'pickup' => 38]);

        $statement = $this->pdo->prepare(
            'INSERT INTO orders (user_id, order_number, customer_name, phone, delivery_address, delivery_method, payment_method, status, total_amount, created_at)
             VALUES (:user_id, :order_number, :customer_name, :phone, :delivery_address, :delivery_method, :payment_method, :status, :total_amount, :created_at)'
        );
        $statement->execute([
            'user_id' => $visitor['user_id'],
            'order_number' => sprintf('DM-%s-%04d', date('Ymd', $time), $this->orderCounter),
            'customer_name' => $user['full_name'],
            'phone' => $user['phone'],
            'delivery_address' => $delivery === 'pickup' ? 'Самовывоз: г. Москва, ул. Багратионова, д. 12/11' : $user['city'] . ', ' . $user['address'],
            'delivery_method' => $delivery,
            'payment_method' => $this->pick(['card_on_delivery' => 58, 'cash' => 42]),
            'status' => $status,
            'total_amount' => $total,
            'created_at' => $this->timestamp($time),
        ]);
        $orderId = (int)$this->pdo->lastInsertId();

        $itemStatement = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price, line_total)
             VALUES (:order_id, :product_id, :quantity, :unit_price, :line_total)'
        );
        foreach ($lines as $line) {
            $itemStatement->execute($line + ['order_id' => $orderId]);
        }

        $this->count('orders');
        $this->count('order_items', count($lines));

        if ($status === 'completed' && $this->rnd() < 0.14) {
            $this->addReview((int)$visitor['user_id'], (int)$lines[0]['product_id'], $time + mt_rand(3, 10) * 86400);
        }
    }

    private function loadUser(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT full_name, phone, city, address FROM users WHERE user_id = :id');
        $statement->execute(['id' => $userId]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'Покупатель', 'phone' => '+7 (900) 000-00-00', 'city' => 'Москва', 'address' => ''];
    }

    private function addReview(int $userId, int $productId, int $time): void
    {
        if ($time >= $this->now) {
            return;
        }

        $rating = $this->pick([5 => 55, 4 => 28, 3 => 10, 2 => 4, 1 => 3]);
        $status = ($this->now - $time) < 5 * 86400 ? 'new' : ($this->rnd() < 0.03 ? 'hidden' : 'published');
        $texts = self::REVIEW_TEXTS[$rating];

        $statement = $this->pdo->prepare(
            'INSERT INTO reviews (user_id, product_id, rating, review_text, status, created_at)
             VALUES (:user_id, :product_id, :rating, :review_text, :status, :created_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'product_id' => $productId,
            'rating' => $rating,
            'review_text' => $texts[mt_rand(0, count($texts) - 1)],
            'status' => $status,
            'created_at' => $this->timestamp($time),
        ]);
        $this->count('reviews');
    }

    private function addFavorite(int $userId, int $productId, int $time): void
    {
        $statement = $this->pdo->prepare(
            'INSERT IGNORE INTO favorites (user_id, product_id, created_at) VALUES (:user_id, :product_id, :created_at)'
        );
        $statement->execute(['user_id' => $userId, 'product_id' => $productId, 'created_at' => $this->timestamp($time)]);
        $this->count('favorites', $statement->rowCount());
    }

    private function addAuthEvent(string $type, array $visitor, int $time, bool $withUser = true): void
    {
        if ($time >= $this->limit) {
            return;
        }

        $email = $visitor['user']['email'] ?? null;
        $this->authEvents[] = [
            'event_type' => $type,
            'user_id' => $withUser ? $visitor['user_id'] : null,
            'email_masked' => $email ? stats_mask_email($email) : null,
            'ip_hash' => $visitor['ip_hash'],
            'user_agent' => $this->userAgent($visitor),
            'is_demo' => 1,
            'created_at' => $this->timestamp($time),
        ];
    }

    // Запрос к TheMealDB при открытии страницы рецептов; возвращает время ожидания ответа в мс
    private function apiRequest(int $time): float
    {
        $hour = (int)date('G', $time);
        $daysAgo = (int)floor((strtotime('today') - strtotime('today', $time)) / 86400);
        $outage = $daysAgo === self::API_OUTAGE_DAY && $hour >= 10 && ($hour < 12 || ($hour === 12 && (int)date('i', $time) < 40));
        $failed = $outage || $this->rnd() < 0.04;

        if ($failed) {
            $timeout = $this->rnd() < 0.7;
            $responseMs = $timeout ? 3000 + 120 * $this->rnd() : 180 + 400 * $this->rnd();
            $status = $timeout ? null : ($this->rnd() < 0.5 ? 503 : 502);
            $error = $timeout ? 'Operation timed out after 3000 milliseconds' : 'HTTP ' . $status;
        } else {
            $responseMs = min(2500, max(120, $this->lognormal(320, 0.35)));
            $status = 200;
            $error = null;
        }

        if ($time < $this->limit) {
            $this->apiRequests[] = [
                'service' => 'TheMealDB',
                'endpoint' => 'https://www.themealdb.com/api/json/v1/1/lookup.php?i=52771',
                'http_status' => $status,
                'response_time_ms' => round($responseMs, 2),
                'is_success' => $failed ? 0 : 1,
                'used_fallback' => $failed ? 1 : 0,
                'error_message' => $error,
                'is_demo' => 1,
                'created_at' => $this->timestamp($time),
            ];
        }

        return $responseMs;
    }

    private function simulateAdminDenied(int $time): void
    {
        $customers = array_values($this->customers);
        if (!$customers || $time >= $this->limit) {
            return;
        }

        $visitor = $customers[mt_rand(0, count($customers) - 1)];
        $sessionId = $this->hexId();
        $this->addHit($visitor, $sessionId, '/', $time, 'direct', null, $visitor['user_id']);
        $time += mt_rand(5, 40);
        $this->addHit($visitor, $sessionId, '/admin/', $time, 'internal', '127.0.0.1', $visitor['user_id'], ['http_status' => 403]);
        $this->addAuthEvent('admin_denied', $visitor, $time);
    }

    private function simulateBots(int $dayStart): void
    {
        $bots = ['YandexBot' => 60, 'Googlebot' => 40];
        $pages = ['/', '/catalog.php', '/product.php', '/recipes.php', '/delivery.php', '/contacts.php', '/reviews.php'];
        $count = mt_rand(15, 40);

        for ($i = 0; $i < $count; $i++) {
            $time = $dayStart + mt_rand(0, 86399);
            if ($time >= $this->limit) {
                continue;
            }
            $bot = $this->pick($bots);
            $visitor = [
                'visitor_id' => $this->hexId(),
                'device' => 'desktop',
                'browser' => 'Другой',
                'os' => 'Другая',
                'ip_hash' => hash('sha256', 'bot|' . $bot),
                'user_id' => null,
            ];
            $page = $pages[mt_rand(0, count($pages) - 1)];
            $extra = ['is_bot' => 1];
            if ($page === '/product.php') {
                $productId = $this->pickProduct();
                $extra += ['query_string' => 'slug=' . $this->products[$productId]['slug'], 'product_id' => $productId];
            }
            $this->addHit($visitor, $this->hexId(), $page, $time, 'direct', null, null, $extra);
        }
    }

    private function simulateAdminWork(int $dayStart): void
    {
        if ($this->adminUserId === 0) {
            return;
        }

        $visitor = [
            'visitor_id' => hash('md5', 'demo-admin'),
            'device' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'ip_hash' => hash('sha256', 'demo|admin'),
            'user_id' => $this->adminUserId,
        ];
        $sessionId = $this->hexId();
        $time = $dayStart + mt_rand(9, 11) * 3600 + mt_rand(0, 3599);
        $pages = ['/admin/', '/admin/table.php', '/admin/table.php', '/admin/stats.php'];

        $hits = mt_rand(4, 14);
        for ($i = 0; $i < $hits && $time < $this->limit; $i++) {
            $page = $i === 0 ? '/admin/' : $pages[mt_rand(0, count($pages) - 1)];
            $this->addHit($visitor, $sessionId, $page, $time, $i === 0 ? 'direct' : 'internal', $i === 0 ? null : '127.0.0.1', $this->adminUserId);
            $time += mt_rand(20, 240);
        }
    }

    private function simulateIncidents(int $dayStart, int $daysAgo): void
    {
        // фоновые предупреждения и замечания: в среднем меньше одного в день
        $background = [
            ['notice', 'Only variables should be passed by reference', '/public/cart.php', 12, '/cart.php'],
            ['warning', 'Undefined array key "strIngredient9"', '/src/data.php', 790, '/recipes.php'],
            ['notice', 'Undefined variable $message', '/public/checkout.php', 71, '/checkout.php'],
        ];
        if ($this->rnd() < 0.55) {
            [$level, $message, $file, $line, $page] = $background[mt_rand(0, count($background) - 1)];
            $this->addError($level, $message, $file, $line, $page, $dayStart + $this->randomSecondOfDay());
        }

        if ($daysAgo === self::SESSION_ERRORS_DAY) {
            // сбой файлового хранилища сессий: серия предупреждений за полчаса
            for ($i = 0; $i < 9; $i++) {
                $this->addError('warning', 'session_start(): Failed to read session data: files (path: storage/sessions)', '/src/layout.php', 19, '/account.php', $dayStart + 11 * 3600 + 1200 + $i * 200);
            }
        }

        if ($daysAgo === self::MEMORY_ERROR_DAY) {
            $time = $dayStart + 16 * 3600 + 1234;
            $this->addError('fatal', 'Allowed memory size of 536870912 bytes exhausted (tried to allocate 20480 bytes)', '/src/data.php', 318, '/catalog.php', $time);
            $visitor = $this->pickVisitor($time);
            $this->addHit($visitor, $this->hexId(), '/catalog.php', $time, 'direct', null, null, ['http_status' => 500, 'query_string' => 'search=%D0%BF']);
        }

        if ($daysAgo === self::BRUTE_FORCE_DAY) {
            // подбор пароля: 28 неудачных попыток входа с одного адреса за 9 минут
            $attacker = ['visitor_id' => $this->hexId(), 'device' => 'desktop', 'browser' => 'Другой', 'os' => 'Другая', 'ip_hash' => hash('sha256', 'demo|attacker'), 'user_id' => null];
            $emails = ['admin@spice-market.ru', 'sultan@example.com', 'admin@example.com', 'root@spice-market.ru'];
            for ($i = 0; $i < 28; $i++) {
                $time = $dayStart + 3 * 3600 + 600 + $i * 19;
                $this->authEvents[] = [
                    'event_type' => 'login_failed',
                    'user_id' => null,
                    'email_masked' => stats_mask_email($emails[$i % count($emails)]),
                    'ip_hash' => $attacker['ip_hash'],
                    'user_agent' => 'python-requests/2.31.0',
                    'is_demo' => 1,
                    'created_at' => $this->timestamp($time),
                ];
                $this->addHit($attacker, $this->hexId(), '/login.php', $time, 'direct', null, null, ['is_bot' => 1]);
            }
        }
    }

    // Проверки доступности каждые 15 минут: сайт, база данных, внешний API, свободное место на диске
    private function simulateHealthChecks(int $dayStart, int $daysAgo): void
    {
        for ($time = $dayStart; $time < $this->limit; $time += 900) {
            $hour = (int)date('G', $time);
            $minute = (int)date('i', $time);
            $dbDown = $daysAgo === self::DB_OUTAGE_DAY && $hour === 14 && $minute >= 5 && $minute < 40;
            $apiDown = ($daysAgo === self::API_OUTAGE_DAY && ($hour === 10 || $hour === 11 || ($hour === 12 && $minute < 40)))
                || $this->rnd() < 0.004;
            $freeMb = $this->diskFreeMb + $daysAgo * 35 + 400 * $this->rnd();

            $this->healthChecks[] = [
                'site_ok' => 1,
                'site_status' => 200,
                'site_response_ms' => round($this->lognormal($dbDown ? 1030 : 38, 0.25), 2),
                'db_ok' => $dbDown ? 0 : 1,
                'db_response_ms' => $dbDown ? null : round($this->lognormal(1.8, 0.3), 2),
                'api_ok' => $apiDown ? 0 : 1,
                'api_response_ms' => round($apiDown ? 3000 + 100 * $this->rnd() : $this->lognormal(330, 0.3), 2),
                'disk_free_mb' => (int)round($freeMb),
                'is_demo' => 1,
                'created_at' => $this->timestamp($time),
            ];
        }
    }

    private function addError(string $level, string $message, string $file, int $line, string $page, int $time): void
    {
        if ($time >= $this->limit) {
            return;
        }

        $this->errors[] = [
            'level' => $level,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'page' => $page,
            'is_demo' => 1,
            'created_at' => $this->timestamp($time),
        ];
    }

    private function flush(): void
    {
        usort($this->hits, static fn(array $a, array $b): int => strcmp($a['created_at'], $b['created_at']));

        foreach ([
            'site_visits' => 'hits',
            'auth_events' => 'authEvents',
            'search_queries' => 'searches',
            'api_requests' => 'apiRequests',
            'app_errors' => 'errors',
            'health_checks' => 'healthChecks',
        ] as $table => $property) {
            $this->insertRows($table, $this->{$property});
            $this->count($table, count($this->{$property}));
            $this->{$property} = [];
        }
    }

    private function insertRows(string $table, array $rows): void
    {
        if (!$rows) {
            return;
        }

        $columns = array_keys($rows[0]);
        $rowPlaceholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        foreach (array_chunk($rows, 250) as $chunk) {
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $table,
                implode(', ', $columns),
                implode(', ', array_fill(0, count($chunk), $rowPlaceholders))
            );
            $params = [];
            foreach ($chunk as $row) {
                foreach ($columns as $column) {
                    $params[] = $row[$column];
                }
            }
            $this->pdo->prepare($sql)->execute($params);
        }
    }

    private function countSearchResults(string $term): int
    {
        $needle = str_replace('ё', 'е', mb_strtolower($term));

        return count(array_filter($this->productIndex, static fn(array $product): bool => str_contains($product['name'], $needle)));
    }

    private function pickProduct(): int
    {
        return (int)$this->pick($this->productWeights);
    }

    private function dwell(string $page): int
    {
        $mean = self::DWELL_SECONDS[$page] ?? 30;

        return (int)max(3, min(600, -$mean * log(max(0.0001, $this->rnd()))));
    }

    private function randomSecondOfDay(): int
    {
        return (int)$this->pick(self::HOUR_WEIGHTS) * 3600 + mt_rand(0, 3599);
    }

    private function userAgent(array $visitor): string
    {
        return match ($visitor['device']) {
            'mobile' => $visitor['os'] === 'iOS'
                ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1'
                : 'Mozilla/5.0 (Linux; Android 14; SM-A546B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36',
            'tablet' => 'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            default => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        };
    }

    private function translit(string $value): string
    {
        return strtr(mb_strtolower($value), self::TRANSLIT);
    }

    private function count(string $key, int $amount = 1): void
    {
        $this->created[$key] = ($this->created[$key] ?? 0) + $amount;
    }

    private function timestamp(int $time): string
    {
        return date('Y-m-d H:i:s', $time);
    }

    private function hexId(): string
    {
        return sprintf('%08x%08x%08x%08x', mt_rand(), mt_rand(), mt_rand(), mt_rand());
    }

    private function rnd(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private function pick(array $weights): string|int
    {
        $roll = $this->rnd() * array_sum($weights);

        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    private function gauss(float $mean, float $deviation): float
    {
        $u = max(1e-9, $this->rnd());
        $v = $this->rnd();

        return $mean + $deviation * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    private function lognormal(float $median, float $sigma): float
    {
        return $median * exp($this->gauss(0.0, $sigma));
    }
}

$options = getopt('', ['days::', 'seed::', 'purge']);
$days = max(7, min(365, (int)($options['days'] ?? 90)));
$seed = (int)($options['seed'] ?? 2026);

$pdo = spice_pdo();
if (!$pdo) {
    fwrite(STDERR, 'Нет подключения к MySQL: ' . (spice_db_error() ?? 'неизвестная ошибка') . PHP_EOL);
    exit(1);
}

$missing = array_diff(
    ['site_visits', 'auth_events', 'search_queries', 'api_requests', 'app_errors', 'health_checks'],
    $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
);
if ($missing) {
    fwrite(STDERR, 'Нет таблиц ' . implode(', ', $missing) . '. Сначала импортируйте database/stats_monitoring.sql.' . PHP_EOL);
    exit(1);
}

$seeder = new StatsDemoSeeder($pdo, $days, $seed);
$started = microtime(true);

echo 'Удаление старых демо-данных…' . PHP_EOL;
foreach ($seeder->purge() as $table => $rows) {
    echo sprintf('  %-15s %6d', $table, $rows) . PHP_EOL;
}

if (isset($options['purge'])) {
    echo 'Готово: демо-данные удалены.' . PHP_EOL;
    exit(0);
}

echo "Генерация истории за $days дн. (seed $seed)…" . PHP_EOL;
foreach ($seeder->run() as $table => $rows) {
    echo sprintf('  %-15s %6d', $table, $rows) . PHP_EOL;
}

echo sprintf('Готово за %.1f с.', microtime(true) - $started) . PHP_EOL;

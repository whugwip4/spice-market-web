<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function spice_fallback_categories(): array
{
    return [
        ['category_id' => 0, 'name' => 'Все', 'slug' => '', 'description' => 'Все товары каталога'],
        ['category_id' => 3, 'name' => 'Смеси', 'slug' => 'spice-mixes', 'description' => 'Готовые смеси специй'],
        ['category_id' => 1, 'name' => 'Для мяса', 'slug' => 'hot-spices', 'description' => 'Острые и насыщенные приправы'],
        ['category_id' => 4, 'name' => 'Для выпечки', 'slug' => 'baking-desserts', 'description' => 'Ароматные специи для десертов'],
        ['category_id' => 2, 'name' => 'Травы', 'slug' => 'herbs', 'description' => 'Сушеные травы'],
        ['category_id' => 5, 'name' => 'Универсальные', 'slug' => 'all-purpose-spices', 'description' => 'Специи на каждый день'],
    ];
}

function spice_fallback_products(): array
{
    return [
        [
            'product_id' => 5,
            'category_id' => 5,
            'country_id' => 5,
            'category_name' => 'Универсальные специи',
            'category_slug' => 'all-purpose-spices',
            'country_name' => 'Испания',
            'name' => 'Паприка копченая',
            'slug' => 'smoked-paprika',
            'short_description' => 'Сладковато-копченая специя для мяса и овощей.',
            'description' => 'Копченая паприка придает блюдам теплый цвет и легкий дымный аромат.',
            'weight_grams' => 60,
            'price' => 179.00,
            'stock_quantity' => 32,
            'heat_level' => 1,
        ],
        [
            'product_id' => 10,
            'category_id' => 4,
            'country_id' => 1,
            'category_name' => 'Выпечка и десерты',
            'category_slug' => 'baking-desserts',
            'country_name' => 'Индия',
            'name' => 'Кардамон молотый',
            'slug' => 'ground-cardamom',
            'short_description' => 'Пряная специя для выпечки, кофе и восточных блюд.',
            'description' => 'Молотый кардамон добавляют в сладкие блюда, чай, кофе, рис и пряные смеси.',
            'weight_grams' => 35,
            'price' => 279.00,
            'stock_quantity' => 12,
            'heat_level' => 1,
        ],
        [
            'product_id' => 4,
            'category_id' => 4,
            'country_id' => 4,
            'category_name' => 'Выпечка и десерты',
            'category_slug' => 'baking-desserts',
            'country_name' => 'Шри-Ланка',
            'name' => 'Корица цейлонская',
            'slug' => 'ceylon-cinnamon',
            'short_description' => 'Мягкая ароматная корица для десертов и напитков.',
            'description' => 'Цейлонская корица подходит для выпечки, каш, кофе, чая и фруктовых блюд.',
            'weight_grams' => 40,
            'price' => 249.00,
            'stock_quantity' => 14,
            'heat_level' => 0,
        ],
        [
            'product_id' => 6,
            'category_id' => 5,
            'country_id' => 1,
            'category_name' => 'Универсальные специи',
            'category_slug' => 'all-purpose-spices',
            'country_name' => 'Индия',
            'name' => 'Куркума молотая',
            'slug' => 'ground-turmeric',
            'short_description' => 'Яркая индийская специя с теплым ароматом.',
            'description' => 'Куркума используется в карри, супах, рисе, соусах и маринадах.',
            'weight_grams' => 80,
            'price' => 129.00,
            'stock_quantity' => 40,
            'heat_level' => 1,
        ],
        [
            'product_id' => 2,
            'category_id' => 3,
            'country_id' => 2,
            'category_name' => 'Смеси приправ',
            'category_slug' => 'spice-mixes',
            'country_name' => 'Грузия',
            'name' => 'Хмели-сунели',
            'slug' => 'khmeli-suneli',
            'short_description' => 'Грузинская смесь пряных трав и специй.',
            'description' => 'Классическая смесь для мяса, птицы, овощей и традиционных грузинских блюд.',
            'weight_grams' => 70,
            'price' => 189.00,
            'stock_quantity' => 28,
            'heat_level' => 2,
        ],
        [
            'product_id' => 8,
            'category_id' => 1,
            'country_id' => 2,
            'category_name' => 'Острые специи',
            'category_slug' => 'hot-spices',
            'country_name' => 'Грузия',
            'name' => 'Аджика сухая',
            'slug' => 'dry-adjika',
            'short_description' => 'Острая грузинская приправа для мяса и овощей.',
            'description' => 'Сухая аджика сочетает остроту перца, аромат чеснока и пряных трав.',
            'weight_grams' => 70,
            'price' => 199.00,
            'stock_quantity' => 18,
            'heat_level' => 5,
        ],
        [
            'product_id' => 7,
            'category_id' => 2,
            'country_id' => 6,
            'category_name' => 'Пряные травы',
            'category_slug' => 'herbs',
            'country_name' => 'Италия',
            'name' => 'Базилик сушеный',
            'slug' => 'dried-basil',
            'short_description' => 'Ароматная трава для соусов, пасты и салатов.',
            'description' => 'Сушеный базилик подходит для итальянских блюд, овощей и томатных соусов.',
            'weight_grams' => 30,
            'price' => 119.00,
            'stock_quantity' => 0,
            'heat_level' => 0,
        ],
        [
            'product_id' => 1,
            'category_id' => 1,
            'country_id' => 3,
            'category_name' => 'Острые специи',
            'category_slug' => 'hot-spices',
            'country_name' => 'Мексика',
            'name' => 'Перец чили молотый',
            'slug' => 'ground-chili-pepper',
            'short_description' => 'Острая молотая специя для соусов и мясных блюд.',
            'description' => 'Молотый перец чили добавляет блюдам выраженную остроту и насыщенный аромат.',
            'weight_grams' => 50,
            'price' => 149.00,
            'stock_quantity' => 35,
            'heat_level' => 5,
        ],
    ];
}

function spice_normalize_product(array $product): array
{
    $slug = (string)($product['slug'] ?? '');
    $colors = [
        'smoked-paprika' => 'paprika',
        'ground-chili-pepper' => 'paprika',
        'dry-adjika' => 'dark',
        'ground-turmeric' => 'saffron',
        'madras-curry' => 'saffron',
        'ground-cardamom' => 'cardamom',
        'dried-basil' => 'cardamom',
        'khmeli-suneli' => 'cardamom',
        'ceylon-cinnamon' => 'sand',
    ];

    $product['visual'] = $colors[$slug] ?? 'sand';
    $product['price'] = (float)($product['price'] ?? 0);
    $product['stock_quantity'] = (int)($product['stock_quantity'] ?? 0);
    $product['weight_grams'] = (int)($product['weight_grams'] ?? 0);
    $product['image_src'] = spice_product_image_url($slug, (string)($product['image_url'] ?? ''));

    return $product;
}

function spice_product_image_url(string $slug, string $imageUrl = ''): string
{
    $images = [
        'ground-chili-pepper' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/Redchilipepperpowder.JPG/960px-Redchilipepperpowder.JPG',
        'khmeli-suneli' => 'https://upload.wikimedia.org/wikipedia/commons/3/35/Khmeli_suneli.JPG',
        'madras-curry' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0a/Curry_madras-spice.jpg/960px-Curry_madras-spice.jpg',
        'ceylon-cinnamon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7a/Ceylon_Cinnamon_sticks.jpg/960px-Ceylon_Cinnamon_sticks.jpg',
        'smoked-paprika' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/97/Spanishsmokedpaprika.jpg/960px-Spanishsmokedpaprika.jpg',
        'ground-turmeric' => 'https://upload.wikimedia.org/wikipedia/commons/8/8c/Turmeric_Powder.jpg',
        'dried-basil' => '/assets/img/products/dried-basil.png',
        'dry-adjika' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Paprika-spice.jpg/960px-Paprika-spice.jpg',
        'dried-garlic' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/Garlic_Powder%2C_Penzeys_Spices%2C_Arlington_Heights_MA.jpg/960px-Garlic_Powder%2C_Penzeys_Spices%2C_Arlington_Heights_MA.jpg',
        'ground-cardamom' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/87/Cardemom-spice.jpg/960px-Cardemom-spice.jpg',
        'black-peppercorns' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/81/Tellicherry_Black_Peppercorns.jpg/960px-Tellicherry_Black_Peppercorns.jpg',
        'coriander-seeds' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/86/Coriander_Seeds.jpg/960px-Coriander_Seeds.jpg',
        'cumin-seeds' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/61/Whole_Cumin_Seeds.jpg/960px-Whole_Cumin_Seeds.jpg',
        'nutmeg-ground' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9e/Nutmeg-spice.jpg/960px-Nutmeg-spice.jpg',
        'cloves-whole' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1d/Cloves_whole.JPG/960px-Cloves_whole.JPG',
        'bay-leaf' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b4/Bay_leaves.jpg/960px-Bay_leaves.jpg',
        'oregano-dried' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f8/Oregano_dried.JPG/960px-Oregano_dried.JPG',
        'rosemary-dried' => 'https://upload.wikimedia.org/wikipedia/commons/c/c9/Dry_rosemary.jpg',
        'sweet-paprika' => '/assets/img/products/sweet-paprika.png',
        'saffron-threads' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/79/Iran_saffron_threads.jpg/960px-Iran_saffron_threads.jpg',
        'vanilla-powder' => '/assets/img/products/vanilla-powder.png',
        'garam-masala' => 'https://upload.wikimedia.org/wikipedia/commons/9/92/Garam_Masala_new_2008-1.jpg',
        'sumac-ground' => 'https://upload.wikimedia.org/wikipedia/commons/7/75/Sumac.jpg',
        'five-spice' => 'https://upload.wikimedia.org/wikipedia/commons/3/32/Five_spices_detailed.jpg',
    ];

    if (isset($images[$slug])) {
        return $images[$slug];
    }

    if (
        (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://'))
        && !str_contains($imageUrl, 'loremflickr.com')
    ) {
        return $imageUrl;
    }

    return 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Paprika-spice.jpg/960px-Paprika-spice.jpg';
}

function spice_categories(): array
{
    $rows = spice_fetch_all(
        'SELECT category_id, name, slug, description FROM categories ORDER BY category_id'
    );

    if (!$rows) {
        return spice_fallback_categories();
    }

    return $rows;
}

function spice_search_query(mixed $value): string
{
    return trim((string)preg_replace('/\s+/u', ' ', (string)$value));
}

function spice_like_pattern(string $value): string
{
    return '%' . strtr($value, [
        '\\' => '\\\\',
        '%' => '\\%',
        '_' => '\\_',
    ]) . '%';
}

function spice_search_fold(string $value): string
{
    return str_replace('ё', 'е', mb_strtolower($value, 'UTF-8'));
}

function spice_products(array $filters = []): array
{
    $params = [];
    $where = ['p.is_active = 1'];
    $search = spice_search_query($filters['search'] ?? '');

    if ($search !== '') {
        $where[] = "(p.name LIKE :search_name ESCAPE '\\\\' OR LOWER(REPLACE(p.name, 'ё', 'е')) LIKE :search_folded ESCAPE '\\\\')";
        $params['search_name'] = spice_like_pattern($search);
        $params['search_folded'] = spice_like_pattern(spice_search_fold($search));
    }

    if (!empty($filters['category'])) {
        $where[] = 'c.slug = :category';
        $params['category'] = $filters['category'];
    }

    $limitSql = '';
    if (!empty($filters['limit'])) {
        $limitSql = ' LIMIT ' . max(1, (int)$filters['limit']);
    }

    $sql = '
        SELECT
            p.product_id,
            p.category_id,
            p.country_id,
            p.name,
            p.slug,
            p.short_description,
            p.description,
            p.weight_grams,
            p.price,
            p.stock_quantity,
            p.heat_level,
            p.image_url,
            c.name AS category_name,
            c.slug AS category_slug,
            co.name AS country_name
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        JOIN countries co ON co.country_id = p.country_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.product_id' . $limitSql;

    $dbAvailable = spice_db_available();
    $rows = spice_fetch_all($sql, $params);

    if (!$dbAvailable) {
        $rows = spice_fallback_products();
        if ($search !== '') {
            $searchLower = spice_search_fold($search);
            $rows = array_values(array_filter($rows, static function (array $product) use ($searchLower): bool {
                return str_contains(spice_search_fold((string)$product['name']), $searchLower);
            }));
        }
        if (!empty($filters['category'])) {
            $category = (string)$filters['category'];
            $rows = array_values(array_filter($rows, static fn(array $product): bool => ($product['category_slug'] ?? '') === $category));
        }
        if (!empty($filters['limit'])) {
            $rows = array_slice($rows, 0, (int)$filters['limit']);
        }
    }

    return array_map('spice_normalize_product', $rows);
}

function spice_featured_products(int $limit = 4): array
{
    $sql = "
        SELECT
            p.product_id,
            p.category_id,
            p.country_id,
            p.name,
            p.slug,
            p.short_description,
            p.description,
            p.weight_grams,
            p.price,
            p.stock_quantity,
            p.heat_level,
            p.image_url,
            c.name AS category_name,
            c.slug AS category_slug,
            co.name AS country_name
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        JOIN countries co ON co.country_id = p.country_id
        WHERE p.is_active = 1
          AND p.slug IN ('ceylon-cinnamon', 'smoked-paprika', 'ground-cardamom', 'ground-turmeric')
        ORDER BY FIELD(p.slug, 'ceylon-cinnamon', 'smoked-paprika', 'ground-cardamom', 'ground-turmeric')
        LIMIT " . max(1, $limit);

    $dbAvailable = spice_db_available();
    $rows = spice_fetch_all($sql);

    if (!$dbAvailable) {
        $preferred = ['ceylon-cinnamon', 'smoked-paprika', 'ground-cardamom', 'ground-turmeric'];
        $rows = array_values(array_filter(
            spice_fallback_products(),
            static fn(array $product): bool => in_array($product['slug'], $preferred, true)
        ));
        usort($rows, static fn(array $a, array $b): int => array_search($a['slug'], $preferred, true) <=> array_search($b['slug'], $preferred, true));
    }

    return array_map('spice_normalize_product', array_slice($rows, 0, $limit));
}

function spice_product_by_slug(string $slug): ?array
{
    $rows = spice_products();

    foreach ($rows as $product) {
        if ($product['slug'] === $slug) {
            return $product;
        }
    }

    return null;
}

function spice_products_by_slugs(array $slugs): array
{
    $slugs = array_values(array_unique(array_filter(array_map('strval', $slugs))));

    if (!$slugs) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $sql = "
        SELECT
            p.product_id,
            p.category_id,
            p.country_id,
            p.name,
            p.slug,
            p.short_description,
            p.description,
            p.weight_grams,
            p.price,
            p.stock_quantity,
            p.heat_level,
            p.image_url,
            c.name AS category_name,
            c.slug AS category_slug,
            co.name AS country_name
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        JOIN countries co ON co.country_id = p.country_id
        WHERE p.is_active = 1 AND p.slug IN ($placeholders)
    ";

    $dbAvailable = spice_db_available();
    $rows = spice_fetch_all($sql, $slugs);

    if (!$dbAvailable) {
        $fallback = spice_fallback_products();
        $rows = array_values(array_filter($fallback, static fn(array $product): bool => in_array($product['slug'], $slugs, true)));
    }

    $normalized = array_map('spice_normalize_product', $rows);
    usort($normalized, static fn(array $a, array $b): int => array_search($a['slug'], $slugs, true) <=> array_search($b['slug'], $slugs, true));

    return $normalized;
}

function spice_user_by_email(string $email): ?array
{
    $user = spice_fetch_one(
        'SELECT user_id, full_name, email, phone, password_hash, role, city, address FROM users WHERE email = :email LIMIT 1',
        ['email' => $email]
    );

    return $user ?: null;
}

function spice_authenticate_user(string $email, string $password): ?array
{
    $user = spice_user_by_email($email);

    if (!$user || trim($password) === '') {
        return null;
    }

    $hash = (string)($user['password_hash'] ?? '');

    if ($hash !== '' && password_verify($password, $hash)) {
        unset($user['password_hash']);
        return $user;
    }

    return null;
}

function spice_user_by_id(int $userId): ?array
{
    $user = spice_fetch_one(
        'SELECT user_id, full_name, email, phone, role, city, address FROM users WHERE user_id = :id LIMIT 1',
        ['id' => $userId]
    );

    // демонстрационный профиль подставляется только при недоступной базе данных
    if ($user || spice_db_available()) {
        return $user;
    }

    return [
        'user_id' => 2,
        'full_name' => 'Анна Петрова',
        'email' => 'anna.petrova@example.com',
        'phone' => '+7 (999) 200-20-20',
        'role' => 'customer',
        'city' => 'Москва',
        'address' => 'ул. Лесная, д. 15',
    ];
}

function spice_create_user(array $data): ?array
{
    $pdo = spice_pdo();

    if (!$pdo) {
        return [
            'user_id' => 2,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => 'customer',
            'city' => $data['city'] ?: 'Москва',
            'address' => $data['address'] ?: null,
        ];
    }

    $statement = $pdo->prepare('
        INSERT INTO users (full_name, email, phone, password_hash, role, city, address)
        VALUES (:full_name, :email, :phone, :password_hash, "customer", :city, :address)
    ');
    $statement->execute([
        'full_name' => $data['full_name'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        'city' => $data['city'] ?: 'Москва',
        'address' => $data['address'] ?: null,
    ]);

    return spice_user_by_id((int)$pdo->lastInsertId());
}

function spice_orders_for_user(int $userId): array
{
    $rows = spice_fetch_all(
        '
        SELECT order_id, order_number, status, total_amount, delivery_method, payment_method, created_at
        FROM orders
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 20
        ',
        ['user_id' => $userId]
    );

    if ($rows) {
        return $rows;
    }

    return [
        [
            'order_id' => 1,
            'order_number' => 'SM-2026-0001',
            'status' => 'completed',
            'total_amount' => 438.00,
            'delivery_method' => 'courier',
            'payment_method' => 'cash',
            'created_at' => date('Y-m-d H:i:s', strtotime('-6 days')),
        ],
        [
            'order_id' => 5,
            'order_number' => 'SM-2026-0005',
            'status' => 'completed',
            'total_amount' => 498.00,
            'delivery_method' => 'courier',
            'payment_method' => 'card_on_delivery',
            'created_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
        ],
    ];
}

function spice_order_items(int $orderId): array
{
    return spice_fetch_all(
        '
        SELECT oi.quantity, oi.unit_price, oi.line_total, p.name, p.slug
        FROM order_items oi
        JOIN products p ON p.product_id = oi.product_id
        WHERE oi.order_id = :order_id
        ORDER BY oi.order_item_id
        ',
        ['order_id' => $orderId]
    );
}

function spice_favorites_for_user(int $userId): array
{
    $sql = '
        SELECT
            p.product_id,
            p.category_id,
            p.country_id,
            p.name,
            p.slug,
            p.short_description,
            p.description,
            p.weight_grams,
            p.price,
            p.stock_quantity,
            p.heat_level,
            p.image_url,
            c.name AS category_name,
            c.slug AS category_slug,
            co.name AS country_name
        FROM favorites f
        JOIN products p ON p.product_id = f.product_id
        JOIN categories c ON c.category_id = p.category_id
        JOIN countries co ON co.country_id = p.country_id
        WHERE f.user_id = :user_id
        ORDER BY f.created_at DESC
        LIMIT 8
    ';

    $dbAvailable = spice_db_available();
    $rows = spice_fetch_all($sql, ['user_id' => $userId]);

    if (!$dbAvailable) {
        $rows = array_slice(spice_fallback_products(), 0, 2);
    }

    return array_map('spice_normalize_product', $rows);
}

function spice_recipes(): array
{
    $rows = spice_fetch_all(
        '
        SELECT
            r.recipe_id,
            r.title,
            r.slug,
            r.description,
            r.cooking_time_minutes,
            c.name AS cuisine_name,
            GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ", ") AS products_list
        FROM recipes r
        JOIN cuisines c ON c.cuisine_id = r.cuisine_id
        LEFT JOIN recipe_products rp ON rp.recipe_id = r.recipe_id
        LEFT JOIN products p ON p.product_id = rp.product_id
        WHERE r.is_active = 1
        GROUP BY r.recipe_id, r.title, r.slug, r.description, r.cooking_time_minutes, c.name
        ORDER BY r.recipe_id
        '
    );

    if (!spice_db_available()) {
        return [
            [
                'recipe_id' => 1,
                'title' => 'Чахохбили с хмели-сунели',
                'slug' => 'chakhokhbili-khmeli-suneli',
                'description' => 'Домашнее блюдо из курицы с томатами, зеленью и грузинскими специями.',
                'cooking_time_minutes' => 60,
                'cuisine_name' => 'Грузинская кухня',
                'products_list' => 'Хмели-сунели, Аджика сухая',
            ],
            [
                'recipe_id' => 2,
                'title' => 'Овощное карри с куркумой',
                'slug' => 'vegetable-curry-turmeric',
                'description' => 'Пряное овощное карри с рисом, куркумой и карри мадрас.',
                'cooking_time_minutes' => 45,
                'cuisine_name' => 'Индийская кухня',
                'products_list' => 'Куркума молотая, Кардамон молотый',
            ],
            [
                'recipe_id' => 3,
                'title' => 'Маффины с корицей и кардамоном',
                'slug' => 'cinnamon-cardamom-muffins',
                'description' => 'Ароматная выпечка с мягкими специями для десертов.',
                'cooking_time_minutes' => 40,
                'cuisine_name' => 'Европейская кухня',
                'products_list' => 'Корица цейлонская, Кардамон молотый',
            ],
        ];
    }

    return $rows;
}

function spice_reviews(): array
{
    $rows = spice_fetch_all(
        '
        SELECT
            r.review_id,
            r.rating,
            r.review_text,
            r.created_at,
            u.full_name,
            p.name AS product_name,
            p.slug AS product_slug
        FROM reviews r
        JOIN users u ON u.user_id = r.user_id
        JOIN products p ON p.product_id = r.product_id
        WHERE r.status IN ("published", "new")
        ORDER BY r.created_at DESC
        LIMIT 12
        '
    );

    if (!spice_db_available()) {
        return [
            [
                'review_id' => 1,
                'rating' => 5,
                'review_text' => 'Ароматная смесь, хорошо подошла для курицы и овощей.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-8 days')),
                'full_name' => 'Анна Петрова',
                'product_name' => 'Хмели-сунели',
                'product_slug' => 'khmeli-suneli',
            ],
            [
                'review_id' => 2,
                'rating' => 4,
                'review_text' => 'Перец действительно острый, расходуется экономно.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
                'full_name' => 'Иван Смирнов',
                'product_name' => 'Перец чили молотый',
                'product_slug' => 'ground-chili-pepper',
            ],
            [
                'review_id' => 3,
                'rating' => 5,
                'review_text' => 'Корица мягкая и приятная, добавляю в кофе и выпечку.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'full_name' => 'Мария Кузнецова',
                'product_name' => 'Корица цейлонская',
                'product_slug' => 'ceylon-cinnamon',
            ],
        ];
    }

    return $rows;
}

function spice_api_recipe(): array
{
    $fallback = [
        'title' => 'Spicy Arrabiata Penne',
        'category' => 'Vegetarian',
        'area' => 'Italian',
        'image' => 'https://www.themealdb.com/images/media/meals/ustsqw1468250014.jpg',
        'ingredients' => 'penne rigate, garlic, red chilli flakes, basil',
        'source' => 'TheMealDB',
        'api_url' => 'https://www.themealdb.com/api/json/v1/1/lookup.php?i=52771',
        'is_fallback' => true,
    ];

    $url = $fallback['api_url'];
    $json = null;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (is_string($response) && $status >= 200 && $status < 300) {
            $json = $response;
        }
    } elseif ((bool)ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => ['timeout' => 3],
        ]);
        $response = @file_get_contents($url, false, $context);
        if (is_string($response)) {
            $json = $response;
        }
    }

    if ($json === null) {
        return $fallback;
    }

    $data = json_decode($json, true);
    $meal = $data['meals'][0] ?? null;
    if (!is_array($meal)) {
        return $fallback;
    }

    $ingredients = [];
    for ($index = 1; $index <= 8; $index++) {
        $ingredient = trim((string)($meal['strIngredient' . $index] ?? ''));
        if ($ingredient !== '') {
            $ingredients[] = $ingredient;
        }
    }

    return [
        'title' => (string)($meal['strMeal'] ?? $fallback['title']),
        'category' => (string)($meal['strCategory'] ?? $fallback['category']),
        'area' => (string)($meal['strArea'] ?? $fallback['area']),
        'image' => (string)($meal['strMealThumb'] ?? $fallback['image']),
        'ingredients' => implode(', ', $ingredients),
        'source' => 'TheMealDB',
        'api_url' => $url,
        'is_fallback' => false,
    ];
}

function spice_create_order(array $customer, array $cart): array
{
    $userId = (int)($customer['user_id'] ?? 0);
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Для оформления заказа войдите в личный кабинет.'];
    }

    if (!in_array($customer['delivery_method'] ?? '', ['pickup', 'courier'], true)
        || !in_array($customer['payment_method'] ?? '', ['cash', 'card_on_delivery'], true)) {
        return ['ok' => false, 'message' => 'Выберите способ доставки и оплаты из списка.'];
    }

    $items = [];
    foreach ($cart as $slug => $quantity) {
        $quantity = max(1, min(99, (int)$quantity));
        if ($slug !== '') {
            $items[$slug] = $quantity;
        }
    }

    if (!$items) {
        return ['ok' => false, 'message' => 'Корзина пуста. Добавьте товары перед оформлением заказа.'];
    }

    $products = spice_products_by_slugs(array_keys($items));
    if (!$products) {
        return ['ok' => false, 'message' => 'Не удалось получить товары для заказа.'];
    }

    $total = 0.0;
    foreach ($products as $product) {
        $total += $product['price'] * ($items[$product['slug']] ?? 1);
    }

    $pdo = spice_pdo();
    if (!$pdo) {
        return [
            'ok' => true,
            'message' => 'Демо-заказ сформирован. Подключите MySQL, чтобы сохранить его в БД.',
            'order_number' => 'DEMO-' . date('His'),
            'total' => $total,
        ];
    }

    try {
        $pdo->beginTransaction();
        $orderNumber = 'SM-' . date('Ymd-His');

        $statement = $pdo->prepare('
            INSERT INTO orders (
                user_id, order_number, customer_name, phone, delivery_address,
                delivery_method, payment_method, status, total_amount
            )
            VALUES (
                :user_id, :order_number, :customer_name, :phone, :delivery_address,
                :delivery_method, :payment_method, "new", :total_amount
            )
        ');
        $statement->execute([
            'user_id' => $userId,
            'order_number' => $orderNumber,
            'customer_name' => $customer['customer_name'],
            'phone' => $customer['phone'],
            'delivery_address' => $customer['delivery_address'],
            'delivery_method' => $customer['delivery_method'],
            'payment_method' => $customer['payment_method'],
            'total_amount' => $total,
        ]);

        $orderId = (int)$pdo->lastInsertId();
        $itemStatement = $pdo->prepare('
            INSERT INTO order_items (order_id, product_id, quantity, unit_price, line_total)
            VALUES (:order_id, :product_id, :quantity, :unit_price, :line_total)
        ');

        foreach ($products as $product) {
            $quantity = $items[$product['slug']] ?? 1;
            $itemStatement->execute([
                'order_id' => $orderId,
                'product_id' => $product['product_id'],
                'quantity' => $quantity,
                'unit_price' => $product['price'],
                'line_total' => $product['price'] * $quantity,
            ]);
        }

        $pdo->commit();

        return [
            'ok' => true,
            'message' => 'Заказ сохранен в базе данных.',
            'order_number' => $orderNumber,
            'total' => $total,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Spice Market order error: ' . $error->getMessage());

        return ['ok' => false, 'message' => 'Не удалось сохранить заказ. Попробуйте ещё раз.'];
    }
}

function spice_admin_tables(): array
{
    return array_merge(spice_admin_base_tables(), spice_admin_views());
}

function spice_admin_base_tables(): array
{
    return [
        'users',
        'categories',
        'countries',
        'cuisines',
        'products',
        'product_cuisines',
        'recipes',
        'recipe_products',
        'favorites',
        'reviews',
        'orders',
        'order_items',
    ];
}

function spice_admin_views(): array
{
    return [
        'view_spices_in_stock',
        'view_hot_spices',
        'view_spices_from_india',
        'view_georgian_cuisine_spices',
        'view_orders_last_week',
    ];
}

function spice_admin_is_base_table(string $table): bool
{
    return in_array($table, spice_admin_base_tables(), true);
}

function spice_admin_is_allowed_object(string $table): bool
{
    return in_array($table, spice_admin_tables(), true);
}

function spice_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function spice_admin_table_columns(string $table): array
{
    if (!spice_admin_is_allowed_object($table)) {
        return [];
    }

    return spice_fetch_all(
        '
        SELECT
            COLUMN_NAME,
            DATA_TYPE,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            EXTRA,
            COLUMN_KEY
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = :table_name
        ORDER BY ORDINAL_POSITION
        ',
        ['table_name' => $table]
    );
}

function spice_admin_primary_key(string $table): ?string
{
    if (!spice_admin_is_base_table($table)) {
        return null;
    }

    foreach (spice_admin_table_columns($table) as $column) {
        if (($column['COLUMN_KEY'] ?? '') === 'PRI') {
            return (string)$column['COLUMN_NAME'];
        }
    }

    return null;
}

function spice_admin_table_rows(string $table): array
{
    if (!spice_admin_is_allowed_object($table)) {
        return [];
    }

    return spice_fetch_all("SELECT * FROM `$table` LIMIT 100");
}

function spice_admin_table_count(string $table): int
{
    if (!spice_admin_is_allowed_object($table)) {
        return 0;
    }

    $row = spice_fetch_one("SELECT COUNT(*) AS rows_count FROM `$table`");

    return (int)($row['rows_count'] ?? 0);
}

function spice_admin_row(string $table, string $primaryKey, string|int $id): ?array
{
    if (!spice_admin_is_base_table($table)) {
        return null;
    }

    $quotedTable = spice_quote_identifier($table);
    $quotedKey = spice_quote_identifier($primaryKey);

    return spice_fetch_one(
        "SELECT * FROM {$quotedTable} WHERE {$quotedKey} = :id LIMIT 1",
        ['id' => $id]
    );
}

function spice_admin_referencing_foreign_keys(string $table, string $column): array
{
    return spice_fetch_all(
        '
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE table_schema = DATABASE()
          AND referenced_table_name = :table_name
          AND referenced_column_name = :column_name
        ORDER BY TABLE_NAME, ORDINAL_POSITION
        ',
        [
            'table_name' => $table,
            'column_name' => $column,
        ]
    );
}

function spice_admin_delete_row_cascade(PDO $pdo, string $table, string $primaryKey, string|int $id, array &$visited): void
{
    $visitKey = $table . ':' . $primaryKey . ':' . (string)$id;
    if (isset($visited[$visitKey])) {
        return;
    }

    $visited[$visitKey] = true;

    foreach (spice_admin_referencing_foreign_keys($table, $primaryKey) as $foreignKey) {
        $childTable = (string)$foreignKey['TABLE_NAME'];
        $childColumn = (string)$foreignKey['COLUMN_NAME'];

        if (!spice_admin_is_base_table($childTable)) {
            continue;
        }

        $childPrimaryKey = spice_admin_primary_key($childTable);
        if (!$childPrimaryKey) {
            $deleteChildren = $pdo->prepare(
                sprintf(
                    'DELETE FROM %s WHERE %s = :parent_id',
                    spice_quote_identifier($childTable),
                    spice_quote_identifier($childColumn)
                )
            );
            $deleteChildren->execute(['parent_id' => $id]);
            continue;
        }

        $selectChildren = $pdo->prepare(
            sprintf(
                'SELECT %s FROM %s WHERE %s = :parent_id',
                spice_quote_identifier($childPrimaryKey),
                spice_quote_identifier($childTable),
                spice_quote_identifier($childColumn)
            )
        );
        $selectChildren->execute(['parent_id' => $id]);
        $childIds = $selectChildren->fetchAll(PDO::FETCH_COLUMN);

        foreach ($childIds as $childId) {
            spice_admin_delete_row_cascade($pdo, $childTable, $childPrimaryKey, (string)$childId, $visited);
        }
    }

    $deleteCurrent = $pdo->prepare(
        sprintf(
            'DELETE FROM %s WHERE %s = :id',
            spice_quote_identifier($table),
            spice_quote_identifier($primaryKey)
        )
    );
    $deleteCurrent->execute(['id' => $id]);
}

function spice_admin_normalize_form_data(string $table, array $input, bool $forCreate = true): array
{
    $data = [];

    foreach (spice_admin_table_columns($table) as $column) {
        $name = (string)$column['COLUMN_NAME'];
        $extra = strtolower((string)($column['EXTRA'] ?? ''));
        $isPrimary = ($column['COLUMN_KEY'] ?? '') === 'PRI';

        if ($isPrimary || str_contains($extra, 'auto_increment')) {
            continue;
        }

        if (!array_key_exists($name, $input)) {
            continue;
        }

        $value = is_array($input[$name]) ? '' : trim((string)$input[$name]);
        $hasDefault = $column['COLUMN_DEFAULT'] !== null || str_contains($extra, 'current_timestamp');
        $isNullable = ($column['IS_NULLABLE'] ?? 'NO') === 'YES';

        if ($name === 'password_hash' && !$forCreate && $value === '') {
            continue;
        }

        if ($value === '') {
            if ($forCreate && $hasDefault) {
                continue;
            }

            $data[$name] = $isNullable ? null : '';
            continue;
        }

        if ($name === 'password_hash' && !str_starts_with($value, '$2y$')) {
            $value = password_hash($value, PASSWORD_DEFAULT);
        }

        $data[$name] = $value;
    }

    return $data;
}

function spice_admin_insert_row(string $table, array $data): bool
{
    if (!spice_admin_is_base_table($table) || !$data) {
        return false;
    }

    $quotedTable = spice_quote_identifier($table);
    $columns = array_keys($data);
    $quotedColumns = array_map('spice_quote_identifier', $columns);
    $placeholders = [];
    $params = [];

    foreach ($columns as $index => $column) {
        $placeholder = 'value_' . $index;
        $placeholders[] = ':' . $placeholder;
        $params[$placeholder] = $data[$column];
    }

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $quotedTable,
        implode(', ', $quotedColumns),
        implode(', ', $placeholders)
    );

    return spice_execute($sql, $params);
}

function spice_admin_update_row(string $table, string $primaryKey, string|int $id, array $data): bool
{
    if (!spice_admin_is_base_table($table) || !$data) {
        return false;
    }

    $quotedTable = spice_quote_identifier($table);
    $quotedKey = spice_quote_identifier($primaryKey);
    $sets = [];
    $params = ['row_id' => $id];

    foreach (array_keys($data) as $index => $column) {
        $placeholder = 'value_' . $index;
        $sets[] = spice_quote_identifier($column) . ' = :' . $placeholder;
        $params[$placeholder] = $data[$column];
    }

    $sql = sprintf(
        'UPDATE %s SET %s WHERE %s = :row_id',
        $quotedTable,
        implode(', ', $sets),
        $quotedKey
    );

    return spice_execute($sql, $params);
}

function spice_admin_delete_row(string $table, string $primaryKey, string|int $id): bool
{
    if (!spice_admin_is_base_table($table)) {
        return false;
    }

    $pdo = spice_pdo();
    if (!$pdo) {
        return false;
    }

    $startedTransaction = !$pdo->inTransaction();

    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $visited = [];
        spice_admin_delete_row_cascade($pdo, $table, $primaryKey, $id, $visited);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return true;
    } catch (Throwable $error) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}

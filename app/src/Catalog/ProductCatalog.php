<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — Product Catalog
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

namespace Catalog;

class ProductCatalog
{
    private static ?bool $hasProductCodeColumn = null;
    private static ?bool $hasCategoryCodePrefixColumn = null;
    private static ?bool $hasOriginalPriceColumn = null;
    private static bool $filterSchemaReady = false;
    private static bool $presentationSchemaReady = false;

    private static function ensurePresentationSchema(): void
    {
        if (self::$presentationSchemaReady) return;
        foreach ([
            "video_url VARCHAR(500) NULL",
            "video_path VARCHAR(500) NULL",
            "show_delivery_info TINYINT(1) NOT NULL DEFAULT 1",
            "delivery_info VARCHAR(255) NULL DEFAULT 'Delivery in 3 - 5 Working Days'",
            "show_free_delivery_info TINYINT(1) NOT NULL DEFAULT 1",
            "free_delivery_info VARCHAR(255) NULL DEFAULT 'Free Delivery on Orders Above ₹999'",
        ] as $definition) { try { \Database::query("ALTER TABLE products ADD COLUMN {$definition}"); } catch (\Throwable) {} }
        self::$presentationSchemaReady=true;
    }

    private static function normalizeVideoUrl(string $url): string
    {
        $url=trim($url);if($url==='')return '';
        return preg_match('~^https://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[&?].*)?$~i',$url,$m)?'https://www.youtube.com/embed/'.$m[1]:'';
    }

    private static function syncPresentation(int $id,array $data): void
    {
        self::ensurePresentationSchema();$videoPath=trim((string)($data['video_path']??''));
        if($videoPath!==''&&!str_starts_with($videoPath,'/uploads/products/'))$videoPath='';
        \Database::query("UPDATE products SET video_url=?,video_path=?,show_delivery_info=?,delivery_info=?,show_free_delivery_info=?,free_delivery_info=? WHERE id=?",[self::normalizeVideoUrl((string)($data['video_url']??'')),$videoPath,!empty($data['show_delivery_info'])?1:0,trim((string)($data['delivery_info']??'')),!empty($data['show_free_delivery_info'])?1:0,trim((string)($data['free_delivery_info']??'')),$id]);
    }

    private static function minPriceExpr(): string
    {
        return "(SELECT MIN(t.price) FROM product_quantity_tiers t WHERE t.product_id = p.id)";
    }

    private static function legacyMinPriceExpr(): string
    {
        return "(SELECT MIN(qs.price) FROM quantity_slabs qs WHERE qs.product_id = p.id)";
    }

    private static function primaryImageExpr(): string
    {
        return "COALESCE(
                    (SELECT COALESCE(pi.image_path, pi.url)
                     FROM product_images pi
                     WHERE pi.product_id = p.id AND pi.is_primary = 1
                     ORDER BY pi.sort_order ASC, pi.id ASC
                     LIMIT 1),
                    p.image_path,
                    (SELECT COALESCE(pi2.image_path, pi2.url)
                     FROM product_images pi2
                     WHERE pi2.product_id = p.id
                     ORDER BY pi2.sort_order ASC, pi2.id ASC
                     LIMIT 1)
                )";
    }

    private static function categoryCodePrefixExpr(): string
    {
        return self::categoryCodePrefixColumnReady() ? 'c.code_prefix' : "''";
    }

    private static function fetchProductRows(string $whereSql, array $params = []): array
    {
        $categoryPrefixExpr = self::categoryCodePrefixExpr();

        try {
            return \Database::rows(
                "SELECT p.*, c.name as category_name, c.slug as category_slug,
                        {$categoryPrefixExpr} as category_code_prefix,
                        " . self::primaryImageExpr() . " as primary_image,
                        " . self::minPriceExpr() . " as min_price
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 {$whereSql}",
                $params
            );
        } catch (\Throwable) {
            return \Database::rows(
                "SELECT p.*, c.name as category_name, c.slug as category_slug,
                        {$categoryPrefixExpr} as category_code_prefix,
                        COALESCE(
                            (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1),
                            p.image_path,
                            (SELECT pi2.url FROM product_images pi2 WHERE pi2.product_id = p.id ORDER BY pi2.sort_order ASC, pi2.id ASC LIMIT 1)
                        ) as primary_image,
                        " . self::legacyMinPriceExpr() . " as min_price
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 {$whereSql}",
                $params
            );
        }
    }

    public static function all(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'WHERE p.is_active = 1' : '';
        return self::fetchProductRows($where . ' ORDER BY c.sort_order ASC, p.sort_order ASC');
    }

    private static function defaultFilterGroups(): array
    {
        return [
            'paper_type' => [
                'label' => 'Paper Type',
                'options' => [
                    ['slug' => 'non-tearable', 'label' => 'Non Tearable'],
                    ['slug' => 'glossy-paper', 'label' => 'Glossy Paper'],
                    ['slug' => 'matt-paper', 'label' => 'Matt Paper'],
                    ['slug' => 'texture-paper', 'label' => 'Texture Paper'],
                    ['slug' => 'craft-paper', 'label' => 'Craft Paper'],
                ],
            ],
            'lamination' => [
                'label' => 'Lamination',
                'options' => [
                    ['slug' => 'glossy', 'label' => 'Glossy'],
                    ['slug' => 'matt', 'label' => 'Matt'],
                    ['slug' => 'velvet', 'label' => 'Velvet'],
                ],
            ],
            'finishing' => [
                'label' => 'Finishing',
                'options' => [
                    ['slug' => 'spot-uv', 'label' => 'Spot UV'],
                    ['slug' => 'dripoff-uv', 'label' => 'Dripoff UV'],
                    ['slug' => 'foil-stamping', 'label' => 'Foil Stamping'],
                    ['slug' => 'die-cutting', 'label' => 'Die Cutting'],
                ],
            ],
        ];
    }

    private static function ensureFilterSchema(): void
    {
        if (self::$filterSchemaReady) return;

        \Database::query(
            "CREATE TABLE IF NOT EXISTS product_filter_options (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                group_slug VARCHAR(64) NOT NULL,
                group_label VARCHAR(100) NOT NULL,
                option_slug VARCHAR(64) NOT NULL,
                label VARCHAR(100) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY uq_product_filter_option (group_slug, option_slug),
                KEY idx_product_filter_group (group_slug, is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        \Database::query(
            "CREATE TABLE IF NOT EXISTS product_filter_map (
                product_id INT UNSIGNED NOT NULL,
                option_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (product_id, option_id),
                KEY idx_product_filter_map_option (option_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $optionCount=(int)(\Database::row("SELECT COUNT(*) c FROM product_filter_options")['c']??0);
        if($optionCount===0) foreach (self::defaultFilterGroups() as $groupSlug => $group) {
            foreach (array_values($group['options']) as $idx => $option) {
                \Database::query(
                    "INSERT INTO product_filter_options (group_slug, group_label, option_slug, label, sort_order, is_active)
                     VALUES (?, ?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE group_label=VALUES(group_label), label=VALUES(label), sort_order=VALUES(sort_order), is_active=1",
                    [$groupSlug, $group['label'], $option['slug'], $option['label'], ($idx + 1) * 10]
                );
            }
        }

        self::$filterSchemaReady = true;
    }

    public static function filterOptions(): array
    {
        try {
            self::ensureFilterSchema();
            $rows = \Database::rows(
                "SELECT * FROM product_filter_options WHERE is_active = 1 ORDER BY group_slug ASC, sort_order ASC, label ASC"
            );
        } catch (\Throwable $e) {
            error_log('Filter options unavailable: ' . $e->getMessage());
            $rows = [];
        }

        $groups = [];
        foreach (self::defaultFilterGroups() as $slug => $group) {
            $groups[$slug] = ['slug' => $slug, 'label' => $group['label'], 'options' => []];
        }

        foreach ($rows as $row) {
            $groupSlug = (string)($row['group_slug'] ?? '');
            if (!isset($groups[$groupSlug])) {
                $groups[$groupSlug]=['slug'=>$groupSlug,'label'=>(string)($row['group_label']??$groupSlug),'options'=>[]];
            }
            $groups[$groupSlug]['options'][] = [
                'id' => (int)($row['id'] ?? 0),
                'slug' => (string)($row['option_slug'] ?? ''),
                'label' => (string)($row['label'] ?? ''),
            ];
        }

        if (!$rows) {
            foreach (self::defaultFilterGroups() as $groupSlug => $group) {
                foreach ($group['options'] as $idx => $option) {
                    $groups[$groupSlug]['options'][] = [
                        'id' => 0,
                        'slug' => $option['slug'],
                        'label' => $option['label'],
                    ];
                }
            }
        }

        return $groups;
    }

    public static function normalizeFilterSelections(array $input): array
    {
        $allowedGroups = array_keys(self::defaultFilterGroups());
        try{self::ensureFilterSchema();$allowedGroups=array_values(array_unique(array_merge($allowedGroups,array_column(\Database::rows("SELECT DISTINCT group_slug FROM product_filter_options WHERE is_active=1"),'group_slug'))));}catch(\Throwable){}
        $out = [];
        foreach ($allowedGroups as $groupSlug) {
            $values = $input[$groupSlug] ?? [];
            if (!is_array($values)) $values = [$values];
            foreach ($values as $value) {
                $slug = strtolower(trim((string)$value));
                $slug = preg_replace('/[^a-z0-9\-]/', '', $slug) ?: '';
                if ($slug === '') continue;
                $out[$groupSlug][$slug] = $slug;
            }
            if (!empty($out[$groupSlug])) $out[$groupSlug] = array_values($out[$groupSlug]);
            else unset($out[$groupSlug]);
        }
        return $out;
    }

    public static function filteredProducts(array $filters = [], ?int $categoryId = null): array
    {
        $filters = self::normalizeFilterSelections($filters);
        $categoryId = $categoryId !== null ? max(0, (int)$categoryId) : 0;
        if (!$filters && $categoryId <= 0) return self::all(true);

        try {
            self::ensureFilterSchema();
        } catch (\Throwable $e) {
            error_log('Product filters unavailable: ' . $e->getMessage());
            if ($categoryId > 0) {
                return self::fetchProductRows('WHERE p.is_active = 1 AND p.category_id = ? ORDER BY p.sort_order ASC', [$categoryId]);
            }
            return self::all(true);
        }

        $where = ['p.is_active = 1'];
        $params = [];
        if ($categoryId > 0) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        foreach ($filters as $groupSlug => $slugs) {
            if (!$slugs) continue;
            $placeholders = implode(',', array_fill(0, count($slugs), '?'));
            $where[] = "EXISTS (
                SELECT 1
                FROM product_filter_map pfm
                JOIN product_filter_options pfo ON pfo.id = pfm.option_id
                WHERE pfm.product_id = p.id
                  AND pfo.group_slug = ?
                  AND pfo.option_slug IN ({$placeholders})
                  AND pfo.is_active = 1
            )";
            $params[] = $groupSlug;
            array_push($params, ...$slugs);
        }

        return self::fetchProductRows('WHERE ' . implode(' AND ', $where) . ' ORDER BY c.sort_order ASC, p.sort_order ASC', $params);
    }

    public static function bySlug(string $slug): ?array
    {
        $products = self::fetchProductRows('WHERE p.slug = ? AND p.is_active = 1 LIMIT 1', [$slug]);
        $product = $products[0] ?? null;
        if (!$product) return null;
        $product = self::ensureProductCode($product);
        return self::hydrate($product);
    }

    public static function byId(int $id): ?array
    {
        $products = self::fetchProductRows('WHERE p.id = ? LIMIT 1', [$id]);
        $product = $products[0] ?? null;
        if (!$product) return null;
        $product = self::ensureProductCode($product);
        return self::hydrate($product);
    }

    public static function categories(): array
    {
        return \Database::rows(
            "SELECT c.*, COUNT(p.id) as product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
             GROUP BY c.id
             ORDER BY c.sort_order ASC"
        );
    }

    public static function related(int $productId, int $categoryId, int $limit = 4): array
    {
        return self::fetchProductRows(
            'WHERE p.is_active = 1 AND p.id != ? AND p.category_id = ? ORDER BY RAND() LIMIT ?',
            [$productId, $categoryId, $limit]
        );
    }

    public static function randomRecommendations(int $productId, int $limit = 5): array
    {
        $limit = max(1, $limit);
        $poolLimit = max($limit * 6, 24);
        $pool = self::fetchProductRows(
            'WHERE p.is_active = 1 AND p.id != ? ORDER BY RAND() LIMIT ' . (int)$poolLimit,
            [$productId]
        );

        if (count($pool) <= $limit) {
            return $pool;
        }

        $picked = [];
        $pickedIds = [];
        $usedCategoryIds = [];

        foreach ($pool as $row) {
            if (count($picked) >= $limit) break;
            $rowId = (int)($row['id'] ?? 0);
            $categoryId = (int)($row['category_id'] ?? 0);
            if ($rowId <= 0 || isset($pickedIds[$rowId]) || ($categoryId > 0 && isset($usedCategoryIds[$categoryId]))) {
                continue;
            }
            $picked[] = $row;
            $pickedIds[$rowId] = true;
            if ($categoryId > 0) $usedCategoryIds[$categoryId] = true;
        }

        foreach ($pool as $row) {
            if (count($picked) >= $limit) break;
            $rowId = (int)($row['id'] ?? 0);
            if ($rowId <= 0 || isset($pickedIds[$rowId])) continue;
            $picked[] = $row;
            $pickedIds[$rowId] = true;
        }

        return array_slice($picked, 0, $limit);
    }

    public static function relatedCategories(int $currentCategoryId = 0, int $limit = 5): array
    {
        $preferred = [
            ['business-cards', 'business-card', 'visiting-cards'],
            ['flyers', 'flyer'],
            ['brochures', 'brochure'],
            ['posters', 'poster'],
            ['calendars', 'calendar'],
        ];
        $allCategories = array_values(array_filter(self::categories(), static function (array $category): bool {
            if (array_key_exists('is_active', $category) && (int)$category['is_active'] !== 1) {
                return false;
            }
            return (int)($category['product_count'] ?? 0) > 0;
        }));
        $categories = array_values(array_filter($allCategories, static function (array $category) use ($currentCategoryId): bool {
            return (int)($category['id'] ?? 0) !== $currentCategoryId;
        }));

        $picked = [];
        $usedIds = [];
        foreach ($preferred as $slugGroup) {
            if (count($picked) >= $limit) break;
            foreach ($categories as $category) {
                $categoryId = (int)($category['id'] ?? 0);
                if ($categoryId <= 0 || isset($usedIds[$categoryId])) continue;
                if (in_array((string)($category['slug'] ?? ''), $slugGroup, true)) {
                    $picked[] = $category;
                    $usedIds[$categoryId] = true;
                    break;
                }
            }
        }

        foreach ($categories as $category) {
            if (count($picked) >= $limit) break;
            $categoryId = (int)($category['id'] ?? 0);
            if ($categoryId <= 0 || isset($usedIds[$categoryId])) continue;
            $picked[] = $category;
            $usedIds[$categoryId] = true;
        }

        if (count($picked) < $limit) {
            foreach ($allCategories as $category) {
                if (count($picked) >= $limit) break;
                $categoryId = (int)($category['id'] ?? 0);
                if ($categoryId <= 0 || isset($usedIds[$categoryId])) continue;
                $picked[] = $category;
                $usedIds[$categoryId] = true;
            }
        }

        return array_slice($picked, 0, $limit);
    }

    public static function relatedFromFixedCategories(int $productId, int $limit = 5): array
    {
        $preferred = [
            ['business-cards', 'business-card', 'visiting-cards'],
            ['flyers', 'flyer'],
            ['brochures', 'brochure'],
            ['posters', 'poster'],
            ['calendars', 'calendar'],
        ];
        $picked = [];
        $usedCategoryIds = [];

        foreach ($preferred as $slugGroup) {
            if (count($picked) >= $limit) break;
            $row = self::fetchOneFromCategorySlugs($productId, $slugGroup);
            if (!$row) continue;
            $picked[] = $row;
            $usedCategoryIds[] = (int)($row['category_id'] ?? 0);
        }

        if (count($picked) < $limit) {
            $remaining = $limit - count($picked);
            $fallback = self::fetchFallbackRelated($productId, $usedCategoryIds, $remaining);
            foreach ($fallback as $row) {
                $picked[] = $row;
            }
        }

        return array_slice($picked, 0, $limit);
    }

    private static function fetchOneFromCategorySlugs(int $productId, array $slugs): ?array
    {
        $slugs = array_values(array_filter(array_map('strval', $slugs)));
        if (empty($slugs)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $params = array_merge([$productId], $slugs);
        $rows = self::fetchProductRows(
            "WHERE p.is_active = 1 AND p.id != ? AND c.slug IN ($placeholders) ORDER BY p.sort_order ASC, p.id DESC LIMIT 1",
            $params
        );
        return $rows[0] ?? null;
    }

    private static function fetchFallbackRelated(int $productId, array $excludeCategoryIds, int $limit): array
    {
        if ($limit <= 0) return [];
        $params = [$productId];
        $where = 'WHERE p.is_active = 1 AND p.id != ?';
        if (!empty($excludeCategoryIds)) {
            $ph = implode(',', array_fill(0, count($excludeCategoryIds), '?'));
            $where .= " AND p.category_id NOT IN ($ph)";
            array_push($params, ...$excludeCategoryIds);
        }
        $where .= ' ORDER BY c.sort_order ASC, p.sort_order ASC, p.id DESC LIMIT ' . (int)$limit;
        return self::fetchProductRows($where, $params);
    }

    public static function byBusinessNeed(int $businessNeedId, array $filters = [], string $search = ''): array
    {
        $businessNeedId = max(0, $businessNeedId);
        if ($businessNeedId <= 0) return [];
        $filters = self::normalizeFilterSelections($filters);
        $where = ["p.is_active = 1", "EXISTS (SELECT 1 FROM product_business_needs pbn WHERE pbn.product_id = p.id AND pbn.business_need_id = ?)"];
        $params = [$businessNeedId];
        $search = trim($search);
        if ($search !== '') {
            $where[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        if ($filters) {
            try { self::ensureFilterSchema(); } catch (\Throwable) {}
            foreach ($filters as $groupSlug => $slugs) {
                if (!$slugs) continue;
                $placeholders = implode(',', array_fill(0, count($slugs), '?'));
                $where[] = "EXISTS (
                    SELECT 1 FROM product_filter_map pfm
                    JOIN product_filter_options pfo ON pfo.id = pfm.option_id
                    WHERE pfm.product_id = p.id
                      AND pfo.group_slug = ?
                      AND pfo.option_slug IN ({$placeholders})
                      AND pfo.is_active = 1
                )";
                $params[] = $groupSlug;
                array_push($params, ...$slugs);
            }
        }

        try {
            return self::fetchProductRows('WHERE ' . implode(' AND ', $where) . ' ORDER BY c.sort_order ASC, p.sort_order ASC', $params);
        } catch (\Throwable $e) {
            error_log('Business need mapped products unavailable: ' . $e->getMessage());
            $need = \Database::row("SELECT product_ids FROM business_needs WHERE id=? LIMIT 1", [$businessNeedId]);
            $ids = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', (string)($need['product_ids'] ?? '')) ?: []), static fn($id) => $id > 0));
            if (!$ids) return [];
            $all = self::all(true);
            $byId = []; foreach ($all as $row) $byId[(int)($row['id'] ?? 0)] = $row;
            $out = []; foreach ($ids as $id) if (isset($byId[$id])) $out[] = $byId[$id];
            return $out;
        }
    }

    public static function search(string $q): array
    {
        $like = '%' . $q . '%';
        return self::fetchProductRows(
            'WHERE p.is_active = 1 AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?) ORDER BY p.sort_order ASC',
            [$like, $like, $like]
        );
    }

    public static function byCategory(string $slug, array $filters = []): ?array
    {
        $category = \Database::row(
            "SELECT * FROM categories WHERE slug = ? AND is_active = 1",
            [$slug]
        );
        if (!$category) return null;

        $products = self::filteredProducts($filters, (int)$category['id']);

        return ['category' => $category, 'products' => $products];
    }

    public static function allProductsPage(): array
    {
        $products   = self::all(true);
        $categories = self::categories();
        return compact('products', 'categories');
    }

    private static function hydrate(array $product): array
    {
        try {
            $product['images'] = \Database::rows(
                "SELECT id, product_id, COALESCE(image_path, url) as url, COALESCE(image_path, url) as image_path, alt_text, is_primary, sort_order FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC",
                [$product['id']]
            );
        } catch (\Throwable) {
            $product['images'] = \Database::rows(
                "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC",
                [$product['id']]
            );
            foreach ($product['images'] as &$img) {
                if (!isset($img['url']) && isset($img['image_path'])) $img['url'] = $img['image_path'];
                if (!isset($img['image_path']) && isset($img['url'])) $img['image_path'] = $img['url'];
            }
            unset($img);
        }

        if (empty($product['images']) && !empty($product['image_path'])) {
            $product['images'][] = [
                'id' => 0,
                'product_id' => (int)$product['id'],
                'url' => $product['image_path'],
                'image_path' => $product['image_path'],
                'alt_text' => $product['name'] ?? '',
                'is_primary' => 1,
                'sort_order' => 0,
            ];
        }

        $product['specs'] = \Database::rows(
            "SELECT * FROM product_specs WHERE product_id = ? ORDER BY sort_order ASC",
            [$product['id']]
        );
        $product['filter_options'] = self::productFilterSelections((int)$product['id']);

        $pricing = \Cart\Pricing::productPricingData((int)$product['id']);
        $product['qualities']      = $pricing['qualities'];
        $product['attr_groups']    = [];
        $product['quantity_tiers'] = $pricing['tiers'];

        return $product;
    }

    public static function upsert(array $data, ?int $editId = null): array
    {
        $errors = [];
        if (empty($data['name'])) $errors[] = 'Name required';
        if (empty($data['category_id'])) $errors[] = 'Category required';
        if (!preg_match('/^\d+$/', trim((string)($data['product_code'] ?? '')))) $errors[] = 'Product Code number required';
        if(trim((string)($data['video_url']??''))!==''&&self::normalizeVideoUrl((string)$data['video_url'])==='')$errors[]='Enter a valid YouTube video URL';
        if ($errors) return ['ok' => false, 'msg' => implode(', ', $errors)];

        $slug = self::makeSlug($data['name'], $editId);
        $productCode = self::resolveProductCode($data, $editId);

        try {
            if ($editId) {
                self::updateProduct((int)$editId, $data, $slug, $productCode);
                self::syncSpecs($editId, $data['specs'] ?? []);
                self::syncQuantityTiers($editId, $data['quantity_tiers'] ?? []);
                self::syncProductFilters($editId, $data['filter_options'] ?? []);
                self::syncPresentation($editId,$data);
                \Orders\AdminAudit::log('product_updated', "Product #{$editId}: {$data['name']}");
                return ['ok' => true, 'id' => $editId];
            }

            $id = self::insertProduct($data, $slug, $productCode);

            self::syncSpecs((int)$id, $data['specs'] ?? []);
            self::syncQuantityTiers((int)$id, $data['quantity_tiers'] ?? []);
            self::syncProductFilters((int)$id, $data['filter_options'] ?? []);
            self::syncPresentation((int)$id,$data);
            \Orders\AdminAudit::log('product_created', "Product #{$id}: {$data['name']}");
            return ['ok' => true, 'id' => (int)$id];
        } catch (\Throwable $e) {
            error_log('Product upsert failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Save failed. Check required DB columns/tables and server logs.'];
        }
    }

    private static function updateProduct(int $editId, array $data, string $slug, ?string $productCode): void
    {
        $originalPrice = self::normalizeOriginalPrice($data['original_price'] ?? null);
        if (self::productCodeColumnReady()) {
            if (self::originalPriceColumnReady()) {
                try {
                    \Database::query(
                        "UPDATE products SET name=?, slug=?, category_id=?, product_code=?, description=?,
                            meta_title=?, design_fee=?, original_price=?, image_path=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?",
                        [
                            $data['name'], $slug, $data['category_id'], $productCode,
                            $data['description'] ?? '',
                            $data['meta_title'] ?? $data['name'],
                            (float)($data['design_fee'] ?? 0),
                            $originalPrice,
                            $data['image_path'] ?? null,
                            $data['is_active'] ?? 1,
                            $data['sort_order'] ?? 0,
                            $editId,
                        ]
                    );
                    return;
                } catch (\Throwable) {}
            }
            try {
                \Database::query(
                    "UPDATE products SET name=?, slug=?, category_id=?, product_code=?, description=?,
                        meta_title=?, design_fee=?, image_path=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?",
                    [
                        $data['name'], $slug, $data['category_id'], $productCode,
                        $data['description'] ?? '',
                        $data['meta_title'] ?? $data['name'],
                        (float)($data['design_fee'] ?? 0),
                        $data['image_path'] ?? null,
                        $data['is_active'] ?? 1,
                        $data['sort_order'] ?? 0,
                        $editId,
                    ]
                );
                return;
            } catch (\Throwable) {}
        }

        if (self::originalPriceColumnReady()) {
            try {
                \Database::query(
                    "UPDATE products SET name=?, slug=?, category_id=?, description=?,
                        meta_title=?, design_fee=?, original_price=?, image_path=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?",
                    [
                        $data['name'], $slug, $data['category_id'],
                        $data['description'] ?? '',
                        $data['meta_title'] ?? $data['name'],
                        (float)($data['design_fee'] ?? 0),
                        $originalPrice,
                        $data['image_path'] ?? null,
                        $data['is_active'] ?? 1,
                        $data['sort_order'] ?? 0,
                        $editId,
                    ]
                );
                return;
            } catch (\Throwable) {}
        }

        try {
            \Database::query(
                "UPDATE products SET name=?, slug=?, category_id=?, description=?,
                    meta_title=?, design_fee=?, image_path=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?",
                [
                    $data['name'], $slug, $data['category_id'],
                    $data['description'] ?? '',
                    $data['meta_title'] ?? $data['name'],
                    (float)($data['design_fee'] ?? 0),
                    $data['image_path'] ?? null,
                    $data['is_active'] ?? 1,
                    $data['sort_order'] ?? 0,
                    $editId,
                ]
            );
        } catch (\Throwable) {
            \Database::query(
                "UPDATE products SET name=?, slug=?, category_id=?, description=?,
                    meta_title=?, design_fee=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?",
                [
                    $data['name'], $slug, $data['category_id'],
                    $data['description'] ?? '',
                    $data['meta_title'] ?? $data['name'],
                    (float)($data['design_fee'] ?? 0),
                    $data['is_active'] ?? 1,
                    $data['sort_order'] ?? 0,
                    $editId,
                ]
            );
        }
    }

    private static function insertProduct(array $data, string $slug, ?string $productCode): int
    {
        $originalPrice = self::normalizeOriginalPrice($data['original_price'] ?? null);
        if (self::productCodeColumnReady()) {
            if (self::originalPriceColumnReady()) {
                try {
                    return (int)\Database::insert(
                        "INSERT INTO products (name, slug, category_id, product_code, description, meta_title, design_fee, original_price, image_path, is_active, sort_order, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $data['name'], $slug, $data['category_id'], $productCode,
                            $data['description'] ?? '',
                            $data['meta_title'] ?? $data['name'],
                            (float)($data['design_fee'] ?? 0),
                            $originalPrice,
                            $data['image_path'] ?? null,
                            $data['is_active'] ?? 1,
                            $data['sort_order'] ?? 0,
                        ]
                    );
                } catch (\Throwable) {}
            }
            try {
                return (int)\Database::insert(
                    "INSERT INTO products (name, slug, category_id, product_code, description, meta_title, design_fee, image_path, is_active, sort_order, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $data['name'], $slug, $data['category_id'], $productCode,
                        $data['description'] ?? '',
                        $data['meta_title'] ?? $data['name'],
                        (float)($data['design_fee'] ?? 0),
                        $data['image_path'] ?? null,
                        $data['is_active'] ?? 1,
                        $data['sort_order'] ?? 0,
                    ]
                );
            } catch (\Throwable) {}
        }

        if (self::originalPriceColumnReady()) {
            try {
                return (int)\Database::insert(
                    "INSERT INTO products (name, slug, category_id, description, meta_title, design_fee, original_price, image_path, is_active, sort_order, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $data['name'], $slug, $data['category_id'],
                        $data['description'] ?? '',
                        $data['meta_title'] ?? $data['name'],
                        (float)($data['design_fee'] ?? 0),
                        $originalPrice,
                        $data['image_path'] ?? null,
                        $data['is_active'] ?? 1,
                        $data['sort_order'] ?? 0,
                    ]
                );
            } catch (\Throwable) {}
        }

        try {
            return (int)\Database::insert(
                "INSERT INTO products (name, slug, category_id, description, meta_title, design_fee, image_path, is_active, sort_order, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $data['name'], $slug, $data['category_id'],
                    $data['description'] ?? '',
                    $data['meta_title'] ?? $data['name'],
                    (float)($data['design_fee'] ?? 0),
                    $data['image_path'] ?? null,
                    $data['is_active'] ?? 1,
                    $data['sort_order'] ?? 0,
                ]
            );
        } catch (\Throwable) {
            return (int)\Database::insert(
                "INSERT INTO products (name, slug, category_id, description, meta_title, design_fee, is_active, sort_order, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $data['name'], $slug, $data['category_id'],
                    $data['description'] ?? '',
                    $data['meta_title'] ?? $data['name'],
                    (float)($data['design_fee'] ?? 0),
                    $data['is_active'] ?? 1,
                    $data['sort_order'] ?? 0,
                ]
            );
        }
    }

    private static function resolveProductCode(array $data, ?int $editId = null): ?string
    {
        if (!self::productCodeColumnReady()) return null;

        $manual = trim((string)($data['product_code'] ?? ''));
        $categoryId = (int)($data['category_id'] ?? 0);
        if (!ctype_digit($manual) || $categoryId <= 0) return null;
        $prefix='RCSPRD';
        if(self::categoryCodePrefixColumnReady()){$cat=\Database::row("SELECT code_prefix FROM categories WHERE id=? LIMIT 1",[$categoryId]);$prefix=preg_replace('/[^A-Z0-9]/','',strtoupper((string)($cat['code_prefix']??'')))?:$prefix;}
        return $prefix.$manual;
    }

    private static function generateProductCode(int $categoryId, ?int $editId = null): ?string
    {
        $prefix = 'RCSPRD';
        if (self::categoryCodePrefixColumnReady()) {
            $cat = \Database::row("SELECT code_prefix FROM categories WHERE id = ? LIMIT 1", [$categoryId]);
            $fromDb = strtoupper(trim((string)($cat['code_prefix'] ?? '')));
            if ($fromDb !== '') {
                $prefix = preg_replace('/[^A-Z0-9]/', '', $fromDb) ?: $prefix;
            }
        }

        $like = $prefix . '-%';
        $params = [$categoryId, $like];
        $sql = "SELECT product_code FROM products WHERE category_id = ? AND product_code LIKE ?";
        if ($editId) {
            $sql .= " AND id <> ?";
            $params[] = $editId;
        }
        $rows = \Database::rows($sql, $params);

        $max = 0;
        foreach ($rows as $r) {
            $code = (string)($r['product_code'] ?? '');
            if (preg_match('/-(\d+)$/', $code, $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
        $next = $max + 1;
        return $prefix . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }

    public static function nextProductCodePreview(int $categoryId, ?int $editId = null): ?string
    {
        if ($categoryId <= 0) return null;
        if (!self::productCodeColumnReady()) return null;
        return self::generateProductCode($categoryId, $editId);
    }

    private static function ensureProductCode(array $product): array
    {
        return $product;
    }

    private static function productCodeColumnReady(): bool
    {
        if (self::$hasProductCodeColumn !== null) return self::$hasProductCodeColumn;
        try {
            $row = \Database::row(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_code'",
                [DB_NAME]
            );
            self::$hasProductCodeColumn = (int)($row['c'] ?? 0) === 1;
        } catch (\Throwable) {
            self::$hasProductCodeColumn = false;
        }
        return self::$hasProductCodeColumn;
    }

    private static function originalPriceColumnReady(): bool
    {
        if (self::$hasOriginalPriceColumn !== null) return self::$hasOriginalPriceColumn;
        try {
            $row = \Database::row(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'products' AND COLUMN_NAME = 'original_price'",
                [DB_NAME]
            );
            self::$hasOriginalPriceColumn = (int)($row['c'] ?? 0) === 1;
        } catch (\Throwable) {
            self::$hasOriginalPriceColumn = false;
        }
        return self::$hasOriginalPriceColumn;
    }

    private static function normalizeOriginalPrice(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        $price = (float)$value;
        return $price > 0 ? $price : null;
    }

    private static function categoryCodePrefixColumnReady(): bool
    {
        if (self::$hasCategoryCodePrefixColumn !== null) return self::$hasCategoryCodePrefixColumn;
        try {
            $row = \Database::row(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'code_prefix'",
                [DB_NAME]
            );
            self::$hasCategoryCodePrefixColumn = (int)($row['c'] ?? 0) === 1;
        } catch (\Throwable) {
            self::$hasCategoryCodePrefixColumn = false;
        }
        return self::$hasCategoryCodePrefixColumn;
    }

    private static function productFilterSelections(int $productId): array
    {
        if ($productId <= 0) return [];
        try {
            self::ensureFilterSchema();
            $rows = \Database::rows(
                "SELECT pfo.group_slug, pfo.option_slug
                 FROM product_filter_map pfm
                 JOIN product_filter_options pfo ON pfo.id = pfm.option_id
                 WHERE pfm.product_id = ? AND pfo.is_active = 1
                 ORDER BY pfo.group_slug ASC, pfo.sort_order ASC",
                [$productId]
            );
        } catch (\Throwable) {
            return [];
        }

        $selected = [];
        foreach ($rows as $row) {
            $group = (string)($row['group_slug'] ?? '');
            $option = (string)($row['option_slug'] ?? '');
            if ($group !== '' && $option !== '') $selected[$group][] = $option;
        }
        return $selected;
    }

    private static function syncProductFilters(int $productId, array $input): void
    {
        if ($productId <= 0) return;
        try {
            self::ensureFilterSchema();
            $selected = self::normalizeFilterSelections($input);
            \Database::query("DELETE FROM product_filter_map WHERE product_id = ?", [$productId]);
            if (!$selected) return;

            $allSlugs = [];
            foreach ($selected as $slugs) {
                foreach ($slugs as $slug) $allSlugs[$slug] = $slug;
            }
            if (!$allSlugs) return;

            $placeholders = implode(',', array_fill(0, count($allSlugs), '?'));
            $rows = \Database::rows(
                "SELECT id, group_slug, option_slug FROM product_filter_options WHERE option_slug IN ({$placeholders}) AND is_active = 1",
                array_values($allSlugs)
            );

            foreach ($rows as $row) {
                $group = (string)($row['group_slug'] ?? '');
                $slug = (string)($row['option_slug'] ?? '');
                if (!in_array($slug, $selected[$group] ?? [], true)) continue;
                \Database::query(
                    "INSERT IGNORE INTO product_filter_map (product_id, option_id) VALUES (?, ?)",
                    [$productId, (int)$row['id']]
                );
            }
        } catch (\Throwable $e) {
            error_log('Product filter sync failed: ' . $e->getMessage());
        }
    }

    private static function syncSpecs(int $productId, array $specs): void
    {
        \Database::query("DELETE FROM product_specs WHERE product_id = ?", [$productId]);
        foreach ($specs as $i => $spec) {
            if (!empty($spec['label'])) {
                \Database::insert(
                    "INSERT INTO product_specs (product_id, label, value, sort_order) VALUES (?, ?, ?, ?)",
                    [$productId, $spec['label'], $spec['value'] ?? '', $i]
                );
            }
        }
    }

    private static function syncQuantityTiers(int $productId, array $tiers): void
    {
        try {
            \Database::query("DELETE FROM product_quantity_tiers WHERE product_id = ?", [$productId]);
        } catch (\Throwable) {
            return;
        }

        $seen = [];
        usort($tiers, fn($a,$b) => ((int)($a['quantity'] ?? 0)) <=> ((int)($b['quantity'] ?? 0)));

        foreach ($tiers as $t) {
            $qty = (int)($t['quantity'] ?? 0);
            $price = (float)($t['price'] ?? 0);
            if ($qty <= 0 || $price <= 0 || isset($seen[$qty])) continue;
            $seen[$qty] = true;
            \Database::insert(
                "INSERT INTO product_quantity_tiers (product_id, quantity, price, created_at)
                 VALUES (?, ?, ?, NOW())",
                [$productId, $qty, $price]
            );
        }
    }

    private static function makeSlug(string $name, ?int $excludeId = null): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $base = trim($base, '-');
        $slug = $base;
        $i = 1;
        while (true) {
            $exists = \Database::row(
                "SELECT id FROM products WHERE slug = ?" . ($excludeId ? " AND id != {$excludeId}" : ''),
                [$slug]
            );
            if (!$exists) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}

<?php
// ═══════════════════════════════════════════════════════════════
//  RCS Graphic — index.php
//  Yeh file: domains/print.rcsgraphic.com/public_html/index.php
// ═══════════════════════════════════════════════════════════════

declare(strict_types=1);

$rootPath = __DIR__;

if (!is_dir($rootPath . '/config')) {
    die('\n    <div style="font-family:monospace;padding:30px;background:#1e1e1e;color:#ff6b6b;min-height:100vh">\n    <h2>⚠️ RCS Graphic — Setup Error</h2>\n    <p>Required <code>config/</code> folder not found at: <code>' . htmlspecialchars($rootPath) . '</code></p>\n    </div>\n    ');
}

require_once $rootPath . '/config/config.php';
require_once SRC_PATH . '/Database.php';

// ── Helper Functions ──────────────────────────────────────────
function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $csrf    = \Auth\Auth::csrfToken();
    $user    = \Auth\Auth::user();
    $isAdmin = \Auth\Auth::isAdmin();
    $uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $file = TMPL_PATH . '/' . $template . '.php';
    if (!file_exists($file)) {
        http_response_code(404);
        echo '<h1 style="font-family:sans-serif;padding:30px">Page not found: ' . htmlspecialchars($template) . '</h1>';
        exit;
    }
    include $file;
}

function json(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}

// ── Request Parse Karo ────────────────────────────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD']);
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = '/' . trim($uri, '/');
if ($uri === '//') $uri = '/';

// ── Security Headers ──────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ═══════════════════════════════════════════════════════════════
//  ROUTES — Public Pages
// ═══════════════════════════════════════════════════════════════


// SEO Sitemap — dynamic URLs for launch indexing
if ($uri === '/sitemap.xml' && $method === 'GET') {
    $baseUrl = defined('APP_URL') ? rtrim((string)APP_URL, '/') : 'https://print.rcsgraphic.com';
    $today = date('Y-m-d');
    $urls = [];
    $addUrl = static function (string $path, string $priority = '0.80', string $changefreq = 'weekly', ?string $lastmod = null) use (&$urls, $baseUrl, $today): void {
        $path = '/' . ltrim($path, '/');
        $urls[$path] = [
            'loc' => $baseUrl . $path,
            'lastmod' => $lastmod ?: $today,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    };

    $addUrl('/', '1.00', 'daily');
    $addUrl('/products', '0.90', 'daily');
    $addUrl('/categories', '0.80', 'weekly');
    $addUrl('/blogs', '0.70', 'weekly');
    $addUrl('/about', '0.70', 'monthly');
    $addUrl('/portfolio', '0.70', 'monthly');
    $addUrl('/contact', '0.70', 'monthly');
    $addUrl('/shipping-policy', '0.40', 'monthly');
    $addUrl('/refund-return-policy', '0.40', 'monthly');
    $addUrl('/terms-and-conditions', '0.40', 'monthly');
    $addUrl('/privacy-policy', '0.40', 'monthly');

    try {
        foreach (\Catalog\ProductCatalog::categories() as $category) {
            $slug = trim((string)($category['slug'] ?? ''));
            if ($slug !== '') $addUrl('/category/' . rawurlencode($slug), '0.80', 'weekly');
        }
    } catch (\Throwable $e) {
        error_log('Sitemap categories unavailable: ' . $e->getMessage());
    }

    try {
        foreach (\Catalog\ProductCatalog::all(true) as $product) {
            $slug = trim((string)($product['slug'] ?? ''));
            if ($slug !== '') $addUrl('/product/' . rawurlencode($slug), '0.90', 'weekly', substr((string)($product['updated_at'] ?? ''), 0, 10) ?: null);
        }
    } catch (\Throwable $e) {
        error_log('Sitemap products unavailable: ' . $e->getMessage());
    }

    try {
        $blogs = Database::rows("SELECT slug, updated_at, published_at FROM blogs WHERE is_active = 1 ORDER BY published_at DESC, id DESC");
        foreach ($blogs as $blog) {
            $slug = trim((string)($blog['slug'] ?? ''));
            if ($slug !== '') {
                $lastmod = substr((string)($blog['updated_at'] ?? $blog['published_at'] ?? ''), 0, 10) ?: null;
                $addUrl('/blog/' . rawurlencode($slug), '0.70', 'monthly', $lastmod);
            }
        }
        try {
            $businessNeeds = Database::rows("SELECT slug, updated_at FROM business_needs WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
            foreach ($businessNeeds as $need) {
                $slug = trim((string)($need['slug'] ?? ''));
                if ($slug !== '') $addUrl('/business/' . rawurlencode($slug), '0.75', 'weekly', substr((string)($need['updated_at'] ?? ''), 0, 10) ?: null);
            }
        } catch (\Throwable $e) {
            error_log('Sitemap business needs unavailable: ' . $e->getMessage());
        }
    } catch (\Throwable $e) {
        error_log('Sitemap blogs unavailable: ' . $e->getMessage());
    }

    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $url) {
        echo "  <url>\n";
        echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
        echo '    <lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
        echo '    <changefreq>' . htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
        echo '    <priority>' . htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
        echo "  </url>\n";
    }
    echo '</urlset>';
    exit;
}

// Home Page
if ($uri === '/' && $method === 'GET') {
    try {
        $categories  = \Catalog\ProductCatalog::categories();
        $products    = \Catalog\ProductCatalog::all();
        $homeBanners = Database::rows("SELECT * FROM home_banners WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
        $homeDeals   = [];
        $comboOffers = [];
        try { $comboOffers = \Combos\ComboOfferManager::home(); } catch (\Throwable $e) { error_log('Home combo offers unavailable: '.$e->getMessage()); }
        try {
            $homeDeals = Database::rows("SELECT * FROM home_deals WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
        } catch (\Throwable $e) {
            error_log('Home deals unavailable: ' . $e->getMessage());
        }
        $homeBlogs = [];
        try {
            $homeBlogs = Database::rows("SELECT * FROM blogs WHERE is_active=1 AND is_featured=1 ORDER BY sort_order ASC, published_at DESC, id DESC");
        } catch (\Throwable $e) {
            error_log('Home blogs unavailable: ' . $e->getMessage());
        }
        $businessNeeds = [];
        try {
            $businessNeeds = Database::rows("SELECT * FROM business_needs WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
        } catch (\Throwable $e) {
            error_log('Business needs unavailable: ' . $e->getMessage());
        }
        $settings    = Database::rows("SELECT `key`, value FROM settings");
        $settingsMap = array_column($settings, 'value', 'key');
        $homeReviews = \Reviews\ProductReview::featured(6);
    } catch (\Throwable $e) {
        error_log('Home error: ' . $e->getMessage());
        $categories = $products = [];
        $homeBanners = [];
        $homeDeals = [];
        $comboOffers = [];
        $homeBlogs = [];
        $businessNeeds = [];
        $settingsMap = [];
        $homeReviews = [];
    }
    view('home', compact('categories', 'products', 'settingsMap', 'homeBanners', 'homeDeals', 'comboOffers', 'homeBlogs', 'homeReviews', 'businessNeeds'));
    exit;
}

if (preg_match('#^/combo/([a-z0-9-]+)$#', $uri, $m) && $method === 'GET') {
    try { $combo = \Combos\ComboOfferManager::findBySlug($m[1]); } catch (\Throwable) { $combo = null; }
    if (!$combo) { http_response_code(404); view('404'); exit; }
    view('combo-offer', compact('combo')); exit;
}


// Blogs Listing Page — /blogs
if ($uri === '/blogs' && $method === 'GET') {
    $blogPage = max(1, (int)($_GET['page'] ?? 1));
    $blogSearch = trim((string)($_GET['search'] ?? ''));
    $blogCategory = trim((string)($_GET['category'] ?? ''));
    $blogsPerPage = 6;
    $blogTotal = 0;
    $blogTotalPages = 1;
    $blogCategories = [];
    $popularBlogs = [];
    $featuredBlog = null;

    try {
        $settings = Database::rows("SELECT `key`, value FROM settings");
        $settingsMap = array_column($settings, 'value', 'key');

        $where = ['is_active = 1'];
        $params = [];
        if ($blogSearch !== '') {
            $where[] = '(title LIKE ? OR excerpt LIKE ? OR category LIKE ?)';
            $like = '%' . $blogSearch . '%';
            array_push($params, $like, $like, $like);
        }
        if ($blogCategory !== '') {
            $where[] = 'category = ?';
            $params[] = $blogCategory;
        }
        $whereSql = implode(' AND ', $where);

        $countRow = Database::row("SELECT COUNT(*) AS total FROM blogs WHERE $whereSql", $params);
        $blogTotal = (int)($countRow['total'] ?? 0);
        $blogTotalPages = max(1, (int)ceil($blogTotal / $blogsPerPage));
        $blogPage = min($blogPage, $blogTotalPages);
        $offset = ($blogPage - 1) * $blogsPerPage;

        $blogs = Database::rows(
            "SELECT id,title,slug,excerpt,featured_image,image_alt,category,badge_theme,author_name,published_at,is_featured
             FROM blogs
             WHERE $whereSql
             ORDER BY is_featured DESC, sort_order ASC, published_at DESC, id DESC
             LIMIT $blogsPerPage OFFSET $offset",
            $params
        );

        $featuredBlog = Database::row(
            "SELECT id,title,slug,excerpt,featured_image,image_alt,category,badge_theme,author_name,published_at
             FROM blogs
             WHERE is_active = 1
             ORDER BY is_featured DESC, sort_order ASC, published_at DESC, id DESC
             LIMIT 1"
        );

        $blogCategories = Database::rows(
            "SELECT category, COUNT(*) AS total
             FROM blogs
             WHERE is_active = 1 AND category <> ''
             GROUP BY category
             ORDER BY total DESC, category ASC"
        );

        $popularBlogs = Database::rows(
            "SELECT id,title,slug,featured_image,image_alt,category,published_at
             FROM blogs
             WHERE is_active = 1
             ORDER BY is_featured DESC, published_at DESC, id DESC
             LIMIT 6"
        );
    } catch (\Throwable $e) {
        error_log('Blogs listing error: ' . $e->getMessage());
        $blogs = [];
        $settingsMap = [];
        $blogCategories = [];
        $popularBlogs = [];
        $featuredBlog = null;
        $blogTotal = 0;
        $blogTotalPages = 1;
        $blogPage = 1;
    }
    view('blogs', compact('blogs', 'settingsMap', 'featuredBlog', 'blogCategories', 'popularBlogs', 'blogSearch', 'blogCategory', 'blogPage', 'blogTotalPages', 'blogTotal'));
    exit;
}

// Blog Detail Page — /blog/{slug}
if (preg_match('#^/blog/([a-z0-9\-]+)$#', $uri, $m) && $method === 'GET') {
    try {
        $blog = Database::row(
            "SELECT * FROM blogs WHERE slug = ? AND is_active = 1 LIMIT 1",
            [$m[1]]
        );
        $relatedBlogs = [];
        $blogCategories = [];
        $popularBlogs = [];
        $previousBlog = null;
        $nextBlog = null;
        if ($blog) {
            $relatedBlogs = Database::rows(
                "SELECT id,title,slug,excerpt,featured_image,image_alt,category,published_at
                 FROM blogs
                 WHERE is_active = 1 AND slug <> ?
                 ORDER BY CASE WHEN category = ? THEN 0 ELSE 1 END, is_featured DESC, sort_order ASC, published_at DESC, id DESC
                 LIMIT 10",
                [$m[1], (string)($blog['category'] ?? '')]
            );
            $blogCategories = Database::rows(
                "SELECT category, COUNT(*) AS total
                 FROM blogs
                 WHERE is_active = 1 AND category <> ''
                 GROUP BY category
                 ORDER BY total DESC, category ASC"
            );
            $popularBlogs = Database::rows(
                "SELECT id,title,slug,featured_image,image_alt,category,published_at
                 FROM blogs
                 WHERE is_active = 1 AND slug <> ?
                 ORDER BY is_featured DESC, published_at DESC, id DESC
                 LIMIT 5",
                [$m[1]]
            );
            $publishedForNav = (string)($blog['published_at'] ?? '1970-01-01 00:00:00');
            $idForNav = (int)($blog['id'] ?? 0);
            $previousBlog = Database::row(
                "SELECT id,title,slug
                 FROM blogs
                 WHERE is_active = 1 AND (published_at < ? OR (published_at = ? AND id < ?))
                 ORDER BY published_at DESC, id DESC
                 LIMIT 1",
                [$publishedForNav, $publishedForNav, $idForNav]
            );
            $nextBlog = Database::row(
                "SELECT id,title,slug
                 FROM blogs
                 WHERE is_active = 1 AND (published_at > ? OR (published_at = ? AND id > ?))
                 ORDER BY published_at ASC, id ASC
                 LIMIT 1",
                [$publishedForNav, $publishedForNav, $idForNav]
            );
        }
        $settings = Database::rows("SELECT `key`, value FROM settings");
        $settingsMap = array_column($settings, 'value', 'key');
    } catch (\Throwable $e) {
        error_log('Blog detail error: ' . $e->getMessage());
        $blog = null;
        $relatedBlogs = [];
        $blogCategories = [];
        $popularBlogs = [];
        $previousBlog = null;
        $nextBlog = null;
        $settingsMap = [];
    }

    if (!$blog) { http_response_code(404); view('404'); exit; }

    view('blog-detail', compact('blog', 'relatedBlogs', 'blogCategories', 'popularBlogs', 'previousBlog', 'nextBlog', 'settingsMap'));
    exit;
}

// Product Page
if (preg_match('#^/product/([a-z0-9\-]+)$#', $uri, $m) && $method === 'GET') {
    try { $product = \Catalog\ProductCatalog::bySlug($m[1]); }
    catch (\Throwable $e) { error_log('Product error: '.$e->getMessage()); $product = null; }

    if (!$product) { http_response_code(404); view('404'); exit; }

    try { $relatedProducts = \Catalog\ProductCatalog::randomRecommendations((int)($product['id'] ?? 0), 5); }
    catch (\Throwable) { $relatedProducts = []; }
    $reviewSummary = \Reviews\ProductReview::summaryForProduct((int)$product['id']);
    $productReviews = \Reviews\ProductReview::approvedForProduct((int)$product['id'], 12);
    $wishlistActive = false;
    if ($user = \Auth\Auth::user()) {
        $wishlistActive = \Wishlist\Wishlist::isWishlisted((int)$user['id'], (int)($product['id'] ?? 0));
    }

    view('product', compact('product', 'relatedProducts', 'reviewSummary', 'productReviews', 'wishlistActive'));
    exit;
}

// ── All Categories Page — /categories ─────────────────────────
if ($uri === '/categories' && $method === 'GET') {
    try {
        $categories = \Catalog\ProductCatalog::categories();
        $settings = Database::rows("SELECT `key`, value FROM settings");
        $settingsMap = array_column($settings, 'value', 'key');
    } catch (\Throwable $e) {
        error_log('Categories page error: ' . $e->getMessage());
        $categories = [];
        $settingsMap = [];
    }
    view('categories', compact('categories', 'settingsMap'));
    exit;
}

// ── Category Page — /category/{slug} ─────────────────────────
if (preg_match('#^/category/([a-z0-9\-]+)$#', $uri, $m) && $method === 'GET') {
    try {
        $selectedFilters = \Catalog\ProductCatalog::normalizeFilterSelections($_GET['filters'] ?? []);
        $result = \Catalog\ProductCatalog::byCategory($m[1], $selectedFilters);
        $filterOptions = \Catalog\ProductCatalog::filterOptions();
    } catch (\Throwable $e) {
        error_log('Category error: ' . $e->getMessage());
        $result = null;
        $selectedFilters = $filterOptions = [];
    }

    if (!$result) { http_response_code(404); view('404'); exit; }

    $category = $result['category'];
    $products = $result['products'];

    try { $categories = \Catalog\ProductCatalog::categories(); }
    catch (\Throwable) { $categories = []; }

    view('category', compact('category', 'products', 'categories', 'filterOptions', 'selectedFilters'));
    exit;
}


// ── All Business Sectors Page — /business ─────────────────────
if (($uri === '/business' || $uri === '/business-needs') && $method === 'GET') {
    try {
        $businessNeeds = Database::rows("SELECT bn.*, COUNT(pbn.product_id) AS product_count FROM business_needs bn LEFT JOIN product_business_needs pbn ON pbn.business_need_id = bn.id WHERE bn.is_active=1 GROUP BY bn.id ORDER BY bn.sort_order ASC, bn.id DESC");
        $settings = Database::rows("SELECT `key`, value FROM settings");
        $settingsMap = array_column($settings, 'value', 'key');
    } catch (\Throwable $e) {
        error_log('Business sectors page error: ' . $e->getMessage());
        $businessNeeds = [];
        $settingsMap = [];
    }
    view('business-needs', compact('businessNeeds', 'settingsMap'));
    exit;
}

// Business Need / Sector Page — /business/{slug}
if (preg_match('#^/business/([a-z0-9\-]+)$#', $uri, $m) && $method === 'GET') {
    try {
        $businessNeed = Database::row("SELECT * FROM business_needs WHERE slug=? AND is_active=1 LIMIT 1", [$m[1]]);
        if (!$businessNeed) { http_response_code(404); view('404'); exit; }
        $businessProducts = \Catalog\ProductCatalog::byBusinessNeed((int)$businessNeed['id']);
        $businessNeeds = Database::rows("SELECT bn.*, COUNT(pbn.product_id) AS product_count FROM business_needs bn LEFT JOIN product_business_needs pbn ON pbn.business_need_id = bn.id WHERE bn.is_active=1 GROUP BY bn.id ORDER BY bn.sort_order ASC, bn.id DESC");
        $settings = Database::rows("SELECT `key`, value FROM settings");
        $settingsMap = array_column($settings, 'value', 'key');
    } catch (\Throwable $e) {
        error_log('Business page error: ' . $e->getMessage());
        http_response_code(404);
        view('404');
        exit;
    }
    view('business-need', compact('businessNeed', 'businessProducts', 'businessNeeds', 'settingsMap'));
    exit;
}

// ── All Products Page — /products ─────────────────────────────
if ($uri === '/products' && $method === 'GET') {
    try {
        $categories = \Catalog\ProductCatalog::categories();
        $products   = \Catalog\ProductCatalog::all();
        $settings   = Database::rows("SELECT `key`, value FROM settings");
        $settingsMap = array_column($settings, 'value', 'key');
    } catch (\Throwable $e) {
        error_log('Products page error: ' . $e->getMessage());
        $categories = $products = [];
        $settingsMap = [];
    }
    view('all-products', compact('categories', 'products', 'settingsMap'));
    exit;
}


// Recently viewed products API
if ($uri === '/api/recent-products' && $method === 'GET') {
    $rawIds = preg_split('/[,\s]+/', (string)($_GET['ids'] ?? '')) ?: [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn($id) => $id > 0)));
    $ids = array_slice($ids, 0, 12);
    if (!$ids) json(['ok' => true, 'products' => []]);
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::rows(
            "SELECT p.id, p.name, p.slug, p.image_path, c.name AS category_name,
                    COALESCE(
                      (SELECT COALESCE(pi.image_path, pi.url) FROM product_images pi WHERE pi.product_id=p.id AND pi.is_primary=1 ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1),
                      p.image_path,
                      (SELECT COALESCE(pi2.image_path, pi2.url) FROM product_images pi2 WHERE pi2.product_id=p.id ORDER BY pi2.sort_order ASC, pi2.id ASC LIMIT 1)
                    ) AS primary_image,
                    (SELECT MIN(t.price) FROM product_quantity_tiers t WHERE t.product_id=p.id) AS min_price
             FROM products p
             LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.is_active=1 AND p.id IN ($placeholders)",
            $ids
        );
        $byId = [];
        foreach ($rows as $row) $byId[(int)$row['id']] = $row;
        $ordered = [];
        foreach ($ids as $id) if (isset($byId[$id])) $ordered[] = $byId[$id];
        json(['ok' => true, 'products' => $ordered]);
    } catch (\Throwable $e) {
        json(['ok' => false, 'msg' => 'Could not load recently viewed products', 'products' => []], 500);
    }
}

// Login
if ($uri === '/login') {
    if ($method === 'GET') {
        $loginNext = trim((string)($_GET['next'] ?? $_GET['redirect'] ?? '/profile'));
        if ($loginNext === '' || $loginNext[0] !== '/' || str_starts_with($loginNext, '//')) {
            $loginNext = '/profile';
        }
        if (\Auth\Auth::check()) redirect($loginNext);
        view('auth/login');
        exit;
    }
}

// Register
if ($uri === '/register' && $method === 'GET') {
    if (\Auth\Auth::check()) redirect('/');
    view('auth/register');
    exit;
}

// Logout
if ($uri === '/logout') {
    \Auth\Auth::logout();
    redirect('/');
}

// My Orders
if ($uri === '/my-orders' && $method === 'GET') {
    \Auth\Auth::require();
    redirect('/profile#orders');
}

// My Profile
if ($uri === '/profile' && $method === 'GET') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $profile = \Auth\Auth::getProfile((int)$user['id']);
    try { $allOrders = \Orders\OrderManager::getUserOrders((int)$user['id']); }
    catch (\Throwable) { $allOrders = []; }
    $orders = array_values(array_filter($allOrders, static fn(array $order): bool =>
        (string)($order['order_type'] ?? 'normal') !== 'custom' && (int)($order['custom_quote_id'] ?? 0) === 0
    ));
    $customFulfillmentOrders = array_values(array_filter($allOrders, static fn(array $order): bool =>
        ((string)($order['order_type'] ?? 'normal') === 'custom' || (int)($order['custom_quote_id'] ?? 0) > 0)
        && (string)($order['payment_status'] ?? '') === 'paid'
    ));
    // A quote remains in the cart/quote workflow until payment is captured. Only
    // confirmed paid custom orders belong in the customer's order history.
    try { $customOrders=Database::rows("SELECT * FROM custom_quote_requests WHERE user_id=? AND payment_status='paid' AND status IN ('paid','converted_to_order') AND order_id IS NOT NULL ORDER BY created_at DESC",[(int)$user['id']]); } catch (\Throwable) { $customOrders=[]; }
    $reviewableItems = \Reviews\ProductReview::reviewableItemsForUser((int)$user['id']);
    $myReviews = \Reviews\ProductReview::userReviews((int)$user['id']);
    $wishlistItems = \Wishlist\Wishlist::itemsForUser((int)$user['id']);
    $myDesigns = \Designs\UserDesigns::forUser((int)$user['id']);
    try { Database::query("CREATE TABLE IF NOT EXISTS user_addresses (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,label VARCHAR(80) NOT NULL DEFAULT 'Address',business_name VARCHAR(180) NULL,address_line1 VARCHAR(255) NOT NULL,address_line2 VARCHAR(255) NULL,city VARCHAR(120) NOT NULL,state VARCHAR(120) NOT NULL,pincode VARCHAR(20) NOT NULL,is_default TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,KEY idx_user_addresses_user (user_id,is_default,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $savedAddresses=Database::rows("SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC",[(int)$user['id']]); } catch (\Throwable) { $savedAddresses=[]; }
    view('profile', compact('user', 'profile', 'orders', 'customOrders', 'customFulfillmentOrders', 'savedAddresses', 'reviewableItems', 'myReviews', 'wishlistItems', 'myDesigns'));
    exit;
}


if (preg_match('#^/account/artwork/(\d+)/(download|view)$#', $uri, $m) && $method === 'GET') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $file = \Designs\UserDesigns::downloadForUser((int)$user['id'], (int)$m[1]);
    if (!$file) {
        http_response_code(404);
        view('404');
        exit;
    }

    $relativePath = '/' . ltrim((string)($file['file_path'] ?? ''), '/');
    $fullPath = PUBLIC_PATH . $relativePath;
    $realPath = realpath($fullPath);
    $uploadRoot = realpath(UPLOAD_PATH);
    if (!$realPath || !$uploadRoot || !str_starts_with($realPath, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
        http_response_code(404);
        exit('File missing');
    }

    $downloadName = basename((string)($file['original_name'] ?: $file['filename'] ?: 'artwork-file'));
    $downloadName = str_replace(['"', "\r", "\n"], '', $downloadName);
    $disposition = ($m[2] ?? 'download') === 'view' ? 'inline' : 'attachment';
    header('Content-Type: ' . (($file['mime_type'] ?? '') ?: 'application/octet-stream'));
    header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($realPath));
    readfile($realPath);
    exit;
}

if ($uri === '/profile/security' && $method === 'GET') {
    \Auth\Auth::require();
    redirect('/profile#security');
}

// Static information pages
$sitePageRoutes = [
    '/about' => 'about',
    '/portfolio' => 'portfolio',
    '/shipping-policy' => 'shipping-policy',
    '/refund-return-policy' => 'refund-return-policy',
    '/terms-and-conditions' => 'terms-and-conditions',
    '/privacy-policy' => 'privacy-policy',
];
if (isset($sitePageRoutes[$uri]) && $method === 'GET') {
    $sitePages = require APP_PATH . '/data/site_pages.php';
    $page = $sitePages[$sitePageRoutes[$uri]] ?? null;
    if (!$page) { http_response_code(404); view('404'); exit; }
    $aboutReviews = [];
    try {
        $settings = Database::rows("SELECT `key`, value FROM settings");
        $settingsMap = array_column($settings, 'value', 'key');
        if ($sitePageRoutes[$uri] === 'about') {
            $aboutReviews = \Reviews\ProductReview::featured(3);
        }
    } catch (\Throwable) {
        $settingsMap = [];
        $aboutReviews = [];
    }
    if ($sitePageRoutes[$uri] === 'portfolio') {
        $portfolioCategories = [];
        $portfolioItems = [];
        $portfolioCategory = trim((string)($_GET['category'] ?? ''));
        $portfolioPage = max(1, (int)($_GET['page'] ?? 1));
        $portfolioPerPage = 12;
        $portfolioTotalPages = 1;
        try {
            $portfolioCategories = Database::rows("SELECT id, name, slug, icon, sort_order FROM portfolio_categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC");
            $portfolioCategorySlugs = [];
            foreach ($portfolioCategories as $portfolioCategoryRow) {
                $portfolioCategorySlugs[(string)($portfolioCategoryRow['slug'] ?? '')] = true;
            }
            foreach (\Catalog\ProductCatalog::categories() as $productCategory) {
                $productCategorySlug = trim((string)($productCategory['slug'] ?? ''));
                if ($productCategorySlug === '' || isset($portfolioCategorySlugs[$productCategorySlug])) {
                    continue;
                }
                $portfolioCategories[] = [
                    'id' => null,
                    'name' => (string)($productCategory['name'] ?? $productCategory['title'] ?? 'Category'),
                    'slug' => $productCategorySlug,
                    'icon' => (string)($productCategory['icon'] ?? 'fa-folder-open'),
                    'sort_order' => (int)($productCategory['sort_order'] ?? 999),
                ];
                $portfolioCategorySlugs[$productCategorySlug] = true;
            }
            usort($portfolioCategories, static function (array $a, array $b): int {
                return [(int)($a['sort_order'] ?? 999), (string)($a['name'] ?? '')] <=> [(int)($b['sort_order'] ?? 999), (string)($b['name'] ?? '')];
            });
            $categoryRow = null;
            if ($portfolioCategory !== '') {
                $categoryRow = Database::row("SELECT id, slug FROM portfolio_categories WHERE slug=? AND is_active=1 LIMIT 1", [$portfolioCategory]);
                if (!$categoryRow && !isset($portfolioCategorySlugs[$portfolioCategory])) {
                    $portfolioCategory = '';
                }
            }
            $where = ["pi.is_active=1"];
            $params = [];
            if ($categoryRow) {
                $where[] = "pi.category_id=?";
                $params[] = (int)$categoryRow['id'];
            } elseif ($portfolioCategory !== '') {
                $where[] = "1=0";
            }
            $whereSql = implode(' AND ', $where);
            $totalRow = Database::row("SELECT COUNT(*) AS total FROM portfolio_items pi WHERE {$whereSql}", $params) ?: [];
            $totalItems = (int)($totalRow['total'] ?? 0);
            $portfolioTotalPages = max(1, (int)ceil($totalItems / $portfolioPerPage));
            $portfolioPage = min($portfolioPage, $portfolioTotalPages);
            $offset = max(0, ($portfolioPage - 1) * $portfolioPerPage);
            $portfolioItems = Database::rows(
                "SELECT pi.*, pc.name AS category_name, pc.slug AS category_slug, pc.icon AS category_icon
                 FROM portfolio_items pi
                 LEFT JOIN portfolio_categories pc ON pc.id = pi.category_id
                 WHERE {$whereSql}
                 ORDER BY pi.is_featured DESC, pi.sort_order ASC, pi.created_at DESC, pi.id DESC
                 LIMIT {$portfolioPerPage} OFFSET {$offset}",
                $params
            );
        } catch (\Throwable) {
            $portfolioCategories = null;
            $portfolioItems = null;
            $portfolioCategory = '';
            $portfolioPage = 1;
            $portfolioTotalPages = 1;
        }
        view('portfolio', compact('settingsMap', 'portfolioCategories', 'portfolioItems', 'portfolioCategory', 'portfolioPage', 'portfolioTotalPages'));
        exit;
    }
    view('info-page', compact('page', 'settingsMap', 'aboutReviews'));
    exit;
}

if ($uri === '/contact' && $method === 'GET') {
    try {
        $settings = Database::rows("SELECT `key`, value FROM settings");
        $settingsMap = array_column($settings, 'value', 'key');
    } catch (\Throwable) {
        $settingsMap = [];
    }
    view('contact', compact('settingsMap'));
    exit;
}


// Legacy custom links add the approved quote to the persistent cart, then use the unified checkout.
if (preg_match('#^/(custom-cart|custom-checkout)/([^/]+)/?$#', $uri, $m) && $method === 'GET') {
    $mode=$m[1]; $token=trim(rawurldecode($m[2]));
    if ($token === '' || strlen($token) > 160) {
        http_response_code(410);
        view('info-page',['page'=>['title'=>'Custom order link invalid','intro'=>'Please ask our team to resend your custom order payment link.'],'settingsMap'=>[]]);
        exit;
    }
    try { $quote=Database::row("SELECT * FROM custom_quote_requests WHERE quote_token=? LIMIT 1",[$token]);
        if(!$quote){http_response_code(410);view('info-page',['page'=>['title'=>'Custom order link expired','intro'=>'Please ask our team to resend your custom order payment link.'],'settingsMap'=>[]]);exit;}
        $quoteStatus=(string)($quote['status']??''); $quotePaymentStatus=(string)($quote['payment_status']??'not_required');
        if($quotePaymentStatus==='paid'){redirect('/profile#custom-orders');}
        if((float)($quote['quoted_amount']??0)<=0 || in_array($quoteStatus,['closed','rejected'],true)){http_response_code(410);view('info-page',['page'=>['title'=>'Custom order unavailable','intro'=>'This custom order is closed. Please contact our team for assistance.'],'settingsMap'=>[]]);exit;}
        // Repair legacy records that were marked confirmed before payment was
        // actually captured. They must remain payable and available in cart.
        if(in_array($quoteStatus,['paid','converted_to_order'],true) && $quotePaymentStatus!=='paid'){
            Database::query("UPDATE custom_quote_requests SET status='payment_pending',payment_status='payment_pending',updated_at=NOW() WHERE id=?",[(int)$quote['id']]);
            $quote['status']='payment_pending'; $quote['payment_status']='payment_pending';
        }
        if(!\Auth\Auth::check()) redirect('/login?next=/'.$mode.'/'.rawurlencode($token));
        $user=\Auth\Auth::user(); if((int)($quote['user_id']??0)!==(int)$user['id']){http_response_code(403);view('info-page',['page'=>['title'=>'Account required','intro'=>'Please login with the account linked to this custom order.'],'settingsMap'=>[]]);exit;}
        $added=\Cart\Cart::addCustomQuote($quote,(int)$user['id']); if(!($added['ok']??false)){http_response_code(422);view('info-page',['page'=>['title'=>'Custom order unavailable','intro'=>$added['msg']??'Please contact us.'],'settingsMap'=>[]]);exit;}
        $cartItems=array_values(array_filter(\Cart\Cart::get(),static fn($item)=>(int)($item['custom_quote_id']??0)===(int)$quote['id']));$totals=\Cart\Cart::totals($cartItems);
        // The WhatsApp link is intentionally a cart link: let the customer
        // review the persistent quote before continuing to its checkout.
        if($mode==='custom-cart') redirect('/cart');
        redirect('/checkout');
    }catch(\Throwable $e){error_log($e->getMessage());http_response_code(500);view('info-page',['page'=>['title'=>'Custom order unavailable','intro'=>'Please try again later.'],'settingsMap'=>[]]);exit;}
}

// Cart Page
if ($uri === '/cart' && $method === 'GET') {
    try {
        $cartItems = \Cart\Cart::get();
        $totals    = \Cart\Cart::totals($cartItems);
    } catch (\Throwable) {
        $cartItems = [];
        $totals = ['subtotal'=>0,'discount'=>0,'gst_pct'=>18,'gst_amt'=>0,'total'=>0];
    }
    view('cart', compact('cartItems', 'totals'));
    exit;
}

// Checkout
if ($uri === '/checkout' && $method === 'GET') {
    try {
        $cartItems = \Cart\Cart::get();
        $totals    = \Cart\Cart::totals($cartItems);
    } catch (\Throwable) {
        $cartItems = [];
        $totals = ['subtotal'=>0,'discount'=>0,'gst_pct'=>18,'gst_amt'=>0,'total'=>0];
    }
    if (empty($cartItems)) redirect('/');
    $user = \Auth\Auth::user();
    view('checkout', compact('cartItems', 'totals', 'user'));
    exit;
}

// Order Confirmation
if (preg_match('#^/order/confirm/([A-Z0-9]+)$#', $uri, $m) && $method === 'GET') {
    \Auth\Auth::require();
    try { $order = \Orders\OrderManager::getOrderByOrderId($m[1]); }
    catch (\Throwable) { $order = null; }
    if (!$order || (int)$order['user_id'] !== (int)\Auth\Auth::user()['id']) {
        http_response_code(404); view('404'); exit;
    }
    view(($order['order_type'] ?? 'normal') === 'custom' ? 'custom-confirm' : 'confirm', compact('order'));
    exit;
}

// Invoice Download
if (preg_match('#^/invoice/([A-Z0-9]+)$#', $uri, $m) && $method === 'GET') {
    \Auth\Auth::require();
    \Orders\OrderManager::ensureInvoiceSchema();
    try { $order = \Orders\OrderManager::getOrderByOrderId($m[1]); }
    catch (\Throwable) { $order = null; }
    if (!$order || (int)$order['user_id'] !== (int)\Auth\Auth::user()['id']) {
        http_response_code(403); exit;
    }
    $path = trim((string)($order['invoice_file_path'] ?? ''));
    $full = $path !== '' && !str_contains($path, '..') ? PUBLIC_PATH . $path : '';
    if ($full === '' || !is_file($full)) {
        http_response_code(404);
        echo 'Invoice PDF is not available yet.';
        exit;
    }
    $downloadName = str_replace(['"', "\r", "\n"], '', basename((string)($order['invoice_original_name'] ?: ('Invoice-' . $order['order_id'] . '.pdf'))));
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($full));
    readfile($full);
    exit;
}

// ── API Routes ────────────────────────────────────────────────
if (str_starts_with($uri, '/api/')) {
    require_once APP_PATH . '/api/router.php';
    exit;
}

// ── Admin Routes ──────────────────────────────────────────────
if (str_starts_with($uri, '/admin')) {
    require_once ADMIN_PATH . '/router.php';
    exit;
}

// ── 404 ───────────────────────────────────────────────────────
http_response_code(404);
view('404');

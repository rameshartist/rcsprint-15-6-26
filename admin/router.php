<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — Admin Router
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

if (!str_starts_with($uri, '/admin')) return;

if ($uri === '/admin/login' && $method === 'GET') {
    if (\Auth\Auth::isAdmin()) redirect('/admin');
    view('admin/login');
    exit;
}

if ($uri === '/admin/login' && $method === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $result   = \Auth\Auth::adminLogin($email, $password);
    if ($result['ok']) redirect('/admin');
    view('admin/login', ['loginError' => $result['msg']]);
    exit;
}

if ($uri === '/admin/logout') {
    \Auth\Auth::adminLogout();
    redirect('/admin/login');
}

\Auth\Auth::requireAdmin();
$orderSeenColumnReady = null;
$orderSeenColumnAvailable = static function () use (&$orderSeenColumnReady): bool {
    if ($orderSeenColumnReady !== null) return $orderSeenColumnReady;
    try {
        $row = Database::row(
            "SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'is_seen'
             LIMIT 1"
        );
        $orderSeenColumnReady = (bool)$row;
    } catch (\Throwable) {
        $orderSeenColumnReady = false;
    }
    return $orderSeenColumnReady;
};
$ensureOrderSeenColumn = static function () use (&$orderSeenColumnReady, $orderSeenColumnAvailable): bool {
    if ($orderSeenColumnAvailable()) return true;
    try {
        Database::query("ALTER TABLE orders ADD COLUMN is_seen TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        try { Database::query("CREATE INDEX idx_orders_is_seen ON orders (is_seen, created_at)"); } catch (\Throwable) {}
        $orderSeenColumnReady = true;
        return true;
    } catch (\Throwable $e) {
        error_log('Order is_seen column unavailable: ' . $e->getMessage());
        $orderSeenColumnReady = false;
        return false;
    }
};
\Orders\OrderManager::ensureWorkflowSchema();
\Orders\OrderManager::ensureCustomOrderSchema();
\Orders\OrderManager::ensureDesignApprovalSchema();
\Orders\OrderManager::ensureDesignEventSchema();
\Orders\OrderManager::ensureCustomerUpdateSchema();
\Approvals\ContentApprovalManager::ensureSchema();
\Faq\FaqManager::ensureSchema();
\Auth\Auth::ensureCustomerCodeSchema();

$ensureBusinessNeedsSchema = static function (): void {
    try {
        Database::query("CREATE TABLE IF NOT EXISTS business_needs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            icon VARCHAR(32) NULL,
            description VARCHAR(500) NULL,
            product_ids TEXT NULL,
            image_path VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { Database::query("ALTER TABLE business_needs ADD COLUMN image_path VARCHAR(255) NULL AFTER product_ids"); } catch (\Throwable) {}
        Database::query("CREATE TABLE IF NOT EXISTS product_business_needs (
            product_id INT UNSIGNED NOT NULL,
            business_need_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (product_id, business_need_id),
            KEY idx_pbn_need (business_need_id),
            KEY idx_pbn_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $legacyNeeds = Database::rows("SELECT id, product_ids FROM business_needs WHERE product_ids IS NOT NULL AND product_ids <> ''");
        foreach ($legacyNeeds as $need) {
            $needId = (int)($need['id'] ?? 0);
            $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', (string)($need['product_ids'] ?? '')) ?: []), static fn($id) => $id > 0)));
            foreach ($ids as $pid) {
                try { Database::query("INSERT IGNORE INTO product_business_needs (product_id, business_need_id) VALUES (?, ?)", [$pid, $needId]); } catch (\Throwable) {}
            }
        }
    } catch (\Throwable $e) {
        error_log('Business needs schema unavailable: ' . $e->getMessage());
    }
};
$businessNeedSlug = static function (string $value): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));
    return $slug !== '' ? $slug : 'business-need';
};
$businessNeedProducts = static function (mixed $value): string {
    $ids = is_array($value) ? $value : preg_split('/[,\s]+/', (string)$value);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids ?: []), static fn($id) => $id > 0)));
    return implode(',', $ids);
};
$ensureBusinessNeedsSchema();


$ensureCustomQuoteSchema = static function (): void {
    try {
        Database::query("CREATE TABLE IF NOT EXISTS custom_quote_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_code VARCHAR(40) NOT NULL UNIQUE,
            user_id INT UNSIGNED NULL,
            customer_name VARCHAR(160) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            email VARCHAR(180) NULL,
            product_name VARCHAR(180) NOT NULL,
            size_dimension VARCHAR(160) NULL,
            material_type VARCHAR(160) NULL,
            quantity VARCHAR(80) NULL,
            instructions TEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'new',
            admin_notes TEXT NULL,
            quoted_amount DECIMAL(12,2) NULL,
            design_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'INR',
            source_page VARCHAR(255) NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            order_id INT UNSIGNED NULL,
            customer_type VARCHAR(30) NOT NULL DEFAULT 'guest',
            quote_token VARCHAR(80) NULL,
            quote_note TEXT NULL,
            payment_status VARCHAR(40) NOT NULL DEFAULT 'not_required',
            sent_at DATETIME NULL,
            payment_link_generated_at DATETIME NULL,
            approved_at DATETIME NULL,
            is_seen TINYINT(1) NOT NULL DEFAULT 0,
            customer_update_pending TINYINT(1) NOT NULL DEFAULT 0,
            customer_update_type VARCHAR(80) NULL,
            customer_update_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_custom_quote_status (status, created_at),
            KEY idx_custom_quote_phone (phone),
            KEY idx_custom_quote_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach ([
            "ALTER TABLE custom_quote_requests ADD COLUMN admin_notes TEXT NULL AFTER status",
            "ALTER TABLE custom_quote_requests ADD COLUMN quoted_amount DECIMAL(12,2) NULL AFTER admin_notes",
            "ALTER TABLE custom_quote_requests ADD COLUMN design_fee DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quoted_amount",
            "ALTER TABLE custom_quote_requests ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'INR' AFTER quoted_amount",
            "ALTER TABLE custom_quote_requests ADD COLUMN order_id INT UNSIGNED NULL AFTER user_agent",
            "ALTER TABLE custom_quote_requests ADD COLUMN customer_type VARCHAR(30) NOT NULL DEFAULT 'guest' AFTER order_id",
            "ALTER TABLE custom_quote_requests ADD COLUMN quote_token VARCHAR(80) NULL AFTER customer_type",
            "ALTER TABLE custom_quote_requests ADD COLUMN quote_note TEXT NULL AFTER quote_token",
            "ALTER TABLE custom_quote_requests ADD COLUMN payment_status VARCHAR(40) NOT NULL DEFAULT 'not_required' AFTER quote_note",
            "ALTER TABLE custom_quote_requests ADD COLUMN sent_at DATETIME NULL AFTER payment_status",
            "ALTER TABLE custom_quote_requests ADD COLUMN payment_link_generated_at DATETIME NULL AFTER sent_at",
            "ALTER TABLE custom_quote_requests ADD COLUMN approved_at DATETIME NULL AFTER payment_link_generated_at",
            "ALTER TABLE custom_quote_requests ADD COLUMN is_seen TINYINT(1) NOT NULL DEFAULT 0 AFTER approved_at",
            "ALTER TABLE custom_quote_requests ADD COLUMN customer_update_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER is_seen",
            "ALTER TABLE custom_quote_requests ADD COLUMN customer_update_type VARCHAR(80) NULL AFTER customer_update_pending",
            "ALTER TABLE custom_quote_requests ADD COLUMN customer_update_at DATETIME NULL AFTER customer_update_type",
        ] as $sql) { try { Database::query($sql); } catch (\Throwable) {} }
        try { Database::query("ALTER TABLE custom_quote_requests DROP COLUMN estimated_delivery"); } catch (\Throwable) {}
        try {
            Database::query("UPDATE custom_quote_requests SET request_code=CONCAT('CQ-TMP-', id)");
            Database::query("UPDATE custom_quote_requests SET request_code=CONCAT('CQ-', LPAD(id, 4, '0'))");
        } catch (\Throwable) {}
    } catch (\Throwable $e) {
        error_log('Custom quote schema unavailable: ' . $e->getMessage());
    }
};
$ensureCustomQuoteSchema();


$syncProductBusinessNeeds = static function (int $productId, mixed $selectedNeedIds) use ($businessNeedProducts): void {
    $selected = is_array($selectedNeedIds) ? $selectedNeedIds : preg_split('/[,\s]+/', (string)$selectedNeedIds);
    $selected = array_values(array_unique(array_filter(array_map('intval', $selected ?: []), static fn($id) => $id > 0)));
    try {
        Database::query("DELETE FROM product_business_needs WHERE product_id=?", [$productId]);
        foreach ($selected as $needId) Database::query("INSERT IGNORE INTO product_business_needs (product_id, business_need_id) VALUES (?, ?)", [$productId, $needId]);
        $needs = Database::rows("SELECT id, product_ids FROM business_needs");
        foreach ($needs as $need) {
            $needId = (int)($need['id'] ?? 0);
            $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', (string)($need['product_ids'] ?? '')) ?: []), static fn($id) => $id > 0)));
            $hasProduct = in_array($productId, $ids, true);
            $shouldHaveProduct = in_array($needId, $selected, true);
            if ($shouldHaveProduct && !$hasProduct) $ids[] = $productId;
            if (!$shouldHaveProduct && $hasProduct) $ids = array_values(array_filter($ids, static fn($id) => $id !== $productId));
            Database::query("UPDATE business_needs SET product_ids=?, updated_at=NOW() WHERE id=?", [$businessNeedProducts($ids), $needId]);
        }
    } catch (\Throwable $e) {
        error_log('Product business needs sync failed: ' . $e->getMessage());
    }
};
$getProductBusinessNeedIds = static function (int $productId): array {
    try {
        $rows = Database::rows("SELECT business_need_id FROM product_business_needs WHERE product_id=?", [$productId]);
        if ($rows) return array_values(array_map('intval', array_column($rows, 'business_need_id')));
        $needs = Database::rows("SELECT id, product_ids FROM business_needs WHERE product_ids IS NOT NULL AND product_ids <> ''");
        $out = [];
        foreach ($needs as $need) {
            $ids = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', (string)($need['product_ids'] ?? '')) ?: []), static fn($id) => $id > 0));
            if (in_array($productId, $ids, true)) $out[] = (int)$need['id'];
        }
        return $out;
    } catch (\Throwable) {
        return [];
    }
};

$whatsappTemplateDefaults = [
    'order_confirmation' => [
        'title' => 'Send Order Confirmation',
        'description' => 'Sent after a new order is received.',
        'body' => "Hello {customer_name}, 👋\n\nThank you for your order. We have received Order #{order_id}.\n\nOrder value: {order_total}\nProducts:\n{products}\n\nOur team will review the details and start processing your order shortly.\n\nYou can login to your account to check order details, current status, design approval status and future updates here:\n{account_order_url}\n\nThank you,\n{business_name}\nFor any query, call or WhatsApp: {business_phone}",
    ],
    'proof_ready' => [
        'title' => 'Send Proof Ready Message',
        'description' => 'Sent when admin uploads a corrected/proof file for customer review.',
        'body' => "Hello {customer_name}, 👋\n\nYour corrected design proof for Order #{order_id} is ready for review.\n\nProduct:\n{products}\n\nPlease login to your account and check My Orders to view the proof. You can approve the design or request a revision here:\n{account_order_url}\n\nThank you,\n{business_name}\nFor any query, call or WhatsApp: {business_phone}",
    ],
    'design_approved' => [
        'title' => 'Send Design Approved Message',
        'description' => 'Sent after design approval to explain printing/production next steps.',
        'body' => "Hello {customer_name}, 👋\n\nYour design for Order #{order_id} has been approved.\n\nProduct:\n{products}\n\nYour order will now move to the next step: Printing / Production.\n\nPlease note: once the design is approved, design changes or order cancellation may not be possible.\n\nYou can login to your account to check order details and further updates here:\n{account_order_url}\n\nThank you,\n{business_name}\nFor any query, call or WhatsApp: {business_phone}",
    ],
    'customer_reorder' => [
        'title' => 'Customer Reorder Reminder',
        'description' => 'Sent from the Customers module when a customer may want to repeat a previous order.',
        'body' => "Hello {customer_name}, 👋\n\nIf you would like to reorder your previous print items, we can process it quickly using your saved order details.\n\nLast product: {last_product}\nTotal orders: {order_count}\n\nReply here and our team will help you with the reorder.\n\nThank you,\n{business_name}",
    ],
    'customer_upsell' => [
        'title' => 'Customer Upsell Message',
        'description' => 'Sent from the Customers module to suggest related print products.',
        'body' => "Hello {customer_name}, 👋\n\nBased on your previous print requirement, this may be useful for you:\n{suggestion}\n\nLast product: {last_product}\n\nReply here and we will share details and pricing.\n\nThank you,\n{business_name}",
    ],
    'customer_welcome' => [
        'title' => 'Customer Welcome Offer',
        'description' => 'Sent to customers who have not placed an order yet.',
        'body' => "Hello {customer_name}, 👋\n\nWelcome to {business_name}. Please share your print requirement and our team will guide you with suitable options, pricing and artwork support.\n\nThank you,\n{business_name}",
    ],
    'lead_followup' => [
        'title' => 'Lead Follow-up Message',
        'description' => 'Sent from the Leads module after a contact form enquiry is received.',
        'body' => "Hello {lead_name}, 👋\n\nThank you for contacting {business_name}. We received your enquiry:\n{lead_subject}\n\nPlease share any artwork, size, quantity or reference details here so our team can guide you quickly.\n\nThank you,\n{business_name}",
    ],
    'custom_quote_sent' => [
        'title' => 'Send Custom Quote',
        'description' => 'Sent after the quoted amount and quote note are saved.',
        'body' => "Hello {customer_name}, 👋\n\nThank you for your custom quotation request {quote_id}.\n\nProduct: {product_name}\nSize: {size_dimension}\nMaterial: {material_type}\nQuantity: {quantity}\nAmount: {quoted_amount}\nDesign Fee: {design_fee}\nSubtotal before GST: {custom_subtotal}\n\n{quote_note}\n\nPlease reply APPROVE to confirm this quote.\n\nThank you,\n{business_name}",
    ],
    'custom_quote_payment' => [
        'title' => 'Send Custom Order Payment Link',
        'description' => 'Sent after approval, account linking and payment-link generation.',
        'body' => "Hello {customer_name}, 👋\n\nYour custom order {quote_id} is ready for payment.\n\nAmount: {quoted_amount}\n\nLogin here: {login_url}\nLogin with: {login_identifier}\nPassword: {login_password}\n\nYour custom order is already added to your cart. Open your secure payment link to continue: {payment_link}\n\nThank you,\n{business_name}",
    ],
];
$ensureWhatsappTemplateSchema = static function () use ($whatsappTemplateDefaults): void {
    try {
        Database::query("CREATE TABLE IF NOT EXISTS whatsapp_message_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_key VARCHAR(80) NOT NULL UNIQUE,
            title VARCHAR(160) NOT NULL,
            description VARCHAR(255) NULL,
            body TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach ($whatsappTemplateDefaults as $key => $template) {
            Database::query(
                "INSERT INTO whatsapp_message_templates (template_key, title, description, body, is_active, created_at, updated_at)
                 SELECT ?,?,?,?,?,NOW(),NOW() FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM whatsapp_message_templates WHERE template_key=? LIMIT 1)",
                [$key, $template['title'], $template['description'], $template['body'], 1, $key]
            );
        }
    } catch (\Throwable $e) {
        error_log('WhatsApp template schema unavailable: ' . $e->getMessage());
    }
};
$ensureWhatsappTemplateSchema();

$adminTableExists = static function (string $table): bool {
    try {
        $row = Database::row(
            "SELECT 1 AS ok
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
              LIMIT 1",
            [$table]
        );
        return (bool)$row;
    } catch (\Throwable) {
        return false;
    }
};
$orderCleanupCounts = static function () use ($adminTableExists): array {
    $count = static function (string $sql) {
        try { return (int)(Database::row($sql)['c'] ?? 0); }
        catch (\Throwable) { return 0; }
    };
    return [
        'orders' => $adminTableExists('orders') ? $count('SELECT COUNT(*) AS c FROM orders') : 0,
        'order_items' => $adminTableExists('order_items') ? $count('SELECT COUNT(*) AS c FROM order_items') : 0,
        'status_history' => $adminTableExists('order_status_history') ? $count('SELECT COUNT(*) AS c FROM order_status_history WHERE order_id IN (SELECT id FROM orders)') : 0,
        'payments' => $adminTableExists('payments') ? $count('SELECT COUNT(*) AS c FROM payments WHERE order_id IN (SELECT id FROM orders)') : 0,
        'coupon_uses' => $adminTableExists('coupon_uses') ? $count('SELECT COUNT(*) AS c FROM coupon_uses WHERE order_id IN (SELECT id FROM orders)') : 0,
        'product_reviews' => $adminTableExists('product_reviews') ? $count('SELECT COUNT(*) AS c FROM product_reviews WHERE order_id IN (SELECT id FROM orders) OR order_item_id IN (SELECT id FROM order_items)') : 0,
        'design_approvals' => $adminTableExists('order_design_approvals') ? $count('SELECT COUNT(*) AS c FROM order_design_approvals WHERE order_id IN (SELECT id FROM orders) OR order_item_id IN (SELECT id FROM order_items)') : 0,
        'design_events' => $adminTableExists('order_design_events') ? $count('SELECT COUNT(*) AS c FROM order_design_events WHERE order_id IN (SELECT id FROM orders) OR order_item_id IN (SELECT id FROM order_items)') : 0,
        'artwork_files' => $adminTableExists('artwork_files') ? $count('SELECT COUNT(*) AS c FROM artwork_files WHERE order_item_id IN (SELECT id FROM order_items)') : 0,
    ];
};

$ensurePageHeroesSchema = static function (): void {
    try {
        Database::query("CREATE TABLE IF NOT EXISTS page_heroes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_key VARCHAR(120) NOT NULL UNIQUE,
            title VARCHAR(180) NOT NULL,
            description VARCHAR(400) NULL,
            background_image VARCHAR(500) NULL,
            fallback_image VARCHAR(500) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try { Database::query("ALTER TABLE page_heroes ADD COLUMN title VARCHAR(180) NOT NULL DEFAULT '' AFTER page_key"); } catch (\Throwable) {}
        try { Database::query("ALTER TABLE page_heroes ADD COLUMN description VARCHAR(400) NULL AFTER title"); } catch (\Throwable) {}

        $defaults = [
            ['about', 'About Us', 'Your trusted partner for premium printing services.', '/assets/images/sample-products/brochures/brochures-2.svg', 10],
            ['contact', 'Contact Us', 'Reach our print experts for quotes, support and custom requirements.', '/assets/images/sample-products/stationery/stationery-1.svg', 20],
            ['blogs', 'Printing Ideas & Guides', 'Explore helpful print tips, business branding ideas and product updates.', '/assets/images/sample-products/flyers/flyers-1.svg', 30],
            ['categories', 'All Product Categories', 'Browse every printing category and find the right product for your business.', '/assets/img/categories/all-categories-hero.svg', 40],
            ['business_sectors', 'All Sectors', 'Explore business-wise printing solutions for your industry.', '/assets/img/categories/print-category.svg', 45],
            ['business_detail', 'Business Printing Solutions', 'Explore products curated for this business sector.', '/assets/img/categories/print-category.svg', 46],
            ['category_detail', 'Premium Printing Products', 'Choose the right print product with quality materials and fast support.', '/assets/img/categories/all-categories-hero.svg', 50],
            ['product_detail', 'Product Details', 'Customize your order, upload artwork and get premium printing delivered.', '/assets/images/sample-products/business-cards/business-cards-1.svg', 60],
            ['portfolio', 'Our Portfolio', 'Explore real printing work created for businesses and brands.', '/assets/images/sample-products/brochures/brochures-2.svg', 70],
            ['profile', 'My Account', 'Manage your profile, orders, design approvals and invoices from your customer dashboard.', '/assets/images/sample-products/stationery/stationery-1.svg', 80],
        ];
        foreach ($defaults as $hero) {
            Database::query(
                "INSERT INTO page_heroes (page_key, title, description, fallback_image, sort_order, is_active, created_at, updated_at)
                 SELECT ?,?,?,?,?,1,NOW(),NOW() FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM page_heroes WHERE page_key=? LIMIT 1)",
                [$hero[0], $hero[1], $hero[2], $hero[3], $hero[4], $hero[0]]
            );
        }
    } catch (\Throwable $e) {
        error_log('Page hero schema unavailable: ' . $e->getMessage());
    }
};
$ensurePageHeroesSchema();

$ensurePortfolioSchema = static function (): void {
    try {
        Database::query("CREATE TABLE IF NOT EXISTS portfolio_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            icon VARCHAR(80) NOT NULL DEFAULT 'fa-border-all',
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::query("CREATE TABLE IF NOT EXISTS portfolio_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NULL,
            title VARCHAR(220) NOT NULL,
            slug VARCHAR(240) NOT NULL UNIQUE,
            short_description VARCHAR(500) NULL,
            description MEDIUMTEXT NULL,
            main_image VARCHAR(500) NOT NULL DEFAULT '',
            image_alt VARCHAR(255) NULL,
            client_name VARCHAR(180) NULL,
            project_type VARCHAR(180) NULL,
            project_date DATE NULL,
            tags VARCHAR(500) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_portfolio_items_active (is_active, sort_order, created_at),
            INDEX idx_portfolio_items_category (category_id),
            CONSTRAINT fk_portfolio_items_category FOREIGN KEY (category_id) REFERENCES portfolio_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $portfolioCategoryColumns = [
            'description' => "ALTER TABLE portfolio_categories ADD COLUMN description TEXT NULL AFTER icon",
            'hero_image' => "ALTER TABLE portfolio_categories ADD COLUMN hero_image VARCHAR(500) NULL AFTER description",
            'meta_title' => "ALTER TABLE portfolio_categories ADD COLUMN meta_title VARCHAR(255) NULL AFTER hero_image",
            'meta_description' => "ALTER TABLE portfolio_categories ADD COLUMN meta_description VARCHAR(500) NULL AFTER meta_title",
        ];
        foreach ($portfolioCategoryColumns as $column => $sql) {
            try {
                $exists = Database::row("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_categories' AND COLUMN_NAME=? LIMIT 1", [$column]);
                if (!$exists) Database::query($sql);
            } catch (\Throwable) {}
        }
        $defaults = [
            ['Visiting Card','visiting-card','fa-id-card-clip',10],
            ['Brochure','brochure','fa-images',20],
            ['Flyer','flyer','fa-file-image',30],
            ['Calender','calender','fa-calendar-days',40],
            ['Rough Pad','rough-pad','fa-note-sticky',50],
            ['Flex Banner','flex-banner','fa-panorama',60],
            ['Poster','poster','fa-newspaper',70],
            ['Stationery','stationery','fa-file-lines',80],
            ['Packaging','packaging','fa-cube',90],
        ];
        foreach ($defaults as $cat) {
            Database::query(
                "INSERT INTO portfolio_categories (name, slug, icon, sort_order, is_active)
                 SELECT ?,?,?,?,1 FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM portfolio_categories WHERE slug = ? LIMIT 1)",
                [$cat[0], $cat[1], $cat[2], $cat[3], $cat[1]]
            );
        }
    } catch (\Throwable $e) {
        error_log('Portfolio schema unavailable: ' . $e->getMessage());
    }
};
$ensurePortfolioSchema();

$adminUsersHasMobile = null;
$hasAdminUsersMobile = static function () use (&$adminUsersHasMobile): bool {
    if ($adminUsersHasMobile !== null) return $adminUsersHasMobile;
    try {
        $row = Database::row(
            "SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'admin_users'
               AND COLUMN_NAME = 'mobile'
             LIMIT 1"
        );
        $adminUsersHasMobile = (bool)$row;
    } catch (\Throwable) {
        $adminUsersHasMobile = false;
    }
    return $adminUsersHasMobile;
};

if (str_starts_with($uri, '/admin/api/')) {
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($uri === '/admin/api/live-updates' && $method === 'GET') {
        $count = static function (string $sql): int { try { return (int)(Database::row($sql)['c'] ?? 0); } catch (\Throwable) { return 0; } };
        $approvals = 0;
        if (\Auth\Auth::isSuperAdmin()) foreach (['products','categories','coupons','home_deals'] as $table) $approvals += $count("SELECT COUNT(*) c FROM {$table} WHERE approval_status='pending'");
        json(['ok'=>true,'counts'=>[
            'orders'=>$count("SELECT COUNT(*) c FROM orders WHERE status='new_order'"),
            'custom_orders'=>$count("SELECT COUNT(*) c FROM custom_quote_requests WHERE status='new'"),
            'leads'=>$count("SELECT COUNT(*) c FROM contact_leads WHERE COALESCE(is_read,0)=0"),
            'approvals'=>$approvals,
        ],'server_time'=>date(DATE_ATOM)]);
    }
    if ($uri === '/admin/api/site-chrome' && $method === 'GET') { json(['ok'=>true,'data'=>\Site\SiteChromeManager::payload()]); }
    if ($uri === '/admin/api/site-chrome' && $method === 'POST') {
        try { \Site\SiteChromeManager::save($body); json(['ok'=>true]); }
        catch (\Throwable $e) { error_log($e->getMessage()); json(['ok'=>false,'msg'=>'Could not save header and footer.'],500); }
    }
    if ($uri === '/admin/api/site-chrome/logo' && $method === 'POST') {
        $file=$_FILES['image']??null;
        if(!$file||$file['error']!==UPLOAD_ERR_OK) json(['ok'=>false,'msg'=>'Choose an image.'],422);
        $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['jpg','jpeg','png','webp'],true)) json(['ok'=>false,'msg'=>'JPG, PNG or WEBP only.'],422);
        if((int)$file['size']>5*1024*1024) json(['ok'=>false,'msg'=>'Image must be under 5 MB.'],422);
        $dir=PUBLIC_PATH.'/uploads/site/'; if(!is_dir($dir)) mkdir($dir,0755,true);
        $name='logo-'.bin2hex(random_bytes(8)).'.'.$ext;
        if(!move_uploaded_file($file['tmp_name'],$dir.$name)) json(['ok'=>false,'msg'=>'Upload failed.'],500);
        json(['ok'=>true,'path'=>'/uploads/site/'.$name]);
    }

    $slugify = static function (string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'blog-post';
    };
    $sanitizeBlogContent = static function (string $html): string {
        $allowed = '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><a><blockquote><img><figure><figcaption><div><span><hr><iframe><video><source>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*("|\')/i', '$1="#"', $clean) ?? $clean;
        $clean = preg_replace_callback('/<iframe\b([^>]*)>/i', static function (array $m): string {
            $attrs = $m[1] ?? '';
            if (!preg_match('/src\s*=\s*("|\')([^"\']+)\1/i', $attrs, $srcMatch)) {
                return '';
            }
            $src = $srcMatch[2];
            if (!preg_match('#^https://(www\.)?(youtube\.com/embed/|player\.vimeo\.com/video/)#i', $src)) {
                return '';
            }
            return '<iframe src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" loading="lazy" allowfullscreen></iframe>';
        }, $clean) ?? $clean;
        return trim($clean);
    };
    $uniqueBlogSlug = static function (string $base, int $ignoreId = 0) use ($slugify): string {
        $slug = $slugify($base);
        $candidate = $slug;
        $i = 2;
        while (true) {
            $params = [$candidate];
            $sql = "SELECT id FROM blogs WHERE slug = ?";
            if ($ignoreId > 0) {
                $sql .= " AND id <> ?";
                $params[] = $ignoreId;
            }
            $sql .= " LIMIT 1";
            $row = Database::row($sql, $params);
            if (!$row) return $candidate;
            $candidate = $slug . '-' . $i;
            $i++;
        }
    };
    $uniquePortfolioSlug = static function (string $base, int $ignoreId = 0) use ($slugify): string {
        $slug = $slugify($base);
        $candidate = $slug;
        $i = 2;
        while (true) {
            $params = [$candidate];
            $sql = "SELECT id FROM portfolio_items WHERE slug = ?";
            if ($ignoreId > 0) {
                $sql .= " AND id <> ?";
                $params[] = $ignoreId;
            }
            $sql .= " LIMIT 1";
            $row = Database::row($sql, $params);
            if (!$row) return $candidate;
            $candidate = $slug . '-' . $i;
            $i++;
        }
    };

    if ($uri === '/admin/api/media/delete' && in_array($method, ['POST', 'DELETE'], true)) {
        $relative = trim((string)($body['path'] ?? $_POST['path'] ?? ''));
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '.trash/')) {
            json(['ok' => false, 'msg' => 'Invalid media path.'], 422);
        }
        $uploadsRoot = realpath(PUBLIC_PATH . '/uploads');
        if (!$uploadsRoot) json(['ok' => false, 'msg' => 'Uploads folder not found.'], 500);
        $target = realpath(PUBLIC_PATH . '/uploads/' . $relative);
        if (!$target || !is_file($target) || !str_starts_with($target, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            json(['ok' => false, 'msg' => 'Media file not found.'], 404);
        }
        $publicPath = '/uploads/' . $relative;
        $linkedApproval = null;
        try {
            $linkedApproval = Database::row(
                "SELECT oda.id, o.order_id
                   FROM order_design_approvals oda
                   INNER JOIN artwork_files af ON af.id IN (oda.customer_artwork_file_id, oda.proof_file_id)
                   INNER JOIN orders o ON o.id = oda.order_id
                  WHERE af.file_path = ?
                  LIMIT 1",
                [$publicPath]
            );
        } catch (\Throwable) {}
        if ($linkedApproval) {
            json(['ok' => false, 'msg' => 'This file is linked to order ' . ($linkedApproval['order_id'] ?? '') . '. Please keep order proof/artwork files for audit history.'], 409);
        }

        $trashDir = PUBLIC_PATH . '/uploads/.trash/' . date('Y/m/d') . '/' . trim(dirname($relative), './');
        if (!is_dir($trashDir)) mkdir($trashDir, 0755, true);
        $trashName = pathinfo($relative, PATHINFO_FILENAME) . '-' . date('His') . '-' . bin2hex(random_bytes(3)) . '.' . pathinfo($relative, PATHINFO_EXTENSION);
        $trashPath = rtrim($trashDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $trashName;
        if (!rename($target, $trashPath)) {
            json(['ok' => false, 'msg' => 'Could not archive media file.'], 500);
        }
        $admin = \Auth\Auth::admin();
        try {
            $eventOrder = Database::row(
                "SELECT o.id AS order_id, oi.id AS order_item_id, oda.id AS approval_id
                   FROM artwork_files af
                   LEFT JOIN order_items oi ON oi.id = af.order_item_id
                   LEFT JOIN orders o ON o.id = oi.order_id
                   LEFT JOIN order_design_approvals oda ON oda.order_item_id = oi.id
                  WHERE af.file_path = ?
                  LIMIT 1",
                [$publicPath]
            );
            if (!empty($eventOrder['order_id'])) {
                \Orders\OrderManager::recordDesignEvent([
                    'order_id' => (int)$eventOrder['order_id'],
                    'order_item_id' => (int)($eventOrder['order_item_id'] ?? 0),
                    'design_approval_id' => (int)($eventOrder['approval_id'] ?? 0),
                    'event_type' => 'media_archived',
                    'actor_type' => 'admin',
                    'actor_id' => (int)($admin['id'] ?? 0),
                    'actor_name' => $admin['name'] ?? null,
                    'file_role' => 'media',
                    'file_name' => basename($relative),
                    'file_path' => $publicPath,
                    'note' => 'Media archived from admin media library.',
                    'meta' => ['trash_path' => str_replace(PUBLIC_PATH, '', $trashPath)],
                ]);
            }
        } catch (\Throwable) {}
        json(['ok' => true, 'msg' => 'Media file moved to trash. You can restore it from /uploads/.trash if needed.', 'archived' => true]);
    }

    if ($uri === '/admin/api/media/bulk-delete' && in_array($method, ['POST', 'DELETE'], true)) {
        $paths = $body['paths'] ?? [];
        if (!is_array($paths) || !$paths) json(['ok' => false, 'msg' => 'Select at least one media file.'], 422);
        $uploadsRoot = realpath(PUBLIC_PATH . '/uploads');
        if (!$uploadsRoot) json(['ok' => false, 'msg' => 'Uploads folder not found.'], 500);
        $deleted = [];
        $failed = [];
        foreach (array_values(array_unique(array_map('strval', $paths))) as $rawPath) {
            $relative = ltrim(str_replace('\\', '/', trim($rawPath)), '/');
            if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '.trash/')) {
                $failed[] = ['path' => $rawPath, 'msg' => 'Invalid media path'];
                continue;
            }
            $target = realpath(PUBLIC_PATH . '/uploads/' . $relative);
            if (!$target || !is_file($target) || !str_starts_with($target, $uploadsRoot . DIRECTORY_SEPARATOR)) {
                $failed[] = ['path' => $relative, 'msg' => 'File not found'];
                continue;
            }
            $publicPath = '/uploads/' . $relative;
            try {
                $linkedApproval = Database::row(
                    "SELECT oda.id, o.order_id
                       FROM order_design_approvals oda
                       INNER JOIN artwork_files af ON af.id IN (oda.customer_artwork_file_id, oda.proof_file_id)
                       INNER JOIN orders o ON o.id = oda.order_id
                      WHERE af.file_path = ?
                      LIMIT 1",
                    [$publicPath]
                );
                if ($linkedApproval) {
                    $failed[] = ['path' => $relative, 'msg' => 'Linked to order ' . ($linkedApproval['order_id'] ?? '')];
                    continue;
                }
            } catch (\Throwable) {}
            $trashDir = PUBLIC_PATH . '/uploads/.trash/' . date('Y/m/d') . '/' . trim(dirname($relative), './');
            if (!is_dir($trashDir)) @mkdir($trashDir, 0755, true);
            $ext = pathinfo($relative, PATHINFO_EXTENSION);
            $trashName = pathinfo($relative, PATHINFO_FILENAME) . '-' . date('His') . '-' . bin2hex(random_bytes(3)) . ($ext !== '' ? '.' . $ext : '');
            $trashPath = rtrim($trashDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $trashName;
            if (@rename($target, $trashPath)) {
                $deleted[] = $relative;
            } else {
                $failed[] = ['path' => $relative, 'msg' => 'Could not archive file'];
            }
        }
        json(['ok' => count($deleted) > 0, 'deleted' => $deleted, 'failed' => $failed, 'msg' => count($deleted) . ' file(s) moved to trash.']);
    }

    if ($uri === '/admin/api/design-history' && $method === 'GET') {
        \Orders\OrderManager::ensureDesignEventSchema();
        $q = trim((string)($_GET['q'] ?? ''));
        $eventType = trim((string)($_GET['event_type'] ?? ''));
        $dateFrom = trim((string)($_GET['date_from'] ?? ''));
        $dateTo = trim((string)($_GET['date_to'] ?? ''));
        $actionFilter = trim((string)($_GET['action'] ?? 'all'));
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(o.order_id LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ? OR oi.product_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($eventType !== '' && $eventType !== 'all') { $where[] = 'e.event_type = ?'; $params[] = $eventType; }
        if ($dateFrom !== '') { $where[] = 'DATE(e.created_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo !== '') { $where[] = 'DATE(e.created_at) <= ?'; $params[] = $dateTo; }
        if ($actionFilter === 'needs_action') {
            $where[] = "oda.status IN ('pending_review','issue_found','proof_uploaded','revision_requested')";
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $events = Database::rows(
            "SELECT e.*, o.order_id AS public_order_id, o.customer_name, o.customer_email, o.customer_phone,
                    o.status AS order_status, oi.product_name, oi.quantity, oi.design_choice,
                    oda.status AS current_design_status,
                    caf.file_path AS current_artwork_path, caf.original_name AS current_artwork_name, caf.filename AS current_artwork_filename,
                    pf.file_path AS current_proof_path, pf.original_name AS current_proof_name, pf.filename AS current_proof_filename
               FROM order_design_events e
               LEFT JOIN orders o ON o.id = e.order_id
               LEFT JOIN order_items oi ON oi.id = e.order_item_id
               LEFT JOIN order_design_approvals oda ON oda.id = e.design_approval_id
               LEFT JOIN artwork_files caf ON caf.id = oda.customer_artwork_file_id
               LEFT JOIN artwork_files pf ON pf.id = oda.proof_file_id
               $whereSql
              ORDER BY e.created_at DESC
              LIMIT 250",
            $params
        );
        $eventTypes = Database::rows("SELECT event_type, COUNT(*) AS count FROM order_design_events GROUP BY event_type ORDER BY event_type ASC");
        json(['ok' => true, 'events' => $events, 'event_types' => $eventTypes]);
    }


    if ($uri === '/admin/api/page-heroes' && $method === 'GET') {
        try {
            try {
                foreach (\Catalog\ProductCatalog::categories() as $category) {
                    $slug = strtolower(trim((string)($category['slug'] ?? '')));
                    if ($slug === '') continue;
                    $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? $slug;
                    $key = 'category_' . trim($slug, '-');
                    $name = trim((string)($category['name'] ?? 'Category')) ?: 'Category';
                    $fallback = trim((string)($category['image_path'] ?? '')) ?: '/assets/img/categories/all-categories-hero.svg';
                    Database::query(
                        "INSERT INTO page_heroes (page_key, title, description, fallback_image, sort_order, is_active, created_at, updated_at)
                         SELECT ?,?,?,?,?,1,NOW(),NOW() FROM DUAL
                         WHERE NOT EXISTS (SELECT 1 FROM page_heroes WHERE page_key=? LIMIT 1)",
                        [$key, $name, 'Explore premium ' . $name . ' printing options and related products.', $fallback, 1000 + (int)($category['sort_order'] ?? 0), $key]
                    );
                }
            } catch (\Throwable $e) {
                error_log('Category page hero sync failed: ' . $e->getMessage());
            }
            try {
                $businessNeeds = Database::rows("SELECT slug, name, description, image_path, sort_order FROM business_needs WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
                foreach ($businessNeeds as $need) {
                    $slug = strtolower(trim((string)($need['slug'] ?? '')));
                    if ($slug === '') continue;
                    $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? $slug;
                    $key = 'business_' . trim($slug, '-');
                    $name = trim((string)($need['name'] ?? 'Business Sector')) ?: 'Business Sector';
                    $fallback = trim((string)($need['image_path'] ?? '')) ?: '/assets/img/categories/print-category.svg';
                    Database::query(
                        "INSERT INTO page_heroes (page_key, title, description, fallback_image, sort_order, is_active, created_at, updated_at)
                         SELECT ?,?,?,?,?,1,NOW(),NOW() FROM DUAL
                         WHERE NOT EXISTS (SELECT 1 FROM page_heroes WHERE page_key=? LIMIT 1)",
                        [$key, $name . ' Printing Solutions', trim((string)($need['description'] ?? '')) ?: 'Explore products curated for ' . $name . '.', $fallback, 1500 + (int)($need['sort_order'] ?? 0), $key]
                    );
                }
            } catch (\Throwable $e) {
                error_log('Business page hero sync failed: ' . $e->getMessage());
            }
            $rows = Database::rows("SELECT * FROM page_heroes ORDER BY sort_order ASC, title ASC");
            json(['ok'=>true,'heroes'=>$rows]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Page heroes table unavailable','heroes'=>[]], 500);
        }
    }
    if (preg_match('#^/admin/api/page-heroes/([a-z0-9_\-]+)$#', $uri, $m) && $method === 'PUT') {
        $key = (string)$m[1];
        $background = trim((string)($body['background_image'] ?? ''));
        $title = trim((string)($body['title'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));
        if ($title === '') {
            $title = ucwords(str_replace(['_', '-'], ' ', $key));
        }
        try {
            Database::query(
                "INSERT INTO page_heroes (page_key, title, description, background_image, fallback_image, sort_order, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, '', 9999, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), background_image=VALUES(background_image), is_active=VALUES(is_active), updated_at=NOW()",
                [$key, $title, $description, $background, (int)($body['is_active'] ?? 1)]
            );
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not save page hero'], 500);
        }
    }
    if ($uri === '/admin/api/page-heroes/upload' && $method === 'POST') {
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            json(['ok'=>false,'msg'=>'Image file is required'], 400);
        }
        $file = $_FILES['image'];
        if ((int)$file['size'] <= 0) json(['ok'=>false,'msg'=>'Empty upload'], 400);
        if ((int)$file['size'] > 8 * 1024 * 1024) json(['ok'=>false,'msg'=>'Max file size is 8MB'], 400);
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) json(['ok'=>false,'msg'=>'Only jpg, png, webp allowed'], 400);
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) json(['ok'=>false,'msg'=>'Invalid image type'], 400);
        $dir = PUBLIC_PATH . '/uploads/page-heroes/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = 'page_hero_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $dir . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) json(['ok'=>false,'msg'=>'Upload failed'], 500);
        json(['ok'=>true,'path'=>'/uploads/page-heroes/' . $name]);
    }

    if ($uri === '/admin/api/whatsapp-templates' && $method === 'GET') {
        try {
            $rows = Database::rows("SELECT template_key, title, description, body, is_active FROM whatsapp_message_templates ORDER BY FIELD(template_key,'order_confirmation','proof_ready','design_approved','customer_reorder','customer_upsell','customer_welcome','lead_followup'), template_key ASC");
            json(['ok' => true, 'templates' => $rows, 'defaults' => $whatsappTemplateDefaults]);
        } catch (\Throwable) {
            json(['ok' => false, 'msg' => 'WhatsApp templates are unavailable', 'templates' => [], 'defaults' => $whatsappTemplateDefaults], 500);
        }
    }
    if (preg_match('#^/admin/api/whatsapp-templates/([a-z0-9_\-]+)$#', $uri, $m) && $method === 'PUT') {
        $key = (string)$m[1];
        if (!isset($whatsappTemplateDefaults[$key])) {
            json(['ok' => false, 'msg' => 'Unknown WhatsApp template'], 404);
        }
        $title = trim((string)($body['title'] ?? $whatsappTemplateDefaults[$key]['title']));
        $description = trim((string)($body['description'] ?? $whatsappTemplateDefaults[$key]['description']));
        $messageBody = trim((string)($body['body'] ?? ''));
        if ($title === '') $title = $whatsappTemplateDefaults[$key]['title'];
        if ($messageBody === '') {
            json(['ok' => false, 'msg' => 'Message body is required'], 422);
        }
        try {
            Database::query(
                "INSERT INTO whatsapp_message_templates (template_key, title, description, body, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), body=VALUES(body), is_active=VALUES(is_active), updated_at=NOW()",
                [$key, $title, $description, $messageBody, (int)($body['is_active'] ?? 1)]
            );
            $row = Database::row("SELECT template_key, title, description, body, is_active FROM whatsapp_message_templates WHERE template_key=? LIMIT 1", [$key]);
            json(['ok' => true, 'template' => $row]);
        } catch (\Throwable) {
            json(['ok' => false, 'msg' => 'Could not save WhatsApp template'], 500);
        }
    }

    if ($uri === '/admin/api/faqs' && $method === 'GET') {
        json(['ok' => true, 'faqs' => \Faq\FaqManager::all(), 'page_labels' => \Faq\FaqManager::PAGE_LABELS]);
    }
    if ($uri === '/admin/api/faqs' && $method === 'POST') {
        $result = \Faq\FaqManager::save($body);
        json($result, ($result['ok'] ?? false) ? 200 : 422);
    }
    if (preg_match('#^/admin/api/faqs/(\d+)$#', $uri, $m) && $method === 'PUT') {
        $result = \Faq\FaqManager::save($body, (int)$m[1]);
        json($result, ($result['ok'] ?? false) ? 200 : 422);
    }
    if (preg_match('#^/admin/api/faqs/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
        $result = \Faq\FaqManager::toggle((int)$m[1]);
        json($result, ($result['ok'] ?? false) ? 200 : 422);
    }
    if (preg_match('#^/admin/api/faqs/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $result = \Faq\FaqManager::delete((int)$m[1]);
        json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    $adminProductImages = static function (int $productId): array {
        try {
            return Database::rows(
                "SELECT id, product_id, COALESCE(image_path, url) AS url, COALESCE(image_path, url) AS image_path, alt_text, is_primary, sort_order
                 FROM product_images
                 WHERE product_id = ?
                 ORDER BY is_primary DESC, sort_order ASC, id ASC",
                [$productId]
            );
        } catch (\Throwable) {
            $images = Database::rows(
                "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC",
                [$productId]
            );
            foreach ($images as &$img) {
                if (!isset($img['url']) && isset($img['image_path'])) $img['url'] = $img['image_path'];
                if (!isset($img['image_path']) && isset($img['url'])) $img['image_path'] = $img['url'];
            }
            unset($img);
            return $images;
        }
    };

    $ensureHomeBannerClickColumns = static function (): void {
        static $ready = false;
        if ($ready) return;
        try {
            $rows = Database::rows(
                "SELECT COLUMN_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'home_banners'
                   AND COLUMN_NAME IN ('image_click_enabled', 'image_click_url')"
            );
            $present = array_flip(array_map(static fn($row) => (string)($row['COLUMN_NAME'] ?? ''), $rows));
            if (!isset($present['image_click_enabled'])) {
                Database::query("ALTER TABLE home_banners ADD COLUMN image_click_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER cta_secondary_url");
            }
            if (!isset($present['image_click_url'])) {
                Database::query("ALTER TABLE home_banners ADD COLUMN image_click_url VARCHAR(255) NOT NULL DEFAULT '' AFTER image_click_enabled");
            }
            $ready = true;
        } catch (\Throwable $e) {
            error_log('Home banner click columns unavailable: ' . $e->getMessage());
        }
    };

    $deleteProductImage = static function (int $productId, int $imageId) use ($adminProductImages): array {
        if ($productId <= 0 || $imageId <= 0) {
            return ['ok' => false, 'msg' => 'Invalid product image'];
        }

        try {
            $image = Database::row(
                "SELECT id, product_id, COALESCE(image_path, url) AS image_path, COALESCE(image_path, url) AS url, is_primary
                 FROM product_images
                 WHERE id = ? AND product_id = ?
                 LIMIT 1",
                [$imageId, $productId]
            );
        } catch (\Throwable) {
            $image = Database::row("SELECT * FROM product_images WHERE id = ? AND product_id = ? LIMIT 1", [$imageId, $productId]);
            if ($image) {
                if (!isset($image['url']) && isset($image['image_path'])) $image['url'] = $image['image_path'];
                if (!isset($image['image_path']) && isset($image['url'])) $image['image_path'] = $image['url'];
            }
        }

        if (!$image) {
            return ['ok' => false, 'msg' => 'Image not found'];
        }

        $path = (string)($image['image_path'] ?? $image['url'] ?? '');
        $wasPrimary = (int)($image['is_primary'] ?? 0) === 1;

        Database::query("DELETE FROM product_images WHERE id = ? AND product_id = ?", [$imageId, $productId]);

        $remaining = $adminProductImages($productId);
        $nextPrimary = null;
        foreach ($remaining as $candidate) {
            if ((int)($candidate['is_primary'] ?? 0) === 1) {
                $nextPrimary = $candidate;
                break;
            }
        }
        if (!$nextPrimary && $remaining) {
            $nextPrimary = $remaining[0];
            Database::query("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?", [(int)$nextPrimary['id'], $productId]);
        }

        $primaryPath = $nextPrimary ? (string)($nextPrimary['image_path'] ?? $nextPrimary['url'] ?? '') : null;
        try {
            Database::query("UPDATE products SET image_path = ? WHERE id = ?", [$primaryPath, $productId]);
        } catch (\Throwable) {
            // Older schemas may not have products.image_path; product_images still stays correct.
        }

        if ($path !== '' && str_starts_with($path, '/uploads/products/')) {
            $productUses = 0;
            $imageUses = 0;
            try { $productUses = (int)(Database::row("SELECT COUNT(*) c FROM products WHERE image_path = ?", [$path])['c'] ?? 0); } catch (\Throwable) {}
            try {
                $imageUses = (int)(Database::row(
                    "SELECT COUNT(*) c FROM product_images WHERE COALESCE(image_path, url) = ?",
                    [$path]
                )['c'] ?? 0);
            } catch (\Throwable) {
                try { $imageUses = (int)(Database::row("SELECT COUNT(*) c FROM product_images WHERE url = ?", [$path])['c'] ?? 0); } catch (\Throwable) {}
            }
            if ($productUses === 0 && $imageUses === 0) {
                $file = PUBLIC_PATH . $path;
                $uploadsRoot = realpath(PUBLIC_PATH . '/uploads/products') ?: (PUBLIC_PATH . '/uploads/products');
                $realFile = realpath($file);
                if ($realFile && str_starts_with($realFile, $uploadsRoot) && is_file($realFile)) {
                    @unlink($realFile);
                }
            }
        }

        return ['ok' => true, 'images' => $adminProductImages($productId), 'deleted_primary' => $wasPrimary];
    };


    // ── Product Reviews Moderation ────────────────────────────
    if ($uri === '/admin/api/reviews' && $method === 'GET') {
        $status = trim((string)($_GET['status'] ?? 'new_order'));
        $search = trim((string)($_GET['search'] ?? ''));
        json(['ok' => true, 'reviews' => \Reviews\ProductReview::adminList($status, $search, 200)]);
    }

    if (preg_match('#^/admin/api/reviews/(\d+)/(approve|reject|pending)$#', $uri, $m) && $method === 'POST') {
        $admin = \Auth\Auth::admin();
        $status = $m[2] === 'approve' ? 'approved' : ($m[2] === 'reject' ? 'rejected' : 'pending');
        json(\Reviews\ProductReview::moderate((int)$m[1], $status, (int)($admin['id'] ?? 0), trim((string)($body['note'] ?? ''))));
    }

    if (preg_match('#^/admin/api/reviews/(\d+)/feature$#', $uri, $m) && $method === 'POST') {
        json(\Reviews\ProductReview::setFeatured((int)$m[1], !empty($body['featured'])));
    }

    if (preg_match('#^/admin/api/reviews/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        json(\Reviews\ProductReview::delete((int)$m[1]));
    }

    if ($uri === '/admin/api/dashboard' && $method === 'GET') {
        $hasSeen = $ensureOrderSeenColumn();
        $newOrderWhere = "status='new_order'";
        $statRows = [
            'total_orders'      => Database::row("SELECT COUNT(*) as c FROM orders")['c'] ?? 0,
            'new_orders'        => Database::row("SELECT COUNT(*) as c FROM orders WHERE $newOrderWhere")['c'] ?? 0,
            'total_revenue'     => Database::row("SELECT COALESCE(SUM(total_amount),0) as r FROM orders WHERE payment_status='paid'")['r'] ?? 0,
            'today_revenue'     => Database::row("SELECT COALESCE(SUM(total_amount),0) as r FROM orders WHERE DATE(created_at)=CURDATE() AND payment_status='paid'")['r'] ?? 0,
            'today_orders'      => Database::row("SELECT COUNT(*) as c FROM orders WHERE DATE(created_at)=CURDATE()")['c'] ?? 0,
            'pending_orders'    => Database::row("SELECT COUNT(*) as c FROM orders WHERE status IN ('new_order','received','whatsapp_pending')")['c'] ?? 0,
            'production_orders' => Database::row("SELECT COUNT(*) as c FROM orders WHERE status IN ('design_approved','other_process','processing','printing')")['c'] ?? 0,
            'ready_orders'      => Database::row("SELECT COUNT(*) as c FROM orders WHERE status='ready'")['c'] ?? 0,
            'delivered_orders'  => Database::row("SELECT COUNT(*) as c FROM orders WHERE status='delivered'")['c'] ?? 0,
            'pending_payments'  => Database::row("SELECT COALESCE(SUM(total_amount),0) as r FROM orders WHERE payment_status IS NULL OR payment_status <> 'paid'")['r'] ?? 0,
            'month_revenue'     => Database::row("SELECT COALESCE(SUM(total_amount),0) as r FROM orders WHERE created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND payment_status='paid'")['r'] ?? 0,
            'avg_order_value'   => Database::row("SELECT COALESCE(AVG(total_amount),0) as a FROM orders WHERE payment_status='paid'")['a'] ?? 0,
            'total_customers'   => Database::row("SELECT COUNT(*) as c FROM users")['c'] ?? 0,
        ];
        $trendPct = static function ($current, $previous): ?float {
            $current = (float)$current;
            $previous = (float)$previous;
            if ($previous <= 0) {
                return $current > 0 ? 100.0 : 0.0;
            }
            return round((($current - $previous) / $previous) * 100, 2);
        };
        $comparisonRows = [
            'new_orders' => [
                'previous' => Database::row("SELECT COUNT(*) as c FROM orders WHERE status='new_order' AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['c'] ?? 0,
                'label' => 'vs yesterday',
            ],
            'pending_orders' => [
                'previous' => Database::row("SELECT COUNT(*) as c FROM orders WHERE status IN ('new_order','received','whatsapp_pending') AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['c'] ?? 0,
                'label' => 'vs yesterday',
            ],
            'production_orders' => [
                'previous' => Database::row("SELECT COUNT(*) as c FROM orders WHERE status IN ('design_approved','other_process','processing','printing') AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['c'] ?? 0,
                'label' => 'vs yesterday',
            ],
            'ready_orders' => [
                'previous' => Database::row("SELECT COUNT(*) as c FROM orders WHERE status='ready' AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['c'] ?? 0,
                'label' => 'vs yesterday',
            ],
            'delivered_orders' => [
                'previous' => Database::row("SELECT COUNT(*) as c FROM orders WHERE status='delivered' AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")['c'] ?? 0,
                'label' => 'vs yesterday',
            ],
            'today_revenue' => [
                'previous' => Database::row("SELECT COALESCE(SUM(total_amount),0) as r FROM orders WHERE DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND payment_status='paid'")['r'] ?? 0,
                'label' => 'vs yesterday',
            ],
            'month_revenue' => [
                'previous' => Database::row("SELECT COALESCE(SUM(total_amount),0) as r FROM orders WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(),'%Y-%m-01') AND payment_status='paid'")['r'] ?? 0,
                'label' => 'vs last month',
            ],
            'avg_order_value' => [
                'previous' => Database::row("SELECT COALESCE(AVG(total_amount),0) as a FROM orders WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(),'%Y-%m-01') AND payment_status='paid'")['a'] ?? 0,
                'label' => 'vs last month',
            ],
            'total_customers' => [
                'previous' => Database::row("SELECT COUNT(*) as c FROM users WHERE created_at < DATE_FORMAT(CURDATE(),'%Y-%m-01')")['c'] ?? 0,
                'label' => 'vs last month',
            ],
        ];
        $stats = [];
        foreach ($statRows as $key => $value) {
            $stats[$key] = $value;
            if (isset($comparisonRows[$key])) {
                $stats[$key . '_trend'] = $trendPct($value, $comparisonRows[$key]['previous']);
                $stats[$key . '_trend_label'] = $comparisonRows[$key]['label'];
            }
        }
        $queue = [
            'new_order' => (int)(Database::row("SELECT COUNT(*) as c FROM orders WHERE status='new_order'")['c'] ?? 0),
            'received' => (int)(Database::row("SELECT COUNT(*) as c FROM orders WHERE status='received'")['c'] ?? 0),
            'design_approved' => (int)(Database::row("SELECT COUNT(*) as c FROM orders WHERE status='design_approved'")['c'] ?? 0),
            'printing' => (int)(Database::row("SELECT COUNT(*) as c FROM orders WHERE status='printing'")['c'] ?? 0),
            'other_process' => (int)(Database::row("SELECT COUNT(*) as c FROM orders WHERE status IN ('other_process','processing')")['c'] ?? 0),
            'ready' => (int)(Database::row("SELECT COUNT(*) as c FROM orders WHERE status='ready'")['c'] ?? 0),
            'delivered' => (int)(Database::row("SELECT COUNT(*) as c FROM orders WHERE status='delivered'")['c'] ?? 0),
        ];
        $byStatus    = Database::rows("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
        $monthly     = Database::rows("SELECT DATE_FORMAT(created_at,'%b %Y') as month, SUM(total_amount) as revenue, COUNT(*) as orders FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY created_at ASC");
        $topProducts = Database::rows("SELECT product_name, COUNT(*) as count, SUM(total_price) as revenue FROM order_items GROUP BY product_name ORDER BY count DESC LIMIT 8");
        $recentOrders= Database::rows("SELECT o.*, COUNT(oi.id) as item_count, SUBSTRING_INDEX(GROUP_CONCAT(oi.product_name ORDER BY oi.id SEPARATOR ', '), ',', 1) as product_summary FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id GROUP BY o.id ORDER BY o.created_at DESC LIMIT 10");
        $recentNewOrders = Database::rows("SELECT o.*, COUNT(oi.id) as item_count, SUBSTRING_INDEX(GROUP_CONCAT(oi.product_name ORDER BY oi.id SEPARATOR ', '), ',', 1) as product_summary FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id WHERE $newOrderWhere GROUP BY o.id ORDER BY o.created_at DESC LIMIT 6");
        json(['ok'=>true,'stats'=>$stats,'queue'=>$queue,'by_status'=>$byStatus,'monthly'=>$monthly,'top_products'=>$topProducts,'recent_orders'=>$recentOrders,'recent_new_orders'=>$recentNewOrders,'seen_supported'=>$hasSeen]);
    }

    if ($uri === '/admin/api/order-notifications' && $method === 'GET') {
        $hasSeen = $ensureOrderSeenColumn();
        $newOrderWhere = "status='new_order'";
        $count = (int)(Database::row("SELECT COUNT(*) AS c FROM orders WHERE $newOrderWhere")['c'] ?? 0);
        $orders = Database::rows("SELECT id, order_id, customer_name, total_amount, status, created_at FROM orders WHERE $newOrderWhere ORDER BY created_at DESC LIMIT 5");
        json(['ok'=>true,'count'=>$count,'orders'=>$orders,'seen_supported'=>$hasSeen]);
    }

    if (preg_match('#^/admin/api/orders/(\d+)/seen$#', $uri, $m) && $method === 'POST') {
        if (!$ensureOrderSeenColumn()) json(['ok'=>false,'msg'=>'Seen tracking is unavailable. Run database migration.'], 500);
        Database::query("UPDATE orders SET is_seen = 1 WHERE id = ?", [(int)$m[1]]);
        json(['ok'=>true]);
    }

    if ($uri === '/admin/api/orders' && $method === 'GET') {
        $status = trim($_GET['status'] ?? '');
        $q      = trim($_GET['q'] ?? '');
        $where = [];
        $params = [];
        if ($status && $status !== 'all') {
            if ($status === 'other_process') {
                $where[] = "o.status IN ('other_process','processing')";
            } else {
                $where[] = 'o.status = ?';
                $params[] = $status;
            }
        }
        if ($q) {
            $where[] = '(o.order_id LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_email LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $orders = Database::rows("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id $whereSql GROUP BY o.id ORDER BY o.created_at DESC", $params);
        json(['ok'=>true,'orders'=>$orders]);
    }

    if (preg_match('#^/admin/api/orders/(\d+)$#', $uri, $m) && $method === 'GET') {
        json(['ok'=>true,'order'=>\Orders\OrderManager::getOrder((int)$m[1])]);
    }

    if (preg_match('#^/admin/api/orders/(\d+)/status$#', $uri, $m) && $method === 'POST') {
        $ok = \Orders\OrderManager::updateStatus((int)$m[1], $body['status'] ?? '', $body['note'] ?? '');
        json(['ok'=>$ok]);
    }

    if (preg_match('#^/admin/api/orders/(\d+)/customer-update/clear$#', $uri, $m) && $method === 'POST') {
        \Orders\OrderManager::clearCustomerUpdate((int)$m[1]);
        json(['ok' => true]);
    }

    if (preg_match('#^/admin/api/orders/(\d+)/shipping$#', $uri, $m) && $method === 'POST') {
        try {
            Database::query(
                "UPDATE orders SET shipping_provider=?, tracking_code=?, shipping_status=?, shipping_notes=?, updated_at=NOW() WHERE id=?",
                [
                    trim((string)($body['shipping_provider'] ?? '')),
                    trim((string)($body['tracking_code'] ?? '')),
                    trim((string)($body['shipping_status'] ?? '')),
                    trim((string)($body['shipping_notes'] ?? '')),
                    (int)$m[1],
                ]
            );
            \Orders\AdminAudit::log('order_shipping_update', 'Order #' . $m[1] . ' shipping updated');
            \Orders\OrderManager::markAdminUpdate((int)$m[1], 'shipping_updated');
            json(['ok'=>true]);
        } catch (\Throwable $e) {
            json(['ok'=>false,'msg'=>'Shipping columns missing. Apply SQL migration first.'], 500);
        }
    }

    if ($uri === '/admin/api/product-filters' && $method === 'GET') {
        json(['ok' => true, 'filters' => \Catalog\ProductCatalog::filterOptions()]);
    }

    if ($uri === '/admin/api/products' && $method === 'GET') {
        try {
            json(['ok'=>true,'products'=>\Catalog\ProductCatalog::all(false)]);
        } catch (\Throwable $e) {
            error_log('Admin products list failed: ' . $e->getMessage());
            json(['ok'=>false,'msg'=>'Could not load products. Check DB schema and logs.','products'=>[]], 500);
        }
    }
    if ($uri === '/admin/api/products' && $method === 'POST') {
        if (!\Auth\Auth::isSuperAdmin()) $body['is_active'] = 0;
        $result = \Catalog\ProductCatalog::upsert($body);
        if (!empty($result['ok']) && !empty($result['id'])) {
            $syncProductBusinessNeeds((int)$result['id'], $body['business_need_ids'] ?? []);
            \Approvals\ContentApprovalManager::applySaveState('products', (int)$result['id']);
        }
        json($result);
    }
    if (preg_match('#^/admin/api/products/(\d+)$#', $uri, $m) && $method === 'GET') {
        $p = \Catalog\ProductCatalog::byId((int)$m[1]);
        if ($p) $p['business_need_ids'] = $getProductBusinessNeedIds((int)$m[1]);
        json($p ? ['ok'=>true,'product'=>$p] : ['ok'=>false,'msg'=>'Not found'], $p ? 200 : 404);
    }
    if (preg_match('#^/admin/api/products/(\d+)$#', $uri, $m) && $method === 'PUT') {
        if (!\Auth\Auth::isSuperAdmin()) $body['is_active'] = 0;
        $result = \Catalog\ProductCatalog::upsert($body, (int)$m[1]);
        if (!empty($result['ok'])) {
            $syncProductBusinessNeeds((int)$m[1], $body['business_need_ids'] ?? []);
            \Approvals\ContentApprovalManager::applySaveState('products', (int)$m[1]);
        }
        json($result);
    }
    if (preg_match('#^/admin/api/products/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        Database::query("UPDATE products SET is_active = NOT is_active WHERE id=?", [$m[1]]);
        json(['ok'=>true]);
    }
    if (preg_match('#^/admin/api/products/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        Database::query("DELETE FROM products WHERE id=?", [$m[1]]);
        json(['ok'=>true]);
    }

    if (preg_match('#^/admin/api/products/(\d+)/image-path$#', $uri, $m) && $method === 'DELETE') {
        $pid = (int)$m[1];
        try {
            $product = Database::row("SELECT id, image_path FROM products WHERE id = ? LIMIT 1", [$pid]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'This product schema does not support a legacy main image field'], 422);
        }
        if (!$product) json(['ok'=>false,'msg'=>'Product not found'], 404);

        $path = (string)($product['image_path'] ?? '');
        try { Database::query("UPDATE products SET image_path = NULL WHERE id = ?", [$pid]); } catch (\Throwable) {}

        if ($path !== '' && str_starts_with($path, '/uploads/products/')) {
            $productUses = 0;
            $imageUses = 0;
            try { $productUses = (int)(Database::row("SELECT COUNT(*) c FROM products WHERE image_path = ?", [$path])['c'] ?? 0); } catch (\Throwable) {}
            try {
                $imageUses = (int)(Database::row(
                    "SELECT COUNT(*) c FROM product_images WHERE COALESCE(image_path, url) = ?",
                    [$path]
                )['c'] ?? 0);
            } catch (\Throwable) {
                try { $imageUses = (int)(Database::row("SELECT COUNT(*) c FROM product_images WHERE url = ?", [$path])['c'] ?? 0); } catch (\Throwable) {}
            }
            if ($productUses === 0 && $imageUses === 0) {
                $file = PUBLIC_PATH . $path;
                $uploadsRoot = realpath(PUBLIC_PATH . '/uploads/products') ?: (PUBLIC_PATH . '/uploads/products');
                $realFile = realpath($file);
                if ($realFile && str_starts_with($realFile, $uploadsRoot) && is_file($realFile)) {
                    @unlink($realFile);
                }
            }
        }

        json(['ok'=>true,'images'=>$adminProductImages($pid)]);
    }

    if (preg_match('#^/admin/api/products/(\d+)/tiers$#', $uri, $m) && $method === 'GET') {
        try {
            $tiers = Database::rows("SELECT id, quantity, price FROM product_quantity_tiers WHERE product_id=? ORDER BY quantity ASC", [(int)$m[1]]);
        } catch (\Throwable) {
            $tiers = [];
        }
        json(['ok'=>true,'tiers'=>$tiers]);
    }
    if (preg_match('#^/admin/api/products/(\d+)/tiers$#', $uri, $m) && $method === 'POST') {
        $tiers = $body['tiers'] ?? [];
        if (!is_array($tiers)) json(['ok'=>false,'msg'=>'Invalid tiers']);

        $seen = [];
        usort($tiers, fn($a,$b) => ((int)($a['quantity']??0)) <=> ((int)($b['quantity']??0)));
        foreach ($tiers as $t) {
            $q = (int)($t['quantity'] ?? 0);
            $pr = (float)($t['price'] ?? 0);
            if ($q <= 0 || $pr <= 0) json(['ok'=>false,'msg'=>'Quantity and price are required']);
            if (isset($seen[$q])) json(['ok'=>false,'msg'=>'Duplicate quantity: ' . $q]);
            $seen[$q] = true;
        }

        Database::query("DELETE FROM product_quantity_tiers WHERE product_id=?", [(int)$m[1]]);
        foreach ($tiers as $t) {
            Database::insert("INSERT INTO product_quantity_tiers (product_id, quantity, price, created_at) VALUES (?,?,?,NOW())", [(int)$m[1], (int)$t['quantity'], (float)$t['price']]);
        }
        json(['ok'=>true]);
    }

    if (preg_match('#^/admin/api/products/(\d+)/image-upload$#', $uri, $m) && $method === 'POST') {
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            json(['ok'=>false,'msg'=>'Image file is required'], 400);
        }
        $pid = (int)$m[1];
        $isPrimary = (int)($_POST['is_primary'] ?? 0) === 1;

        $file = $_FILES['image'];
        if ((int)$file['size'] <= 0) json(['ok'=>false,'msg'=>'Empty upload'], 400);
        if ((int)$file['size'] > 5 * 1024 * 1024) json(['ok'=>false,'msg'=>'Max file size is 5MB'], 400);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowedExt, true)) json(['ok'=>false,'msg'=>'Only jpg, png, webp allowed'], 400);

        $mime = mime_content_type($file['tmp_name']) ?: '';
        $allowedMime = ['image/jpeg','image/png','image/webp'];
        if (!in_array($mime, $allowedMime, true)) json(['ok'=>false,'msg'=>'Invalid image type'], 400);

        $dir = PUBLIC_PATH . '/uploads/products/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $name = 'prod_' . $pid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $dir . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            json(['ok'=>false,'msg'=>'Upload failed'], 500);
        }

        $publicPath = '/uploads/products/' . $name;

        try {
            if ($isPrimary) Database::query("UPDATE product_images SET is_primary=0 WHERE product_id=?", [$pid]);
            $imageId = Database::insert(
                "INSERT INTO product_images (product_id, image_path, url, alt_text, is_primary, sort_order) VALUES (?,?,?,?,?,?)",
                [$pid, $publicPath, $publicPath, 'Product image', $isPrimary ? 1 : 0, (int)($_POST['sort_order'] ?? 0)]
            );
        } catch (\Throwable) {
            if ($isPrimary) Database::query("UPDATE product_images SET is_primary=0 WHERE product_id=?", [$pid]);
            $imageId = Database::insert(
                "INSERT INTO product_images (product_id, url, alt_text, is_primary, sort_order) VALUES (?,?,?,?,?)",
                [$pid, $publicPath, 'Product image', $isPrimary ? 1 : 0, (int)($_POST['sort_order'] ?? 0)]
            );
        }

        if ($isPrimary) {
            try { Database::query("UPDATE products SET image_path=? WHERE id=?", [$publicPath, $pid]); } catch (\Throwable) {}
        }

        json(['ok'=>true,'path'=>$publicPath,'image_id'=>$imageId]);
    }

    if (preg_match('#^/admin/api/products/(\d+)/images-upload$#', $uri, $m) && $method === 'POST') {
        $files = $_FILES['images'] ?? null;
        if (!$files || !is_array($files['tmp_name'] ?? null)) json(['ok'=>false,'msg'=>'No files uploaded'], 400);
        $uploaded = [];
        $pid = (int)$m[1];
        foreach ($files['tmp_name'] as $i => $tmp) {
            if (!is_uploaded_file($tmp)) continue;
            $_FILES['image'] = [
                'name' => $files['name'][$i] ?? ('image_' . $i),
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $tmp,
                'error' => $files['error'][$i] ?? 0,
                'size' => $files['size'][$i] ?? 0,
            ];
            $_POST['is_primary'] = (string)(empty($uploaded) ? 1 : 0);
            $_POST['sort_order'] = (string)$i;
            // Reuse single upload route by internal call expectations.
            $file = $_FILES['image'];
            if ((int)$file['size'] <= 0) continue;
            if ((int)$file['size'] > 5 * 1024 * 1024) continue;
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;
            $mime = mime_content_type($file['tmp_name']) ?: '';
            if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) continue;
            $dir = PUBLIC_PATH . '/uploads/products/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $name = 'prod_' . $pid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $target = $dir . $name;
            if (!move_uploaded_file($file['tmp_name'], $target)) continue;
            $publicPath = '/uploads/products/' . $name;
            try {
                if (empty($uploaded)) Database::query("UPDATE product_images SET is_primary=0 WHERE product_id=?", [$pid]);
                $imageId = Database::insert("INSERT INTO product_images (product_id, image_path, url, alt_text, is_primary, sort_order) VALUES (?,?,?,?,?,?)", [$pid, $publicPath, $publicPath, 'Product image', empty($uploaded)?1:0, $i]);
            } catch (\Throwable) {
                if (empty($uploaded)) Database::query("UPDATE product_images SET is_primary=0 WHERE product_id=?", [$pid]);
                $imageId = Database::insert("INSERT INTO product_images (product_id, url, alt_text, is_primary, sort_order) VALUES (?,?,?,?,?)", [$pid, $publicPath, 'Product image', empty($uploaded)?1:0, $i]);
            }
            if (empty($uploaded)) { try { Database::query("UPDATE products SET image_path=? WHERE id=?", [$publicPath, $pid]); } catch (\Throwable) {} }
            $uploaded[] = ['id'=>$imageId,'path'=>$publicPath];
        }
        if (!$uploaded) json(['ok'=>false,'msg'=>'No valid images were uploaded'], 400);
        json(['ok'=>true,'images'=>$uploaded]);
    }


    if (preg_match('#^/admin/api/products/(\d+)/images$#', $uri, $m) && $method === 'POST') {
        $pid = (int)$m[1];
        try {
            $id = Database::insert("INSERT INTO product_images (product_id, image_path, url, alt_text, is_primary, sort_order) VALUES (?,?,?,?,?,?)",
                [$pid, $body['image_path'] ?? $body['url'] ?? '', $body['image_path'] ?? $body['url'] ?? '', $body['alt_text']??'', $body['is_primary']??0, $body['sort_order']??0]);
        } catch (\Throwable) {
            $id = Database::insert("INSERT INTO product_images (product_id, url, alt_text, is_primary, sort_order) VALUES (?,?,?,?,?)",
                [$pid, $body['url'] ?? $body['image_path'] ?? '', $body['alt_text']??'', $body['is_primary']??0, $body['sort_order']??0]);
        }
        if (!empty($body['is_primary'])) {
            Database::query("UPDATE product_images SET is_primary=0 WHERE product_id=? AND id!=?", [$pid,$id]);
            $primaryPath = (string)($body['image_path'] ?? $body['url'] ?? '');
            try { Database::query("UPDATE products SET image_path=? WHERE id=?", [$primaryPath, $pid]); } catch (\Throwable) {}
        }
        json(['ok'=>true,'id'=>$id]);
    }
    if (preg_match('#^/admin/api/products/(\d+)/images/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $result = $deleteProductImage((int)$m[1], (int)$m[2]);
        json($result, $result['ok'] ? 200 : 404);
    }
    if (preg_match('#^/admin/api/products/(\d+)/images$#', $uri, $m) && $method === 'DELETE') {
        $pid = (int)$m[1];
        $images = $adminProductImages($pid);
        foreach ($images as $image) {
            if (!empty($image['id'])) $deleteProductImage($pid, (int)$image['id']);
        }
        json(['ok'=>true,'images'=>[]]);
    }
    if (preg_match('#^/admin/api/images/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $image = Database::row("SELECT product_id FROM product_images WHERE id = ? LIMIT 1", [(int)$m[1]]);
        if (!$image) json(['ok'=>false,'msg'=>'Image not found'], 404);
        $result = $deleteProductImage((int)$image['product_id'], (int)$m[1]);
        json($result, $result['ok'] ? 200 : 404);
    }

    if ($uri === '/admin/api/qualities' && $method === 'GET') {
        json(['ok'=>true,'qualities'=>Database::rows("SELECT * FROM qualities ORDER BY sort_order ASC")]);
    }
    if ($uri === '/admin/api/qualities' && $method === 'POST') {
        $id = Database::insert("INSERT INTO qualities (name, description, sort_order, is_active) VALUES (?,?,?,1)",
            [$body['name'], $body['description']??'', $body['sort_order']??0]);
        json(['ok'=>true,'id'=>$id]);
    }
    if (preg_match('#^/admin/api/qualities/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        Database::query("DELETE FROM qualities WHERE id=?", [$m[1]]);
        json(['ok'=>true]);
    }
    if (preg_match('#^/admin/api/qualities/(\d+)/slabs$#', $uri, $m) && $method === 'POST') {
        $qid = (int)$m[1]; $pid = (int)($body['product_id']??0);
        foreach ($body['slabs']??[] as $qty => $price) {
            if ((float)$price > 0) {
                Database::query("INSERT INTO quantity_slabs (product_id, quality_id, quantity, price) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price)",
                    [$pid, $qid, (int)$qty, (float)$price]);
            }
        }
        json(['ok'=>true]);
    }

    if ($uri === '/admin/api/attribute-groups' && $method === 'GET') {
        $groups = Database::rows("SELECT * FROM attribute_groups ORDER BY sort_order");
        foreach ($groups as &$g) $g['options'] = Database::rows("SELECT * FROM attribute_options WHERE group_id=? ORDER BY sort_order", [$g['id']]);
        json(['ok'=>true,'groups'=>$groups]);
    }
    if ($uri === '/admin/api/attribute-groups' && $method === 'POST') {
        $gid = Database::insert("INSERT INTO attribute_groups (name, sort_order) VALUES (?,?)", [$body['name'], $body['sort_order']??0]);
        foreach ($body['options']??[] as $i => $opt) {
            if (!empty($opt['label'])) Database::insert("INSERT INTO attribute_options (group_id, label, price_addon, sort_order) VALUES (?,?,?,?)",
                [$gid, $opt['label'], (float)($opt['price_addon']??0), $i]);
        }
        json(['ok'=>true,'id'=>$gid]);
    }
    if (preg_match('#^/admin/api/attribute-groups/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        Database::query("DELETE FROM attribute_groups WHERE id=?", [$m[1]]);
        json(['ok'=>true]);
    }
    if (preg_match('#^/admin/api/products/(\d+)/attribute-groups$#', $uri, $m) && $method === 'POST') {
        Database::query("INSERT IGNORE INTO product_attribute_groups (product_id, group_id, sort_order) VALUES (?,?,?)",
            [$m[1], $body['group_id'], $body['sort_order']??0]);
        json(['ok'=>true]);
    }
    if (preg_match('#^/admin/api/products/(\d+)/attribute-groups/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        Database::query("DELETE FROM product_attribute_groups WHERE product_id=? AND group_id=?", [$m[1],$m[2]]);
        json(['ok'=>true]);
    }

    if ($uri === '/admin/api/coupons' && $method === 'GET') {
        try {
            $coupons = Database::rows(
                "SELECT c.*, cat.name AS category_name
                 FROM coupons c
                 LEFT JOIN categories cat ON cat.id = c.category_id
                 ORDER BY c.created_at DESC"
            );
        } catch (\Throwable) {
            $coupons = Database::rows("SELECT * FROM coupons ORDER BY created_at DESC");
            foreach ($coupons as &$c) $c['category_name'] = null;
        }
        json(['ok'=>true,'coupons'=>$coupons]);
    }
    if ($uri === '/admin/api/coupons' && $method === 'POST') {
        $code = strtoupper(trim($body['code']??''));
        if (!$code) json(['ok'=>false,'msg'=>'Code required']);
        if (Database::row("SELECT id FROM coupons WHERE code=?",[$code])) json(['ok'=>false,'msg'=>'Code exists']);
        $scopeType = ($body['scope_type'] ?? 'all') === 'category' ? 'category' : 'all';
        $categoryId = (int)($body['category_id'] ?? 0);
        if ($scopeType === 'category' && $categoryId <= 0) {
            json(['ok'=>false,'msg'=>'Please select a category for category-specific coupon.'], 422);
        }
        $active = \Auth\Auth::isSuperAdmin() ? 1 : 0;
        try {
            $id = Database::insert(
                "INSERT INTO coupons (code,description,discount_type,discount_value,min_order_amount,max_uses,valid_from,valid_until,scope_type,category_id,is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $code, $body['description'] ?? '', $body['discount_type'] ?? 'percent',
                    (float)($body['discount_value'] ?? 0), (float)($body['min_order_amount'] ?? 0),
                    (int)($body['max_uses'] ?? 0), $body['valid_from'] ?: null, $body['valid_until'] ?: null,
                    $scopeType, $scopeType === 'category' ? $categoryId : null, $active,
                ]
            );
        } catch (\Throwable) {
            $id = Database::insert(
                "INSERT INTO coupons (code,description,discount_type,discount_value,min_order_amount,max_uses,valid_from,valid_until,is_active)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [
                    $code, $body['description'] ?? '', $body['discount_type'] ?? 'percent',
                    (float)($body['discount_value'] ?? 0), (float)($body['min_order_amount'] ?? 0),
                    (int)($body['max_uses'] ?? 0), $body['valid_from'] ?: null, $body['valid_until'] ?: null, $active,
                ]
            );
        }
        \Orders\AdminAudit::log('coupon_created',"Coupon: $code");
        \Approvals\ContentApprovalManager::applySaveState('coupons', (int)$id);
        json(['ok'=>true,'id'=>$id]);
    }
    if (preg_match('#^/admin/api/coupons/(\d+)$#', $uri, $m) && $method === 'PUT') {
        $id = (int)$m[1];
        $code = strtoupper(trim($body['code']??''));
        if (!$code) json(['ok'=>false,'msg'=>'Code required']);
        if (Database::row("SELECT id FROM coupons WHERE code=? AND id<>?",[$code, $id])) json(['ok'=>false,'msg'=>'Code exists']);
        $scopeType = ($body['scope_type'] ?? 'all') === 'category' ? 'category' : 'all';
        $categoryId = (int)($body['category_id'] ?? 0);
        if ($scopeType === 'category' && $categoryId <= 0) {
            json(['ok'=>false,'msg'=>'Please select a category for category-specific coupon.'], 422);
        }
        try {
            Database::query(
                "UPDATE coupons
                 SET code=?, description=?, discount_type=?, discount_value=?, min_order_amount=?, max_uses=?, valid_from=?, valid_until=?, scope_type=?, category_id=?
                 WHERE id=?",
                [
                    $code, $body['description'] ?? '', $body['discount_type'] ?? 'percent',
                    (float)($body['discount_value'] ?? 0), (float)($body['min_order_amount'] ?? 0),
                    (int)($body['max_uses'] ?? 0), $body['valid_from'] ?: null, $body['valid_until'] ?: null,
                    $scopeType, $scopeType === 'category' ? $categoryId : null, $id,
                ]
            );
        } catch (\Throwable) {
            Database::query(
                "UPDATE coupons
                 SET code=?, description=?, discount_type=?, discount_value=?, min_order_amount=?, max_uses=?, valid_from=?, valid_until=?
                 WHERE id=?",
                [
                    $code, $body['description'] ?? '', $body['discount_type'] ?? 'percent',
                    (float)($body['discount_value'] ?? 0), (float)($body['min_order_amount'] ?? 0),
                    (int)($body['max_uses'] ?? 0), $body['valid_from'] ?: null, $body['valid_until'] ?: null, $id,
                ]
            );
        }
        \Orders\AdminAudit::log('coupon_updated',"Coupon: $code");
        \Approvals\ContentApprovalManager::applySaveState('coupons', $id);
        json(['ok'=>true,'id'=>$id]);
    }
    if (preg_match('#^/admin/api/coupons/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        Database::query("UPDATE coupons SET is_active=NOT is_active WHERE id=?",[$m[1]]);
        json(['ok'=>true]);
    }
    if (preg_match('#^/admin/api/coupons/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        Database::query("DELETE FROM coupons WHERE id=?",[$m[1]]);
        json(['ok'=>true]);
    }

    if ($uri === '/admin/api/categories' && $method === 'GET') {
        json(['ok'=>true,'categories'=>\Catalog\ProductCatalog::categories()]);
    }
    if (preg_match('#^/admin/api/categories/(\d+)/next-product-code$#', $uri, $m) && $method === 'GET') {
        $categoryId = (int)$m[1];
        $editId = (int)($_GET['edit_id'] ?? 0);
        $code = \Catalog\ProductCatalog::nextProductCodePreview($categoryId, $editId > 0 ? $editId : null);
        json(['ok' => true, 'code' => $code]);
    }
    if ($uri === '/admin/api/categories/upload' && $method === 'POST') {
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            json(['ok'=>false,'msg'=>'Image file is required'], 400);
        }
        $file = $_FILES['image'];
        if ((int)$file['size'] <= 0) json(['ok'=>false,'msg'=>'Empty upload'], 400);
        if ((int)$file['size'] > 6 * 1024 * 1024) json(['ok'=>false,'msg'=>'Max file size is 6MB'], 400);
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) json(['ok'=>false,'msg'=>'Only jpg, png, webp allowed'], 400);
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) json(['ok'=>false,'msg'=>'Invalid image type'], 400);
        $dir = PUBLIC_PATH . '/uploads/categories/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = 'category_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $dir . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) json(['ok'=>false,'msg'=>'Upload failed'], 500);
        json(['ok'=>true,'path'=>'/uploads/categories/' . $name]);
    }
    if ($uri === '/admin/api/categories' && $method === 'POST') {
        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') json(['ok'=>false,'msg'=>'Category name is required'], 422);
        $slugBase = trim((string)($body['slug'] ?? $name));
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $slugBase) ?? '');
        $slug = trim($slug, '-') ?: strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        $prefix = strtoupper(trim((string)($body['code_prefix'] ?? '')));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: null;
        $icon = trim((string)($body['icon'] ?? '🖨️')) ?: '🖨️';
        $imagePath = trim((string)($body['image_path'] ?? '')) ?: null;
        $imageAlt = trim((string)($body['image_alt'] ?? '')) ?: ($name . ' category image');
        $sort = (int)($body['sort_order'] ?? 0);
        $active = isset($body['is_active']) ? (int)((int)$body['is_active'] > 0) : 1;
        if (!\Auth\Auth::isSuperAdmin()) $active = 0;
        try {
            $id = Database::insert(
                "INSERT INTO categories (name,slug,code_prefix,icon,image_path,image_alt,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?)",
                [$name,$slug,$prefix,$icon,$imagePath,$imageAlt,$sort,$active]
            );
        } catch (\Throwable) {
            try {
                $id = Database::insert(
                    "INSERT INTO categories (name,slug,icon,image_path,image_alt,sort_order,is_active) VALUES (?,?,?,?,?,?,?)",
                    [$name,$slug,$icon,$imagePath,$imageAlt,$sort,$active]
                );
            } catch (\Throwable) {
                $id = Database::insert(
                    "INSERT INTO categories (name,slug,icon,sort_order,is_active) VALUES (?,?,?,?,?)",
                    [$name,$slug,$icon,$sort,$active]
                );
            }
        }
        \Orders\AdminAudit::log('category_created', "Category #{$id}: {$name}");
        \Approvals\ContentApprovalManager::applySaveState('categories', (int)$id);
        json(['ok'=>true,'id'=>$id]);
    }
    if (preg_match('#^/admin/api/categories/(\d+)$#', $uri, $m) && $method === 'PUT') {
        $id = (int)$m[1];
        $existing = Database::row("SELECT * FROM categories WHERE id=?", [$id]);
        if (!$existing) json(['ok'=>false,'msg'=>'Category not found'], 404);

        $name = trim((string)($body['name'] ?? $existing['name']));
        if ($name === '') json(['ok'=>false,'msg'=>'Category name is required'], 422);

        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $body['slug'] ?? $name));
        $slug = trim((string)$slug, '-') ?: ('category-' . $id);
        $prefix = strtoupper(trim((string)($body['code_prefix'] ?? ($existing['code_prefix'] ?? ''))));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: null;
        $icon = trim((string)($body['icon'] ?? ($existing['icon'] ?? '🖨️'))) ?: '🖨️';
        $imagePath = trim((string)($body['image_path'] ?? ($existing['image_path'] ?? ''))) ?: null;
        $imageAlt = trim((string)($body['image_alt'] ?? ($existing['image_alt'] ?? ''))) ?: ($name . ' category image');
        $sort = (int)($body['sort_order'] ?? ($existing['sort_order'] ?? 0));
        $active = isset($body['is_active']) ? (int)((int)$body['is_active'] > 0) : (int)($existing['is_active'] ?? 1);
        if (!\Auth\Auth::isSuperAdmin()) $active = 0;

        try {
            Database::query(
                "UPDATE categories SET name=?, slug=?, code_prefix=?, icon=?, image_path=?, image_alt=?, sort_order=?, is_active=? WHERE id=?",
                [$name, $slug, $prefix, $icon, $imagePath, $imageAlt, $sort, $active, $id]
            );
        } catch (\Throwable) {
            try {
                Database::query(
                    "UPDATE categories SET name=?, slug=?, icon=?, image_path=?, image_alt=?, sort_order=?, is_active=? WHERE id=?",
                    [$name, $slug, $icon, $imagePath, $imageAlt, $sort, $active, $id]
                );
            } catch (\Throwable) {
                Database::query(
                    "UPDATE categories SET name=?, slug=?, icon=?, sort_order=?, is_active=? WHERE id=?",
                    [$name, $slug, $icon, $sort, $active, $id]
                );
            }
        }
        \Orders\AdminAudit::log('category_updated', "Category #{$id}: {$name}");
        \Approvals\ContentApprovalManager::applySaveState('categories', $id);
        json(['ok'=>true]);
    }
    if (preg_match('#^/admin/api/categories/(\d+)/toggle$#', $uri, $m) && $method === 'POST') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        $id = (int)$m[1];
        Database::query("UPDATE categories SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?", [$id]);
        \Orders\AdminAudit::log('category_toggled', "Category #{$id} status toggled");
        json(['ok'=>true]);
    }
    if (preg_match('#^/admin/api/categories/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        $id = (int)$m[1];
        $cat = Database::row("SELECT id,name FROM categories WHERE id=?", [$id]);
        if (!$cat) json(['ok'=>false,'msg'=>'Category not found'], 404);
        $usage = (int)(Database::row("SELECT COUNT(*) c FROM products WHERE category_id=?", [$id])['c'] ?? 0);
        if ($usage > 0) {
            json(['ok'=>false,'msg'=>'Category is in use by products. Reassign products before deleting.'], 422);
        }
        try {
            Database::query("DELETE FROM categories WHERE id=?", [$id]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not delete category. It may be referenced elsewhere.'], 422);
        }
        \Orders\AdminAudit::log('category_deleted', "Category #{$id}: {$cat['name']}");
        json(['ok'=>true]);
    }

    if ($uri === '/admin/api/banners' && $method === 'GET') {
        try {
            $ensureHomeBannerClickColumns();
            $rows = Database::rows("SELECT * FROM home_banners ORDER BY sort_order ASC, id DESC");
            json(['ok'=>true,'banners'=>$rows]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'home_banners table missing. Run SQL migration first.','banners'=>[]], 500);
        }
    }
    if ($uri === '/admin/api/banners' && $method === 'POST') {
        if (trim((string)($body['image_path'] ?? '')) === '') {
            json(['ok'=>false,'msg'=>'Banner image path is required'], 400);
        }
        $imageClickEnabled = (int)($body['image_click_enabled'] ?? 0) === 1 ? 1 : 0;
        $imageClickUrl = trim((string)($body['image_click_url'] ?? ''));
        if ($imageClickEnabled && $imageClickUrl === '') {
            json(['ok'=>false,'msg'=>'Image click URL is required when clickable image is enabled'], 400);
        }
        try {
            $ensureHomeBannerClickColumns();
            $id = Database::insert(
                "INSERT INTO home_banners (eyebrow,title,subtitle,image_path,image_alt,cta_primary_text,cta_primary_url,cta_secondary_text,cta_secondary_type,cta_secondary_url,image_click_enabled,image_click_url,sort_order,is_active,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                [
                    trim((string)($body['eyebrow'] ?? '')),
                    trim((string)($body['title'] ?? '')),
                    trim((string)($body['subtitle'] ?? '')),
                    trim((string)($body['image_path'] ?? '')),
                    trim((string)($body['image_alt'] ?? '')),
                    trim((string)($body['cta_primary_text'] ?? '')),
                    trim((string)($body['cta_primary_url'] ?? '')),
                    trim((string)($body['cta_secondary_text'] ?? '')),
                    trim((string)($body['cta_secondary_type'] ?? 'whatsapp')),
                    trim((string)($body['cta_secondary_url'] ?? '')),
                    $imageClickEnabled,
                    $imageClickUrl,
                    (int)($body['sort_order'] ?? 0),
                    (int)($body['is_active'] ?? 1),
                ]
            );
            json(['ok'=>true,'id'=>$id]);
        } catch (\Throwable $e) {
            json(['ok'=>false,'msg'=>'Could not create banner. Run migration first.'], 500);
        }
    }
    if (preg_match('#^/admin/api/banners/(\d+)$#', $uri, $m) && $method === 'PUT') {
        if (trim((string)($body['image_path'] ?? '')) === '') {
            json(['ok'=>false,'msg'=>'Banner image path is required'], 400);
        }
        $imageClickEnabled = (int)($body['image_click_enabled'] ?? 0) === 1 ? 1 : 0;
        $imageClickUrl = trim((string)($body['image_click_url'] ?? ''));
        if ($imageClickEnabled && $imageClickUrl === '') {
            json(['ok'=>false,'msg'=>'Image click URL is required when clickable image is enabled'], 400);
        }
        try {
            $ensureHomeBannerClickColumns();
            Database::query(
                "UPDATE home_banners
                 SET eyebrow=?, title=?, subtitle=?, image_path=?, image_alt=?, cta_primary_text=?, cta_primary_url=?, cta_secondary_text=?, cta_secondary_type=?, cta_secondary_url=?, image_click_enabled=?, image_click_url=?, sort_order=?, is_active=?, updated_at=NOW()
                 WHERE id=?",
                [
                    trim((string)($body['eyebrow'] ?? '')),
                    trim((string)($body['title'] ?? '')),
                    trim((string)($body['subtitle'] ?? '')),
                    trim((string)($body['image_path'] ?? '')),
                    trim((string)($body['image_alt'] ?? '')),
                    trim((string)($body['cta_primary_text'] ?? '')),
                    trim((string)($body['cta_primary_url'] ?? '')),
                    trim((string)($body['cta_secondary_text'] ?? '')),
                    trim((string)($body['cta_secondary_type'] ?? 'whatsapp')),
                    trim((string)($body['cta_secondary_url'] ?? '')),
                    $imageClickEnabled,
                    $imageClickUrl,
                    (int)($body['sort_order'] ?? 0),
                    (int)($body['is_active'] ?? 1),
                    (int)$m[1],
                ]
            );
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not update banner'], 500);
        }
    }
    if (preg_match('#^/admin/api/banners/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        try {
            Database::query("DELETE FROM home_banners WHERE id=?", [(int)$m[1]]);
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not delete banner'], 500);
        }
    }
    if ($uri === '/admin/api/banners/reorder' && $method === 'POST') {
        foreach (($body['items'] ?? []) as $item) {
            Database::query("UPDATE home_banners SET sort_order=?, updated_at=NOW() WHERE id=?", [(int)($item['sort_order'] ?? 0), (int)($item['id'] ?? 0)]);
        }
        json(['ok'=>true]);
    }
    if ($uri === '/admin/api/banners/upload' && $method === 'POST') {
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            json(['ok'=>false,'msg'=>'Image file is required'], 400);
        }
        $file = $_FILES['image'];
        if ((int)$file['size'] <= 0) json(['ok'=>false,'msg'=>'Empty upload'], 400);
        if ((int)$file['size'] > 6 * 1024 * 1024) json(['ok'=>false,'msg'=>'Max file size is 6MB'], 400);
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) json(['ok'=>false,'msg'=>'Only jpg, png, webp allowed'], 400);
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) json(['ok'=>false,'msg'=>'Invalid image type'], 400);
        $dir = PUBLIC_PATH . '/uploads/banners/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = 'banner_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $dir . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) json(['ok'=>false,'msg'=>'Upload failed'], 500);
        json(['ok'=>true,'path'=>'/uploads/banners/' . $name]);
    }


    if ($uri === '/admin/api/combo-offers' && $method === 'GET') { try { json(['ok'=>true,'offers'=>\Combos\ComboOfferManager::all(),'products'=>Database::rows("SELECT id,name FROM products WHERE is_active=1 ORDER BY name")]); } catch (\Throwable $e) { json(['ok'=>false,'msg'=>$e->getMessage()],500); } }
    if ($uri === '/admin/api/combo-offers' && $method === 'POST') { try { json(\Combos\ComboOfferManager::save($body),200); } catch (\Throwable $e) { json(['ok'=>false,'msg'=>'Could not save combo offer.'],500); } }
    if (preg_match('#^/admin/api/combo-offers/(\d+)$#',$uri,$m) && $method === 'GET') { $offer=\Combos\ComboOfferManager::find((int)$m[1]); json(['ok'=>(bool)$offer,'offer'=>$offer],$offer?200:404); }
    if (preg_match('#^/admin/api/combo-offers/(\d+)$#',$uri,$m) && in_array($method,['PUT','POST'],true)) { try { json(\Combos\ComboOfferManager::save($body,(int)$m[1])); } catch (\Throwable $e) { json(['ok'=>false,'msg'=>'Could not update combo offer.'],500); } }
    if (preg_match('#^/admin/api/combo-offers/(\d+)$#',$uri,$m) && $method === 'DELETE') { \Combos\ComboOfferManager::delete((int)$m[1]); json(['ok'=>true]); }
    if ($uri === '/admin/api/combo-offers/upload' && $method === 'POST') { $file=$_FILES['image']??null; if(!$file||$file['error']!==UPLOAD_ERR_OK) json(['ok'=>false,'msg'=>'Choose an image.'],422); $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)); if(!in_array($ext,['jpg','jpeg','png','webp'],true)) json(['ok'=>false,'msg'=>'JPG, PNG or WEBP only.'],422); $dir=PUBLIC_PATH.'/uploads/combos/'; if(!is_dir($dir)) mkdir($dir,0755,true); $name='combo-'.bin2hex(random_bytes(8)).'.'.$ext; if(!move_uploaded_file($file['tmp_name'],$dir.$name)) json(['ok'=>false,'msg'=>'Upload failed.'],500); json(['ok'=>true,'path'=>'/uploads/combos/'.$name]); }

    if ($uri === '/admin/api/deals' && $method === 'GET') {
        try {
            $rows = Database::rows("SELECT * FROM home_deals ORDER BY sort_order ASC, id DESC");
            json(['ok'=>true,'deals'=>$rows]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'home_deals table missing. Run SQL migration first.','deals'=>[]], 500);
        }
    }
    if ($uri === '/admin/api/deals' && $method === 'POST') {
        $dealType = strtolower(trim((string)($body['deal_type'] ?? 'deal')));
        $title = trim((string)($body['title'] ?? ''));
        $imagePath = trim((string)($body['image_path'] ?? ''));
        $priceText = trim((string)($body['price_text'] ?? ''));
        $theme = strtolower(trim((string)($body['color_theme'] ?? 'green')));
        if (!in_array($theme, ['green','orange','purple'], true)) $theme = 'green';
        if (!in_array($dealType, ['deal','promo'], true)) json(['ok'=>false,'msg'=>'Invalid deal type'], 400);
        if ($title === '') json(['ok'=>false,'msg'=>'Deal title is required'], 400);
        if ($dealType === 'deal' && $imagePath === '') json(['ok'=>false,'msg'=>'Deal image is required'], 400);
        if ($dealType === 'deal' && $priceText === '') json(['ok'=>false,'msg'=>'Deal price is required'], 400);
        try {
            $id = Database::insert(
                "INSERT INTO home_deals (deal_type,title,highlight_text,subtitle,price_text,description,image_path,image_alt,cta_text,cta_url,color_theme,sort_order,is_active,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                [
                    $dealType,
                    $title,
                    trim((string)($body['highlight_text'] ?? '')),
                    trim((string)($body['subtitle'] ?? ($dealType === 'deal' ? 'Starting from' : ''))),
                    $priceText,
                    trim((string)($body['description'] ?? '')),
                    $imagePath,
                    trim((string)($body['image_alt'] ?? '')),
                    trim((string)($body['cta_text'] ?? '')) ?: ($dealType === 'promo' ? 'Get Offer' : 'Order Now'),
                    trim((string)($body['cta_url'] ?? '/categories')) ?: '/categories',
                    $theme,
                    (int)($body['sort_order'] ?? 0),
                    \Auth\Auth::isSuperAdmin() ? (int)($body['is_active'] ?? 1) : 0,
                ]
            );
            \Approvals\ContentApprovalManager::applySaveState('home_deals', (int)$id);
            json(['ok'=>true,'id'=>$id]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not create deal. Run migration first.'], 500);
        }
    }
    if (preg_match('#^/admin/api/deals/(\d+)$#', $uri, $m) && $method === 'PUT') {
        $dealType = strtolower(trim((string)($body['deal_type'] ?? 'deal')));
        $title = trim((string)($body['title'] ?? ''));
        $imagePath = trim((string)($body['image_path'] ?? ''));
        $priceText = trim((string)($body['price_text'] ?? ''));
        $theme = strtolower(trim((string)($body['color_theme'] ?? 'green')));
        if (!in_array($theme, ['green','orange','purple'], true)) $theme = 'green';
        if (!in_array($dealType, ['deal','promo'], true)) json(['ok'=>false,'msg'=>'Invalid deal type'], 400);
        if ($title === '') json(['ok'=>false,'msg'=>'Deal title is required'], 400);
        if ($dealType === 'deal' && $imagePath === '') json(['ok'=>false,'msg'=>'Deal image is required'], 400);
        if ($dealType === 'deal' && $priceText === '') json(['ok'=>false,'msg'=>'Deal price is required'], 400);
        try {
            Database::query(
                "UPDATE home_deals
                 SET deal_type=?, title=?, highlight_text=?, subtitle=?, price_text=?, description=?, image_path=?, image_alt=?, cta_text=?, cta_url=?, color_theme=?, sort_order=?, is_active=?, updated_at=NOW()
                 WHERE id=?",
                [
                    $dealType,
                    $title,
                    trim((string)($body['highlight_text'] ?? '')),
                    trim((string)($body['subtitle'] ?? ($dealType === 'deal' ? 'Starting from' : ''))),
                    $priceText,
                    trim((string)($body['description'] ?? '')),
                    $imagePath,
                    trim((string)($body['image_alt'] ?? '')),
                    trim((string)($body['cta_text'] ?? '')) ?: ($dealType === 'promo' ? 'Get Offer' : 'Order Now'),
                    trim((string)($body['cta_url'] ?? '/categories')) ?: '/categories',
                    $theme,
                    (int)($body['sort_order'] ?? 0),
                    \Auth\Auth::isSuperAdmin() ? (int)($body['is_active'] ?? 1) : 0,
                    (int)$m[1],
                ]
            );
            \Approvals\ContentApprovalManager::applySaveState('home_deals', (int)$m[1]);
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not update deal'], 500);
        }
    }
    if (preg_match('#^/admin/api/deals/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        try {
            Database::query("DELETE FROM home_deals WHERE id=?", [(int)$m[1]]);
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not delete deal'], 500);
        }
    }
    if ($uri === '/admin/api/deals/reorder' && $method === 'POST') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        foreach (($body['items'] ?? []) as $item) {
            Database::query("UPDATE home_deals SET sort_order=?, updated_at=NOW() WHERE id=?", [(int)($item['sort_order'] ?? 0), (int)($item['id'] ?? 0)]);
        }
        json(['ok'=>true]);
    }
    if ($uri === '/admin/api/deals/upload' && $method === 'POST') {
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            json(['ok'=>false,'msg'=>'Image file is required'], 400);
        }
        $file = $_FILES['image'];
        if ((int)$file['size'] <= 0) json(['ok'=>false,'msg'=>'Empty upload'], 400);
        if ((int)$file['size'] > 6 * 1024 * 1024) json(['ok'=>false,'msg'=>'Max file size is 6MB'], 400);
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) json(['ok'=>false,'msg'=>'Only jpg, png, webp allowed'], 400);
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) json(['ok'=>false,'msg'=>'Invalid image type'], 400);
        $dir = PUBLIC_PATH . '/uploads/deals/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = 'deal_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $dir . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) json(['ok'=>false,'msg'=>'Upload failed'], 500);
        json(['ok'=>true,'path'=>'/uploads/deals/' . $name]);
    }





    if ($uri === '/admin/api/custom-orders' && $method === 'POST') {
        $name = trim((string)($body['customer_name'] ?? ''));
        $phone = trim((string)($body['phone'] ?? ''));
        $product = trim((string)($body['product_name'] ?? ''));
        if ($name === '' || $phone === '' || $product === '') json(['ok'=>false,'msg'=>'Customer name, WhatsApp number and product are required.'], 422);
        $email=trim((string)($body['email'] ?? ''));
        $matched=$email!==''?Database::row("SELECT id FROM users WHERE is_active=1 AND (email=? OR phone=?) LIMIT 1",[$email,$phone]):Database::row("SELECT id FROM users WHERE is_active=1 AND phone=? LIMIT 1",[$phone]);
        $userId=(int)($matched['id']??0); $pendingCode='CQ-PENDING-'.strtoupper(bin2hex(random_bytes(8))); $token=bin2hex(random_bytes(24));
        $id = Database::insert("INSERT INTO custom_quote_requests (request_code,user_id,customer_name,phone,email,product_name,size_dimension,material_type,quantity,instructions,status,customer_type,quote_token,source_page,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,'new',?,?, 'admin',NOW())", [
            $pendingCode,$userId?:null,$name,$phone,$email?:null,$product,trim((string)($body['size_dimension'] ?? '')),trim((string)($body['material_type'] ?? '')),trim((string)($body['quantity'] ?? '')),trim((string)($body['instructions'] ?? '')),$userId?'registered':'guest',$token
        ]);
        $code = 'CQ-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
        Database::query("UPDATE custom_quote_requests SET request_code=? WHERE id=?", [$code,(int)$id]);
        json(['ok'=>true,'id'=>(int)$id,'request_code'=>$code]);
    }

    if ($uri === '/admin/api/custom-orders' && $method === 'GET') {
        try {
            $rows = Database::rows("SELECT cqr.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone
                FROM custom_quote_requests cqr
                LEFT JOIN users u ON u.id = cqr.user_id
                ORDER BY cqr.created_at DESC, cqr.id DESC
                LIMIT 300");
            $counts = ['all'=>0,'new'=>0,'reviewing'=>0,'sent_to_customer'=>0,'customer_approved'=>0,'payment_pending'=>0,'converted_to_order'=>0,'rejected'=>0];
            $updateCounts = ['new'=>0,'converted_to_order'=>0];
            foreach ($rows as $row) {
                $st = (string)($row['status'] ?? 'new');
                if ($st === 'paid') $st = 'converted_to_order';
                if (array_key_exists($st, $counts)) $counts[$st]++;
                if (!in_array($st, ['rejected','closed'], true)) $counts['all']++;
                if ($st === 'new' && (int)($row['is_seen'] ?? 0) === 0) $updateCounts['new']++;
                if ($st === 'converted_to_order' && (int)($row['customer_update_pending'] ?? 0) === 1) $updateCounts['converted_to_order']++;
            }
            $unseen = (int)(Database::row("SELECT COUNT(*) AS c FROM custom_quote_requests WHERE is_seen=0")['c'] ?? 0);
            Database::query("UPDATE custom_quote_requests SET is_seen=1 WHERE is_seen=0");
            json(['ok'=>true,'quotes'=>$rows,'counts'=>$counts,'unseen'=>$unseen,'update_counts'=>$updateCounts]);
        } catch (\Throwable $e) {
            json(['ok'=>false,'msg'=>'Could not load custom orders','quotes'=>[],'counts'=>[]], 500);
        }
    }

    if (preg_match('#^/admin/api/custom-orders/(\d+)$#', $uri, $m) && in_array($method, ['POST','PUT'], true)) {
        $allowed = ['new','reviewing','quoted','sent_to_customer','customer_approved','payment_pending','paid','converted_to_order','rejected','closed'];
        $status = trim((string)($body['status'] ?? 'new'));
        if (!in_array($status, $allowed, true)) json(['ok'=>false,'msg'=>'Invalid status'], 422);
        try {
            $existing = Database::row("SELECT payment_status,order_id,user_id FROM custom_quote_requests WHERE id=? LIMIT 1", [(int)$m[1]]);
            if (!$existing) json(['ok'=>false,'msg'=>'Custom quote not found'], 404);
            if (in_array($status, ['paid','converted_to_order'], true) && ((string)($existing['payment_status'] ?? '') !== 'paid' || (int)($existing['order_id'] ?? 0) < 1)) {
                Database::query("UPDATE custom_quote_requests SET status='payment_pending',payment_status='payment_pending',updated_at=NOW() WHERE id=?", [(int)$m[1]]);
                json(['ok'=>false,'msg'=>'Payment is still pending. The custom order remains in Payment Pending.'], 422);
            }
            $timestampSql = '';
            if ($status === 'sent_to_customer') {
                $timestampSql .= ', sent_at=COALESCE(sent_at, NOW())';
            }
            if ($status === 'customer_approved') {
                $timestampSql .= ', approved_at=COALESCE(approved_at, NOW())';
            }
            Database::query("UPDATE custom_quote_requests SET customer_name=?, phone=?, email=?, product_name=?, size_dimension=?, material_type=?, quantity=?, instructions=?, status=?, quoted_amount=?, design_fee=?, quote_note=?, payment_status=?{$timestampSql}, updated_at=NOW() WHERE id=?", [
                trim((string)($body['customer_name'] ?? '')),
                trim((string)($body['phone'] ?? '')),
                trim((string)($body['email'] ?? '')) ?: null,
                trim((string)($body['product_name'] ?? '')),
                trim((string)($body['size_dimension'] ?? '')),
                trim((string)($body['material_type'] ?? '')),
                trim((string)($body['quantity'] ?? '')),
                trim((string)($body['instructions'] ?? '')),
                $status,
                ($body['quoted_amount'] ?? '') !== '' && ($body['quoted_amount'] ?? null) !== null ? (float)$body['quoted_amount'] : null,
                max(0, (float)($body['design_fee'] ?? 0)),
                trim((string)($body['quote_note'] ?? '')),
                (string)($existing['payment_status'] ?? 'not_required'),
                (int)$m[1],
            ]);
            if (!empty($existing['user_id']) && in_array($status, ['customer_approved','payment_pending'], true)) {
                $savedQuote = Database::row("SELECT * FROM custom_quote_requests WHERE id=?", [(int)$m[1]]);
                if ($savedQuote) \Cart\Cart::addCustomQuote($savedQuote, (int)$existing['user_id']);
            }
            json(['ok'=>true]);
        } catch (\Throwable $e) {
            json(['ok'=>false,'msg'=>'Could not save custom order: ' . $e->getMessage()], 500);
        }
    }

    if (preg_match('#^/admin/api/custom-orders/(\d+)/customer-account$#', $uri, $m) && $method === 'POST') {
        \Cart\Cart::ensureCustomQuoteSchema();
        $db = Database::get();
        try {
            $db->beginTransaction();
            $quote = Database::row("SELECT * FROM custom_quote_requests WHERE id=? FOR UPDATE", [(int)$m[1]]);
            if (!$quote) { $db->rollBack(); json(['ok'=>false,'msg'=>'Custom quote not found'], 404); }
            $name = trim((string)($quote['customer_name'] ?? '')) ?: 'RCS Customer';
            $phone = trim((string)($quote['phone'] ?? ''));
            $email = strtolower(trim((string)($quote['email'] ?? '')));
            $loginPassword = preg_replace('/\D+/', '', $phone) ?: '';
            if ($email === '' || $loginPassword === '') { $db->rollBack(); json(['ok'=>false,'msg'=>'Customer email and mobile number are required to create an account.'], 422); }
            $user = Database::row("SELECT * FROM users WHERE email=? OR phone=? LIMIT 1", [$email, $phone]);
            $created = false;
            if (!$user) {
                $userId = Database::insert("INSERT INTO users (name, email, phone, company, password, marketing_consent, created_at) VALUES (?, ?, ?, '', ?, 0, NOW())", [$name, $email, $phone, password_hash($loginPassword, PASSWORD_BCRYPT, ['cost'=>10])]);
                $user = Database::row("SELECT * FROM users WHERE id=?", [(int)$userId]);
                $created = true;
            }
            if (!$user) throw new \RuntimeException('Customer account could not be loaded after creation.');
            Database::query("UPDATE custom_quote_requests SET user_id=?, customer_type='registered', email=COALESCE(NULLIF(email,''), ?), phone=COALESCE(NULLIF(phone,''), ?), updated_at=NOW() WHERE id=?", [(int)$user['id'], (string)($user['email'] ?? $email), (string)($user['phone'] ?? $phone), (int)$m[1]]);
            $quote = Database::row("SELECT * FROM custom_quote_requests WHERE id=?", [(int)$m[1]]);
            $cartResult = \Cart\Cart::addCustomQuote($quote ?: [], (int)$user['id']);
            if (!($cartResult['ok'] ?? false)) throw new \RuntimeException($cartResult['msg'] ?? 'Custom order could not be added to customer cart.');
            $db->commit();
            json(['ok'=>true,'created'=>$created,'cart_added'=>true,'user'=>['id'=>(int)$user['id'],'name'=>(string)($user['name'] ?? $name),'email'=>(string)($user['email'] ?? $email),'phone'=>(string)($user['phone'] ?? $phone)],'login_password'=>$created ? $loginPassword : null]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            json(['ok'=>false,'msg'=>'Could not create/link account: ' . $e->getMessage()], 500);
        }
    }

    if (preg_match('#^/admin/api/custom-orders/(\d+)/payment-link$#', $uri, $m) && $method === 'POST') {
        try {
            $quote = Database::row("SELECT * FROM custom_quote_requests WHERE id=? LIMIT 1", [(int)$m[1]]);
            if (!$quote) json(['ok'=>false,'msg'=>'Custom quote not found'], 404);
            if ((string)($quote['status'] ?? '') !== 'customer_approved') json(['ok'=>false,'msg'=>'Mark this quote approved before generating the payment link.'], 422);
            if (empty($quote['user_id'])) json(['ok'=>false,'msg'=>'Create or link the customer account before generating the payment link.'], 422);
            if ((float)($quote['quoted_amount'] ?? 0) <= 0) json(['ok'=>false,'msg'=>'Please save quoted amount before generating payment link.'], 422);
            $token = trim((string)($quote['quote_token'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9._~-]{16,160}$/', $token)) $token = bin2hex(random_bytes(24));
            Database::query("UPDATE custom_quote_requests SET quote_token=?, status='payment_pending', payment_status='payment_pending', payment_link_generated_at=COALESCE(payment_link_generated_at, NOW()), updated_at=NOW() WHERE id=?", [$token, (int)$m[1]]);
            $quote['quote_token'] = $token;
            $quote['status'] = 'payment_pending';
            $quote['payment_status'] = 'payment_pending';
            $cartResult = \Cart\Cart::addCustomQuote($quote, (int)$quote['user_id']);
            if (!($cartResult['ok'] ?? false)) json(['ok'=>false,'msg'=>$cartResult['msg'] ?? 'Could not add custom order to the customer cart.'], 422);
            $base = rtrim((defined('APP_URL') ? (string)APP_URL : ''), '/'); if ($base === '') { $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? ''); }
            json(['ok'=>true,'link'=>$base . '/custom-cart/' . rawurlencode($token),'token'=>$token,'status'=>'payment_pending']);
        } catch (\Throwable $e) { json(['ok'=>false,'msg'=>'Could not generate payment link: ' . $e->getMessage()], 500); }
    }

    if (preg_match('#^/admin/api/custom-orders/(\d+)/whatsapp-message$#', $uri, $m) && $method === 'POST') {
        try {
            $quote = Database::row("SELECT cqr.*,u.email AS account_email,u.phone AS account_phone FROM custom_quote_requests cqr LEFT JOIN users u ON u.id=cqr.user_id WHERE cqr.id=? LIMIT 1", [(int)$m[1]]);
            if (!$quote) json(['ok'=>false,'msg'=>'Custom quote not found'], 404); $type = (string)($body['type'] ?? 'quote'); $key = $type === 'payment' ? 'custom_quote_payment' : 'custom_quote_sent';
            if ($type === 'quote' && ((float)($quote['quoted_amount'] ?? 0) <= 0 || trim((string)($quote['quote_note'] ?? '')) === '')) json(['ok'=>false,'msg'=>'Save quoted amount and quote note before sending WhatsApp quote.'], 422);
            if ($type === 'payment' && (empty($quote['user_id']) || trim((string)($quote['quote_token'] ?? '')) === '')) json(['ok'=>false,'msg'=>'Create the account and generate payment link first.'], 422);
            $template = Database::row("SELECT body FROM whatsapp_message_templates WHERE template_key=? AND is_active=1 LIMIT 1", [$key]); $text = (string)($template['body'] ?? $whatsappTemplateDefaults[$key]['body']);
            $base = rtrim((defined('APP_URL') ? (string)APP_URL : ''), '/'); if ($base === '') { $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? ''); }
            $customCartUrl = $base.'/custom-cart/'.rawurlencode((string)$quote['quote_token']);
            $data = ['customer_name'=>(string)$quote['customer_name'],'quote_id'=>(string)$quote['request_code'],'product_name'=>(string)$quote['product_name'],'size_dimension'=>(string)$quote['size_dimension'],'material_type'=>(string)$quote['material_type'],'quantity'=>(string)$quote['quantity'],'quoted_amount'=>'₹'.number_format((float)$quote['quoted_amount'],2),'design_fee'=>'₹'.number_format((float)($quote['design_fee']??0),2),'custom_subtotal'=>'₹'.number_format((float)$quote['quoted_amount']+(float)($quote['design_fee']??0),2),'quote_note'=>(string)$quote['quote_note'],'business_name'=>(string)Database::setting('site_name','RCS Print'),'login_url'=>$base.'/login?next='.rawurlencode('/custom-cart/'.(string)$quote['quote_token']),'login_identifier'=>(string)($quote['account_email'] ?: $quote['account_phone'] ?: $quote['email']),'login_password'=>(string)preg_replace('/\D+/', '', (string)($quote['account_phone'] ?: $quote['phone'])),'payment_link'=>$customCartUrl,'custom_cart_url'=>$customCartUrl];
            $message = preg_replace_callback('/\{([a-z0-9_]+)\}/i', static fn($x) => $data[$x[1]] ?? $x[0], $text); json(['ok'=>true,'message'=>$message]);
        } catch (\Throwable $e) { json(['ok'=>false,'msg'=>'Could not prepare WhatsApp message: '.$e->getMessage()],500); }
    }

    if (preg_match('#^/admin/api/custom-orders/(\d+)/status$#', $uri, $m) && $method === 'POST') {
        $allowed = ['new','reviewing','quoted','sent_to_customer','customer_approved','payment_pending','paid','converted_to_order','rejected','closed'];
        $status = trim((string)($body['status'] ?? ''));
        if (!in_array($status, $allowed, true)) json(['ok'=>false,'msg'=>'Invalid status'], 422);
        try {
            $existing = Database::row("SELECT payment_status,order_id FROM custom_quote_requests WHERE id=? LIMIT 1", [(int)$m[1]]);
            if (!$existing) json(['ok'=>false,'msg'=>'Custom quote not found'], 404);
            if (in_array($status, ['paid','converted_to_order'], true) && ((string)($existing['payment_status'] ?? '') !== 'paid' || (int)($existing['order_id'] ?? 0) < 1)) {
                Database::query("UPDATE custom_quote_requests SET status='payment_pending',payment_status='payment_pending',updated_at=NOW() WHERE id=?", [(int)$m[1]]);
                json(['ok'=>false,'msg'=>'Payment is still pending. The custom order remains in Payment Pending.'], 422);
            }
            $timestampSql = '';
            if ($status === 'sent_to_customer') {
                $timestampSql .= ', sent_at=COALESCE(sent_at, NOW())';
            }
            if ($status === 'customer_approved') {
                $timestampSql .= ', approved_at=COALESCE(approved_at, NOW())';
            }
            Database::query("UPDATE custom_quote_requests SET status=?{$timestampSql}, updated_at=NOW() WHERE id=?", [$status, (int)$m[1]]);
            json(['ok'=>true]);
        } catch (\Throwable $e) {
            json(['ok'=>false,'msg'=>'Could not update custom order: ' . $e->getMessage()], 500);
        }
    }
    if (preg_match('#^/admin/api/custom-orders/(\d+)/attention/clear$#', $uri, $m) && $method === 'POST') {
        Database::query("UPDATE custom_quote_requests SET customer_update_pending=0,customer_update_type=NULL,customer_update_at=NULL WHERE id=?",[(int)$m[1]]);
        json(['ok'=>true]);
    }

    if ($uri === '/admin/api/business-needs' && $method === 'GET') {
        try {
            $needs = Database::rows("SELECT bn.*, COUNT(pbn.product_id) AS product_count FROM business_needs bn LEFT JOIN product_business_needs pbn ON pbn.business_need_id = bn.id GROUP BY bn.id ORDER BY bn.sort_order ASC, bn.id DESC");
            json(['ok'=>true,'needs'=>$needs]);
        } catch (\Throwable $e) {
            json(['ok'=>false,'msg'=>'Could not load business needs','needs'=>[]], 500);
        }
    }
    if ($uri === '/admin/api/business-needs' && $method === 'POST') {
        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') json(['ok'=>false,'msg'=>'Business / sector name is required'], 422);
        $slug = $businessNeedSlug((string)($body['slug'] ?? $name));
        try {
            $id = Database::insert(
                "INSERT INTO business_needs (name,slug,icon,description,product_ids,image_path,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?)",
                [$name,$slug,trim((string)($body['icon'] ?? '🏢')) ?: '🏢',trim((string)($body['description'] ?? '')),'',trim((string)($body['image_path'] ?? '')),(int)($body['sort_order'] ?? 0),(int)((int)($body['is_active'] ?? 1) > 0)]
            );
            json(['ok'=>true,'id'=>(int)$id]);
        } catch (\Throwable $e) {
            json(['ok'=>false,'msg'=>'Could not save business need. Slug may already exist.'], 500);
        }
    }
    if (preg_match('#^/admin/api/business-needs/(\d+)$#', $uri, $m) && $method === 'PUT') {
        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') json(['ok'=>false,'msg'=>'Business / sector name is required'], 422);
        $slug = $businessNeedSlug((string)($body['slug'] ?? $name));
        try {
            Database::query(
                "UPDATE business_needs SET name=?, slug=?, icon=?, description=?, image_path=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?",
                [$name,$slug,trim((string)($body['icon'] ?? '🏢')) ?: '🏢',trim((string)($body['description'] ?? '')),trim((string)($body['image_path'] ?? '')),(int)($body['sort_order'] ?? 0),(int)((int)($body['is_active'] ?? 1) > 0),(int)$m[1]]
            );
            json(['ok'=>true]);
        } catch (\Throwable $e) {
            json(['ok'=>false,'msg'=>'Could not update business need. Slug may already exist.'], 500);
        }
    }

    if (preg_match('#^/admin/api/business-needs/(\d+)/image-upload$#', $uri, $m) && $method === 'POST') {
        $id = (int)$m[1];
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) json(['ok'=>false,'msg'=>'Image file required'], 422);
        $file = $_FILES['image'];
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) json(['ok'=>false,'msg'=>'Image must be 5MB or less'], 422);
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) json(['ok'=>false,'msg'=>'Only JPG, PNG or WEBP images are allowed'], 422);
        $dir = PUBLIC_PATH . '/uploads/business-needs/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = 'business-' . $id . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
        $publicPath = '/uploads/business-needs/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) json(['ok'=>false,'msg'=>'Could not upload image'], 500);
        Database::query("UPDATE business_needs SET image_path=?, updated_at=NOW() WHERE id=?", [$publicPath, $id]);
        json(['ok'=>true,'image_path'=>$publicPath]);
    }

    if (preg_match('#^/admin/api/business-needs/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        try { Database::query("DELETE FROM product_business_needs WHERE business_need_id=?", [(int)$m[1]]); Database::query("DELETE FROM business_needs WHERE id=?", [(int)$m[1]]); json(['ok'=>true]); }
        catch (\Throwable) { json(['ok'=>false,'msg'=>'Could not delete business need'], 500); }
    }

    if ($uri === '/admin/api/blogs' && $method === 'GET') {
        try {
            $rows = Database::rows("SELECT * FROM blogs ORDER BY sort_order ASC, published_at DESC, id DESC");
            json(['ok'=>true,'blogs'=>$rows]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'blogs table missing. Run SQL migration first.','blogs'=>[]], 500);
        }
    }
    if (preg_match('#^/admin/api/blogs/(\d+)$#', $uri, $m) && $method === 'GET') {
        try {
            $blog = Database::row("SELECT * FROM blogs WHERE id=?", [(int)$m[1]]);
            if (!$blog) {
                json(['ok'=>false,'msg'=>'Blog not found'], 404);
            }
            json(['ok'=>true,'blog'=>$blog]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'blogs table missing. Run SQL migration first.'], 500);
        }
    }
    if ($uri === '/admin/api/blogs' && $method === 'POST') {
        $title = trim((string)($body['title'] ?? ''));
        $content = $sanitizeBlogContent((string)($body['content'] ?? ''));
        if ($title === '') json(['ok'=>false,'msg'=>'Blog title is required'], 400);
        if ($content === '') json(['ok'=>false,'msg'=>'Blog content is required'], 400);
        $badgeTheme = strtolower(trim((string)($body['badge_theme'] ?? 'purple')));
        if (!in_array($badgeTheme, ['purple','orange','green'], true)) $badgeTheme = 'purple';
        try {
            $slug = $uniqueBlogSlug(trim((string)($body['slug'] ?? '')) ?: $title);
            $id = Database::insert(
                "INSERT INTO blogs (title,slug,excerpt,content,featured_image,image_alt,category,badge_theme,author_name,meta_title,meta_description,published_at,sort_order,is_featured,is_active,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                [
                    $title,
                    $slug,
                    trim((string)($body['excerpt'] ?? '')),
                    $content,
                    trim((string)($body['featured_image'] ?? '')),
                    trim((string)($body['image_alt'] ?? '')),
                    trim((string)($body['category'] ?? 'Print Tips')) ?: 'Print Tips',
                    $badgeTheme,
                    trim((string)($body['author_name'] ?? 'RCS Print Team')) ?: 'RCS Print Team',
                    trim((string)($body['meta_title'] ?? '')),
                    trim((string)($body['meta_description'] ?? '')),
                    trim((string)($body['published_at'] ?? '')) ?: date('Y-m-d H:i:s'),
                    (int)($body['sort_order'] ?? 0),
                    (int)($body['is_featured'] ?? 1),
                    (int)($body['is_active'] ?? 1),
                ]
            );
            json(['ok'=>true,'id'=>$id,'slug'=>$slug]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not create blog. Run migration first.'], 500);
        }
    }
    if (preg_match('#^/admin/api/blogs/(\d+)$#', $uri, $m) && $method === 'PUT') {
        $id = (int)$m[1];
        $title = trim((string)($body['title'] ?? ''));
        $content = $sanitizeBlogContent((string)($body['content'] ?? ''));
        if ($title === '') json(['ok'=>false,'msg'=>'Blog title is required'], 400);
        if ($content === '') json(['ok'=>false,'msg'=>'Blog content is required'], 400);
        $badgeTheme = strtolower(trim((string)($body['badge_theme'] ?? 'purple')));
        if (!in_array($badgeTheme, ['purple','orange','green'], true)) $badgeTheme = 'purple';
        try {
            $slug = $uniqueBlogSlug(trim((string)($body['slug'] ?? '')) ?: $title, $id);
            Database::query(
                "UPDATE blogs
                 SET title=?, slug=?, excerpt=?, content=?, featured_image=?, image_alt=?, category=?, badge_theme=?, author_name=?, meta_title=?, meta_description=?, published_at=?, sort_order=?, is_featured=?, is_active=?, updated_at=NOW()
                 WHERE id=?",
                [
                    $title,
                    $slug,
                    trim((string)($body['excerpt'] ?? '')),
                    $content,
                    trim((string)($body['featured_image'] ?? '')),
                    trim((string)($body['image_alt'] ?? '')),
                    trim((string)($body['category'] ?? 'Print Tips')) ?: 'Print Tips',
                    $badgeTheme,
                    trim((string)($body['author_name'] ?? 'RCS Print Team')) ?: 'RCS Print Team',
                    trim((string)($body['meta_title'] ?? '')),
                    trim((string)($body['meta_description'] ?? '')),
                    trim((string)($body['published_at'] ?? '')) ?: date('Y-m-d H:i:s'),
                    (int)($body['sort_order'] ?? 0),
                    (int)($body['is_featured'] ?? 1),
                    (int)($body['is_active'] ?? 1),
                    $id,
                ]
            );
            json(['ok'=>true,'slug'=>$slug]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not update blog'], 500);
        }
    }
    if (preg_match('#^/admin/api/blogs/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        try {
            Database::query("DELETE FROM blogs WHERE id=?", [(int)$m[1]]);
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not delete blog'], 500);
        }
    }
    if ($uri === '/admin/api/blogs/upload' && $method === 'POST') {
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            json(['ok'=>false,'msg'=>'Image file is required'], 400);
        }
        $file = $_FILES['image'];
        if ((int)$file['size'] <= 0) json(['ok'=>false,'msg'=>'Empty upload'], 400);
        if ((int)$file['size'] > 30 * 1024 * 1024) json(['ok'=>false,'msg'=>'Max file size is 30MB'], 400);
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','mp4','webm'], true)) json(['ok'=>false,'msg'=>'Only jpg, png, webp, mp4, webm allowed'], 400);
        $mime = mime_content_type($file['tmp_name']) ?: '';
        $isVideo = in_array($mime, ['video/mp4','video/webm'], true);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','video/mp4','video/webm'], true)) json(['ok'=>false,'msg'=>'Invalid media type'], 400);
        $dir = PUBLIC_PATH . '/uploads/blogs/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = 'blog_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $dir . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) json(['ok'=>false,'msg'=>'Upload failed'], 500);
        json(['ok'=>true,'path'=>'/uploads/blogs/' . $name,'type'=>$isVideo ? 'video' : 'image']);
    }


    if ($uri === '/admin/api/portfolio-categories' && $method === 'GET') {
        try {
            try {
                foreach (\Catalog\ProductCatalog::categories() as $productCat) {
                    $name = trim((string)($productCat['name'] ?? ''));
                    if ($name === '') continue;
                    $slug = $slugify(trim((string)($productCat['slug'] ?? '')) ?: $name);
                    Database::query(
                        "INSERT INTO portfolio_categories (name, slug, icon, sort_order, is_active, created_at, updated_at)
                         VALUES (?,?,?,?,1,NOW(),NOW())
                         ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), updated_at=NOW()",
                        [$name, $slug, trim((string)($productCat['icon'] ?? 'fa-folder')) ?: 'fa-folder', (int)($productCat['sort_order'] ?? 0)]
                    );
                }
            } catch (\Throwable) {}
            $rows = Database::rows("SELECT * FROM portfolio_categories ORDER BY sort_order ASC, name ASC");
            json(['ok'=>true,'categories'=>$rows]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Portfolio category table unavailable','categories'=>[]], 500);
        }
    }
    if ($uri === '/admin/api/portfolio-categories' && $method === 'POST') {
        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') json(['ok'=>false,'msg'=>'Category name is required'], 400);
        $slug = $slugify(trim((string)($body['slug'] ?? '')) ?: $name);
        try {
            Database::insert(
                "INSERT INTO portfolio_categories (name, slug, icon, description, hero_image, meta_title, meta_description, sort_order, is_active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                [$name, $slug, trim((string)($body['icon'] ?? 'fa-border-all')) ?: 'fa-border-all', trim((string)($body['description'] ?? '')), trim((string)($body['hero_image'] ?? '')), trim((string)($body['meta_title'] ?? '')), trim((string)($body['meta_description'] ?? '')), (int)($body['sort_order'] ?? 0), (int)($body['is_active'] ?? 1)]
            );
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not save category. Slug may already exist.'], 500);
        }
    }
    if (preg_match('#^/admin/api/portfolio-categories/(\d+)$#', $uri, $m) && $method === 'PUT') {
        $id = (int)$m[1];
        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') json(['ok'=>false,'msg'=>'Category name is required'], 400);
        $slug = $slugify(trim((string)($body['slug'] ?? '')) ?: $name);
        try {
            Database::query(
                "UPDATE portfolio_categories SET name=?, slug=?, icon=?, description=?, hero_image=?, meta_title=?, meta_description=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?",
                [$name, $slug, trim((string)($body['icon'] ?? 'fa-border-all')) ?: 'fa-border-all', trim((string)($body['description'] ?? '')), trim((string)($body['hero_image'] ?? '')), trim((string)($body['meta_title'] ?? '')), trim((string)($body['meta_description'] ?? '')), (int)($body['sort_order'] ?? 0), (int)($body['is_active'] ?? 1), $id]
            );
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not update category'], 500);
        }
    }
    if (preg_match('#^/admin/api/portfolio-categories/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        try {
            Database::query("DELETE FROM portfolio_categories WHERE id=?", [(int)$m[1]]);
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not delete category. Remove or reassign portfolio items first.'], 500);
        }
    }

    if ($uri === '/admin/api/portfolio' && $method === 'GET') {
        try {
            $rows = Database::rows(
                "SELECT pi.*, pc.name AS category_name, pc.slug AS category_slug, pc.icon AS category_icon
                 FROM portfolio_items pi
                 LEFT JOIN portfolio_categories pc ON pc.id = pi.category_id
                 ORDER BY pi.sort_order ASC, pi.is_featured DESC, pi.created_at DESC, pi.id DESC"
            );
            json(['ok'=>true,'items'=>$rows]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Portfolio table unavailable','items'=>[]], 500);
        }
    }
    if (preg_match('#^/admin/api/portfolio/(\d+)$#', $uri, $m) && $method === 'GET') {
        $item = Database::row("SELECT * FROM portfolio_items WHERE id=?", [(int)$m[1]]);
        if (!$item) json(['ok'=>false,'msg'=>'Portfolio item not found'], 404);
        json(['ok'=>true,'item'=>$item]);
    }

    if ($uri === '/admin/api/portfolio' && $method === 'POST') {
        $title = trim((string)($body['title'] ?? ''));
        if ($title === '') json(['ok'=>false,'msg'=>'Portfolio title is required'], 400);
        try {
            $slug = $uniquePortfolioSlug(trim((string)($body['slug'] ?? '')) ?: $title);
            $id = Database::insert(
                "INSERT INTO portfolio_items (category_id,title,slug,short_description,description,main_image,image_alt,client_name,project_type,project_date,tags,sort_order,is_featured,is_active,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                [
                    (int)($body['category_id'] ?? 0) ?: null,
                    $title,
                    $slug,
                    trim((string)($body['short_description'] ?? '')),
                    trim((string)($body['description'] ?? '')),
                    trim((string)($body['main_image'] ?? '')),
                    trim((string)($body['image_alt'] ?? '')),
                    trim((string)($body['client_name'] ?? '')),
                    trim((string)($body['project_type'] ?? '')),
                    trim((string)($body['project_date'] ?? '')) ?: null,
                    trim((string)($body['tags'] ?? '')),
                    (int)($body['sort_order'] ?? 0),
                    (int)($body['is_featured'] ?? 0),
                    (int)($body['is_active'] ?? 1),
                ]
            );
            json(['ok'=>true,'id'=>$id,'slug'=>$slug]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not create portfolio item'], 500);
        }
    }
    if (preg_match('#^/admin/api/portfolio/(\d+)$#', $uri, $m) && $method === 'PUT') {
        $id = (int)$m[1];
        $title = trim((string)($body['title'] ?? ''));
        if ($title === '') json(['ok'=>false,'msg'=>'Portfolio title is required'], 400);
        try {
            $slug = $uniquePortfolioSlug(trim((string)($body['slug'] ?? '')) ?: $title, $id);
            Database::query(
                "UPDATE portfolio_items SET category_id=?, title=?, slug=?, short_description=?, description=?, main_image=?, image_alt=?, client_name=?, project_type=?, project_date=?, tags=?, sort_order=?, is_featured=?, is_active=?, updated_at=NOW() WHERE id=?",
                [
                    (int)($body['category_id'] ?? 0) ?: null,
                    $title,
                    $slug,
                    trim((string)($body['short_description'] ?? '')),
                    trim((string)($body['description'] ?? '')),
                    trim((string)($body['main_image'] ?? '')),
                    trim((string)($body['image_alt'] ?? '')),
                    trim((string)($body['client_name'] ?? '')),
                    trim((string)($body['project_type'] ?? '')),
                    trim((string)($body['project_date'] ?? '')) ?: null,
                    trim((string)($body['tags'] ?? '')),
                    (int)($body['sort_order'] ?? 0),
                    (int)($body['is_featured'] ?? 0),
                    (int)($body['is_active'] ?? 1),
                    $id,
                ]
            );
            json(['ok'=>true,'slug'=>$slug]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not update portfolio item'], 500);
        }
    }
    if (preg_match('#^/admin/api/portfolio/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        try {
            Database::query("DELETE FROM portfolio_items WHERE id=?", [(int)$m[1]]);
            json(['ok'=>true]);
        } catch (\Throwable) {
            json(['ok'=>false,'msg'=>'Could not delete portfolio item'], 500);
        }
    }
    if ($uri === '/admin/api/portfolio/upload' && $method === 'POST') {
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            json(['ok'=>false,'msg'=>'Image file is required'], 400);
        }
        $file = $_FILES['image'];
        if ((int)$file['size'] <= 0) json(['ok'=>false,'msg'=>'Empty upload'], 400);
        if ((int)$file['size'] > 8 * 1024 * 1024) json(['ok'=>false,'msg'=>'Max file size is 8MB'], 400);
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) json(['ok'=>false,'msg'=>'Only jpg, png, webp allowed'], 400);
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) json(['ok'=>false,'msg'=>'Invalid image type'], 400);
        $dir = PUBLIC_PATH . '/uploads/portfolio/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = 'portfolio_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $dir . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) json(['ok'=>false,'msg'=>'Upload failed'], 500);
        json(['ok'=>true,'path'=>'/uploads/portfolio/' . $name]);
    }


    if ($uri === '/admin/api/theme' && $method === 'GET') {
        try {
            $theme = \Theme\SiteTheme::load();
            json(['ok'=>true,'theme'=>$theme,'defaults'=>\Theme\SiteTheme::defaults(),'element_styles'=>\Theme\SiteTheme::loadElementStyles(),'element_schema'=>\Theme\SiteTheme::elementSchema(),'css'=>\Theme\SiteTheme::css($theme)]);
        } catch (\Throwable $e) {
            error_log('Theme load failed: ' . $e->getMessage());
            json(['ok'=>false,'msg'=>'Theme load failed. Check database settings/theme_element_styles tables.'], 500);
        }
    }
    if ($uri === '/admin/api/theme' && $method === 'POST') {
        try {
            $saved = \Theme\SiteTheme::save(is_array($body) ? $body : []);
            $elementStyles = \Theme\SiteTheme::saveElementStyles(is_array($body['element_styles'] ?? null) ? $body['element_styles'] : \Theme\SiteTheme::loadElementStyles());
            $theme = array_merge(\Theme\SiteTheme::load(), $saved);
            $css = \Theme\SiteTheme::css($theme, $elementStyles);
            \Orders\AdminAudit::log('theme_updated','Website design theme updated');
            json(['ok'=>true,'theme'=>$theme,'element_styles'=>$elementStyles,'css'=>$css,'meta'=>array_merge(\Theme\SiteTheme::lastElementSaveMeta(), ['css_length'=>strlen($css), 'element_style_count'=>count($elementStyles)])]);
        } catch (\Throwable $e) {
            error_log('Theme save failed: ' . $e->getMessage());
            json(['ok'=>false,'msg'=>'Theme save failed. Run database/sql/add_theme_element_styles.sql and try again.'], 500);
        }
    }
    if ($uri === '/admin/api/theme/preview' && $method === 'POST') {
        try {
            $theme = array_merge(\Theme\SiteTheme::defaults(), is_array($body) ? $body : []);
            $theme = \Theme\SiteTheme::sanitizeValues($theme);
            $rawElementStyles = is_array($body['element_styles'] ?? null) ? $body['element_styles'] : \Theme\SiteTheme::loadElementStyles();
            $elementStyles = \Theme\SiteTheme::sanitizeElementStyles($rawElementStyles);
            $css = \Theme\SiteTheme::css($theme, $elementStyles);
            json(['ok'=>true,'theme'=>$theme,'element_styles'=>$elementStyles,'css'=>$css,'meta'=>['css_length'=>strlen($css),'element_style_count'=>count($elementStyles),'dropped_element_style_count'=>max(0, count($rawElementStyles) - count($elementStyles))]]);
        } catch (\Throwable $e) {
            error_log('Theme preview failed: ' . $e->getMessage());
            json(['ok'=>false,'msg'=>'Theme preview failed. Check generated style values.'], 500);
        }
    }
    if ($uri === '/admin/api/theme/reset' && $method === 'POST') {
        try {
            $theme = \Theme\SiteTheme::reset();
            $elementStyles = \Theme\SiteTheme::resetElementStyles();
            \Orders\AdminAudit::log('theme_reset','Website design theme reset to defaults');
            $css = \Theme\SiteTheme::css($theme, $elementStyles);
            json(['ok'=>true,'theme'=>$theme,'element_styles'=>$elementStyles,'css'=>$css,'meta'=>['css_length'=>strlen($css),'element_style_count'=>count($elementStyles)]]);
        } catch (\Throwable $e) {
            error_log('Theme reset failed: ' . $e->getMessage());
            json(['ok'=>false,'msg'=>'Theme reset failed. Check database permissions.'], 500);
        }
    }

    if ($uri === '/admin/api/settings' && $method === 'GET') {
        $rows = Database::rows("SELECT `key`,value FROM settings");
        json(['ok'=>true,'settings'=>array_column($rows,'value','key')]);
    }
    if ($uri === '/admin/api/settings' && $method === 'POST') {
        foreach ($body as $k=>$v) if ($k) Database::setSetting($k,$v);
        \Orders\AdminAudit::log('settings_updated','Settings saved');
        json(['ok'=>true]);
    }

    if ($uri === '/admin/api/leads' && $method === 'GET') {
        try {
            $data = \Leads\ContactLeadManager::adminList();
            json(['ok' => empty($data['msg'])] + $data, empty($data['msg']) ? 200 : 500);
        } catch (\Throwable $e) {
            error_log('Admin leads API failed: ' . $e->getMessage());
            json(['ok' => false, 'leads' => [], 'summary' => [], 'msg' => 'Unable to load leads.'], 500);
        }
    }
    if (preg_match('#^/admin/api/leads/(\d+)$#', $uri, $m) && $method === 'POST') {
        $result = \Leads\ContactLeadManager::update((int)$m[1], $body);
        json($result, ($result['ok'] ?? false) ? 200 : 422);
    }
    if (preg_match('#^/admin/api/leads/(\d+)/read$#', $uri, $m) && $method === 'POST') {
        $result = \Leads\ContactLeadManager::markRead((int)$m[1]);
        json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    if ($uri === '/admin/api/customers' && $method === 'GET') {
        \Auth\Auth::ensureCustomerCodeSchema();
        $customers = Database::rows(
            "SELECT u.id, u.customer_code, u.name, u.email, u.phone, u.company, u.created_at, u.is_active,
                    COUNT(o.id) AS order_count,
                    COALESCE(SUM(o.total_amount),0) AS total_spent,
                    COALESCE(AVG(o.total_amount),0) AS avg_order_value,
                    MAX(o.created_at) AS last_order_at,
                    SUM(CASE WHEN o.status IN ('new_order','received','design_approved','printing','other_process','processing','ready','whatsapp_pending') THEN 1 ELSE 0 END) AS active_orders,
                    SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) AS delivered_orders,
                    SUM(CASE WHEN o.status='cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
                    (SELECT oi.product_name
                       FROM order_items oi
                       INNER JOIN orders lo ON lo.id = oi.order_id
                      WHERE lo.user_id = u.id
                      ORDER BY lo.created_at DESC, oi.id ASC
                      LIMIT 1) AS last_product
             FROM users u
             LEFT JOIN orders o ON o.user_id = u.id
             WHERE COALESCE(u.is_active, 1) = 1
             GROUP BY u.id
             ORDER BY total_spent DESC, last_order_at DESC"
        );
        $summary = [
            'total_customers' => count($customers),
            'repeat_customers' => 0,
            'high_value_customers' => 0,
            'inactive_customers' => 0,
            'total_revenue' => 0,
        ];
        $now = time();
        foreach ($customers as &$customer) {
            $orders = (int)($customer['order_count'] ?? 0);
            $spent = (float)($customer['total_spent'] ?? 0);
            $lastOrderAt = (string)($customer['last_order_at'] ?? '');
            $daysSince = $lastOrderAt !== '' ? (int)floor(max(0, $now - app_timestamp($lastOrderAt)) / 86400) : null;
            $segment = 'new';
            if ($orders === 0) $segment = 'no_orders';
            elseif ($daysSince !== null && $daysSince >= 60) $segment = 'inactive';
            elseif ($spent >= 25000) $segment = 'high_value';
            elseif ($orders >= 2) $segment = 'repeat';
            $customer['segment'] = $segment;
            $customer['days_since_last_order'] = $daysSince;
            $customer['recent_orders'] = Database::rows(
                "SELECT order_id, total_amount, status, payment_status, created_at
                   FROM orders
                  WHERE user_id = ?
                  ORDER BY created_at DESC
                  LIMIT 4",
                [(int)$customer['id']]
            );
            if ($orders >= 2) $summary['repeat_customers']++;
            if ($spent >= 25000) $summary['high_value_customers']++;
            if ($segment === 'inactive') $summary['inactive_customers']++;
            $summary['total_revenue'] += $spent;
        }
        unset($customer);
        json(['ok'=>true,'customers'=>$customers,'summary'=>$summary]);
    }
    if (preg_match('#^/admin/api/customers/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $customerId = (int)$m[1];
        $customer = Database::row("SELECT id, name FROM users WHERE id = ? LIMIT 1", [$customerId]);
        if (!$customer) json(['ok' => false, 'msg' => 'Customer not found'], 404);
        try {
            Database::query("UPDATE users SET is_active = 0 WHERE id = ?", [$customerId]);
            \Orders\AdminAudit::log('customer_deactivated', "Customer #{$customerId}: " . ($customer['name'] ?? ''));
            json(['ok' => true, 'msg' => 'Customer deleted/deactivated.']);
        } catch (\Throwable) {
            json(['ok' => false, 'msg' => 'Could not delete customer.'], 500);
        }
    }
    if ($uri === '/admin/api/approvals' && $method === 'GET') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        json(['ok'=>true,'items'=>\Approvals\ContentApprovalManager::listPending(),'can_approve'=>true]);
    }
    if (preg_match('#^/admin/api/approvals/([a-z_]+)/(\d+)/(approve|reject)$#', $uri, $m) && $method === 'POST') {
        $result = \Approvals\ContentApprovalManager::decide((string)$m[1], (int)$m[2], (string)$m[3], trim((string)($body['note'] ?? '')));
        json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    if ($uri === '/admin/api/admin-users' && $method === 'GET') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        $hasMobile = $hasAdminUsersMobile();
        $mobileSelect = $hasMobile ? "mobile" : "'' AS mobile";
        $admins = Database::rows(
            "SELECT id, name, email, role, is_active, created_at, last_login, $mobileSelect
             FROM admin_users
             ORDER BY created_at DESC"
        );
        json(['ok' => true, 'admins' => $admins, 'has_mobile_column' => $hasMobile]);
    }
    if ($uri === '/admin/api/admin-users' && $method === 'POST') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        $name = trim((string)($body['name'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $mobile = trim((string)($body['mobile'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $role = trim((string)($body['role'] ?? 'admin')) ?: 'admin';
        $role = in_array($role, ['admin','super'], true) ? $role : 'admin';

        if ($name === '' || $email === '' || $mobile === '' || $password === '') {
            json(['ok'=>false,'msg'=>'Name, email, mobile and password are required.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json(['ok'=>false,'msg'=>'Please enter a valid email address.'], 422);
        }
        if (!preg_match('/^[0-9]{10,15}$/', preg_replace('/\D+/', '', $mobile))) {
            json(['ok'=>false,'msg'=>'Please enter a valid mobile number (10-15 digits).'], 422);
        }
        if (strlen($password) < 6) {
            json(['ok'=>false,'msg'=>'Password must be at least 6 characters.'], 422);
        }

        if (Database::row("SELECT id FROM admin_users WHERE email = ? LIMIT 1", [$email])) {
            json(['ok'=>false,'msg'=>'Email is already used by another admin.'], 409);
        }

        $hasMobile = $hasAdminUsersMobile();
        if ($hasMobile && Database::row("SELECT id FROM admin_users WHERE mobile = ? LIMIT 1", [$mobile])) {
            json(['ok'=>false,'msg'=>'Mobile number is already used by another admin.'], 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        if ($hasMobile) {
            $id = Database::insert(
                "INSERT INTO admin_users (name, email, mobile, password, role, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, NOW())",
                [$name, $email, $mobile, $hash, $role]
            );
        } else {
            $id = Database::insert(
                "INSERT INTO admin_users (name, email, password, role, is_active, created_at)
                 VALUES (?, ?, ?, ?, 1, NOW())",
                [$name, $email, $hash, $role]
            );
        }
        \Orders\AdminAudit::log('admin_user_created', "Admin user #{$id} created ({$email})");
        json(['ok' => true, 'id' => $id]);
    }
    if (preg_match('#^/admin/api/admin-users/(\d+)/password$#', $uri, $m) && $method === 'POST') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        $adminId = (int)$m[1];
        $newPassword = (string)($body['new_password'] ?? '');
        if (strlen($newPassword) < 6) {
            json(['ok' => false, 'msg' => 'Password must be at least 6 characters.'], 422);
        }
        $target = Database::row("SELECT id, email FROM admin_users WHERE id = ? LIMIT 1", [$adminId]);
        if (!$target) json(['ok' => false, 'msg' => 'Admin user not found.'], 404);
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
        Database::query("UPDATE admin_users SET password = ? WHERE id = ?", [$hash, $adminId]);
        \Orders\AdminAudit::log('admin_user_password_changed', "Password changed for admin #{$adminId} ({$target['email']})");
        json(['ok' => true]);
    }
    if (preg_match('#^/admin/api/admin-users/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        \Approvals\ContentApprovalManager::requireSuperAdmin();
        $targetId = (int)$m[1];
        $current = \Auth\Auth::admin();
        $currentId = (int)($current['id'] ?? 0);
        if ($targetId === $currentId) {
            json(['ok' => false, 'msg' => 'You cannot remove your own admin account.'], 422);
        }

        $target = Database::row("SELECT id, email, is_active FROM admin_users WHERE id = ? LIMIT 1", [$targetId]);
        if (!$target) json(['ok' => false, 'msg' => 'Admin user not found.'], 404);
        if ((int)$target['is_active'] !== 1) json(['ok' => false, 'msg' => 'Admin is already inactive.'], 422);

        $activeCount = (int)(Database::row("SELECT COUNT(*) AS c FROM admin_users WHERE is_active = 1")['c'] ?? 0);
        if ($activeCount <= 1) {
            json(['ok' => false, 'msg' => 'At least one active admin is required.'], 422);
        }

        Database::query("UPDATE admin_users SET is_active = 0 WHERE id = ?", [$targetId]);
        \Orders\AdminAudit::log('admin_user_removed', "Admin #{$targetId} deactivated ({$target['email']})");
        json(['ok' => true]);
    }
    if ($uri === '/admin/api/audit-logs' && $method === 'GET') {
        json(['ok'=>true,'logs'=>Database::rows("SELECT * FROM admin_audit_logs ORDER BY created_at DESC LIMIT 200")]);
    }
    if (preg_match('#^/admin/api/artwork/(\d+)$#', $uri, $m) && $method === 'GET') {
        $file = Database::row("SELECT * FROM artwork_files WHERE id=?",[$m[1]]);
        json($file ? ['ok'=>true,'file'=>$file] : ['ok'=>false,'msg'=>'Not found'],404);
    }
    if (preg_match('#^/admin/api/design-approvals/(\d+)$#', $uri, $m) && $method === 'POST') {
        $status = trim((string)($body['status'] ?? 'pending_review'));
        $note = trim((string)($body['admin_note'] ?? ''));
        $ok = \Orders\OrderManager::updateDesignApproval((int)$m[1], $status, $note);
        json(['ok' => $ok]);
    }
    if (preg_match('#^/admin/api/design-approvals/(\d+)/proof$#', $uri, $m) && $method === 'POST') {
        $approval = Database::row("SELECT * FROM order_design_approvals WHERE id = ?", [(int)$m[1]]);
        if (!$approval) json(['ok' => false, 'msg' => 'Design approval not found'], 404);
        if (empty($_FILES['proof'])) json(['ok' => false, 'msg' => 'No proof file uploaded'], 400);

        $file = $_FILES['proof'];
        $maxMb = (int)Database::setting('upload_max_mb', env('UPLOAD_MAX_SIZE_MB', '50'));
        if ($file['size'] > ($maxMb * 1024 * 1024)) json(['ok' => false, 'msg' => "File too large. Max {$maxMb}MB."], 400);
        $allowed = explode(',', Database::setting('upload_allowed_ext', 'pdf,ai,eps,png,jpg,jpeg,psd,cdr,svg,tif,tiff,zip'));
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) json(['ok' => false, 'msg' => "File type .{$ext} not allowed."], 400);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $dir = UPLOAD_PATH . '/artwork/proofs/' . date('Y/m/');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = uniqid('proof_', true) . '.' . $ext;
        $filepath = $dir . $filename;
        $publicPath = '/uploads/artwork/proofs/' . date('Y/m/') . $filename;
        if (!move_uploaded_file($file['tmp_name'], $filepath)) json(['ok' => false, 'msg' => 'Upload failed'], 500);

        $adminId = (int)($admin['id'] ?? 0);
        $fileId = Database::insert(
            "INSERT INTO artwork_files (uploaded_by, order_item_id, filename, original_name, file_path, mime_type, file_size, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$adminId ?: 0, (int)$approval['order_item_id'], $filename, $file['name'], $publicPath, $mime, $file['size']]
        );
        \Orders\OrderManager::updateDesignApproval((int)$m[1], 'proof_uploaded', trim((string)($_POST['admin_note'] ?? '')), (int)$fileId);
        json(['ok' => true, 'file_id' => (int)$fileId]);
    }
    if ($uri === '/admin/api/sheets/retry' && $method === 'POST') {
        $failed = Database::rows("SELECT * FROM sheets_sync_log WHERE resolved=0 LIMIT 20");
        foreach ($failed as $row) Database::query("UPDATE sheets_sync_log SET resolved=1 WHERE id=?",[$row['id']]);
        json(['ok'=>true,'retried'=>count($failed)]);
    }

    json(['ok'=>false,'msg'=>'Admin API not found'],404);
}

if ($uri === '/admin/settings/save' && $method === 'POST') {
    foreach ($_POST as $k => $v) {
        if ($k !== '_token' && $k !== 'new_admin_password') Database::setSetting($k, trim((string)$v));
    }

    $newPass = trim((string)($_POST['new_admin_password'] ?? ''));
    if ($newPass !== '') {
        if (strlen($newPass) < 6) {
            redirect('/admin/settings?saved=0&err=password_min_6');
        }
        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 10]);
        $admin = \Auth\Auth::admin();
        if ($admin && !empty($admin['id'])) {
            Database::query("UPDATE admin_users SET password=? WHERE id=?", [$hash, $admin['id']]);
        }
    }

    \Orders\AdminAudit::log('settings_updated', 'Settings saved via form');
    redirect('/admin/settings?saved=1');
}

if ($uri === '/admin/export/orders') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"');
    $orders = Database::rows("SELECT o.order_id,o.created_at,o.customer_name,o.customer_phone,o.customer_email,o.subtotal,o.discount_amount,o.gst_amount,o.total_amount,o.payment_status,o.status,o.coupon_code,o.payment_id FROM orders o ORDER BY o.created_at DESC");
    echo implode(',', ['Order ID','Date','Customer','Phone','Email','Subtotal','Discount','GST','Total','Payment','Status','Coupon','Payment ID']) . "\n";
    foreach ($orders as $row) echo implode(',', array_map(fn($v) => '"' . str_replace('"','""',$v??'') . '"', $row)) . "\n";
    exit;
}

if ($uri === '/admin/export/custom-orders') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="custom-orders-' . date('Y-m-d') . '.csv"');
    $quotes = Database::rows("SELECT request_code,created_at,customer_name,phone,email,product_name,size_dimension,material_type,quantity,instructions,quoted_amount,currency,quote_note,status,payment_status FROM custom_quote_requests ORDER BY created_at DESC, id DESC");
    echo implode(',', ['Quote ID','Date','Customer','Phone','Email','Product','Size / Dimension','Material','Quantity','Customer Instructions','Quoted Amount','Currency','Quote Note','Status','Payment Status']) . "\n";
    foreach ($quotes as $row) echo implode(',', array_map(fn($v) => '"' . str_replace('"','""',$v??'') . '"', $row)) . "\n";
    exit;
}

if (preg_match('#^/admin/orders/(\d+)/invoice$#', $uri, $m) && $method === 'POST') {
    $token = (string)($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        redirect('/admin/orders?error=' . urlencode('Security token expired. Please refresh and try again.'));
    }

    \Orders\OrderManager::ensureInvoiceSchema();
    $order = \Orders\OrderManager::getOrder((int)$m[1]);
    if (!$order) {
        http_response_code(404);
        exit('Order not found');
    }
    if (empty($_FILES['invoice_pdf']) || !is_uploaded_file($_FILES['invoice_pdf']['tmp_name'])) {
        redirect('/admin/orders?error=' . urlencode('Please choose an invoice PDF to upload.'));
    }

    $file = $_FILES['invoice_pdf'];
    $maxSize = 10 * 1024 * 1024;
    $originalName = (string)($file['name'] ?? 'invoice.pdf');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = (string)(mime_content_type($file['tmp_name']) ?: '');
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxSize || $ext !== 'pdf' || !in_array($mime, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true)) {
        redirect('/admin/orders?error=' . urlencode('Only PDF invoices up to 10MB are allowed.'));
    }

    $dir = UPLOAD_PATH . '/invoices/order-' . (int)$order['id'] . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $filename = 'invoice-' . date('Ymd-His') . '-' . ($safeBase ?: 'order-' . (int)$order['id']) . '.pdf';
    $target = $dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        redirect('/admin/orders?error=' . urlencode('Invoice upload failed. Please try again.'));
    }
    $relativePath = '/uploads/invoices/order-' . (int)$order['id'] . '/' . $filename;

    $oldPath = trim((string)($order['invoice_file_path'] ?? ''));
    if ($oldPath !== '' && str_starts_with($oldPath, '/uploads/invoices/') && is_file(PUBLIC_PATH . $oldPath)) {
        $trashDir = UPLOAD_PATH . '/.trash/invoices/' . date('Ymd-His') . '/';
        if (!is_dir($trashDir)) @mkdir($trashDir, 0755, true);
        @rename(PUBLIC_PATH . $oldPath, $trashDir . basename($oldPath));
    }

    $admin = \Auth\Auth::admin();
    \Orders\OrderManager::saveUploadedInvoice((int)$order['id'], $relativePath, $originalName, !empty($admin['id']) ? (int)$admin['id'] : null);
    \Orders\OrderManager::markAdminUpdate((int)$order['id'], 'invoice_uploaded');
    \Orders\AdminAudit::log('invoice_uploaded', 'Invoice uploaded for order ' . (string)$order['order_id']);
    redirect('/admin/orders?success=' . urlencode('Invoice PDF uploaded for order #' . (string)$order['order_id']) . '#ord-' . (int)$order['id']);
}

if (preg_match('#^/admin/invoice/(.+)$#', $uri, $m)) {
    try {
        \Orders\OrderManager::ensureInvoiceSchema();
        $order = \Orders\OrderManager::getOrderByOrderId($m[1]);
        if (!$order) { http_response_code(404); exit; }
        $path = trim((string)($order['invoice_file_path'] ?? ''));
        $full = $path !== '' && !str_contains($path, '..') ? PUBLIC_PATH . $path : '';
        if ($full === '' || !is_file($full)) {
            http_response_code(404);
            echo 'Invoice PDF has not been uploaded for this order yet.';
            exit;
        }
        $downloadName = str_replace(['"', "\r", "\n"], '', basename((string)($order['invoice_original_name'] ?: ('Invoice-' . $order['order_id'] . '.pdf'))));
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($full));
        readfile($full);
    } catch (\Throwable $e) {
        error_log('Admin invoice download failed for ' . $m[1] . ': ' . $e->getMessage());
        http_response_code(500);
        echo 'Invoice download failed. Please check logs.';
    }
    exit;
}

if (preg_match('#^/admin/artwork/(\d+)/(download|view)$#', $uri, $m)) {
    $file = Database::row("SELECT * FROM artwork_files WHERE id=?", [$m[1]]);
    if (!$file) { http_response_code(404); exit('Not found'); }
    $full = PUBLIC_PATH . ($file['file_path'] ?? '');
    if (!is_file($full)) { http_response_code(404); exit('File missing'); }
    $downloadName = str_replace(['"', "\r", "\n"], '', basename($file['original_name'] ?: $file['filename']));
    $disposition = ($m[2] ?? 'download') === 'view' ? 'inline' : 'attachment';
    header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($full));
    readfile($full);
    exit;
}

$settingsMap = [];
if (str_contains($uri, '/admin/settings') || str_contains($uri, '/admin/integrations')) {
    $rows = Database::rows("SELECT `key`, value FROM settings");
    foreach ($rows as $r) $settingsMap[$r['key']] = $r['value'];
}

if ($uri === '/admin/orders') {
    \Orders\OrderManager::ensureInvoiceSchema();
    $search = trim((string)($_GET['search'] ?? ''));
    $status = trim((string)($_GET['status'] ?? 'all'));
    $paymentStatus = trim((string)($_GET['payment_status'] ?? 'all'));
    $seen = trim((string)($_GET['seen'] ?? 'all'));
    $attention = trim((string)($_GET['attention'] ?? '0')) === '1';
    $sort = trim((string)($_GET['sort'] ?? 'newest'));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));
    $page = 1;
    $perPage = PHP_INT_MAX;
    $hasSeen = $ensureOrderSeenColumn();

    $summaryRow = Database::row(
        "SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS new_today,
            SUM(CASE WHEN status IN ('new_order','received','whatsapp_pending') THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status IN ('other_process','processing') THEN 1 ELSE 0 END) AS processing_orders,
            SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) AS ready_orders,
            SUM(CASE WHEN status IN ('new_order','received','whatsapp_pending','design_approved','other_process','processing','printing') AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS delayed_orders
         FROM orders"
    ) ?: [];
    $summaryCounts = [
        'total_orders' => (int)($summaryRow['total_orders'] ?? 0),
        'new_today' => (int)($summaryRow['new_today'] ?? 0),
        'pending_orders' => (int)($summaryRow['pending_orders'] ?? 0),
        'processing_orders' => (int)($summaryRow['processing_orders'] ?? 0),
        'ready_orders' => (int)($summaryRow['ready_orders'] ?? 0),
        'delayed_orders' => (int)($summaryRow['delayed_orders'] ?? 0),
    ];
    $statusCountRows = Database::rows("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
    $statusCounts = ['all' => $summaryCounts['total_orders']];
    foreach ($statusCountRows as $row) {
        $statusCounts[(string)$row['status']] = (int)($row['c'] ?? 0);
    }
    $statusCounts['new_order'] = (int)($statusCounts['new_order'] ?? 0);
    $statusCounts['design_approved'] = (int)($statusCounts['design_approved'] ?? 0);
    $statusCounts['other_process'] = (int)($statusCounts['other_process'] ?? 0) + (int)($statusCounts['processing'] ?? 0);
    $statusCounts['ready_dispatch'] = (int)($statusCounts['ready'] ?? 0);
    $statusCounts['attention'] = ($statusCounts['received'] ?? 0) + ($statusCounts['whatsapp_pending'] ?? 0) + ($statusCounts['design_approved'] ?? 0) + ($statusCounts['other_process'] ?? 0) + ($statusCounts['printing'] ?? 0);
    $statusCounts['delayed'] = $summaryCounts['delayed_orders'];
    $attentionPredicate = ($hasSeen ? "(COALESCE(customer_update_pending,0)=1 OR (status='new_order' AND COALESCE(is_seen,0)=0))" : "COALESCE(customer_update_pending,0)=1");
    $attentionCountRows = Database::rows("SELECT status, COUNT(*) AS c FROM orders WHERE $attentionPredicate GROUP BY status");
    $attentionCounts = ['all' => 0];
    foreach ($attentionCountRows as $row) {
        $attentionCounts[(string)$row['status']] = (int)$row['c'];
        $attentionCounts['all'] += (int)$row['c'];
    }
    $attentionCounts['other_process'] = (int)($attentionCounts['other_process'] ?? 0) + (int)($attentionCounts['processing'] ?? 0);
    $attentionCounts['ready_dispatch'] = (int)($attentionCounts['ready'] ?? 0);

    $where = [];
    $params = [];
    if ($status === 'attention') {
        $where[] = "status IN ('new_order','received','whatsapp_pending','design_approved','other_process','processing','printing')";
    } elseif ($status === 'delayed') {
        $where[] = "status IN ('new_order','received','whatsapp_pending','design_approved','other_process','processing','printing') AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    } elseif ($status === 'other_process') {
        $where[] = "status IN ('other_process','processing')";
    } elseif ($status !== 'all' && $status !== '') { $where[] = 'status = ?'; $params[] = $status; }
    if ($paymentStatus !== 'all' && $paymentStatus !== '') { $where[] = 'payment_status = ?'; $params[] = $paymentStatus; }
    if ($attention) $where[] = $attentionPredicate;
    if ($seen === 'new') {
        $where[] = "status = 'new_order'";
    } elseif ($seen === 'seen' && $hasSeen) {
        $where[] = 'is_seen = 1';
    }
    if ($dateFrom !== '') { $where[] = 'DATE(created_at) >= ?'; $params[] = $dateFrom; }
    if ($dateTo !== '') { $where[] = 'DATE(created_at) <= ?'; $params[] = $dateTo; }
    if ($search !== '') {
        $where[] = '(order_id LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR customer_email LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $orderSql = match ($sort) {
        'oldest' => 'created_at ASC',
        'high_value' => 'total_amount DESC, created_at DESC',
        'urgent' => ($hasSeen ? 'is_seen ASC, ' : '') . "FIELD(status,'new_order','received','whatsapp_pending','design_approved','other_process','processing','printing','ready','delivered','cancelled'), created_at ASC",
        default => 'created_at DESC',
    };

    $countRow = Database::row("SELECT COUNT(*) c FROM orders $whereSql", $params);
    $total = (int)($countRow['c'] ?? 0);
    $orders = Database::rows("SELECT * FROM orders $whereSql ORDER BY $orderSql", $params);
    $itemsByOrder = [];
    if ($orders) {
        $orderIds = array_map(static fn($order) => (int)$order['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $allItems = Database::rows(
            "SELECT oi.*,
                    COALESCE(pi.image_path, pi.url) AS product_image,
                    af.id AS artwork_file_id,
                    af.original_name AS artwork_original_name,
                    af.filename AS artwork_filename,
                    af.file_path AS artwork_file_path,
                    af.mime_type AS artwork_mime_type,
                    oda.id AS design_approval_id,
                    oda.status AS design_approval_status,
                    oda.admin_note AS design_admin_note,
                    oda.customer_note AS design_customer_note,
                    oda.approved_at AS design_approved_at,
                    oda.proof_file_id AS design_proof_file_id,
                    pf.original_name AS design_proof_original_name,
                    pf.filename AS design_proof_filename,
                    pf.file_path AS design_proof_file_path,
                    pf.mime_type AS design_proof_mime_type
             FROM order_items oi
             LEFT JOIN product_images pi ON pi.product_id = oi.product_id AND pi.is_primary = 1
             LEFT JOIN order_design_approvals oda ON oda.order_item_id = oi.id
             LEFT JOIN artwork_files af ON af.id = oda.customer_artwork_file_id
             LEFT JOIN artwork_files pf ON pf.id = oda.proof_file_id
             WHERE oi.order_id IN ($placeholders)
             ORDER BY oi.order_id, oi.id ASC",
            $orderIds
        );
        foreach ($allItems as $item) $itemsByOrder[(int)$item['order_id']][] = $item;
    }
    foreach ($orders as &$o) {
        $o['items'] = $itemsByOrder[(int)$o['id']] ?? [];
        foreach ($o['items'] as &$item) {
            if (empty($item['design_approval_id'])) {
                $customerArtwork = Database::row(
                    "SELECT af.id FROM artwork_files af
                      WHERE af.order_item_id = ?
                        AND NOT EXISTS (SELECT 1 FROM order_design_approvals oda2 WHERE oda2.proof_file_id = af.id)
                      ORDER BY af.id ASC LIMIT 1",
                    [(int)$item['id']]
                );
                \Orders\OrderManager::ensureDesignApprovalForItem(
                    (int)$o['id'],
                    (int)$item['id'],
                    (string)($item['design_choice'] ?? 'upload'),
                    !empty($customerArtwork['id']) ? (int)$customerArtwork['id'] : null
                );
            }
        }
        unset($item);
    }

    if ($hasSeen) Database::query("UPDATE orders SET is_seen=1 WHERE status='new_order' AND is_seen=0 AND created_at <= NOW()");
    view('admin/orders', compact('orders','total','page','perPage','status','search','summaryCounts','statusCounts','attentionCounts','attention','paymentStatus','seen','sort','dateFrom','dateTo','hasSeen'));
    exit;
}


if ($uri === '/admin/order-cleanup/delete-all' && $method === 'POST') {
    \Auth\Auth::requireSuperAdmin();
    $token = (string)($_POST['_token'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        redirect('/admin/order-cleanup?error=' . urlencode('Security token expired. Please refresh and try again.'));
    }
    $confirm = trim((string)($_POST['confirm_text'] ?? ''));
    if ($confirm !== 'DELETE ALL ORDERS') {
        redirect('/admin/order-cleanup?error=' . urlencode('Please type DELETE ALL ORDERS exactly to confirm cleanup.'));
    }

    $archiveFiles = !empty($_POST['archive_files']);
    $countsBefore = $orderCleanupCounts();
    if (($countsBefore['orders'] ?? 0) <= 0) {
        redirect('/admin/order-cleanup?success=' . urlencode('No orders found to delete.'));
    }

    $fileRows = [];
    if ($archiveFiles && $adminTableExists('artwork_files')) {
        try {
            $fileRows = Database::rows(
                "SELECT id, file_path, original_name, filename
                   FROM artwork_files
                  WHERE order_item_id IN (SELECT id FROM order_items)"
            );
        } catch (\Throwable) { $fileRows = []; }
    }

    $db = Database::get();
    $deleted = [];
    try {
        $db->beginTransaction();
        $delete = static function (string $key, string $sql) use (&$deleted): void {
            $stmt = Database::query($sql);
            $deleted[$key] = ($deleted[$key] ?? 0) + $stmt->rowCount();
        };
        if ($adminTableExists('product_reviews')) {
            $delete('product_reviews', 'DELETE FROM product_reviews WHERE order_id IN (SELECT id FROM orders) OR order_item_id IN (SELECT id FROM order_items)');
        }
        if ($adminTableExists('order_design_events')) {
            $delete('order_design_events', 'DELETE FROM order_design_events WHERE order_id IN (SELECT id FROM orders) OR order_item_id IN (SELECT id FROM order_items)');
        }
        if ($adminTableExists('order_design_approvals')) {
            $delete('order_design_approvals', 'DELETE FROM order_design_approvals WHERE order_id IN (SELECT id FROM orders) OR order_item_id IN (SELECT id FROM order_items)');
        }
        if ($adminTableExists('artwork_files')) {
            $delete('artwork_files', 'DELETE FROM artwork_files WHERE order_item_id IN (SELECT id FROM order_items)');
        }
        if ($adminTableExists('payments')) {
            $delete('payments', 'DELETE FROM payments WHERE order_id IN (SELECT id FROM orders)');
        }
        if ($adminTableExists('coupon_uses')) {
            $delete('coupon_uses', 'DELETE FROM coupon_uses WHERE order_id IN (SELECT id FROM orders)');
        }
        if ($adminTableExists('order_status_history')) {
            $delete('order_status_history', 'DELETE FROM order_status_history WHERE order_id IN (SELECT id FROM orders)');
        }
        if ($adminTableExists('order_items')) {
            $delete('order_items', 'DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders)');
        }
        if ($adminTableExists('orders')) {
            $delete('orders', 'DELETE FROM orders');
        }
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Order cleanup failed: ' . $e->getMessage());
        redirect('/admin/order-cleanup?error=' . urlencode('Cleanup failed: ' . $e->getMessage()));
    }

    $archived = 0;
    $archiveFailed = 0;
    if ($archiveFiles && $fileRows) {
        $trashDir = UPLOAD_PATH . '/.trash/order-cleanup/' . date('Ymd-His') . '/';
        if (!is_dir($trashDir)) @mkdir($trashDir, 0755, true);
        foreach ($fileRows as $file) {
            $path = trim((string)($file['file_path'] ?? ''));
            if ($path === '' || str_contains($path, '..')) continue;
            $full = PUBLIC_PATH . $path;
            if (!is_file($full)) continue;
            $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string)($file['original_name'] ?: $file['filename'] ?: basename($path))));
            $target = $trashDir . ((int)($file['id'] ?? 0)) . '-' . ($safeName ?: basename($path));
            if (@rename($full, $target)) $archived++;
            else $archiveFailed++;
        }
    }

    \Orders\AdminAudit::log('orders_cleanup', 'Deleted all testing orders: ' . json_encode($deleted));
    $msg = 'Order cleanup completed. Deleted orders: ' . (int)($deleted['orders'] ?? 0) . '. Archived files: ' . $archived . ($archiveFailed ? ('. File archive failed: ' . $archiveFailed) : '');
    redirect('/admin/order-cleanup?success=' . urlencode($msg));
}

if ($uri === '/admin/backup/download' && $method === 'POST') {
    \Auth\Auth::requireSuperAdmin();
    $token = (string)($_POST['_token'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(419);
        echo 'Security token expired. Please refresh and try again.';
        exit;
    }
    $type = trim((string)($_POST['backup_type'] ?? 'full'));
    $includeConfig = !empty($_POST['include_config']);
    try {
        \Backup\BackupManager::cleanupOld();
        $backup = \Backup\BackupManager::create($type, $includeConfig);
        if (!empty($backup['path']) && is_file((string)$backup['path'])) {
            \Orders\AdminAudit::log('backup_created', 'Backup generated: ' . (string)($backup['name'] ?? $type));
            while (ob_get_level() > 0) ob_end_clean();
            header('Content-Type: ' . (string)($backup['mime'] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . basename((string)$backup['name']) . '"');
            header('Content-Length: ' . filesize((string)$backup['path']));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            readfile((string)$backup['path']);
            @unlink((string)$backup['path']);
            exit;
        }
        throw new RuntimeException('Backup file was not created.');
    } catch (Throwable $e) {
        error_log('Backup generation failed: ' . $e->getMessage());
        redirect('/admin/backup?error=' . urlencode($e->getMessage()));
    }
}

if (preg_match('#^/admin/blogs/edit/(\d+)$#', $uri, $m) && $method === 'GET') {
    view('admin/blogs-new', ['blogEditId' => (int)$m[1]]);
    exit;
}

if (preg_match('#^/admin/portfolio/edit/(\d+)$#', $uri, $m) && $method === 'GET') {
    view('admin/portfolio-new', ['portfolioEditId' => (int)$m[1]]);
    exit;
}

if (preg_match('#^/admin/deals/edit/(\d+)$#', $uri, $m) && $method === 'GET') {
    view('admin/deals-new', ['dealEditId' => (int)$m[1]]);
    exit;
}

if (preg_match('#^/admin/business-needs/edit/(\d+)$#', $uri, $m) && $method === 'GET') {
    view('admin/business-needs-new', ['businessNeedEditId' => (int)$m[1]]);
    exit;
}

if (preg_match('#^/admin/coupons/edit/(\d+)$#', $uri, $m) && $method === 'GET') {
    view('admin/coupons-new', ['couponEditId' => (int)$m[1]]);
    exit;
}

if (in_array($uri, ['/admin/admins', '/admin/approvals', '/admin/backup', '/admin/order-cleanup'], true)) {
    \Auth\Auth::requireSuperAdmin();
}

$adminPage = match(true) {
    $uri === '/admin' || $uri === '/admin/dashboard' => 'admin/dashboard',
    $uri === '/admin/analytics'  => 'admin/analytics',
    $uri === '/admin/products'   => 'admin/products',
    $uri === '/admin/categories' => 'admin/categories',
    $uri === '/admin/media'      => 'admin/media',
    $uri === '/admin/design-history' => 'admin/design-history',
    $uri === '/admin/portfolio'  => 'admin/portfolio',
    $uri === '/admin/page-heroes' => 'admin/page-heroes',
    $uri === '/admin/portfolio/new' => 'admin/portfolio-new',
    $uri === '/admin/products/new' => 'admin/products-new',
    $uri === '/admin/banners'    => 'admin/banners',
    $uri === '/admin/deals'      => 'admin/deals',
    $uri === '/admin/combo-offers' => 'admin/combo-offers',
    $uri === '/admin/business-needs' => 'admin/business-needs',
    $uri === '/admin/business-needs/new' => 'admin/business-needs-new',
    $uri === '/admin/deals/new'  => 'admin/deals-new',
    $uri === '/admin/blogs'      => 'admin/blogs',
    $uri === '/admin/blogs/new'  => 'admin/blogs-new',
    $uri === '/admin/pricing'    => 'admin/pricing',
    $uri === '/admin/coupons'    => 'admin/coupons',
    $uri === '/admin/custom-orders' => 'admin/custom-orders',
    $uri === '/admin/coupons/new' => 'admin/coupons-new',
    $uri === '/admin/reviews'    => 'admin/reviews',
    $uri === '/admin/faqs'       => 'admin/faqs',
    $uri === '/admin/customers'  => 'admin/customers',
    $uri === '/admin/leads'      => 'admin/leads',
    $uri === '/admin/whatsapp-templates' => 'admin/whatsapp-templates',
    $uri === '/admin/approvals'  => 'admin/approvals',
    $uri === '/admin/admins'     => 'admin/admins',
    $uri === '/admin/backup'     => 'admin/backup',
    $uri === '/admin/order-cleanup' => 'admin/order-cleanup',
    $uri === '/admin/settings'   => 'admin/settings',
    $uri === '/admin/header-footer' => 'admin/header-footer',
    $uri === '/admin/integrations' => 'admin/integrations',
    $uri === '/admin/audit-logs' => 'admin/audit-logs',
    default                      => null,
};

if ($adminPage) { view($adminPage, compact('settingsMap')); exit; }

http_response_code(404);
view('404');
exit;

<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — API Router
//  All endpoints return JSON
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

if (!str_starts_with($uri, '/api/')) return; // Not an API route

header('Content-Type: application/json');

// Parse JSON body
$body = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? $_POST;
}

// ── Auth ──────────────────────────────────────────────────────

if ($uri === '/api/auth/login' && $method === 'POST') {
    $result = \Auth\Auth::login(
        trim($body['identifier'] ?? ''),
        $body['password'] ?? ''
    );
    json($result);
}

if ($uri === '/api/auth/register' && $method === 'POST') {
    $result = \Auth\Auth::register($body);
    json($result);
}

if ($uri === '/api/auth/logout' && $method === 'POST') {
    \Auth\Auth::logout();
    json(['ok' => true]);
}

if ($uri === '/api/auth/me' && $method === 'GET') {
    json(['ok' => true, 'user' => \Auth\Auth::user()]);
}

if ($uri === '/api/auth/account-exists' && $method === 'POST') {
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $phone = trim((string)($body['phone'] ?? ''));
    if ($email === '' || $phone === '') {
        json(['ok' => false, 'msg' => 'Email and phone are required.'], 422);
    }
    $row = \Database::row(
        "SELECT id FROM users WHERE is_active = 1 AND (email = ? OR phone = ?) LIMIT 1",
        [$email, $phone]
    );
    json(['ok' => true, 'exists' => !empty($row)]);
}

if ($uri === '/api/profile' && $method === 'GET') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $profile = \Auth\Auth::getProfile((int)$user['id']);
    json(['ok' => true, 'profile' => $profile]);
}

if ($uri === '/api/profile' && in_array($method, ['POST', 'PUT'], true)) {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $result = \Auth\Auth::updateProfile((int)$user['id'], $body);
    json($result, ($result['ok'] ?? false) ? 200 : 422);
}

if ($uri === '/api/profile/password' && $method === 'POST') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $result = \Auth\Auth::changePassword((int)$user['id'], $body);
    json($result, ($result['ok'] ?? false) ? 200 : 422);
}

if (preg_match('#^/api/design-approvals/(\d+)/approve$#', $uri, $m) && $method === 'POST') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $result = \Orders\OrderManager::customerDesignDecision((int)$m[1], (int)$user['id'], 'approve', trim((string)($body['note'] ?? '')));
    if (($result['ok'] ?? false)) $result['status'] = 'approved';
    json($result, ($result['ok'] ?? false) ? 200 : 422);
}

if (preg_match('#^/api/design-approvals/(\d+)/revision$#', $uri, $m) && $method === 'POST') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $result = \Orders\OrderManager::customerDesignDecision((int)$m[1], (int)$user['id'], 'revision', trim((string)($body['message'] ?? '')));
    if (($result['ok'] ?? false)) $result['status'] = 'revision_requested';
    json($result, ($result['ok'] ?? false) ? 200 : 422);
}

if (preg_match('#^/api/design-approvals/(\d+)/artwork$#', $uri, $m) && $method === 'POST') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $userId = (int)($user['id'] ?? 0);
    $approvalId = (int)$m[1];

    $approval = Database::row(
        "SELECT oda.*, o.user_id
           FROM order_design_approvals oda
           INNER JOIN orders o ON o.id = oda.order_id
          WHERE oda.id = ? AND o.user_id = ?
          LIMIT 1",
        [$approvalId, $userId]
    );
    if (!$approval) json(['ok' => false, 'msg' => 'Design approval not found.'], 404);
    $approvalStatus = (string)($approval['status'] ?? '');
    $canInitialUpload = $approvalStatus === 'pending_review'
        && (string)($approval['design_choice'] ?? '') === 'upload'
        && empty($approval['customer_artwork_file_id']);
    $canIssueReupload = $approvalStatus === 'issue_found';
    if (!$canInitialUpload && !$canIssueReupload) {
        json(['ok' => false, 'msg' => 'Upload is available only when a design file is required for this order.'], 422);
    }
    if (empty($_FILES['artwork'])) json(['ok' => false, 'msg' => 'No file uploaded'], 400);

    $file = $_FILES['artwork'];
    $maxMb = (int)Database::setting('upload_max_mb', env('UPLOAD_MAX_SIZE_MB', '50'));
    $maxSize = $maxMb * 1024 * 1024;
    $allowed = array_map('trim', explode(',', Database::setting('upload_allowed_ext', 'pdf,ai,eps,png,jpg,jpeg,psd,cdr')));
    if ($file['size'] > $maxSize) json(['ok' => false, 'msg' => "File too large. Max {$maxMb}MB."], 400);
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) json(['ok' => false, 'msg' => "File type .{$ext} not allowed."], 400);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $safeMimes = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/svg+xml',
        'image/tiff', 'application/zip', 'application/x-zip-compressed',
        'application/postscript', 'application/illustrator',
    ];
    if (!in_array($mime, $safeMimes, true) && !str_starts_with((string)$mime, 'image/')) {
        error_log("Unusual MIME type artwork reupload: {$mime} from user {$userId}");
    }

    $dir = UPLOAD_PATH . '/artwork/revisions/' . date('Y/m/');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = uniqid('rev_', true) . '.' . $ext;
    $filepath = $dir . $filename;
    $publicPath = '/uploads/artwork/revisions/' . date('Y/m/') . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) json(['ok' => false, 'msg' => 'Upload failed'], 500);

    $fileId = Database::insert(
        "INSERT INTO artwork_files (uploaded_by, order_item_id, filename, original_name, file_path, mime_type, file_size, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$userId, (int)$approval['order_item_id'], $filename, $file['name'], $publicPath, $mime, $file['size']]
    );
    Database::query(
        "UPDATE order_design_approvals
            SET status = 'pending_review',
                customer_artwork_file_id = ?,
                proof_file_id = NULL,
                customer_note = ?,
                approved_at = NULL,
                updated_at = NOW()
          WHERE id = ?",
        [(int)$fileId, $canInitialUpload ? 'Customer uploaded artwork after selecting upload later.' : 'Customer reuploaded artwork.', $approvalId]
    );
    \Orders\OrderManager::recordDesignEvent([
        'order_id' => (int)$approval['order_id'],
        'order_item_id' => (int)$approval['order_item_id'],
        'design_approval_id' => $approvalId,
        'event_type' => $canInitialUpload ? 'customer_artwork_uploaded' : 'customer_artwork_reuploaded',
        'actor_type' => 'customer',
        'actor_id' => $userId,
        'actor_name' => $user['name'] ?? null,
        'status_before' => $approvalStatus,
        'status_after' => 'pending_review',
        'file_id' => (int)$fileId,
        'file_role' => $canInitialUpload ? 'customer_artwork' : 'revision',
        'note' => $canInitialUpload ? 'Customer uploaded artwork after selecting upload later.' : 'Customer reuploaded artwork.',
    ]);
    \Orders\OrderManager::markCustomerUpdate((int)$approval['order_id'], $canInitialUpload ? 'customer_artwork_uploaded' : 'customer_artwork_reuploaded');
    \Orders\OrderManager::syncOrderDesignApproved((int)$approval['order_id']);
    json([
        'ok' => true,
        'msg' => $canInitialUpload ? 'Artwork uploaded for admin review.' : 'Artwork reuploaded for admin review.',
        'status' => 'pending_review',
        'artwork_id' => (int)$fileId,
        'file' => [
            'id' => (int)$fileId,
            'name' => (string)$file['name'],
            'path' => $publicPath,
            'mime' => $mime,
            'view_url' => '/account/artwork/' . (int)$fileId . '/view',
            'download_url' => '/account/artwork/' . (int)$fileId . '/download',
        ],
    ]);
}


if ($uri === '/api/custom-quotes' && $method === 'POST') {
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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_custom_quote_status (status, created_at),
            KEY idx_custom_quote_phone (phone),
            KEY idx_custom_quote_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach ([
            "ALTER TABLE custom_quote_requests ADD COLUMN admin_notes TEXT NULL AFTER status",
            "ALTER TABLE custom_quote_requests ADD COLUMN quoted_amount DECIMAL(12,2) NULL AFTER admin_notes",
            "ALTER TABLE custom_quote_requests ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'INR' AFTER quoted_amount",
            "ALTER TABLE custom_quote_requests ADD COLUMN order_id INT UNSIGNED NULL AFTER user_agent",
            "ALTER TABLE custom_quote_requests ADD COLUMN customer_type VARCHAR(30) NOT NULL DEFAULT 'guest' AFTER order_id",
            "ALTER TABLE custom_quote_requests ADD COLUMN quote_token VARCHAR(80) NULL AFTER customer_type",
            "ALTER TABLE custom_quote_requests ADD COLUMN quote_note TEXT NULL AFTER quote_token",
            "ALTER TABLE custom_quote_requests ADD COLUMN payment_status VARCHAR(40) NOT NULL DEFAULT 'not_required' AFTER quote_note",
            "ALTER TABLE custom_quote_requests ADD COLUMN sent_at DATETIME NULL AFTER payment_status",
            "ALTER TABLE custom_quote_requests ADD COLUMN payment_link_generated_at DATETIME NULL AFTER sent_at",
            "ALTER TABLE custom_quote_requests ADD COLUMN approved_at DATETIME NULL AFTER payment_link_generated_at",
        ] as $sql) { try { Database::query($sql); } catch (\Throwable) {} }
        try { Database::query("ALTER TABLE custom_quote_requests DROP COLUMN estimated_delivery"); } catch (\Throwable) {}
    } catch (\Throwable $e) {
        error_log('Custom quote schema unavailable: ' . $e->getMessage());
        json(['ok' => false, 'msg' => 'Custom quote system unavailable. Please try again later.'], 500);
    }

    $name = trim((string)($body['customer_name'] ?? $body['name'] ?? ''));
    $phone = trim((string)($body['phone'] ?? ''));
    $product = trim((string)($body['product_name'] ?? ''));
    if ($name === '' || $phone === '' || $product === '') {
        json(['ok' => false, 'msg' => 'Name, WhatsApp number and product name are required.'], 422);
    }
    $user = \Auth\Auth::user();
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $matchedUserId = $user ? (int)($user['id'] ?? 0) : 0;
    if (!$matchedUserId) {
        try {
            $matched = $email !== ''
                ? Database::row("SELECT id FROM users WHERE is_active=1 AND (phone=? OR email=?) LIMIT 1", [$phone, $email])
                : Database::row("SELECT id FROM users WHERE is_active=1 AND phone=? LIMIT 1", [$phone]);
            $matchedUserId = (int)($matched['id'] ?? 0);
        } catch (\Throwable) {}
    }
    $customerType = $matchedUserId > 0 ? 'registered' : 'guest';
    $token = bin2hex(random_bytes(24));
    $code = 'CQ-PENDING-' . strtoupper(bin2hex(random_bytes(8)));
    try {
        $id = Database::insert(
            "INSERT INTO custom_quote_requests (request_code,user_id,customer_name,phone,email,product_name,size_dimension,material_type,quantity,instructions,status,customer_type,quote_token,source_page,ip_address,user_agent,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [
                $code,
                $matchedUserId > 0 ? $matchedUserId : null,
                $name,
                $phone,
                $email ?: null,
                $product,
                trim((string)($body['size_dimension'] ?? '')),
                trim((string)($body['material_type'] ?? '')),
                trim((string)($body['quantity'] ?? '')),
                trim((string)($body['instructions'] ?? '')),
                'new',
                $customerType,
                $token,
                trim((string)($body['source_page'] ?? ($_SERVER['HTTP_REFERER'] ?? ''))),
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]
        );
        $code = 'CQ-' . str_pad((string)(int)$id, 4, '0', STR_PAD_LEFT);
        Database::query("UPDATE custom_quote_requests SET request_code=? WHERE id=?", [$code, (int)$id]);
        json(['ok' => true, 'id' => (int)$id, 'request_code' => $code, 'customer_type' => $customerType, 'msg' => $customerType === 'registered' ? 'Quotation request received and linked to your account.' : 'Quotation request received. Create/login to an account later to track and pay.']);
    } catch (\Throwable $e) {
        error_log('Custom quote save failed: ' . $e->getMessage());
        json(['ok' => false, 'msg' => 'Could not submit quotation request.'], 500);
    }
}

if ($uri === '/api/contact-leads' && $method === 'POST') {
    $result = \Leads\ContactLeadManager::create($body);
    json($result, ($result['ok'] ?? false) ? 200 : 422);
}

// ── Products ──────────────────────────────────────────────────

if ($uri === '/api/products' && $method === 'GET') {
    $q    = $_GET['q'] ?? '';
    $cat  = $_GET['category'] ?? '';
    $prods = $q
        ? \Catalog\ProductCatalog::search($q)
        : \Catalog\ProductCatalog::all();
    if ($cat) {
        $prods = array_filter($prods, fn($p) => $p['category_name'] === $cat);
    }
    json(['ok' => true, 'products' => array_values($prods)]);
}

if ($uri === '/api/categories' && $method === 'GET') {
    json(['ok' => true, 'categories' => \Catalog\ProductCatalog::categories()]);
}

if (preg_match('#^/api/products/(\d+)/pricing$#', $uri, $m) && $method === 'GET') {
    $data = \Cart\Pricing::productPricingData((int)$m[1]);
    json(['ok' => true, ...$data]);
}

// ── Wishlist ─────────────────────────────────────────────────

if ($uri === '/api/wishlist' && $method === 'GET') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    json(['ok' => true, 'items' => \Wishlist\Wishlist::itemsForUser((int)$user['id'])]);
}

if ($uri === '/api/wishlist/toggle' && $method === 'POST') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $result = \Wishlist\Wishlist::toggle((int)$user['id'], (int)($body['product_id'] ?? 0));
    json($result, ($result['ok'] ?? false) ? 200 : 422);
}

if (preg_match('#^/api/wishlist/(\\d+)$#', $uri, $m) && $method === 'DELETE') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $result = \Wishlist\Wishlist::remove((int)$user['id'], (int)$m[1]);
    json($result, ($result['ok'] ?? false) ? 200 : 422);
}

// Live price calculation
if ($uri === '/api/price/calculate' && $method === 'POST') {
    $result = \Cart\Pricing::calculate(
        (int)($body['product_id'] ?? 0),
        (int)($body['quality_id'] ?? 0),
        (int)($body['quantity'] ?? 0),
        $body['attribute_selections'] ?? [],
        $body['design_choice'] ?? 'upload'
    );
    json($result);
}

// ── Cart ──────────────────────────────────────────────────────

if ($uri === '/api/cart' && $method === 'GET') {
    $items  = \Cart\Cart::get();
    $coupon = $_GET['coupon'] ?? null;
    $totals = \Cart\Cart::totals($items, $coupon);
    json(['ok' => true, 'items' => $items, 'totals' => $totals]);
}

if ($uri === '/api/cart/add' && $method === 'POST') {
    $result = \Cart\Cart::add($body);
    json($result);
}

if (preg_match('#^/api/cart/remove/(.+)$#', $uri, $m) && $method === 'DELETE') {
    json(\Cart\Cart::remove($m[1]));
}

if (preg_match('#^/api/cart/update/(.+)$#', $uri, $m) && $method === 'POST') {
    json(\Cart\Cart::updateQuantity($m[1], (int)($body['quantity'] ?? 0)));
}

if ($uri === '/api/cart/clear' && $method === 'POST') {
    \Cart\Cart::clear();
    json(['ok' => true]);
}

if ($uri === '/api/coupon/validate' && $method === 'POST') {
    $items    = \Cart\Cart::get();
    $subtotal = array_sum(array_column($items, 'total_price'));
    $result   = \Cart\Pricing::validateCoupon($body['code'] ?? '', $subtotal, $items);
    json($result);
}

// ── Artwork Upload ────────────────────────────────────────────

if ($uri === '/api/upload/artwork' && $method === 'POST') {
    $user = \Auth\Auth::user();
    $userId = (int)($user['id'] ?? 0);

    if (empty($_FILES['artwork'])) {
        json(['ok' => false, 'msg' => 'No file uploaded'], 400);
    }

    $file    = $_FILES['artwork'];
    $maxMb   = (int)Database::setting('upload_max_mb', env('UPLOAD_MAX_SIZE_MB', '50'));
    $maxSize = $maxMb * 1024 * 1024;
    $allowed = explode(',', Database::setting('upload_allowed_ext', 'pdf,ai,eps,png,jpg,jpeg,psd,cdr'));

    if ($file['size'] > $maxSize) {
        json(['ok' => false, 'msg' => "File too large. Max {$maxMb}MB."], 400);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        json(['ok' => false, 'msg' => "File type .{$ext} not allowed."], 400);
    }

    // Validate MIME to prevent file spoofing
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $safeMimes = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/svg+xml',
        'image/tiff', 'application/zip', 'application/x-zip-compressed',
        'application/postscript', 'application/illustrator',
    ];
    // Allow unknown MIME for specialized print files (AI, CDR, PSD)
    if (!in_array($mime, $safeMimes) && !str_starts_with($mime, 'image/')) {
        // Still allow; log it
        error_log("Unusual MIME type upload: {$mime} from user {$userId}");
    }

    $dir = UPLOAD_PATH . '/artwork/' . date('Y/m/');
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = uniqid('art_', true) . '.' . $ext;
    $filepath = $dir . $filename;
    $publicPath = '/uploads/artwork/' . date('Y/m/') . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        json(['ok' => false, 'msg' => 'Upload failed'], 500);
    }

    $ownerForInsert = $userId > 0 ? $userId : null;
    try {
        $fileId = Database::insert(
            "INSERT INTO artwork_files (uploaded_by, filename, original_name, file_path, mime_type, file_size, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$ownerForInsert, $filename, $file['name'], $publicPath, $mime, $file['size']]
        );
    } catch (\Throwable $e) {
        // Some schemas may have `uploaded_by` as NOT NULL.
        // Fallback to 0 for guests and keep flow working.
        if ($userId === 0) {
            $fileId = Database::insert(
                "INSERT INTO artwork_files (uploaded_by, filename, original_name, file_path, mime_type, file_size, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [0, $filename, $file['name'], $publicPath, $mime, $file['size']]
            );
        } else {
            throw $e;
        }
    }

    if ($userId === 0) {
        $_SESSION['guest_artwork_ids'] = $_SESSION['guest_artwork_ids'] ?? [];
        $_SESSION['guest_artwork_ids'][] = (int)$fileId;
        $_SESSION['guest_artwork_ids'] = array_values(array_unique(array_map('intval', $_SESSION['guest_artwork_ids'])));
    }

    json(['ok' => true, 'artwork_id' => $fileId, 'filename' => $file['name'], 'path' => $publicPath]);
}

// ── Orders ────────────────────────────────────────────────────

if ($uri === '/api/orders/place' && $method === 'POST') {
    $ensure = \Auth\Auth::ensureCheckoutUser($body['customer'] ?? []);
    if (!$ensure['ok']) json($ensure, 400);
    json(\Orders\OrderManager::place([
        ...$body,
        'shipping' => $body['shipping'] ?? null,
    ]));
}

if ($uri === '/api/orders' && $method === 'GET') {
    \Auth\Auth::require();
    $user   = \Auth\Auth::user();
    $orders = \Orders\OrderManager::getUserOrders($user['id']);
    json(['ok' => true, 'orders' => $orders]);
}

// ── Razorpay ──────────────────────────────────────────────────

if ($uri === '/api/payment/create-order' && $method === 'POST') {
    $ensure = \Auth\Auth::ensureCheckoutUser($body['customer'] ?? []);
    if (!$ensure['ok']) json($ensure, 400);
    $items  = \Cart\Cart::get();
    $customQuoteId=(int)($body['custom_quote_id'] ?? 0); if($customQuoteId)$items=array_values(array_filter($items,static fn($item)=>(int)($item['custom_quote_id']??0)===$customQuoteId));
    $coupon = $customQuoteId > 0 ? null : ($body['coupon_code'] ?? null);
    $totals = \Cart\Cart::totals($items, $coupon);

    if ($totals['total'] <= 0) json(['ok' => false, 'msg' => 'Invalid order total']);

    $user   = \Auth\Auth::user();
    $result = \Payment\Razorpay::createOrder(
        $totals['total'],
        'rcpt_' . time(),
        ['customer_name' => $user['name'], 'customer_phone' => $user['phone']]
    );
    json($result);
}

if ($uri === '/api/payment/verify' && $method === 'POST') {
    $ensure = \Auth\Auth::ensureCheckoutUser($body['customer'] ?? []);
    if (!$ensure['ok']) json($ensure, 400);

    $razorpayOrderId = trim((string)($body['razorpay_order_id'] ?? ''));
    $razorpayPaymentId = trim((string)($body['razorpay_payment_id'] ?? ''));
    $razorpaySignature = trim((string)($body['razorpay_signature'] ?? ''));

    if ($razorpayOrderId === '' || $razorpayPaymentId === '' || $razorpaySignature === '') {
        json(['ok' => false, 'msg' => 'Missing payment verification fields'], 422);
    }

    // Idempotency: if this payment ID is already recorded, return existing order directly.
    $existingOrder = \Payment\Razorpay::findOrderByPaymentId($razorpayPaymentId);
    if ($existingOrder) {
        json(['ok' => true, 'already_processed' => true, 'order' => $existingOrder]);
    }

    // 1) Place order first (records in DB)
    $placeResult = \Orders\OrderManager::place([
        'coupon_code'    => (int)($body['custom_quote_id'] ?? 0) > 0 ? null : ($body['coupon_code'] ?? null),
        'payment_method' => 'razorpay',
        'payment_status' => 'pending',
        'billing'        => $body['billing'] ?? null,
        'shipping'       => $body['shipping'] ?? null,
        'custom_quote_id' => (int)($body['custom_quote_id'] ?? 0),
    ]);

    if (!$placeResult['ok']) json($placeResult);

    // 2) Verify signature + mark payment success
    $verifyResult = \Payment\Razorpay::handleSuccess(
        (int)$placeResult['order']['id'],
        $razorpayOrderId,
        $razorpayPaymentId,
        $razorpaySignature
    );

    // Defensive: always try to return order object so frontend can redirect reliably.
    if (($verifyResult['ok'] ?? false) && empty($verifyResult['order'])) {
        $verifyResult['order'] = \Orders\OrderManager::getOrder((int)$placeResult['order']['id']);
    }

    json($verifyResult);
}

// ── WhatsApp Order (no payment) ───────────────────────────────

if ($uri === '/api/orders/whatsapp' && $method === 'POST') {
    $ensure = \Auth\Auth::ensureCheckoutUser($body['customer'] ?? []);
    if (!$ensure['ok']) json($ensure, 400);
    $result = \Orders\OrderManager::place([
        'coupon_code'    => $body['coupon_code'] ?? null,
        'payment_method' => 'whatsapp',
        'payment_status' => 'pending',
        'notes'          => $body['notes'] ?? '',
        'billing'        => $body['billing'] ?? null,
        'shipping'       => $body['shipping'] ?? null,
    ]);

    if ($result['ok']) {
        // Update status to whatsapp_pending
        \Orders\OrderManager::updateStatus($result['order']['id'], 'whatsapp_pending', 'Placed via WhatsApp');
    }

    json($result);
}


// ── Product Reviews ─────────────────────────────────────────

if ($uri === '/api/reviews/my' && $method === 'GET') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    json([
        'ok' => true,
        'reviewable_items' => \Reviews\ProductReview::reviewableItemsForUser((int)$user['id']),
        'reviews' => \Reviews\ProductReview::userReviews((int)$user['id']),
    ]);
}

if ($uri === '/api/reviews' && $method === 'POST') {
    \Auth\Auth::require();
    $user = \Auth\Auth::user();
    $result = \Reviews\ProductReview::createOrUpdate((int)$user['id'], $body);
    json($result, ($result['ok'] ?? false) ? 200 : 422);
}

// ── Settings (public read-only) ───────────────────────────────

if ($uri === '/api/settings/public' && $method === 'GET') {
    $publicKeys = ['biz_name', 'biz_phone', 'biz_whatsapp', 'biz_email', 'biz_address', 'gst_percent', 'razorpay_key_id'];
    $settings = [];
    foreach ($publicKeys as $k) {
        $settings[$k] = Database::setting($k, '');
    }
    json(['ok' => true, 'settings' => $settings]);
}


// ── Chatbot ───────────────────────────────────────────────────

if ($uri === '/api/chat/ask' && $method === 'POST') {
    $question = trim((string)($body['question'] ?? ''));
    $history = $body['history'] ?? [];
    if ($question === '') {
        json(['ok' => false, 'msg' => 'Question is required'], 422);
    }

    $result = \Chatbot\SupportBot::ask($question, is_array($history) ? $history : []);
    json($result, $result['ok'] ? 200 : 400);
}

json(['ok' => false, 'msg' => 'API endpoint not found'], 404);

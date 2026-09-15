<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — Order Manager
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

namespace Orders;

class OrderManager
{
    private static ?bool $workflowSchemaReady = null;
    private static ?bool $designApprovalSchemaReady = null;
    private static ?bool $designEventSchemaReady = null;
    private static ?bool $invoiceSchemaReady = null;
    private static ?bool $customOrderSchemaReady = null;

    public static function ensureCustomOrderSchema(): bool
    {
        if (self::$customOrderSchemaReady !== null) return self::$customOrderSchemaReady;
        try {
            foreach ([
                "ALTER TABLE orders ADD COLUMN order_type VARCHAR(30) NOT NULL DEFAULT 'normal' AFTER order_id",
                "ALTER TABLE orders ADD COLUMN custom_quote_id INT UNSIGNED NULL AFTER order_type",
                "CREATE INDEX idx_orders_order_type ON orders (order_type)",
                "CREATE INDEX idx_orders_custom_quote ON orders (custom_quote_id)",
                "ALTER TABLE order_items MODIFY COLUMN product_id INT UNSIGNED NULL",
                "ALTER TABLE order_items MODIFY COLUMN quality_id INT UNSIGNED NULL",
                "ALTER TABLE order_items ADD COLUMN item_type VARCHAR(30) NOT NULL DEFAULT 'product' AFTER order_id",
                "ALTER TABLE order_items ADD COLUMN custom_quote_id INT UNSIGNED NULL AFTER item_type",
                "CREATE INDEX idx_order_items_custom_quote ON order_items (custom_quote_id)",
            ] as $sql) { try { \Database::query($sql); } catch (\Throwable) {} }
            self::$customOrderSchemaReady = true;
        } catch (\Throwable $e) {
            error_log('Order custom quote schema unavailable: ' . $e->getMessage());
            self::$customOrderSchemaReady = false;
        }
        return self::$customOrderSchemaReady;
    }

    public static function ensureWorkflowSchema(): bool
    {
        if (self::$workflowSchemaReady !== null) return self::$workflowSchemaReady;

        $statusEnum = "ENUM('new_order','received','design_approved','printing','other_process','processing','ready','delivered','cancelled','whatsapp_pending')";

        try {
            $ordersStatus = \Database::row(
                "SELECT COLUMN_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'orders'
                   AND COLUMN_NAME = 'status'
                 LIMIT 1"
            );
            if (!str_contains((string)($ordersStatus['COLUMN_TYPE'] ?? ''), "'new_order'")) {
                \Database::query("ALTER TABLE orders MODIFY COLUMN status {$statusEnum} NOT NULL DEFAULT 'new_order'");
            }

            try {
                $historyStatus = \Database::row(
                    "SELECT COLUMN_TYPE
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'order_status_history'
                       AND COLUMN_NAME = 'status'
                     LIMIT 1"
                );
                if ($historyStatus && !str_contains((string)($historyStatus['COLUMN_TYPE'] ?? ''), "'new_order'")) {
                    \Database::query("ALTER TABLE order_status_history MODIFY COLUMN status {$statusEnum} NOT NULL");
                }
            } catch (\Throwable $e) {
                error_log('Order status history schema update skipped: ' . $e->getMessage());
            }

            self::$workflowSchemaReady = true;
        } catch (\Throwable $e) {
            error_log('Order workflow status schema unavailable: ' . $e->getMessage());
            self::$workflowSchemaReady = false;
        }

        return self::$workflowSchemaReady;
    }

    public static function ensureDesignApprovalSchema(): bool
    {
        if (self::$designApprovalSchemaReady !== null) return self::$designApprovalSchemaReady;

        try {
            \Database::query(
                "CREATE TABLE IF NOT EXISTS order_design_approvals (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    order_item_id INT NOT NULL,
                    design_choice ENUM('upload','rcs') NOT NULL,
                    status ENUM('pending_review','issue_found','proof_uploaded','revision_requested','approved') NOT NULL DEFAULT 'pending_review',
                    customer_artwork_file_id INT NULL,
                    proof_file_id INT NULL,
                    admin_note TEXT NULL,
                    customer_note TEXT NULL,
                    approved_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uniq_order_design_item (order_item_id),
                    KEY idx_order_design_order (order_id),
                    KEY idx_order_design_status (status)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $approvalStatus = \Database::row(
                "SELECT COLUMN_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'order_design_approvals'
                   AND COLUMN_NAME = 'status'
                 LIMIT 1"
            );
            if ($approvalStatus && !str_contains((string)($approvalStatus['COLUMN_TYPE'] ?? ''), "'revision_requested'")) {
                \Database::query("ALTER TABLE order_design_approvals MODIFY COLUMN status ENUM('pending_review','issue_found','proof_uploaded','revision_requested','approved') NOT NULL DEFAULT 'pending_review'");
            }

            self::$designApprovalSchemaReady = true;
        } catch (\Throwable $e) {
            error_log('Order design approval schema unavailable: ' . $e->getMessage());
            self::$designApprovalSchemaReady = false;
        }

        return self::$designApprovalSchemaReady;
    }

    public static function ensureDesignEventSchema(): bool
    {
        if (self::$designEventSchemaReady !== null) return self::$designEventSchemaReady;

        try {
            \Database::query(
                "CREATE TABLE IF NOT EXISTS order_design_events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    order_item_id INT NULL,
                    design_approval_id INT NULL,
                    event_type VARCHAR(80) NOT NULL,
                    actor_type ENUM('customer','admin','system') NOT NULL DEFAULT 'system',
                    actor_id INT NULL,
                    actor_name VARCHAR(180) NULL,
                    status_before VARCHAR(80) NULL,
                    status_after VARCHAR(80) NULL,
                    file_id INT NULL,
                    file_role ENUM('customer_artwork','admin_proof','revision','media','other') NULL,
                    file_name VARCHAR(255) NULL,
                    file_path VARCHAR(500) NULL,
                    file_mime VARCHAR(140) NULL,
                    file_size INT NULL,
                    note TEXT NULL,
                    meta_json LONGTEXT NULL,
                    ip_address VARCHAR(64) NULL,
                    user_agent VARCHAR(255) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_ode_order (order_id, created_at),
                    KEY idx_ode_item (order_item_id, created_at),
                    KEY idx_ode_approval (design_approval_id, created_at),
                    KEY idx_ode_type (event_type, created_at)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            self::$designEventSchemaReady = true;
        } catch (\Throwable $e) {
            error_log('Order design event schema unavailable: ' . $e->getMessage());
            self::$designEventSchemaReady = false;
        }

        return self::$designEventSchemaReady;
    }

    public static function ensureCustomerUpdateSchema(): bool
    {
        static $ready = false;
        if ($ready) return true;
        try {
            $hasPending = \Database::row(
                "SELECT 1 AS ok
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'orders'
                    AND COLUMN_NAME = 'customer_update_pending'
                  LIMIT 1"
            );
            if (!$hasPending) {
                \Database::query("ALTER TABLE orders ADD COLUMN customer_update_pending TINYINT(1) NOT NULL DEFAULT 0");
            }
            $hasType = \Database::row(
                "SELECT 1 AS ok
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'orders'
                    AND COLUMN_NAME = 'customer_update_type'
                  LIMIT 1"
            );
            if (!$hasType) {
                \Database::query("ALTER TABLE orders ADD COLUMN customer_update_type VARCHAR(80) NULL AFTER customer_update_pending");
            }
            $hasAt = \Database::row(
                "SELECT 1 AS ok
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'orders'
                    AND COLUMN_NAME = 'customer_update_at'
                  LIMIT 1"
            );
            if (!$hasAt) {
                \Database::query("ALTER TABLE orders ADD COLUMN customer_update_at DATETIME NULL AFTER customer_update_type");
            }
            foreach ([
                "ALTER TABLE orders ADD COLUMN admin_update_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_update_at",
                "ALTER TABLE orders ADD COLUMN admin_update_type VARCHAR(80) NULL AFTER admin_update_pending",
                "ALTER TABLE orders ADD COLUMN admin_update_at DATETIME NULL AFTER admin_update_type",
            ] as $sql) { try { \Database::query($sql); } catch (\Throwable) {} }
            try { \Database::query("CREATE INDEX idx_orders_customer_update ON orders (customer_update_pending, customer_update_at)"); } catch (\Throwable) {}
            try { \Database::query("CREATE INDEX idx_orders_admin_update ON orders (admin_update_pending, admin_update_at)"); } catch (\Throwable) {}
            $ready = true;
            return true;
        } catch (\Throwable $e) {
            error_log('Order customer update schema unavailable: ' . $e->getMessage());
            return false;
        }
    }

    public static function ensureInvoiceSchema(): bool
    {
        if (self::$invoiceSchemaReady !== null) return self::$invoiceSchemaReady;

        try {
            $columns = [
                'invoice_file_path' => "ALTER TABLE orders ADD COLUMN invoice_file_path VARCHAR(500) NULL AFTER payment_id",
                'invoice_original_name' => "ALTER TABLE orders ADD COLUMN invoice_original_name VARCHAR(255) NULL AFTER invoice_file_path",
                'invoice_uploaded_at' => "ALTER TABLE orders ADD COLUMN invoice_uploaded_at DATETIME NULL AFTER invoice_original_name",
                'invoice_uploaded_by' => "ALTER TABLE orders ADD COLUMN invoice_uploaded_by INT NULL AFTER invoice_uploaded_at",
            ];
            foreach ($columns as $column => $sql) {
                $exists = \Database::row(
                    "SELECT 1 AS ok
                       FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'orders'
                        AND COLUMN_NAME = ?
                      LIMIT 1",
                    [$column]
                );
                if (!$exists) \Database::query($sql);
            }
            self::$invoiceSchemaReady = true;
        } catch (\Throwable $e) {
            error_log('Order invoice schema unavailable: ' . $e->getMessage());
            self::$invoiceSchemaReady = false;
        }

        return self::$invoiceSchemaReady;
    }

    public static function saveUploadedInvoice(int $orderId, string $path, string $originalName, ?int $adminId = null): bool
    {
        if ($orderId <= 0 || trim($path) === '' || !self::ensureInvoiceSchema()) return false;
        \Database::query(
            "UPDATE orders
                SET invoice_file_path = ?,
                    invoice_original_name = ?,
                    invoice_uploaded_at = NOW(),
                    invoice_uploaded_by = ?,
                    updated_at = NOW()
              WHERE id = ?",
            [$path, substr($originalName, 0, 255), $adminId, $orderId]
        );
        return true;
    }

    public static function markCustomerUpdate(int $orderId, string $type): void
    {
        if ($orderId <= 0 || !self::ensureCustomerUpdateSchema()) return;
        \Database::query(
            "UPDATE orders
                SET customer_update_pending = 1,
                    customer_update_type = ?,
                    customer_update_at = NOW(),
                    updated_at = NOW()
              WHERE id = ?",
            [substr($type, 0, 80), $orderId]
        );
    }

    public static function clearCustomerUpdate(int $orderId): void
    {
        if ($orderId <= 0 || !self::ensureCustomerUpdateSchema()) return;
        \Database::query(
            "UPDATE orders
                SET customer_update_pending = 0,
                    customer_update_type = NULL,
                    customer_update_at = NULL,
                    updated_at = NOW()
              WHERE id = ?",
            [$orderId]
        );
    }

    public static function markAdminUpdate(int $orderId, string $type): void
    {
        if ($orderId <= 0 || !self::ensureCustomerUpdateSchema()) return;
        \Database::query("UPDATE orders SET admin_update_pending=1, admin_update_type=?, admin_update_at=NOW(), updated_at=NOW() WHERE id=?", [substr($type, 0, 80), $orderId]);
    }

    public static function clearAdminUpdate(int $orderId): void
    {
        if ($orderId <= 0 || !self::ensureCustomerUpdateSchema()) return;
        \Database::query("UPDATE orders SET admin_update_pending=0, admin_update_type=NULL, admin_update_at=NULL, updated_at=NOW() WHERE id=?", [$orderId]);
    }

    public static function recordDesignEvent(array $event): bool
    {
        if (!self::ensureDesignEventSchema()) return false;

        $orderId = (int)($event['order_id'] ?? 0);
        if ($orderId <= 0) return false;

        $actorType = (string)($event['actor_type'] ?? 'system');
        if (!in_array($actorType, ['customer', 'admin', 'system'], true)) $actorType = 'system';

        $fileId = isset($event['file_id']) ? (int)$event['file_id'] : null;
        $file = null;
        if ($fileId) {
            try { $file = \Database::row("SELECT * FROM artwork_files WHERE id = ?", [$fileId]); } catch (\Throwable) { $file = null; }
        }

        $meta = $event['meta'] ?? null;
        $metaJson = $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        try {
            \Database::insert(
                "INSERT INTO order_design_events
                    (order_id, order_item_id, design_approval_id, event_type, actor_type, actor_id, actor_name,
                     status_before, status_after, file_id, file_role, file_name, file_path, file_mime, file_size,
                     note, meta_json, ip_address, user_agent, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
                [
                    $orderId,
                    isset($event['order_item_id']) ? (int)$event['order_item_id'] : null,
                    isset($event['design_approval_id']) ? (int)$event['design_approval_id'] : null,
                    substr((string)($event['event_type'] ?? 'event'), 0, 80),
                    $actorType,
                    isset($event['actor_id']) ? (int)$event['actor_id'] : null,
                    isset($event['actor_name']) ? substr((string)$event['actor_name'], 0, 180) : null,
                    isset($event['status_before']) ? substr((string)$event['status_before'], 0, 80) : null,
                    isset($event['status_after']) ? substr((string)$event['status_after'], 0, 80) : null,
                    $fileId,
                    isset($event['file_role']) ? (string)$event['file_role'] : null,
                    (string)($event['file_name'] ?? ($file['original_name'] ?? $file['filename'] ?? '')) ?: null,
                    (string)($event['file_path'] ?? ($file['file_path'] ?? '')) ?: null,
                    (string)($event['file_mime'] ?? ($file['mime_type'] ?? '')) ?: null,
                    isset($event['file_size']) ? (int)$event['file_size'] : (isset($file['file_size']) ? (int)$file['file_size'] : null),
                    isset($event['note']) ? trim((string)$event['note']) : null,
                    $metaJson,
                    substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64) ?: null,
                    $userAgent !== '' ? $userAgent : null,
                ]
            );
            return true;
        } catch (\Throwable $e) {
            error_log('Order design event insert failed: ' . $e->getMessage());
            return false;
        }
    }


    public static function place(array $params): array
    {
        self::ensureWorkflowSchema();
        self::ensureDesignApprovalSchema();
        // Ensure the additive audit table before checkout starts its DB transaction.
        // MySQL DDL can implicitly commit active transactions, so never create this table mid-order.
        self::ensureDesignEventSchema();
        self::ensureCustomOrderSchema();

        $user = \Auth\Auth::user();
        if (!$user) return ['ok' => false, 'msg' => 'Not authenticated'];

        $cartItems = \Cart\Cart::get();
        $onlyCustomQuoteId = (int)($params['custom_quote_id'] ?? 0);
        if ($onlyCustomQuoteId) $cartItems = array_values(array_filter($cartItems, static fn($item) => (int)($item['custom_quote_id'] ?? 0) === $onlyCustomQuoteId));
        if (empty($cartItems)) return ['ok' => false, 'msg' => 'Cart is empty'];

        // An approved custom quote has a fixed payable amount and must never receive cart coupons.
        $couponCode = $onlyCustomQuoteId > 0 ? null : ($params['coupon_code'] ?? null);
        $totals = \Cart\Cart::totals($cartItems, $couponCode);
        $billing = self::sanitizeBilling($params['billing'] ?? null);
        $shipping = self::sanitizeShipping($params['shipping'] ?? null);
        $saveShippingDefault = !empty($shipping['save_as_default']);
        $plainNotes = trim((string)($params['notes'] ?? ''));
        $meta = [];
        if ($plainNotes !== '') $meta['note'] = $plainNotes;
        if ($billing !== null) $meta['billing'] = $billing;
        if ($shipping !== null) $meta['shipping'] = $shipping;

        $storedNotes = $plainNotes;
        if (!empty($meta)) {
            $storedNotes = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }
        $customQuoteIds = array_values(array_unique(array_filter(array_map('intval', array_column($cartItems, 'custom_quote_id')), static fn($id) => $id > 0)));
        $hasCustomQuote = !empty($customQuoteIds);

        // Generate readable order ID
        $orderId = self::generateOrderId();
        $now = date('Y-m-d H:i:s');

        $db = \Database::get();
        $dbOrderId = null;

        try {
            $db->beginTransaction();

            // Create order
            $dbOrderId = \Database::insert(
                "INSERT INTO orders (order_id, order_type, custom_quote_id, user_id, customer_name, customer_email, customer_phone,
                    subtotal, discount_amount, gst_amount, gst_percent, total_amount,
                    coupon_code, payment_method, payment_status, status, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new_order', ?, ?)",
                [
                    $orderId,
                    $hasCustomQuote ? 'custom' : 'normal',
                    $customQuoteIds[0] ?? null,
                    $user['id'],
                    $user['name'],
                    $user['email'],
                    $user['phone'],
                    $totals['subtotal'],
                    $totals['discount'],
                    $totals['gst_amt'],
                    $totals['gst_pct'],
                    $totals['total'],
                    $couponCode,
                    $params['payment_method'] ?? 'razorpay',
                    $params['payment_status'] ?? 'pending',
                    $storedNotes,
                    $now,
                ]
            );

            // Insert order items (snapshot of cart)
            foreach ($cartItems as $item) {
                $orderItemId = \Database::insert(
                    "INSERT INTO order_items (order_id, item_type, custom_quote_id, product_id, quality_id, quantity,
                        product_name, quality_name, attribute_selections, design_choice,
                        design_brief, notes, price_breakdown, total_price, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $dbOrderId,
                        ($item['item_type'] ?? 'product') === 'custom_quote' ? 'custom_quote' : 'product',
                        !empty($item['custom_quote_id']) ? (int)$item['custom_quote_id'] : null,
                        ($item['item_type'] ?? 'product') === 'custom_quote' ? null : (int)($item['product_id'] ?? 0),
                        ($item['item_type'] ?? 'product') === 'custom_quote' ? null : (int)($item['quality_id'] ?? 1),
                        (int)($item['quantity'] ?? 1),
                        $item['product_name'],
                        $item['quality_name'],
                        $item['attribute_selections'],
                        $item['design_choice'],
                        $item['design_brief'],
                        $item['notes'],
                        $item['price_breakdown'],
                        $item['total_price'],
                        $now,
                    ]
                );

                if (!empty($item['custom_quote_id'])) {
                    \Database::query(
                        "UPDATE custom_quote_requests SET payment_status=?, status=?, order_id=?, updated_at=NOW() WHERE id=? AND (order_id IS NULL OR order_id=?)",
                        [($params['payment_status'] ?? 'pending') === 'paid' ? 'paid' : 'payment_pending', ($params['payment_status'] ?? 'pending') === 'paid' ? 'converted_to_order' : 'payment_pending', $dbOrderId, (int)$item['custom_quote_id'], $dbOrderId]
                    );
                }

                // Link artwork files
                if (!empty($item['id'])) {
                    \Database::query(
                        "UPDATE artwork_files SET order_item_id = ?, cart_item_id = NULL
                         WHERE cart_item_id = ?",
                        [$orderItemId, $item['id']]
                    );
                }

                $customerArtwork = \Database::row(
                    "SELECT id FROM artwork_files WHERE order_item_id = ? ORDER BY id DESC LIMIT 1",
                    [$orderItemId]
                );
                self::ensureDesignApprovalForItem(
                    (int)$dbOrderId,
                    (int)$orderItemId,
                    (string)($item['design_choice'] ?? 'upload'),
                    $customerArtwork ? (int)$customerArtwork['id'] : null
                );
            }

            // Status history
            \Database::insert(
                "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
                 VALUES (?, 'new_order', 'Order placed', ?, ?)",
                [$dbOrderId, 'system', $now]
            );

            // Mark coupon as used
            if ($couponCode && $totals['coupon']) {
                \Database::query(
                    "UPDATE coupons SET used_count = used_count + 1 WHERE code = ?",
                    [$couponCode]
                );
                \Database::insert(
                    "INSERT INTO coupon_uses (coupon_id, order_id, user_id, discount_applied, used_at)
                     VALUES (?, ?, ?, ?, ?)",
                    [$totals['coupon']['id'], $dbOrderId, $user['id'], $totals['discount'], $now]
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Order placement failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Order placement failed. Please try again.'];
        }

        // Non-critical operations after commit should not fail checkout.
        try {
            \Cart\Cart::clear($onlyCustomQuoteId ?: null);
        } catch (\Throwable $e) {
            error_log('Order placed but cart clear failed: ' . $e->getMessage());
        }

        $order = null;
        try {
            $order = self::getOrder((int)$dbOrderId);
        } catch (\Throwable $e) {
            error_log('Order placed but order fetch failed: ' . $e->getMessage());
        }

        if (!$order) {
            $order = ['id' => (int)$dbOrderId, 'order_id' => $orderId, 'items' => []];
        }

        // Async tasks (non-blocking)
        self::afterOrderPlaced($order);
        self::saveUserShippingDefault((int)$user['id'], $shipping, $saveShippingDefault);

        return ['ok' => true, 'order' => $order, 'order_id' => $orderId];
    }

    public static function updateStatus(int $orderId, string $status, string $note = '', string $actor = 'admin'): bool
    {
        self::ensureWorkflowSchema();

        $validStatuses = ['new_order', 'received', 'design_approved', 'printing', 'other_process', 'processing', 'ready', 'delivered', 'cancelled', 'whatsapp_pending'];
        if (!in_array($status, $validStatuses)) return false;

        \Database::query(
            "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $orderId]
        );
        if ($actor !== 'system') {
            self::clearCustomerUpdate($orderId);
            self::markAdminUpdate($orderId, 'order_status_updated');
        }

        \Database::insert(
            "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$orderId, $status, $note, $actor]
        );

        // Audit log
        if (\Auth\Auth::isAdmin()) {
            AdminAudit::log('order_status_update', "Order #{$orderId} → {$status}");
        }

        // Google Sheets sync
        $order = self::getOrder($orderId);
        if ($order) {
            \Sheets\SheetsSync::syncStatusUpdate($order);
            // Notify customer via email
            \Email\Mailer::sendStatusUpdate($order);
        }

        return true;
    }

    public static function ensureDesignApprovalForItem(int $orderId, int $orderItemId, string $designChoice, ?int $customerArtworkFileId = null): void
    {
        if (!self::ensureDesignApprovalSchema()) return;
        $designChoice = $designChoice === 'rcs' ? 'rcs' : 'upload';
        $existingApproval = \Database::row("SELECT id, status, customer_artwork_file_id FROM order_design_approvals WHERE order_item_id = ?", [$orderItemId]);
        \Database::query(
            "INSERT INTO order_design_approvals
                (order_id, order_item_id, design_choice, status, customer_artwork_file_id, created_at)
             VALUES (?, ?, ?, 'pending_review', ?, NOW())
             ON DUPLICATE KEY UPDATE
                design_choice = VALUES(design_choice),
                customer_artwork_file_id = COALESCE(order_design_approvals.customer_artwork_file_id, VALUES(customer_artwork_file_id)),
                updated_at = NOW()",
            [$orderId, $orderItemId, $designChoice, $customerArtworkFileId]
        );
        $approval = \Database::row("SELECT id, status, customer_artwork_file_id FROM order_design_approvals WHERE order_item_id = ?", [$orderItemId]);
        self::recordDesignEvent([
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'design_approval_id' => (int)($approval['id'] ?? 0),
            'event_type' => $existingApproval ? 'design_approval_synced' : 'design_approval_created',
            'actor_type' => 'system',
            'status_before' => $existingApproval['status'] ?? null,
            'status_after' => $approval['status'] ?? 'pending_review',
            'file_id' => $customerArtworkFileId,
            'file_role' => $customerArtworkFileId ? 'customer_artwork' : null,
            'note' => $customerArtworkFileId ? 'Customer artwork linked to order item.' : 'Design approval workflow started.',
            'meta' => ['design_choice' => $designChoice],
        ]);
    }

    public static function updateDesignApproval(int $approvalId, string $status, string $adminNote = '', ?int $proofFileId = null): bool
    {
        if (!self::ensureDesignApprovalSchema()) return false;
        $valid = ['pending_review', 'issue_found', 'proof_uploaded', 'revision_requested', 'approved'];
        if (!in_array($status, $valid, true)) return false;

        $approval = \Database::row("SELECT * FROM order_design_approvals WHERE id = ?", [$approvalId]);
        if (!$approval) return false;

        $sets = ['status = ?', 'admin_note = ?', 'updated_at = NOW()'];
        $params = [$status, $adminNote];
        if ($proofFileId !== null) {
            $sets[] = 'proof_file_id = ?';
            $params[] = $proofFileId;
        }
        if ($status === 'approved') {
            $sets[] = 'approved_at = NOW()';
        } elseif (in_array($status, ['pending_review', 'issue_found', 'proof_uploaded', 'revision_requested'], true)) {
            $sets[] = 'approved_at = NULL';
        }
        if ($status === 'proof_uploaded') {
            $sets[] = 'customer_note = NULL';
        }
        $params[] = $approvalId;
        \Database::query("UPDATE order_design_approvals SET " . implode(', ', $sets) . " WHERE id = ?", $params);

        $admin = \Auth\Auth::admin();
        $eventType = match ($status) {
            'issue_found' => 'admin_issue_marked',
            'proof_uploaded' => 'admin_proof_uploaded',
            'approved' => 'admin_design_approved',
            'revision_requested' => 'revision_requested',
            default => 'design_status_changed',
        };
        $eventFileId = $proofFileId;
        $eventFileRole = $proofFileId ? 'admin_proof' : null;
        if ($status === 'approved' && !$eventFileId) {
            $eventFileId = !empty($approval['proof_file_id']) ? (int)$approval['proof_file_id'] : (!empty($approval['customer_artwork_file_id']) ? (int)$approval['customer_artwork_file_id'] : null);
            $eventFileRole = !empty($approval['proof_file_id']) ? 'admin_proof' : (!empty($approval['customer_artwork_file_id']) ? 'customer_artwork' : null);
        }
        self::recordDesignEvent([
            'order_id' => (int)$approval['order_id'],
            'order_item_id' => (int)$approval['order_item_id'],
            'design_approval_id' => $approvalId,
            'event_type' => $eventType,
            'actor_type' => $admin ? 'admin' : 'system',
            'actor_id' => $admin ? (int)$admin['id'] : null,
            'actor_name' => $admin['name'] ?? null,
            'status_before' => (string)($approval['status'] ?? ''),
            'status_after' => $status,
            'file_id' => $eventFileId,
            'file_role' => $eventFileRole,
            'note' => $adminNote,
        ]);
        if ($admin) {
            self::clearCustomerUpdate((int)$approval['order_id']);
            if (in_array($status, ['issue_found', 'proof_uploaded', 'approved'], true)) {
                $updateType = match ($status) { 'proof_uploaded' => 'admin_proof_uploaded', 'issue_found' => 'admin_issue_marked', default => 'admin_design_approved' };
                self::markAdminUpdate((int)$approval['order_id'], $updateType);
            }
        }

        self::syncOrderDesignApproved((int)$approval['order_id']);
        return true;
    }

    public static function customerDesignDecision(int $approvalId, int $userId, string $decision, string $customerNote = ''): array
    {
        if (!self::ensureDesignApprovalSchema()) return ['ok' => false, 'msg' => 'Design approval system is not ready.'];
        if ($approvalId <= 0 || $userId <= 0) return ['ok' => false, 'msg' => 'Invalid request.'];

        $approval = \Database::row(
            "SELECT oda.*, o.user_id
             FROM order_design_approvals oda
             INNER JOIN orders o ON o.id = oda.order_id
             WHERE oda.id = ? AND o.user_id = ?
             LIMIT 1",
            [$approvalId, $userId]
        );
        if (!$approval) return ['ok' => false, 'msg' => 'Design approval not found.'];
        if (empty($approval['proof_file_id'])) return ['ok' => false, 'msg' => 'Corrected design file is not uploaded yet.'];
        if ((string)($approval['status'] ?? '') !== 'proof_uploaded') {
            return ['ok' => false, 'msg' => 'This corrected design is not waiting for customer review.'];
        }

        if ($decision === 'approve') {
            $note = trim($customerNote) !== '' ? trim($customerNote) : 'Approved by customer.';
            \Database::query(
                "UPDATE order_design_approvals
                    SET status = 'approved', customer_note = ?, approved_at = NOW(), updated_at = NOW()
                  WHERE id = ?",
                [$note, $approvalId]
            );
            $user = \Auth\Auth::user();
            self::recordDesignEvent([
                'order_id' => (int)$approval['order_id'],
                'order_item_id' => (int)$approval['order_item_id'],
                'design_approval_id' => $approvalId,
                'event_type' => 'customer_design_approved',
                'actor_type' => 'customer',
                'actor_id' => $userId,
                'actor_name' => $user['name'] ?? null,
                'status_before' => (string)($approval['status'] ?? ''),
                'status_after' => 'approved',
                'file_id' => isset($approval['proof_file_id']) ? (int)$approval['proof_file_id'] : null,
                'file_role' => 'admin_proof',
                'note' => $note,
            ]);
            self::markCustomerUpdate((int)$approval['order_id'], 'customer_design_approved');
            self::clearAdminUpdate((int)$approval['order_id']);
            self::syncOrderDesignApproved((int)$approval['order_id']);
            return ['ok' => true, 'msg' => 'Design approved successfully.'];
        }

        if ($decision === 'revision') {
            $note = trim($customerNote);
            if ($note === '') return ['ok' => false, 'msg' => 'Please describe the required revision.'];
            \Database::query(
                "UPDATE order_design_approvals
                    SET status = 'revision_requested', customer_note = ?, approved_at = NULL, updated_at = NOW()
                  WHERE id = ?",
                [$note, $approvalId]
            );
            $user = \Auth\Auth::user();
            self::recordDesignEvent([
                'order_id' => (int)$approval['order_id'],
                'order_item_id' => (int)$approval['order_item_id'],
                'design_approval_id' => $approvalId,
                'event_type' => 'customer_revision_requested',
                'actor_type' => 'customer',
                'actor_id' => $userId,
                'actor_name' => $user['name'] ?? null,
                'status_before' => (string)($approval['status'] ?? ''),
                'status_after' => 'revision_requested',
                'file_id' => isset($approval['proof_file_id']) ? (int)$approval['proof_file_id'] : null,
                'file_role' => 'admin_proof',
                'note' => $note,
            ]);
            self::markCustomerUpdate((int)$approval['order_id'], 'customer_revision_requested');
            self::clearAdminUpdate((int)$approval['order_id']);
            return ['ok' => true, 'msg' => 'Revision request sent to admin.'];
        }

        return ['ok' => false, 'msg' => 'Invalid design action.'];
    }

    public static function syncOrderDesignApproved(int $orderId): void
    {
        if (!self::ensureDesignApprovalSchema()) return;
        $row = \Database::row(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved
             FROM order_design_approvals
             WHERE order_id = ?",
            [$orderId]
        );
        if ((int)($row['total'] ?? 0) > 0 && (int)($row['total'] ?? 0) === (int)($row['approved'] ?? 0)) {
            $order = \Database::row("SELECT status FROM orders WHERE id = ?", [$orderId]);
            if ($order && in_array((string)$order['status'], ['new_order', 'received'], true)) {
                self::updateStatus($orderId, 'design_approved', 'All designs approved', 'system');
            }
        } else {
            $order = \Database::row("SELECT status FROM orders WHERE id = ?", [$orderId]);
            if ($order && (string)($order['status'] ?? '') === 'design_approved') {
                self::updateStatus($orderId, 'received', 'Design approval pending for one or more items', 'system');
            }
        }
    }

    public static function getOrder(int $id): ?array
    {
        self::ensureDesignApprovalSchema();
        self::ensureInvoiceSchema();

        $order = \Database::row("SELECT * FROM orders WHERE id = ?", [$id]);
        if (!$order) return null;

        $order['items'] = \Database::rows(
            "SELECT oi.*,
                    COALESCE(pi.image_path, pi.url) AS product_image,
                    af.id AS artwork_file_id,
                    af.filename AS artwork_filename,
                    af.original_name AS artwork_original_name,
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
             WHERE oi.order_id = ?",
            [$id]
        );
        foreach ($order['items'] as &$item) {
            $item['attribute_selections'] = json_decode($item['attribute_selections'] ?? '[]', true);
            $item['price_breakdown'] = json_decode($item['price_breakdown'] ?? '{}', true);
        }

        $order['status_history'] = \Database::rows(
            "SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC",
            [$id]
        );

        return $order;
    }

    public static function getOrderByOrderId(string $orderId): ?array
    {
        $row = \Database::row("SELECT id FROM orders WHERE order_id = ?", [$orderId]);
        return $row ? self::getOrder((int)$row['id']) : null;
    }

    public static function getUserOrders(int $userId): array
    {
        self::ensureDesignApprovalSchema();
        self::ensureInvoiceSchema();

        $orders = \Database::rows(
            "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );
        foreach ($orders as &$order) {
            $order['items'] = \Database::rows(
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
                 WHERE oi.order_id = ?",
                [$order['id']]
            );
        }
        return $orders;
    }

    // ── After Order Hook ──────────────────────────────────────

    private static function afterOrderPlaced(array $order): void
    {
        try { \Email\Mailer::sendOrderConfirmation($order); } catch (\Throwable) {}
        try { \Sheets\SheetsSync::syncOrder($order); } catch (\Throwable) {}
    }

    // ── Helpers ───────────────────────────────────────────────

    private static function generateOrderId(): string
    {
        $count = \Database::row("SELECT COUNT(*) as c FROM orders")['c'] ?? 0;
        return 'RCS' . str_pad((string)((int)$count + 1001), 5, '0', STR_PAD_LEFT);
    }

    private static function sanitizeBilling(mixed $billing): ?array
    {
        if (!is_array($billing) || empty($billing['required'])) return null;

        $clean = [
            'legal_name'    => trim((string)($billing['legal_name'] ?? '')),
            'gst_no'        => strtoupper(trim((string)($billing['gst_no'] ?? ''))),
            'phone'         => trim((string)($billing['phone'] ?? '')),
            'email'         => strtolower(trim((string)($billing['email'] ?? ''))),
            'address_line1' => trim((string)($billing['address_line1'] ?? '')),
            'address_line2' => trim((string)($billing['address_line2'] ?? '')),
            'city'          => trim((string)($billing['city'] ?? '')),
            'state'         => trim((string)($billing['state'] ?? '')),
            'pincode'       => trim((string)($billing['pincode'] ?? '')),
        ];

        if ($clean['email'] !== '' && !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if ($clean['legal_name'] === '' || $clean['address_line1'] === '' ||
            $clean['city'] === '' || $clean['state'] === '' || $clean['pincode'] === '') {
            return null;
        }
        return $clean;
    }

    private static function sanitizeShipping(mixed $shipping): ?array
    {
        if (!is_array($shipping)) return null;

        $clean = [
            'business_name' => trim((string)($shipping['business_name'] ?? '')),
            'address_line1' => trim((string)($shipping['address_line1'] ?? '')),
            'address_line2' => trim((string)($shipping['address_line2'] ?? '')),
            'city'          => trim((string)($shipping['city'] ?? '')),
            'state'         => trim((string)($shipping['state'] ?? '')),
            'pincode'       => trim((string)($shipping['pincode'] ?? '')),
            'save_as_default' => !empty($shipping['save_as_default']),
        ];

        if ($clean['address_line1'] === '' || $clean['city'] === '' || $clean['state'] === '' || $clean['pincode'] === '') {
            return null;
        }
        return $clean;
    }

    private static function saveUserShippingDefault(int $userId, ?array $shipping, bool $save): void
    {
        if (!$save || !$shipping || $userId <= 0) return;
        try {
            \Database::query(
                "UPDATE users
                 SET shipping_address_line1 = ?, shipping_address_line2 = ?, shipping_city = ?, shipping_state = ?, shipping_pincode = ?, profile_updated_at = NOW()
                 WHERE id = ?",
                [
                    $shipping['address_line1'] ?? null,
                    $shipping['address_line2'] ?? null,
                    $shipping['city'] ?? null,
                    $shipping['state'] ?? null,
                    $shipping['pincode'] ?? null,
                    $userId,
                ]
            );
        } catch (\Throwable $e) {
            error_log('Could not save default shipping from checkout: ' . $e->getMessage());
        }
    }
}

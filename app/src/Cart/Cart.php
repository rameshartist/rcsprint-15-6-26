<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — Cart
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

namespace Cart;

class Cart
{
    private static ?bool $customQuoteSchemaReady = null;

    public static function ensureCustomQuoteSchema(): bool
    {
        if (self::$customQuoteSchemaReady !== null) return self::$customQuoteSchemaReady;
        try {
            foreach ([
                "ALTER TABLE cart_items MODIFY COLUMN product_id INT UNSIGNED NULL",
                "ALTER TABLE cart_items MODIFY COLUMN quality_id INT UNSIGNED NULL",
                "ALTER TABLE cart_items ADD COLUMN item_type VARCHAR(30) NOT NULL DEFAULT 'product' AFTER cart_id",
                "ALTER TABLE cart_items ADD COLUMN custom_quote_id INT UNSIGNED NULL AFTER item_type",
                "ALTER TABLE cart_items ADD COLUMN custom_quote_token VARCHAR(80) NULL AFTER custom_quote_id",
                "ALTER TABLE cart_items ADD COLUMN combo_offer_id INT UNSIGNED NULL AFTER custom_quote_token",
                "CREATE INDEX idx_cart_items_custom_quote ON cart_items (custom_quote_id)",
            ] as $sql) { try { \Database::query($sql); } catch (\Throwable) {} }
            self::$customQuoteSchemaReady = true;
        } catch (\Throwable $e) {
            error_log('Cart custom quote schema unavailable: ' . $e->getMessage());
            self::$customQuoteSchemaReady = false;
        }
        return self::$customQuoteSchemaReady;
    }

    // ── Core ──────────────────────────────────────────────────

    public static function add(array $data): array
    {
        self::ensureCustomQuoteSchema();
        $validation = self::validateItem($data);
        if (!$validation['ok']) return $validation;

        $priceInfo = Pricing::calculate(
            (int)$data['product_id'],
            (int)($data['quality_id'] ?? 1),
            (int)$data['quantity'],
            $data['attribute_selections'] ?? [],
            $data['design_choice'] ?? 'upload'
        );

        if (!$priceInfo['ok']) return $priceInfo;

        $item = [
            'product_id'          => (int)$data['product_id'],
            'quality_id'          => (int)($data['quality_id'] ?? 1),
            'quantity'            => (int)$data['quantity'],
            'attribute_selections'=> json_encode($data['attribute_selections'] ?? []),
            'design_choice'       => $data['design_choice'] ?? 'upload',
            'design_brief'        => $data['design_brief'] ?? '',
            'notes'               => $data['notes'] ?? '',
            'price_breakdown'     => json_encode($priceInfo['breakdown']),
            'total_price'         => $priceInfo['total'],
        ];

        $userId = \Auth\Auth::user()['id'] ?? null;

        if ($userId) {
            // Ensure DB cart exists
            $cart = \Database::row("SELECT id FROM carts WHERE user_id = ?", [$userId]);
            if (!$cart) {
                $cartId = \Database::insert("INSERT INTO carts (user_id, created_at) VALUES (?, NOW())", [$userId]);
            } else {
                $cartId = $cart['id'];
            }

            $cartItemId = \Database::insert(
                "INSERT INTO cart_items (cart_id, product_id, quality_id, quantity, attribute_selections,
                  design_choice, design_brief, notes, price_breakdown, total_price, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cartId,
                    $item['product_id'], $item['quality_id'], $item['quantity'],
                    $item['attribute_selections'],
                    $item['design_choice'], $item['design_brief'], $item['notes'],
                    $item['price_breakdown'], $item['total_price'],
                ]
            );

            // Handle artwork upload reference if provided
            if (!empty($data['artwork_id'])) {
                \Database::query(
                    "UPDATE artwork_files
                     SET cart_item_id = ?, uploaded_by = COALESCE(uploaded_by, ?)
                     WHERE id = ? AND (uploaded_by IS NULL OR uploaded_by = 0 OR uploaded_by = ?)",
                    [$cartItemId, $userId, $data['artwork_id'], $userId]
                );
            }

        } else {
            // Guest cart in session
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            $item['id']         = uniqid('ci_', true);
            $item['artwork_id'] = $data['artwork_id'] ?? null;
            $_SESSION['cart'][] = $item;
            $cartItemId = $item['id'];
        }

        return ['ok' => true, 'cart_item_id' => $cartItemId, 'price' => $priceInfo];
    }

    public static function addCustomQuote(array $quote, ?int $targetUserId = null): array
    {
        self::ensureCustomQuoteSchema();
        $quoteId = (int)($quote['id'] ?? 0);
        $baseAmount = (float)($quote['quoted_amount'] ?? 0);
        $designFee = max(0, (float)($quote['design_fee'] ?? 0));
        $amount = $baseAmount + $designFee;
        if ($quoteId <= 0) return ['ok' => false, 'msg' => 'Invalid custom quote.'];
        if ($amount <= 0) return ['ok' => false, 'msg' => 'Quote amount is not ready yet.'];
        if (in_array((string)($quote['status'] ?? ''), ['converted_to_order','closed','rejected'], true)) {
            return ['ok' => false, 'msg' => 'This custom quote is no longer available for checkout.'];
        }

        $item = [
            'item_type' => 'custom_quote',
            'custom_quote_id' => $quoteId,
            'custom_quote_token' => (string)($quote['quote_token'] ?? ''),
            'product_id' => null,
            'quality_id' => null,
            'quantity' => 1,
            'attribute_selections' => json_encode([
                'size_dimension' => (string)($quote['size_dimension'] ?? ''),
                'material_type' => (string)($quote['material_type'] ?? ''),
                'requested_quantity' => (string)($quote['quantity'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
            'design_choice' => 'rcs',
            'design_brief' => (string)($quote['instructions'] ?? ''),
            'notes' => (string)($quote['quote_note'] ?? ''),
            'price_breakdown' => json_encode([
                'base_price' => $baseAmount,
                'design_fee' => $designFee,
                'custom_quote_id' => $quoteId,
                'request_code' => (string)($quote['request_code'] ?? ''),
                'requested_quantity' => (string)($quote['quantity'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
            'total_price' => $amount,
        ];

        $userId = $targetUserId ?? (\Auth\Auth::user()['id'] ?? null);
        if ($userId) {
            $cart = \Database::row("SELECT id FROM carts WHERE user_id = ?", [$userId]);
            $cartId = $cart ? (int)$cart['id'] : (int)\Database::insert("INSERT INTO carts (user_id, created_at) VALUES (?, NOW())", [$userId]);
            $existing = \Database::row("SELECT id FROM cart_items WHERE cart_id=? AND custom_quote_id=? LIMIT 1", [$cartId, $quoteId]);
            if ($existing) {
                \Database::query("UPDATE cart_items SET item_type='custom_quote', quantity=1, attribute_selections=?, design_choice=?, design_brief=?, notes=?, price_breakdown=?, total_price=?, custom_quote_token=? WHERE id=?", [
                    $item['attribute_selections'], $item['design_choice'], $item['design_brief'], $item['notes'], $item['price_breakdown'], $item['total_price'], $item['custom_quote_token'], (int)$existing['id']
                ]);
                return ['ok' => true, 'cart_item_id' => (int)$existing['id'], 'already_exists' => true];
            }
            $cartItemId = \Database::insert(
                "INSERT INTO cart_items (cart_id, item_type, custom_quote_id, custom_quote_token, product_id, quality_id, quantity, attribute_selections, design_choice, design_brief, notes, price_breakdown, total_price, created_at)
                 VALUES (?, 'custom_quote', ?, ?, NULL, NULL, 1, ?, ?, ?, ?, ?, ?, NOW())",
                [$cartId, $quoteId, $item['custom_quote_token'], $item['attribute_selections'], $item['design_choice'], $item['design_brief'], $item['notes'], $item['price_breakdown'], $item['total_price']]
            );
            return ['ok' => true, 'cart_item_id' => (int)$cartItemId];
        }

        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        foreach ($_SESSION['cart'] as &$existing) {
            if ((int)($existing['custom_quote_id'] ?? 0) === $quoteId) {
                $existing = array_merge($existing, $item, ['id' => (string)($existing['id'] ?? uniqid('ci_', true))]);
                return ['ok' => true, 'cart_item_id' => $existing['id'], 'already_exists' => true];
            }
        }
        $item['id'] = uniqid('ci_', true);
        $_SESSION['cart'][] = $item;
        return ['ok' => true, 'cart_item_id' => $item['id']];
    }

    public static function addComboOffer(int $comboId): array
    {
        self::ensureCustomQuoteSchema();
        $combo = \Combos\ComboOfferManager::find($comboId);
        if (!$combo || empty($combo['is_active'])) return ['ok'=>false,'msg'=>'Combo offer is unavailable.'];
        $userId = \Auth\Auth::user()['id'] ?? null;
        $snapshot = json_encode(['combo_offer_id'=>$comboId,'regular_price'=>(float)$combo['regular_price'],'saving'=>max(0,(float)$combo['regular_price']-(float)$combo['combo_price']),'items'=>$combo['items']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (!$userId) {
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            foreach ($_SESSION['cart'] as &$existing) {
                if ((int)($existing['combo_offer_id'] ?? 0) === $comboId) { $existing['price_breakdown']=$snapshot; $existing['total_price']=(float)$combo['combo_price']; return ['ok'=>true,'cart_item_id'=>$existing['id'],'already_exists'=>true]; }
            }
            $item=['id'=>uniqid('ci_',true),'item_type'=>'combo_offer','combo_offer_id'=>$comboId,'product_id'=>null,'quality_id'=>null,'quantity'=>1,'attribute_selections'=>'[]','design_choice'=>'combo','design_brief'=>'','notes'=>'','price_breakdown'=>$snapshot,'total_price'=>(float)$combo['combo_price'],'product_name'=>(string)$combo['title'],'quality_name'=>'Combo Offer','product_image'=>(string)$combo['banner_image']];
            $_SESSION['cart'][]=$item; return ['ok'=>true,'cart_item_id'=>$item['id']];
        }
        $cart = \Database::row("SELECT id FROM carts WHERE user_id=?", [$userId]);
        $cartId = $cart ? (int)$cart['id'] : (int)\Database::insert("INSERT INTO carts(user_id,created_at) VALUES(?,NOW())",[$userId]);
        $existing=\Database::row("SELECT id FROM cart_items WHERE cart_id=? AND combo_offer_id=?",[$cartId,$comboId]);
        if($existing){\Database::query("UPDATE cart_items SET item_type='combo_offer',quantity=1,price_breakdown=?,total_price=? WHERE id=?",[$snapshot,(float)$combo['combo_price'],(int)$existing['id']]);return ['ok'=>true,'cart_item_id'=>(int)$existing['id'],'already_exists'=>true];}
        $id=\Database::insert("INSERT INTO cart_items(cart_id,item_type,combo_offer_id,product_id,quality_id,quantity,attribute_selections,design_choice,design_brief,notes,price_breakdown,total_price,created_at) VALUES(?,'combo_offer',?,NULL,NULL,1,'[]','combo','','',?,?,NOW())",[$cartId,$comboId,$snapshot,(float)$combo['combo_price']]);
        return ['ok'=>true,'cart_item_id'=>(int)$id];
    }

    public static function remove(string $itemId): array
    {
        $userId = \Auth\Auth::user()['id'] ?? null;

        if ($userId) {
            $item = \Database::row(
                "SELECT ci.id, ci.item_type FROM cart_items ci
                 JOIN carts c ON ci.cart_id = c.id
                 WHERE ci.id = ? AND c.user_id = ?",
                [$itemId, $userId]
            );
            if (!$item) return ['ok' => false, 'msg' => 'Item not found'];
            if (($item['item_type'] ?? 'product') === 'custom_quote') {
                return ['ok' => false, 'msg' => 'A custom order stays in your cart until its payment is completed.'];
            }
            \Database::query("DELETE FROM cart_items WHERE id = ?", [$itemId]);
        } else {
            foreach (($_SESSION['cart'] ?? []) as $item) {
                if (($item['id'] ?? '') === $itemId && ($item['item_type'] ?? 'product') === 'custom_quote') {
                    return ['ok' => false, 'msg' => 'A custom order stays in your cart until its payment is completed.'];
                }
            }
            $_SESSION['cart'] = array_filter(
                $_SESSION['cart'] ?? [],
                fn($i) => $i['id'] !== $itemId
            );
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }

        return ['ok' => true];
    }

    public static function updateQuantity(string $itemId, int $quantity): array
    {
        if ($quantity <= 0) {
            return ['ok' => false, 'msg' => 'Invalid quantity'];
        }

        $userId = \Auth\Auth::user()['id'] ?? null;

        if ($userId) {
            $item = \Database::row(
                "SELECT ci.* FROM cart_items ci
                 JOIN carts c ON ci.cart_id = c.id
                 WHERE ci.id = ? AND c.user_id = ?",
                [$itemId, $userId]
            );
            if (!$item) return ['ok' => false, 'msg' => 'Item not found'];
            if (($item['item_type'] ?? 'product') === 'custom_quote') return ['ok' => false, 'msg' => 'Custom quote quantity cannot be changed from cart.'];

            $priceInfo = Pricing::calculate(
                (int)$item['product_id'],
                (int)($item['quality_id'] ?? 1),
                $quantity,
                json_decode((string)($item['attribute_selections'] ?? '[]'), true) ?: [],
                (string)($item['design_choice'] ?? 'upload')
            );
            if (!$priceInfo['ok']) return $priceInfo;

            \Database::query(
                "UPDATE cart_items SET quantity = ?, price_breakdown = ?, total_price = ? WHERE id = ?",
                [$quantity, json_encode($priceInfo['breakdown']), $priceInfo['total'], $itemId]
            );

            return ['ok' => true, 'price' => $priceInfo];
        }

        $items = $_SESSION['cart'] ?? [];
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $itemId) continue;
            if (($item['item_type'] ?? 'product') === 'custom_quote') return ['ok' => false, 'msg' => 'Custom quote quantity cannot be changed from cart.'];

            $priceInfo = Pricing::calculate(
                (int)$item['product_id'],
                (int)($item['quality_id'] ?? 1),
                $quantity,
                json_decode((string)($item['attribute_selections'] ?? '[]'), true) ?: [],
                (string)($item['design_choice'] ?? 'upload')
            );
            if (!$priceInfo['ok']) return $priceInfo;

            $_SESSION['cart'][$idx]['quantity'] = $quantity;
            $_SESSION['cart'][$idx]['price_breakdown'] = json_encode($priceInfo['breakdown']);
            $_SESSION['cart'][$idx]['total_price'] = $priceInfo['total'];

            return ['ok' => true, 'price' => $priceInfo];
        }

        return ['ok' => false, 'msg' => 'Item not found'];
    }

    public static function get(): array
    {
        \Combos\ComboOfferManager::ensureSchema();
        $userId = \Auth\Auth::user()['id'] ?? null;

        if ($userId) {
            self::ensureCustomQuoteSchema();
            $items = \Database::rows(
                "SELECT ci.*, COALESCE(cqr.product_name, p.name) as product_name, p.slug,
                        p.category_id,
                        CASE WHEN ci.item_type='custom_quote' THEN 'Custom Quote' ELSE 'Standard' END as quality_name,
                        pi.url as product_image,
                        cqr.request_code AS custom_quote_code, co.title AS combo_offer_title, co.banner_image AS combo_offer_image,
                        cqr.size_dimension AS custom_size_dimension,
                        cqr.material_type AS custom_material_type,
                        cqr.quantity AS custom_requested_quantity
                 FROM cart_items ci
                 JOIN carts c ON ci.cart_id = c.id
                 LEFT JOIN products p ON ci.product_id = p.id
                 LEFT JOIN custom_quote_requests cqr ON cqr.id = ci.custom_quote_id
                 LEFT JOIN combo_offers co ON co.id = ci.combo_offer_id
                 LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                 LEFT JOIN artwork_files af ON af.cart_item_id = ci.id
                 WHERE c.user_id = ?
                 ORDER BY ci.created_at ASC",
                [$userId]
            );
            foreach ($items as &$item) { self::normalizeCustomQuoteItem($item); self::normalizeComboOfferItem($item); }
            return $items;
        }

        // Guest cart — enrich with product data
        $items = $_SESSION['cart'] ?? [];
        foreach ($items as &$item) {
            if (($item['item_type'] ?? 'product') === 'custom_quote') {
                self::normalizeCustomQuoteItem($item);
                continue;
            }
            $prod = \Database::row(
                "SELECT p.name as product_name, p.slug, pi.url as product_image,
                        p.category_id,
                        'Standard' as quality_name
                 FROM products p
                 LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                 WHERE p.id = ?",
                [$item['product_id']]
            );
            if ($prod) $item = array_merge($item, $prod);
        }
        return $items;
    }

    /** Remove the full purchased cart after a verified payment. */
    public static function clearPurchased(): void
    {
        $userId = \Auth\Auth::user()['id'] ?? null;
        if ($userId) {
            $cart = \Database::row("SELECT id FROM carts WHERE user_id = ?", [$userId]);
            if ($cart) \Database::query("DELETE FROM cart_items WHERE cart_id = ?", [(int)$cart['id']]);
        }
        $_SESSION['cart'] = [];
    }

    public static function clear(?int $customQuoteId = null): void
    {
        $userId = \Auth\Auth::user()['id'] ?? null;
        if ($userId) {
            $cart = \Database::row("SELECT id FROM carts WHERE user_id = ?", [$userId]);
            if ($cart) {
                \Database::query($customQuoteId ? "DELETE FROM cart_items WHERE cart_id=? AND custom_quote_id=?" : "DELETE FROM cart_items WHERE cart_id=? AND COALESCE(item_type,'product')<>'custom_quote'", $customQuoteId ? [$cart['id'], $customQuoteId] : [$cart['id']]);
            }
        } else {
            $_SESSION['cart'] = $customQuoteId
                ? array_values(array_filter($_SESSION['cart'] ?? [], static fn($item) => (int)($item['custom_quote_id'] ?? 0) !== $customQuoteId))
                : array_values(array_filter($_SESSION['cart'] ?? [], static fn($item) => ($item['item_type'] ?? 'product') === 'custom_quote'));
        }
    }

    public static function totals(array $items, ?string $couponCode = null): array
    {
        $subtotal = array_sum(array_column($items, 'total_price'));
        $discount = 0;
        $coupon = null;

        if ($couponCode) {
            $coupon = Pricing::validateCoupon($couponCode, $subtotal, $items);
            if ($coupon['ok']) {
                $discount = $coupon['discount'];
                $coupon = $coupon['coupon'];
            }
        }

        $gstPct = (float)(\Database::setting('gst_percent', env('GST_PERCENT', '18')));
        $taxable = $subtotal - $discount;
        $gstAmt  = round($taxable * $gstPct / 100);

        // Shipping is collected manually before dispatch.
        // Keep checkout/order totals exclusive of shipping for now.
        $shippingMode = 'manual';
        $shipping = 0.0;
        $total   = $taxable + $gstAmt;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'gst_pct'  => $gstPct,
            'gst_amt'  => $gstAmt,
            'shipping' => $shipping,
            'shipping_mode' => $shippingMode,
            'total'    => $total,
            'coupon'   => $coupon,
        ];
    }

    // Merge guest session cart into DB cart on login
    public static function mergeGuestCart(int $userId): void
    {
        $guestItems = $_SESSION['cart'] ?? [];
        if (empty($guestItems)) return;

        $cart = \Database::row("SELECT id FROM carts WHERE user_id = ?", [$userId]);
        if (!$cart) {
            $cartId = \Database::insert("INSERT INTO carts (user_id, created_at) VALUES (?, NOW())", [$userId]);
        } else {
            $cartId = $cart['id'];
        }

        self::ensureCustomQuoteSchema();
        foreach ($guestItems as $item) {
            $isCustomQuote = ($item['item_type'] ?? 'product') === 'custom_quote';
            $newCartItemId = \Database::insert(
                "INSERT INTO cart_items (cart_id, item_type, custom_quote_id, custom_quote_token, product_id, quality_id, quantity, attribute_selections,
                  design_choice, design_brief, notes, price_breakdown, total_price, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cartId,
                    $isCustomQuote ? 'custom_quote' : 'product',
                    $isCustomQuote ? (int)($item['custom_quote_id'] ?? 0) : null,
                    $isCustomQuote ? (string)($item['custom_quote_token'] ?? '') : null,
                    $isCustomQuote ? null : (int)($item['product_id'] ?? 0),
                    $isCustomQuote ? null : (int)($item['quality_id'] ?? 1),
                    $isCustomQuote ? 1 : (int)($item['quantity'] ?? 0),
                    $item['attribute_selections'] ?? '[]',
                    $item['design_choice'] ?? 'upload',
                    $item['design_brief'] ?? '',
                    $item['notes'] ?? '',
                    $item['price_breakdown'] ?? '{}',
                    $item['total_price'] ?? 0,
                ]
            );

            if (!$isCustomQuote && !empty($item['artwork_id'])) {
                \Database::query(
                    "UPDATE artwork_files
                     SET cart_item_id = ?, uploaded_by = COALESCE(uploaded_by, ?)
                     WHERE id = ? AND (uploaded_by IS NULL OR uploaded_by = 0 OR uploaded_by = ?)",
                    [$newCartItemId, $userId, (int)$item['artwork_id'], $userId]
                );
            }
        }

        $_SESSION['cart'] = [];
    }

    private static function normalizeCustomQuoteItem(array &$item): void
    {
        if (($item['item_type'] ?? 'product') !== 'custom_quote') return;
        $attrs = is_array($item['attribute_selections'] ?? null) ? $item['attribute_selections'] : (json_decode((string)($item['attribute_selections'] ?? '{}'), true) ?: []);
        $item['product_name'] = $item['product_name'] ?: ('Custom Quote ' . ($item['custom_quote_code'] ?? ''));
        $item['slug'] = '';
        $item['product_image'] = $item['product_image'] ?: '/assets/images/RCS%20PRINT%20LOGO.png';
        $item['quality_name'] = 'Custom Quote';
        $item['quantity'] = 1;
        $item['custom_size_dimension'] = $item['custom_size_dimension'] ?? ($attrs['size_dimension'] ?? '');
        $item['custom_material_type'] = $item['custom_material_type'] ?? ($attrs['material_type'] ?? '');
        $item['custom_requested_quantity'] = $item['custom_requested_quantity'] ?? ($attrs['requested_quantity'] ?? '');
    }

    private static function normalizeComboOfferItem(array &$item): void
    {
        if (($item['item_type'] ?? '') !== 'combo_offer') return;
        $item['product_name'] = $item['combo_offer_title'] ?: 'Combo Offer';
        $item['product_image'] = $item['combo_offer_image'] ?: '/assets/images/RCS%20PRINT%20LOGO.png';
        $item['quality_name'] = 'Combo Offer'; $item['slug']=''; $item['quantity']=1;
    }

    private static function validateItem(array $d): array
    {
        if (empty($d['product_id'])) return ['ok' => false, 'msg' => 'Product required'];
        if (empty($d['quantity']))   return ['ok' => false, 'msg' => 'Quantity required'];
        if (empty($d['design_choice'])) return ['ok' => false, 'msg' => 'Design choice required'];
        return ['ok' => true];
    }
}

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

        if(($data['design_choice']??'')==='upload'&&!empty($data['artwork_id'])){
            $artworkId=(int)$data['artwork_id'];$artwork=\Database::row("SELECT id,uploaded_by,cart_item_id FROM artwork_files WHERE id=?",[$artworkId]);$userId=(int)(\Auth\Auth::user()['id']??0);
            $owned=$userId>0?($artwork&&(int)($artwork['uploaded_by']??0)===$userId):($artwork&&in_array($artworkId,array_map('intval',(array)($_SESSION['guest_artwork_ids']??[])),true));
            if(!$owned||!empty($artwork['cart_item_id']))return ['ok'=>false,'msg'=>'The selected design upload is invalid or already in use.'];
        }

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

    public static function addComboOffer(int $comboId, array $options = []): array
    {
        self::ensureCustomQuoteSchema();
        $combo = \Combos\ComboOfferManager::find($comboId);
        if (!$combo || empty($combo['is_active'])) return ['ok'=>false,'msg'=>'Combo offer is unavailable.'];
        $allowedItemKeys=[];
        $designFees=[];
        $requiredQuantities=[];
        foreach($combo['items'] as $row){$key='product-'.(int)$row['id'];$allowedItemKeys[$key]=true;$designFees[$key]=max(0,(float)($row['design_fee']??0));$requiredQuantities[$key]=max(1,(int)$row['quantity']);}
        foreach(($combo['custom_items']??[]) as $row){$key='custom-'.(int)$row['id'];$allowedItemKeys[$key]=true;$designFees[$key]=max(0,(float)($row['design_fee']??0));$requiredQuantities[$key]=max(1,(int)($row['quantity']??1));}
        $submittedDesigns=is_array($options['item_designs']??null)?$options['item_designs']:[];
        $submittedQuantities=is_array($options['item_quantities']??null)?$options['item_quantities']:[];
        if(!$submittedDesigns){$submittedDesigns=['combo'=>['design_choice'=>$options['design_choice']??'upload','design_brief'=>$options['design_brief']??'','artwork_id'=>$options['artwork_id']??null,'upload_later'=>false]];$allowedItemKeys=['combo'=>true];}
        $userId = \Auth\Auth::user()['id'] ?? null;
        $itemDesigns=[];$artworkIds=[];
        foreach($allowedItemKeys as $key=>$_){
            if(isset($requiredQuantities[$key])&&(int)($submittedQuantities[$key]??0)!==$requiredQuantities[$key])return ['ok'=>false,'msg'=>'Select the configured quantity for every combo item.'];
            $design=is_array($submittedDesigns[$key]??null)?$submittedDesigns[$key]:[];
            $choice=in_array(($design['design_choice']??'upload'),['upload','rcs'],true)?(string)$design['design_choice']:'upload';
            $uploadLater=!empty($design['upload_later']);$artworkId=$choice==='upload'&&!$uploadLater?(int)($design['artwork_id']??0):0;
            if($choice==='upload'&&!$uploadLater&&$artworkId<=0)return ['ok'=>false,'msg'=>'Upload a design or select “Upload Later” for every combo item.'];
            if($artworkId>0){
            $artwork = \Database::row("SELECT id,uploaded_by,cart_item_id FROM artwork_files WHERE id=?", [$artworkId]);
            $guestArtworkIds = array_map('intval', (array)($_SESSION['guest_artwork_ids'] ?? []));
            $ownsArtwork = $userId
                ? ($artwork && (int)($artwork['uploaded_by'] ?? 0) === (int)$userId)
                : ($artwork && in_array($artworkId, $guestArtworkIds, true));
            if (!$ownsArtwork || !empty($artwork['cart_item_id'])) return ['ok'=>false,'msg'=>'The selected design upload is invalid or already in use.'];
                $artworkIds[]=$artworkId;
            }
            $itemDesigns[$key]=['design_choice'=>$choice,'artwork_id'=>$artworkId?:null,'upload_later'=>$uploadLater,'design_fee'=>$choice==='rcs'?(float)($designFees[$key]??0):0.0];
        }
        $choices=array_column($itemDesigns,'design_choice');$designChoice=$choices&&count(array_unique($choices))===1&&$choices[0]==='rcs'?'rcs':'upload';
        $designBrief='';$designFeeTotal=array_sum(array_column($itemDesigns,'design_fee'));$comboTotal=round((float)$combo['combo_price']+$designFeeTotal,2);
        $snapshot = json_encode(['combo_offer_id'=>$comboId,'regular_price'=>(float)$combo['regular_price'],'base_price'=>(float)$combo['combo_price'],'combo_base_price'=>(float)$combo['combo_price'],'design_fee'=>$designFeeTotal,'design_fee_total'=>$designFeeTotal,'discount_percent'=>(float)($combo['discount_percent']??0),'saving'=>max(0,(float)$combo['regular_price']-(float)$combo['combo_price']),'items'=>$combo['items'],'custom_items'=>$combo['custom_items']??[],'item_designs'=>$itemDesigns], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (!$userId) {
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            foreach ($_SESSION['cart'] as &$existing) {
                if ((int)($existing['combo_offer_id'] ?? 0) === $comboId) {
                    $existing['price_breakdown'] = $snapshot;
                    $existing['total_price'] = $comboTotal;
                    $existing['design_choice'] = $designChoice;
                    $existing['design_brief'] = $designBrief;
                    $existing['artwork_id'] = $artworkIds[0] ?? null;
                    return ['ok'=>true,'cart_item_id'=>$existing['id'],'already_exists'=>true];
                }
            }
            $item=['id'=>uniqid('ci_',true),'artwork_id'=>$artworkIds[0]??null,'item_type'=>'combo_offer','combo_offer_id'=>$comboId,'product_id'=>null,'quality_id'=>null,'quantity'=>1,'attribute_selections'=>'[]','design_choice'=>$designChoice,'design_brief'=>$designBrief,'notes'=>'','price_breakdown'=>$snapshot,'total_price'=>$comboTotal,'product_name'=>(string)$combo['title'],'quality_name'=>'Combo Offer','product_image'=>(string)$combo['banner_image']];
            $_SESSION['cart'][]=$item; return ['ok'=>true,'cart_item_id'=>$item['id']];
        }
        $cart = \Database::row("SELECT id FROM carts WHERE user_id=?", [$userId]);
        $cartId = $cart ? (int)$cart['id'] : (int)\Database::insert("INSERT INTO carts(user_id,created_at) VALUES(?,NOW())",[$userId]);
        $existing=\Database::row("SELECT id FROM cart_items WHERE cart_id=? AND combo_offer_id=?",[$cartId,$comboId]);
        if($existing){$existingId=(int)$existing['id'];\Database::query("UPDATE cart_items SET item_type='combo_offer',quantity=1,design_choice=?,design_brief=?,price_breakdown=?,total_price=? WHERE id=?",[$designChoice,$designBrief,$snapshot,$comboTotal,$existingId]); \Database::query("UPDATE artwork_files SET cart_item_id=NULL WHERE cart_item_id=?",[$existingId]); foreach($artworkIds as $artworkId)\Database::query("UPDATE artwork_files SET cart_item_id=?,uploaded_by=COALESCE(uploaded_by,?) WHERE id=?",[$existingId,$userId,$artworkId]); return ['ok'=>true,'cart_item_id'=>$existingId,'already_exists'=>true];}
        $id=\Database::insert("INSERT INTO cart_items(cart_id,item_type,combo_offer_id,product_id,quality_id,quantity,attribute_selections,design_choice,design_brief,notes,price_breakdown,total_price,created_at) VALUES(?,'combo_offer',?,NULL,NULL,1,'[]',?,?,'',?,?,NOW())",[$cartId,$comboId,$designChoice,$designBrief,$snapshot,$comboTotal]);
        foreach($artworkIds as $artworkId)\Database::query("UPDATE artwork_files SET cart_item_id=?,uploaded_by=COALESCE(uploaded_by,?) WHERE id=?",[(int)$id,$userId,$artworkId]);
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
                        COALESCE(cqr.product_image, pi.url) as product_image,
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
            $isComboOffer = ($item['item_type'] ?? 'product') === 'combo_offer';
            $newCartItemId = \Database::insert(
                "INSERT INTO cart_items (cart_id, item_type, custom_quote_id, custom_quote_token, combo_offer_id, product_id, quality_id, quantity, attribute_selections,
                  design_choice, design_brief, notes, price_breakdown, total_price, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cartId,
                    $isCustomQuote ? 'custom_quote' : ($isComboOffer ? 'combo_offer' : 'product'),
                    $isCustomQuote ? (int)($item['custom_quote_id'] ?? 0) : null,
                    $isCustomQuote ? (string)($item['custom_quote_token'] ?? '') : null,
                    $isComboOffer ? (int)($item['combo_offer_id'] ?? 0) : null,
                    ($isCustomQuote || $isComboOffer) ? null : (int)($item['product_id'] ?? 0),
                    ($isCustomQuote || $isComboOffer) ? null : (int)($item['quality_id'] ?? 1),
                    ($isCustomQuote || $isComboOffer) ? 1 : (int)($item['quantity'] ?? 0),
                    $item['attribute_selections'] ?? '[]',
                    $item['design_choice'] ?? 'upload',
                    $item['design_brief'] ?? '',
                    $item['notes'] ?? '',
                    $item['price_breakdown'] ?? '{}',
                    $item['total_price'] ?? 0,
                ]
            );

            $artworkIds=[];
            if (!$isCustomQuote && !empty($item['artwork_id'])) $artworkIds[]=(int)$item['artwork_id'];
            if($isComboOffer){$breakdown=is_array($item['price_breakdown']??null)?$item['price_breakdown']:(json_decode((string)($item['price_breakdown']??'{}'),true)?:[]);foreach(($breakdown['item_designs']??[]) as $design)if(!empty($design['artwork_id']))$artworkIds[]=(int)$design['artwork_id'];}
            foreach(array_unique($artworkIds) as $artworkId) {
                \Database::query(
                    "UPDATE artwork_files
                     SET cart_item_id = ?, uploaded_by = COALESCE(uploaded_by, ?)
                     WHERE id = ? AND (uploaded_by IS NULL OR uploaded_by = 0 OR uploaded_by = ?)",
                    [$newCartItemId, $userId, $artworkId, $userId]
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
        $combo = \Combos\ComboOfferManager::find((int)($item['combo_offer_id'] ?? 0));
        if (!$combo || empty($combo['is_active'])) {
            $item['total_price'] = 0; $item['combo_unavailable'] = true;
        } else {
            $existingSnapshot=is_array($item['price_breakdown']??null)?$item['price_breakdown']:(json_decode((string)($item['price_breakdown']??'{}'),true)?:[]);
            $itemDesigns=$existingSnapshot['item_designs']??[];$designFeeTotal=array_sum(array_map(static fn($design)=>(float)($design['design_fee']??0),$itemDesigns));
            $snapshot = ['combo_offer_id'=>(int)$combo['id'],'regular_price'=>(float)$combo['regular_price'],'base_price'=>(float)$combo['combo_price'],'combo_base_price'=>(float)$combo['combo_price'],'design_fee'=>$designFeeTotal,'design_fee_total'=>$designFeeTotal,'discount_percent'=>(float)$combo['discount_percent'],'saving'=>max(0,(float)$combo['regular_price']-(float)$combo['combo_price']),'items'=>$combo['items'],'custom_items'=>$combo['custom_items']??[],'item_designs'=>$itemDesigns];
            $item['price_breakdown'] = json_encode($snapshot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $item['total_price'] = round((float)$combo['combo_price']+$designFeeTotal,2);
            $item['combo_offer_title'] = $combo['title']; $item['combo_offer_image'] = $combo['banner_image'];
        }
        $item['product_name'] = $item['combo_offer_title'] ?: 'Combo Offer';
        $item['product_image'] = $item['combo_offer_image'] ?: '/assets/images/RCS%20PRINT%20LOGO.png';
        $item['quality_name'] = 'Combo Offer'; $item['slug']=''; $item['quantity']=1;
    }

    private static function validateItem(array $d): array
    {
        if (empty($d['product_id'])) return ['ok' => false, 'msg' => 'Product required'];
        if (empty($d['quantity']))   return ['ok' => false, 'msg' => 'Quantity required'];
        $choice=(string)($d['design_choice']??'');
        if (!in_array($choice,['upload','rcs'],true)) return ['ok' => false, 'msg' => 'Design choice required'];
        $uploadLater=str_contains(strtolower((string)($d['design_brief']??'').' '.(string)($d['notes']??'')),'upload later');
        if($choice==='upload'&&!$uploadLater&&empty($d['artwork_id']))return ['ok'=>false,'msg'=>'Upload a design or select “Upload Design Later”.'];
        return ['ok' => true];
    }
}

<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — Pricing Engine
//  Total = selected quantity tier price + design fee (if RCS)
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

namespace Cart;

class Pricing
{
    public static function calculate(
        int    $productId,
        int    $qualityId,
        int    $quantity,
        array  $attributeSelections = [],
        string $designChoice = 'upload'
    ): array {
        $tier = \Database::row(
            "SELECT quantity, price FROM product_quantity_tiers WHERE product_id=? AND quantity=?",
            [$productId, $quantity]
        );

        if (!$tier) {
            return [
                'ok'  => false,
                'msg' => 'No pricing configured for selected quantity.',
            ];
        }

        $basePrice = (float)$tier['price'];

        $designFee = 0.0;
        if ($designChoice === 'rcs') {
            try {
                $productFee = \Database::row("SELECT design_fee FROM products WHERE id=?", [$productId]);
                if ($productFee && $productFee['design_fee'] !== null && $productFee['design_fee'] !== '') {
                    $designFee = (float)$productFee['design_fee'];
                } else {
                    $designFee = (float)\Database::setting('design_fee', '0');
                }
            } catch (\Throwable) {
                $designFee = (float)\Database::setting('design_fee', '0');
            }
        }

        $total = $basePrice + $designFee;

        return [
            'ok'    => true,
            'total' => $total,
            'breakdown' => [
                'base_price'   => $basePrice,
                'quality_name' => 'Standard',
                'quantity'     => (int)$tier['quantity'],
                'design_fee'   => $designFee,
                'design_choice'=> $designChoice,
                'attr_addons'  => 0,
                'attr_items'   => [],
                'total'        => $total,
            ],
        ];
    }

    public static function validateCoupon(string $code, float $subtotal, array $items = []): array
    {
        $coupon = \Database::row(
            "SELECT * FROM coupons WHERE code = ? AND is_active = 1",
            [strtoupper(trim($code))]
        );

        if (!$coupon) return ['ok' => false, 'msg' => 'Invalid coupon code.'];

        $orderNumber = (int)($coupon['order_number'] ?? 0);
        if ($orderNumber > 0) {
            $user = \Auth\Auth::user();
            if (!$user) return ['ok'=>false,'msg'=>'Please login to use this customer order offer.'];
            $completed = (int)(\Database::row("SELECT COUNT(*) c FROM orders WHERE user_id=? AND payment_status='paid' AND status<>'cancelled'",[(int)$user['id']])['c']??0);
            if ($completed !== $orderNumber - 1) return ['ok'=>false,'msg'=>'This offer is valid only on your '.$orderNumber.($orderNumber===1?'st':($orderNumber===2?'nd':'rd')).' order.'];
        }

        $today = date('Y-m-d');
        if ($coupon['valid_from']  && $coupon['valid_from']  > $today)
            return ['ok' => false, 'msg' => 'Coupon not yet active.'];
        if ($coupon['valid_until'] && $coupon['valid_until'] < $today)
            return ['ok' => false, 'msg' => 'Coupon has expired.'];
        $scopeType = (string)($coupon['scope_type'] ?? 'all');
        $scopeCategoryId = (int)($coupon['category_id'] ?? 0);
        $eligibleSubtotal = $subtotal;
        if ($scopeType === 'category' && $scopeCategoryId > 0) {
            $selectedProductIds = [];
            try {
                $selectedProductIds = array_map('intval', array_column(\Database::rows("SELECT product_id FROM coupon_products WHERE coupon_id=?", [(int)$coupon['id']]), 'product_id'));
            } catch (\Throwable) {}
            $eligibleSubtotal = 0.0;
            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                if ((int)($item['category_id'] ?? 0) === $scopeCategoryId && (!$selectedProductIds || in_array($productId, $selectedProductIds, true))) {
                    $eligibleSubtotal += (float)($item['total_price'] ?? 0);
                }
            }
            if ($eligibleSubtotal <= 0) {
                return ['ok' => false, 'msg' => 'Coupon is valid only for its selected products.'];
            }
        }

        if ($coupon['min_order_amount'] > 0 && $eligibleSubtotal < $coupon['min_order_amount'])
            return ['ok' => false, 'msg' => 'Minimum order ₹' . number_format($coupon['min_order_amount']) . ' required.'];
        if ($coupon['max_uses'] > 0 && $coupon['used_count'] >= $coupon['max_uses'])
            return ['ok' => false, 'msg' => 'Coupon usage limit reached.'];

        $discount = $coupon['discount_type'] === 'free_shipping' ? 0.0 : ($coupon['discount_type'] === 'percent'
            ? round($eligibleSubtotal * $coupon['discount_value'] / 100)
            : (float)$coupon['discount_value']);

        return [
            'ok'       => true,
            'discount' => min($discount, $eligibleSubtotal),
            'coupon'   => $coupon,
        ];
    }

    public static function productPricingData(int $productId): array
    {
        $tiers = \Database::rows(
            "SELECT id, quantity, price FROM product_quantity_tiers WHERE product_id=? ORDER BY quantity ASC",
            [$productId]
        );

        $slabs = array_map(fn($t) => [
            'quantity' => (int)$t['quantity'],
            'price' => (float)$t['price'],
        ], $tiers);

        $qualities = [];
        if (!empty($slabs)) {
            $qualities[] = [
                'id' => 1,
                'name' => 'Standard',
                'description' => 'Product quantity tiers',
                'sort_order' => 0,
                'slabs' => $slabs,
                'min_price' => min(array_column($slabs, 'price')),
            ];
        }

        $designFee = 0.0;
        try {
            $fee = \Database::row("SELECT design_fee FROM products WHERE id=?", [$productId]);
            if ($fee && $fee['design_fee'] !== null && $fee['design_fee'] !== '') $designFee = (float)$fee['design_fee'];
            else $designFee = (float)\Database::setting('design_fee', '0');
        } catch (\Throwable) {
            $designFee = (float)\Database::setting('design_fee', '0');
        }

        return [
            'qualities'   => $qualities,
            'attr_groups' => [],
            'tiers'       => $slabs,
            'design_fee'  => $designFee,
        ];
    }
}

<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — Razorpay Payment Handler (Server-Side)
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

namespace Payment;

class Razorpay
{
    private static string $apiBase = 'https://api.razorpay.com/v1';

    // ── Create Order (server-side) ────────────────────────────

    public static function createOrder(float $amount, string $receipt = '', array $notes = []): array
    {
        [$keyId, $keySecret] = self::credentials();
        if (!$keyId || !$keySecret) {
            return ['ok' => false, 'msg' => 'Razorpay not configured. Contact admin.'];
        }

        $payload = [
            'amount'   => (int)round($amount * 100), // Razorpay uses paise
            'currency' => 'INR',
            'receipt'  => $receipt ?: ('rcpt_' . uniqid()),
            'notes'    => $notes,
        ];

        $response = self::apiCall('POST', '/orders', $payload);

        if (!empty($response['id'])) {
            return [
                'ok'              => true,
                'razorpay_order_id' => $response['id'],
                'amount'          => $response['amount'],
                'currency'        => $response['currency'],
                'key_id'          => $keyId,
            ];
        }

        return ['ok' => false, 'msg' => $response['error']['description'] ?? 'Failed to create Razorpay order.'];
    }

    // ── Verify Payment Signature (CRITICAL — server-side only) ─

    public static function verifyPayment(
        string $razorpayOrderId,
        string $razorpayPaymentId,
        string $razorpaySignature
    ): bool {
        [, $keySecret] = self::credentials();
        if (!$keySecret) return false;

        $expectedSignature = hash_hmac(
            'sha256',
            $razorpayOrderId . '|' . $razorpayPaymentId,
            $keySecret
        );

        return hash_equals($expectedSignature, $razorpaySignature);
    }

    // ── Mark Order Paid ───────────────────────────────────────

    public static function handleSuccess(
        int $internalOrderId,
        string $razorpayOrderId,
        string $razorpayPaymentId,
        string $razorpaySignature
    ): array {
        \Orders\OrderManager::ensureWorkflowSchema();
        \Orders\OrderManager::ensureCustomOrderSchema();

        if (!self::verifyPayment($razorpayOrderId, $razorpayPaymentId, $razorpaySignature)) {
            // Log suspicious activity
            error_log("Razorpay signature verification FAILED for order {$internalOrderId}. Possible tampering.");
            return ['ok' => false, 'msg' => 'Payment verification failed.'];
        }

        // Idempotency: check not already recorded
        $existing = \Database::row(
            "SELECT id FROM payments WHERE razorpay_payment_id = ?",
            [$razorpayPaymentId]
        );
        if ($existing) {
            $order = self::findOrderByPaymentId($razorpayPaymentId);
            \Cart\Cart::clearPurchased();
            return ['ok' => true, 'already_processed' => true, 'order' => $order];
        }

        $db = \Database::get();
        $db->beginTransaction();
        try {
            // Record payment
            \Database::insert(
                "INSERT INTO payments (order_id, razorpay_order_id, razorpay_payment_id,
                    razorpay_signature, status, created_at)
                 VALUES (?, ?, ?, ?, 'captured', NOW())",
                [$internalOrderId, $razorpayOrderId, $razorpayPaymentId, $razorpaySignature]
            );

            // Update order
            \Database::query(
                "UPDATE orders SET payment_status = 'paid', payment_id = ?,
                    razorpay_order_id = ?, status = 'new_order', updated_at = NOW()
                 WHERE id = ?",
                [$razorpayPaymentId, $razorpayOrderId, $internalOrderId]
            );

            \Database::query(
                "UPDATE custom_quote_requests SET payment_status='paid', status='converted_to_order', order_id=?, customer_update_pending=1, customer_update_type='customer_paid', customer_update_at=NOW(), updated_at=NOW() WHERE order_id=?",
                [$internalOrderId, $internalOrderId]
            );

            \Database::insert(
                "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
                 VALUES (?, 'new_order', 'Payment captured; order moved to New Order queue', ?, NOW())",
                [$internalOrderId, 'system']
            );

            $db->commit();

            $order = \Orders\OrderManager::getOrder($internalOrderId);
            \Cart\Cart::clearPurchased();
            if ($order) {
                try { \Email\Mailer::sendPaymentSuccess($order); } catch (\Throwable) {}
                try { \Sheets\SheetsSync::syncOrder($order); } catch (\Throwable) {}
            }

            return ['ok' => true, 'order' => $order];

        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Payment recording failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Payment recorded but order update failed. Contact support with payment ID: ' . $razorpayPaymentId];
        }
    }

    public static function findOrderByPaymentId(string $razorpayPaymentId): ?array
    {
        if ($razorpayPaymentId === '') return null;

        $row = \Database::row(
            "SELECT o.id
             FROM payments p
             JOIN orders o ON o.id = p.order_id
             WHERE p.razorpay_payment_id = ?
             ORDER BY p.id DESC
             LIMIT 1",
            [$razorpayPaymentId]
        );

        return $row ? \Orders\OrderManager::getOrder((int)$row['id']) : null;
    }

    // ── Internal ──────────────────────────────────────────────

    private static function credentials(): array
    {
        $keyId     = \Database::setting('razorpay_key_id', env('RAZORPAY_KEY_ID', ''));
        $keySecret = \Database::setting('razorpay_key_secret', env('RAZORPAY_KEY_SECRET', ''));
        return [$keyId, $keySecret];
    }

    private static function apiCall(string $method, string $path, array $data = []): array
    {
        [$keyId, $keySecret] = self::credentials();

        $ch = curl_init(self::$apiBase . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "{$keyId}:{$keySecret}",
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($response ?: '{}', true) ?? [];
    }
}

<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — Mailer (SMTP + Brevo Marketing)
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

namespace Email;

class Mailer
{
    // ── Transactional Emails ──────────────────────────────────

    public static function sendWelcome(array $user): void
    {
        $biz = self::bizInfo();
        self::send(
            $user['email'],
            $user['name'],
            "Welcome to {$biz['name']}!",
            self::wrap("
                <h2>Welcome, {$user['name']}! 🎉</h2>
                <p>Thank you for joining <strong>{$biz['name']}</strong>.</p>
                <p>You can now browse our products, place orders and track them in real time.</p>
                <a href='{$biz['url']}' class='btn'>Start Ordering →</a>
            ")
        );
    }

    public static function sendOrderConfirmation(array $order): void
    {
        $biz = self::bizInfo();
        $itemsHtml = self::renderOrderItems($order['items'] ?? []);

        self::send(
            $order['customer_email'],
            $order['customer_name'],
            "Order Confirmed {$order['order_id']} — {$biz['name']}",
            self::wrap("
                <h2>Order Confirmed! 🖨️</h2>
                <p>Hi {$order['customer_name']}, your order <strong>{$order['order_id']}</strong> has been received.</p>
                {$itemsHtml}
                " . self::totalsTable($order) . "
                <p>We'll update you at each stage. Questions? WhatsApp us at {$biz['whatsapp']}.</p>
                <a href='{$biz['url']}/my-orders' class='btn'>Track Order →</a>
            ")
        );
    }

    public static function sendPaymentSuccess(array $order): void
    {
        $biz = self::bizInfo();
        self::send(
            $order['customer_email'],
            $order['customer_name'],
            "Payment Confirmed ✅ — {$order['order_id']}",
            self::wrap("
                <h2>Payment Received! ✅</h2>
                <p>Hi {$order['customer_name']}, your payment of <strong>₹" . number_format($order['total_amount']) . "</strong> has been confirmed.</p>
                <p><strong>Payment ID:</strong> {$order['payment_id']}</p>
                " . self::totalsTable($order) . "
                <a href='{$biz['url']}/my-orders' class='btn'>Track Your Order →</a>
            ")
        );
    }

    public static function sendStatusUpdate(array $order): void
    {
        $biz = self::bizInfo();
        $statusMessages = [
            'processing' => 'Your order is being processed by our team.',
            'printing'   => 'Your print job has started! 🖨️',
            'ready'      => 'Great news! Your order is ready for pickup/delivery.',
            'delivered'  => 'Your order has been delivered. Thank you for choosing us!',
            'cancelled'  => 'Your order has been cancelled. Please contact us for any queries.',
        ];

        $msg = $statusMessages[$order['status']] ?? 'Your order status has been updated.';

        self::send(
            $order['customer_email'],
            $order['customer_name'],
            "Order Update {$order['order_id']}: " . ucfirst($order['status']),
            self::wrap("
                <h2>Order Update 📋</h2>
                <p>Hi {$order['customer_name']},</p>
                <p>{$msg}</p>
                <p><strong>Order ID:</strong> {$order['order_id']}<br>
                   <strong>Status:</strong> " . ucfirst($order['status']) . "</p>
                <a href='{$biz['url']}/my-orders' class='btn'>View Order →</a>
            ")
        );
    }

    // ── Email Marketing (Brevo) ───────────────────────────────

    public static function marketingOptIn(array $user): void
    {
        $apiKey = env('BREVO_API_KEY', '');
        $listId = (int)env('BREVO_LIST_ID', '1');

        if (!$apiKey) return;

        try {
            self::brevoApiCall('POST', '/contacts', [
                'email'         => $user['email'],
                'attributes'    => [
                    'FIRSTNAME' => explode(' ', $user['name'])[0] ?? '',
                    'LASTNAME'  => implode(' ', array_slice(explode(' ', $user['name']), 1)) ?: '',
                    'SMS'       => $user['phone'],
                ],
                'listIds'       => [$listId],
                'updateEnabled' => true,
            ]);
        } catch (\Throwable $e) {
            error_log('Brevo opt-in failed: ' . $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────

    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $host     = env('SMTP_HOST', '');
        $apiKey   = env('BREVO_API_KEY', '');

        // Prefer Brevo transactional if configured
        if ($apiKey) {
            return self::sendViaBrevo($toEmail, $toName, $subject, $htmlBody);
        }

        // Fallback to raw SMTP via socket
        if ($host) {
            return self::sendViaSmtp($toEmail, $toName, $subject, $htmlBody);
        }

        // Log to file if neither configured
        $logDir = BASE_PATH . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents(
            $logDir . '/emails.log',
            date('Y-m-d H:i:s') . " TO:{$toEmail} SUBJ:{$subject}\n",
            FILE_APPEND
        );
        return false;
    }

    private static function sendViaBrevo(string $toEmail, string $toName, string $subject, string $html): bool
    {
        try {
            $biz = self::bizInfo();
            self::brevoApiCall('POST', '/smtp/email', [
                'sender'     => ['name' => $biz['name'], 'email' => env('SMTP_FROM_EMAIL', $biz['email'])],
                'to'         => [['email' => $toEmail, 'name' => $toName]],
                'subject'    => $subject,
                'htmlContent'=> $html,
            ]);
            return true;
        } catch (\Throwable $e) {
            error_log('Brevo send failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function sendViaSmtp(string $toEmail, string $toName, string $subject, string $html): bool
    {
        try {
            $biz      = self::bizInfo();
            $fromName = env('SMTP_FROM_NAME', $biz['name']);
            $fromAddr = env('SMTP_FROM_EMAIL', $biz['email']);

            $headers  = implode("\r\n", [
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromAddr}>",
                "Reply-To: {$fromAddr}",
            ]);

            return mail($toEmail, $subject, $html, $headers);
        } catch (\Throwable $e) {
            error_log('SMTP send failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function brevoApiCall(string $method, string $path, array $data): array
    {
        $apiKey = env('BREVO_API_KEY', '');
        $ch = curl_init('https://api.brevo.com/v3' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $resp = curl_exec($ch);
        curl_close($ch);
        return json_decode($resp ?: '{}', true) ?? [];
    }

    private static function bizInfo(): array
    {
        try {
            return [
                'name'      => \Database::setting('biz_name', env('APP_NAME', 'RCS Graphic')),
                'email'     => \Database::setting('biz_email', env('BIZ_EMAIL', '')),
                'phone'     => \Database::setting('biz_phone', env('BIZ_PHONE', '')),
                'whatsapp'  => \Database::setting('biz_whatsapp', env('BIZ_WHATSAPP', '')),
                'url'       => env('APP_URL', 'http://localhost:8000'),
            ];
        } catch (\Throwable) {
            return ['name' => 'RCS Graphic', 'email' => '', 'phone' => '', 'whatsapp' => '', 'url' => '/'];
        }
    }

    private static function renderOrderItems(array $items): string
    {
        if (!$items) return '';
        $rows = '';
        foreach ($items as $item) {
            $rows .= "<tr>
                <td style='padding:8px 12px;border-bottom:1px solid #eee'>{$item['product_name']}</td>
                <td style='padding:8px 12px;border-bottom:1px solid #eee'>{$item['quantity']} pcs</td>
                <td style='padding:8px 12px;border-bottom:1px solid #eee'>{$item['quality_name']}</td>
                <td style='padding:8px 12px;border-bottom:1px solid #eee;text-align:right'>₹" . number_format($item['total_price']) . "</td>
            </tr>";
        }
        return "<table style='width:100%;border-collapse:collapse;margin:16px 0'>
            <thead><tr style='background:#EEF3FD'>
                <th style='padding:9px 12px;text-align:left'>Product</th>
                <th style='padding:9px 12px;text-align:left'>Qty</th>
                <th style='padding:9px 12px;text-align:left'>Quality</th>
                <th style='padding:9px 12px;text-align:right'>Price</th>
            </tr></thead><tbody>{$rows}</tbody></table>";
    }

    private static function totalsTable(array $order): string
    {
        $discount = $order['discount_amount'] ?? 0;
        $discRow = $discount > 0
            ? "<tr><td>Discount</td><td style='color:green'>-₹" . number_format($discount) . "</td></tr>"
            : '';
        return "<table style='width:100%;max-width:260px;margin-left:auto;border-collapse:collapse'>
            <tr><td style='padding:5px 0'>Subtotal</td><td style='text-align:right'>₹" . number_format($order['subtotal']) . "</td></tr>
            {$discRow}
            <tr><td>GST ({$order['gst_percent']}%)</td><td style='text-align:right'>₹" . number_format($order['gst_amount']) . "</td></tr>
            <tr style='font-weight:700;font-size:16px;border-top:2px solid #1A56E8'><td>Total</td><td style='text-align:right;color:#1A56E8'>₹" . number_format($order['total_amount']) . "</td></tr>
        </table>";
    }

    private static function wrap(string $content): string
    {
        $biz = self::bizInfo();
        return "<!DOCTYPE html><html><head><style>
            body{font-family:Arial,sans-serif;color:#111;background:#f9fafb;margin:0;padding:0}
            .wrap{max-width:580px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)}
            .top{background:linear-gradient(135deg,#1A56E8,#1245C5);padding:22px 28px}
            .top h1{font-size:20px;color:#fff;margin:0}
            .body{padding:28px}
            .body h2{font-size:20px;color:#111;margin-top:0}
            .body p{font-size:14px;color:#374151;line-height:1.7}
            .btn{display:inline-block;background:#1A56E8;color:#fff;padding:12px 24px;border-radius:9px;text-decoration:none;font-weight:700;margin-top:10px}
            .footer{background:#F3F4F6;padding:14px 28px;text-align:center;font-size:12px;color:#9CA3AF}
        </style></head><body>
        <div class='wrap'>
            <div class='top'><h1>{$biz['name']}</h1></div>
            <div class='body'>{$content}</div>
            <div class='footer'>{$biz['name']} · {$biz['email']} · This is an automated email.</div>
        </div></body></html>";
    }
}

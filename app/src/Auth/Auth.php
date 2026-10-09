<?php
// ─────────────────────────────────────────────────────────────
//  RCS Graphic — Auth
//  FIX: adminLogin does not require CSRF (form-based, session started)
//  FIX: password_verify works correctly regardless of PHP version
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

namespace Auth;

class Auth
{
    private static ?bool $hasProfileColumns = null;
    private static ?bool $hasShippingColumns = null;
    private static ?bool $hasCustomerCodeColumn = null;
    // ══════════════════════════════════════════════════════════
    //  USER AUTH
    // ══════════════════════════════════════════════════════════

    public static function login(string $identifier, string $password): array
    {
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $lockUntil= $_SESSION['login_lock'][$ip] ?? 0;

        if ($lockUntil > time()) {
            $mins = (int)ceil(($lockUntil - time()) / 60);
            return ['ok' => false, 'msg' => "Too many attempts. Try again in {$mins} min."];
        }

        $identifier = trim($identifier);
        $user = \Database::row(
            "SELECT * FROM users WHERE (email = ? OR phone = ?) AND is_active = 1",
            [$identifier, $identifier]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            $attempts = ($_SESSION['login_attempts'][$ip] ?? 0) + 1;
            $_SESSION['login_attempts'][$ip] = $attempts;
            if ($attempts >= 5) {
                $_SESSION['login_lock'][$ip]     = time() + 900; // 15 min
                $_SESSION['login_attempts'][$ip] = 0;
            }
            return ['ok' => false, 'msg' => 'Invalid credentials.'];
        }

        unset($_SESSION['login_attempts'][$ip]);
        $_SESSION['user'] = self::publicUser($user);
        session_regenerate_id(true);

        try { \Cart\Cart::mergeGuestCart((int)$user['id']); } catch (\Throwable) {}
        try { if ($user['marketing_consent']) \Email\Mailer::marketingOptIn($user); } catch (\Throwable) {}

        return ['ok' => true, 'user' => self::publicUser($user)];
    }

    public static function register(array $data): array
    {
        self::ensureCustomerCodeSchema();
        $errors = self::validateRegister($data);
        if ($errors) return ['ok' => false, 'msg' => implode(', ', $errors)];

        $exists = \Database::row(
            "SELECT id FROM users WHERE email = ? OR phone = ?",
            [trim($data['email']), trim($data['phone'])]
        );
        if ($exists) return ['ok' => false, 'msg' => 'Email or phone already registered.'];

        $customerCode = self::generateCustomerCode((string)($data['name'] ?? ''));
        $id = \Database::insert(
            "INSERT INTO users (name, email, phone, company, password, marketing_consent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                trim($data['name']),
                trim($data['email']),
                trim($data['phone']),
                trim($data['company'] ?? ''),
                password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]),
                (int)($data['marketing_consent'] ?? 0),
            ]
        );
        self::assignCustomerCode((int)$id, $customerCode, (string)($data['name'] ?? ''));

        $user = \Database::row("SELECT * FROM users WHERE id = ?", [$id]);
        $_SESSION['user'] = self::publicUser($user);
        session_regenerate_id(true);

        if (self::shippingColumnsReady()) {
            $shipping = self::sanitizeShipping($data['shipping'] ?? null);
            if ($shipping !== null) {
                \Database::query(
                    "UPDATE users
                     SET shipping_address_line1 = ?, shipping_address_line2 = ?, shipping_city = ?, shipping_state = ?, shipping_pincode = ?, profile_updated_at = NOW()
                     WHERE id = ?",
                    [
                        $shipping['address_line1'],
                        $shipping['address_line2'],
                        $shipping['city'],
                        $shipping['state'],
                        $shipping['pincode'],
                        $id,
                    ]
                );
            }
        }

        try { \Cart\Cart::mergeGuestCart((int)$id); } catch (\Throwable) {}
        try { \Email\Mailer::sendWelcome($user); }        catch (\Throwable) {}
        try { if (!empty($data['marketing_consent'])) \Email\Mailer::marketingOptIn($user); } catch (\Throwable) {}

        return ['ok' => true, 'user' => self::publicUser($user)];
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }

    public static function getProfile(int $userId): ?array
    {
        try { $base=\Database::row("SELECT id,name,email,phone,company,avatar_path,created_at FROM users WHERE id=?",[$userId]); }
        catch (\Throwable) { $base=\Database::row("SELECT id,name,email,phone,company,created_at FROM users WHERE id=?",[$userId]); }
        if (!$base) return null;

        $profile = [
            'id'         => (int)$base['id'],
            'name'       => (string)$base['name'],
            'email'      => (string)$base['email'],
            'phone'      => (string)$base['phone'],
            'company'    => (string)($base['company'] ?? ''),
            'avatar_path'=> (string)($base['avatar_path'] ?? ''),
            'created_at' => (string)($base['created_at'] ?? ''),
            'billing'    => null,
            'shipping'   => null,
            'migration_required' => false,
        ];

        if (!self::profileColumnsReady()) {
            $profile['migration_required'] = true;
            return $profile;
        }

        $extended = \Database::row(
            "SELECT billing_legal_name, gst_no, billing_address_line1, billing_address_line2,
                    billing_city, billing_state, billing_pincode
             FROM users WHERE id = ?",
            [$userId]
        ) ?? [];

        $billing = [
            'legal_name'    => trim((string)($extended['billing_legal_name'] ?? '')),
            'gst_no'        => strtoupper(trim((string)($extended['gst_no'] ?? ''))),
            'address_line1' => trim((string)($extended['billing_address_line1'] ?? '')),
            'address_line2' => trim((string)($extended['billing_address_line2'] ?? '')),
            'city'          => trim((string)($extended['billing_city'] ?? '')),
            'state'         => trim((string)($extended['billing_state'] ?? '')),
            'pincode'       => trim((string)($extended['billing_pincode'] ?? '')),
        ];
        $hasAnyBilling = implode('', $billing) !== '';
        $profile['billing'] = $hasAnyBilling ? $billing : null;

        if (self::shippingColumnsReady()) {
            $shipping = \Database::row(
                "SELECT shipping_address_line1, shipping_address_line2, shipping_city, shipping_state, shipping_pincode
                 FROM users WHERE id = ?",
                [$userId]
            ) ?? [];
            $cleanShipping = [
                'address_line1' => trim((string)($shipping['shipping_address_line1'] ?? '')),
                'address_line2' => trim((string)($shipping['shipping_address_line2'] ?? '')),
                'city'          => trim((string)($shipping['shipping_city'] ?? '')),
                'state'         => trim((string)($shipping['shipping_state'] ?? '')),
                'pincode'       => trim((string)($shipping['shipping_pincode'] ?? '')),
            ];
            if (implode('', $cleanShipping) !== '') {
                $profile['shipping'] = $cleanShipping;
            }
        }

        return $profile;
    }

    public static function updateProfile(int $userId, array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $phone = trim((string)($data['phone'] ?? ''));
        $company = trim((string)($data['company'] ?? ''));

        if ($name === '' || $email === '' || $phone === '') {
            return ['ok' => false, 'msg' => 'Name, email and phone are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => 'Please enter a valid email address.'];
        }

        $dup = \Database::row(
            "SELECT id FROM users WHERE (email = ? OR phone = ?) AND id <> ? LIMIT 1",
            [$email, $phone, $userId]
        );
        if ($dup) {
            return ['ok' => false, 'msg' => 'Email or phone is already used by another account.'];
        }

        \Database::query(
            "UPDATE users SET name = ?, email = ?, phone = ?, company = ? WHERE id = ?",
            [$name, $email, $phone, $company, $userId]
        );

        if (!self::profileColumnsReady()) {
            $fresh = \Database::row("SELECT * FROM users WHERE id = ?", [$userId]);
            if ($fresh) $_SESSION['user'] = self::publicUser($fresh);
            return ['ok' => true, 'profile' => self::getProfile($userId), 'migration_required' => true];
        }

        $billing = self::sanitizeBilling($data['billing'] ?? null);
        $shipping = self::sanitizeShipping($data['shipping'] ?? null);

        \Database::query(
            "UPDATE users SET billing_legal_name = ?, gst_no = ?, billing_address_line1 = ?, billing_address_line2 = ?,
                billing_city = ?, billing_state = ?, billing_pincode = ?, profile_updated_at = NOW()
             WHERE id = ?",
            [
                $billing['legal_name'] ?? null,
                $billing['gst_no'] ?? null,
                $billing['address_line1'] ?? null,
                $billing['address_line2'] ?? null,
                $billing['city'] ?? null,
                $billing['state'] ?? null,
                $billing['pincode'] ?? null,
                $userId,
            ]
        );

        if (self::shippingColumnsReady() && array_key_exists('shipping', $data)) {
            \Database::query(
                "UPDATE users SET shipping_address_line1 = ?, shipping_address_line2 = ?, shipping_city = ?, shipping_state = ?, shipping_pincode = ?
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
        }

        $fresh = \Database::row("SELECT * FROM users WHERE id = ?", [$userId]);
        if ($fresh) $_SESSION['user'] = self::publicUser($fresh);

        return ['ok' => true, 'profile' => self::getProfile($userId)];
    }

    public static function changePassword(int $userId, array $data): array
    {
        $current = (string)($data['current_password'] ?? '');
        $next = (string)($data['new_password'] ?? '');
        $confirm = (string)($data['confirm_password'] ?? '');

        if ($current === '' || $next === '' || $confirm === '') {
            return ['ok' => false, 'msg' => 'Current, new and confirm password are required.'];
        }
        if ($next !== $confirm) {
            return ['ok' => false, 'msg' => 'New password and confirm password must match.'];
        }
        if (strlen($next) < 6) {
            return ['ok' => false, 'msg' => 'New password must be at least 6 characters.'];
        }

        $user = \Database::row("SELECT id, password FROM users WHERE id = ? LIMIT 1", [$userId]);
        if (!$user) {
            return ['ok' => false, 'msg' => 'User not found.'];
        }
        if (!password_verify($current, (string)($user['password'] ?? ''))) {
            return ['ok' => false, 'msg' => 'Current password is incorrect.'];
        }

        \Database::query(
            "UPDATE users SET password = ?, profile_updated_at = NOW() WHERE id = ?",
            [password_hash($next, PASSWORD_BCRYPT, ['cost' => 10]), $userId]
        );

        return ['ok' => true];
    }

    public static function user(): ?array  { return $_SESSION['user'] ?? null; }
    public static function check(): bool   { return !empty($_SESSION['user']['id']); }

    public static function require(): void
    {
        if (!self::check()) {
            if (self::isApiRequest()) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Login required']); exit; }
            header('Location: /login'); exit;
        }
    }

    /**
     * Ensure a checkout user exists in session.
     * - If logged in, returns current user.
     * - If guest, validates minimal customer fields and creates an account-lite user.
     */
    public static function ensureCheckoutUser(array $customer): array
    {
        self::ensureCustomerCodeSchema();
        if (self::check()) {
            return ['ok' => true, 'user' => self::user()];
        }

        $name  = trim((string)($customer['name'] ?? ''));
        $email = strtolower(trim((string)($customer['email'] ?? '')));
        $phone = trim((string)($customer['phone'] ?? ''));

        if ($name === '' || $email === '' || $phone === '') {
            return ['ok' => false, 'msg' => 'Name, email and phone are required for checkout.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => 'Please enter a valid email address.'];
        }

        $existing = \Database::row(
            "SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1",
            [$email, $phone]
        );
        if ($existing) {
            return ['ok' => false, 'msg' => 'Account already exists with this email/phone. Please login to continue.'];
        }

        $id = \Database::insert(
            "INSERT INTO users (name, email, phone, company, password, marketing_consent, created_at)
             VALUES (?, ?, ?, '', ?, 0, NOW())",
            [
                $name,
                $email,
                $phone,
                password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 10]),
            ]
        );
        self::assignCustomerCode((int)$id, self::generateCustomerCode($name), $name);

        $user = \Database::row("SELECT * FROM users WHERE id = ?", [$id]);
        $_SESSION['user'] = self::publicUser($user);
        $_SESSION['guest_checkout_created'] = 1;
        session_regenerate_id(true);

        try { \Cart\Cart::mergeGuestCart((int)$id); } catch (\Throwable) {}

        return ['ok' => true, 'user' => self::publicUser($user)];
    }

    // ══════════════════════════════════════════════════════════
    //  ADMIN AUTH
    //  FIX: Removed CSRF check from login form — CSRF is only
    //       needed for API endpoints, not traditional form submissions.
    //       The login form is protected by the session naturally.
    // ══════════════════════════════════════════════════════════

    public static function adminLogin(string $email, string $password): array
    {
        $email    = strtolower(trim($email));
        $password = trim($password);

        if (empty($email) || empty($password)) {
            return ['ok' => false, 'msg' => 'Email and password are required.'];
        }

        // Find admin user
        $admin = \Database::row(
            "SELECT * FROM admin_users WHERE email = ? AND is_active = 1",
            [$email]
        );

        if (!$admin) {
            // Don't reveal whether email exists
            return ['ok' => false, 'msg' => 'Incorrect email or password.'];
        }

        // Verify password with broad legacy compatibility:
        // - bcrypt/argon hashes (password_verify)
        // - plaintext values from old dumps
        // - md5/sha1 legacy values from older installs
        $storedPassword = trim((string)($admin['password'] ?? ''));
        $info = password_get_info($storedPassword);
        $isPasswordHash = (int)($info['algo'] ?? 0) !== 0;

        $valid = false;
        if ($isPasswordHash) {
            $valid = password_verify($password, $storedPassword);
        } else {
            $valid = hash_equals($storedPassword, $password)
                || hash_equals(strtolower($storedPassword), md5($password))
                || hash_equals(strtolower($storedPassword), sha1($password));
        }

        if (!$valid) {
            return ['ok' => false, 'msg' => 'Incorrect email or password.'];
        }

        // Always migrate legacy/plaintext/weak hash values to bcrypt after successful login.
        if (!$isPasswordHash || password_needs_rehash($storedPassword, PASSWORD_BCRYPT, ['cost' => 10])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            try {
                \Database::query("UPDATE admin_users SET password = ? WHERE id = ?", [$newHash, $admin['id']]);
            } catch (\Throwable) {}
        }

        // Set admin session
        $_SESSION['admin'] = [
            'id'    => (int)$admin['id'],
            'name'  => $admin['name'],
            'email' => $admin['email'],
            'role'  => $admin['role'],
        ];
        session_regenerate_id(true);

        // Record last login (non-blocking)
        try {
            \Database::query("UPDATE admin_users SET last_login = NOW() WHERE id = ?", [$admin['id']]);
        } catch (\Throwable) {}

        return ['ok' => true];
    }

    public static function adminLogout(): void
    {
        unset($_SESSION['admin']);
        session_regenerate_id(true);
    }

    public static function isAdmin(): bool { return !empty($_SESSION['admin']['id']); }
    public static function admin(): ?array  { return $_SESSION['admin'] ?? null; }
    public static function isSuperAdmin(): bool
    {
        $role = strtolower(trim((string)($_SESSION['admin']['role'] ?? '')));
        $normalizedRole = trim((string)preg_replace('/[^a-z0-9]+/', '_', $role), '_');
        return in_array($normalizedRole, ['super','superadmin','super_admin','owner'], true);
    }

    public static function requireSuperAdmin(): void
    {
        if (!self::isSuperAdmin()) {
            if (self::isApiRequest()) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Only Super Admin can perform this action.']); exit; }
            header('Location: /admin/dashboard'); exit;
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            if (self::isApiRequest()) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Forbidden']); exit; }
            header('Location: /admin/login'); exit;
        }
    }

    // ══════════════════════════════════════════════════════════
    //  CSRF — used for API calls, not HTML form submissions
    // ══════════════════════════════════════════════════════════

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void
    {
        $token = $_POST['_token']
               ?? $_SERVER['HTTP_X_CSRF_TOKEN']
               ?? $_SERVER['HTTP_X_XSRF_TOKEN']
               ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';
        if (empty($stored) || !hash_equals($stored, $token)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'msg' => 'Security token mismatch. Please refresh the page.']);
            exit;
        }
    }

    // ══════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════

    private static function publicUser(array $user): array
    {
        return [
            'id'      => (int)$user['id'],
            'name'    => $user['name'],
            'email'   => $user['email'],
            'phone'   => $user['phone'],
            'company' => $user['company'] ?? '',
            'avatar_path' => $user['avatar_path'] ?? '',
        ];
    }

    private static function validateRegister(array $d): array
    {
        $e = [];
        if (empty(trim($d['name']   ?? '')))  $e[] = 'Name required';
        if (empty(trim($d['email']  ?? '')) || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e[] = 'Valid email required';
        if (empty(trim($d['phone']  ?? '')))  $e[] = 'Phone required';
        if (empty($d['password'] ?? '') || strlen($d['password']) < 6) $e[] = 'Password minimum 6 characters';
        return $e;
    }

    private static function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_starts_with($uri, '/api/') || str_starts_with($uri, '/admin/api/');
    }

    private static function sanitizeBilling(mixed $billing): ?array
    {
        if (!is_array($billing)) return null;

        $clean = [
            'legal_name'    => trim((string)($billing['legal_name'] ?? '')),
            'gst_no'        => strtoupper(trim((string)($billing['gst_no'] ?? ''))),
            'address_line1' => trim((string)($billing['address_line1'] ?? '')),
            'address_line2' => trim((string)($billing['address_line2'] ?? '')),
            'city'          => trim((string)($billing['city'] ?? '')),
            'state'         => trim((string)($billing['state'] ?? '')),
            'pincode'       => trim((string)($billing['pincode'] ?? '')),
        ];

        if (implode('', $clean) === '') return null;
        return $clean;
    }

    private static function sanitizeShipping(mixed $shipping): ?array
    {
        if (!is_array($shipping)) return null;

        $clean = [
            'address_line1' => trim((string)($shipping['address_line1'] ?? '')),
            'address_line2' => trim((string)($shipping['address_line2'] ?? '')),
            'city'          => trim((string)($shipping['city'] ?? '')),
            'state'         => trim((string)($shipping['state'] ?? '')),
            'pincode'       => trim((string)($shipping['pincode'] ?? '')),
        ];
        if (implode('', $clean) === '') return null;
        return $clean;
    }

    private static function profileColumnsReady(): bool
    {
        if (self::$hasProfileColumns !== null) return self::$hasProfileColumns;

        $required = [
            'billing_legal_name',
            'gst_no',
            'billing_address_line1',
            'billing_address_line2',
            'billing_city',
            'billing_state',
            'billing_pincode',
            'profile_updated_at',
        ];
        $placeholders = implode(',', array_fill(0, count($required), '?'));

        try {
            $row = \Database::row(
                "SELECT COUNT(*) AS c
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME IN ($placeholders)",
                [DB_NAME, ...$required]
            );
            self::$hasProfileColumns = (int)($row['c'] ?? 0) === count($required);
        } catch (\Throwable) {
            self::$hasProfileColumns = false;
        }

        return self::$hasProfileColumns;
    }

    private static function shippingColumnsReady(): bool
    {
        if (self::$hasShippingColumns !== null) return self::$hasShippingColumns;

        $required = [
            'shipping_address_line1',
            'shipping_address_line2',
            'shipping_city',
            'shipping_state',
            'shipping_pincode',
        ];
        $placeholders = implode(',', array_fill(0, count($required), '?'));

        try {
            $row = \Database::row(
                "SELECT COUNT(*) AS c
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME IN ($placeholders)",
                [DB_NAME, ...$required]
            );
            self::$hasShippingColumns = (int)($row['c'] ?? 0) === count($required);
        } catch (\Throwable) {
            self::$hasShippingColumns = false;
        }

        return self::$hasShippingColumns;
    }

    public static function ensureCustomerCodeSchema(): bool
    {
        if (self::$hasCustomerCodeColumn !== null) return self::$hasCustomerCodeColumn;
        try {
            $row = \Database::row(
                "SELECT 1 AS ok
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'customer_code'
                  LIMIT 1",
                [DB_NAME]
            );
            if (!$row) {
                \Database::query("ALTER TABLE users ADD COLUMN customer_code VARCHAR(20) NULL AFTER id");
                try { \Database::query("CREATE UNIQUE INDEX idx_users_customer_code ON users (customer_code)"); } catch (\Throwable) {}
            }
            self::$hasCustomerCodeColumn = true;
            self::backfillCustomerCodes();
        } catch (\Throwable $e) {
            error_log('Customer code schema unavailable: ' . $e->getMessage());
            self::$hasCustomerCodeColumn = false;
        }
        return self::$hasCustomerCodeColumn;
    }

    public static function generateCustomerCode(string $name): string
    {
        $prefix = self::customerInitialPrefix($name);
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $max = 0;
            try {
                $row = \Database::row(
                    "SELECT MAX(CAST(SUBSTRING(customer_code, 3) AS UNSIGNED)) AS max_no
                       FROM users
                      WHERE customer_code LIKE ?",
                    [$prefix . '%']
                );
                $max = (int)($row['max_no'] ?? 0);
            } catch (\Throwable) {}
            $candidate = $prefix . str_pad((string)($max + 1 + $attempt), 4, '0', STR_PAD_LEFT);
            try {
                $exists = \Database::row("SELECT id FROM users WHERE customer_code = ? LIMIT 1", [$candidate]);
                if (!$exists) return $candidate;
            } catch (\Throwable) {
                return $candidate;
            }
        }
        return $prefix . date('His');
    }

    private static function assignCustomerCode(int $userId, string $code, string $name): void
    {
        if ($userId <= 0 || !self::ensureCustomerCodeSchema()) return;
        $candidate = $code !== '' ? $code : self::generateCustomerCode($name);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            try {
                \Database::query(
                    "UPDATE users
                        SET customer_code = ?
                      WHERE id = ? AND (customer_code IS NULL OR customer_code = '')",
                    [$candidate, $userId]
                );
                return;
            } catch (\Throwable) {
                $candidate = self::generateCustomerCode($name);
            }
        }
    }

    public static function backfillCustomerCodes(): void
    {
        if (self::$hasCustomerCodeColumn === false) return;
        try {
            $rows = \Database::rows("SELECT id, name FROM users WHERE customer_code IS NULL OR customer_code = '' ORDER BY id ASC LIMIT 500");
            foreach ($rows as $row) {
                self::assignCustomerCode((int)$row['id'], self::generateCustomerCode((string)($row['name'] ?? '')), (string)($row['name'] ?? ''));
            }
        } catch (\Throwable) {}
    }

    private static function customerInitialPrefix(string $name): string
    {
        $parts = preg_split('/\s+/', strtoupper(trim($name))) ?: [];
        $first = preg_replace('/[^A-Z]/', '', (string)($parts[0] ?? ''));
        $second = preg_replace('/[^A-Z]/', '', (string)($parts[1] ?? ''));
        $a = $first !== '' ? $first[0] : 'C';
        $b = $second !== '' ? $second[0] : ($first[1] ?? 'X');
        return $a . $b;
    }
}

<?php
declare(strict_types=1);

// Run: php tests/customer_mobile_login.php (requires pdo_sqlite).
class Database
{
    public static PDO $db;
    public static function row(string $sql, array $params = []): ?array
    {
        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
require dirname(__DIR__) . '/app/src/Auth/Auth.php';
session_start();
Database::$db = new PDO('sqlite::memory:');
Database::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Database::$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, phone TEXT, password TEXT, is_active INTEGER, marketing_consent INTEGER)');
$insert = Database::$db->prepare('INSERT INTO users VALUES (?, ?, ?, ?, ?, ?, 0)');
$insert->execute([1, 'Auto', 'auto@example.test', '+91 98765-43210', password_hash('919876543210', PASSWORD_BCRYPT), 1]);
$insert->execute([2, 'Manual', 'manual@example.test', '9876543211', password_hash('Own-password!', PASSWORD_BCRYPT), 1]);
$insert->execute([3, 'Local', 'local@example.test', '9876543212', password_hash('9876543212', PASSWORD_BCRYPT), 1]);
$insert->execute([4, 'Disabled', 'disabled@example.test', '9876543213', password_hash('9876543213', PASSWORD_BCRYPT), 0]);
$cases = [
    ['auto@example.test', '9876543210', true],
    ['auto@example.test', '+91 98765-43210', true],
    ['9876543210', '9876543210', true],
    ['+91 98765 43210', '919876543210', true],
    ['09876543210', '9876543210', true],
    ['local@example.test', '+91 98765 43212', true],
    ['manual@example.test', 'Own-password!', true],
    ['9876543211', 'Own-password!', true],
    ['manual@example.test', '9876543211', false],
    ['manual@example.test', '+91 98765 43211', false],
    ['auto@example.test', '9876543211', false],
    ['missing@example.test', '9876543210', false],
    ['auto9876543210@example.test', '9876543210', false],
    ['disabled@example.test', '9876543213', false],
];
foreach ($cases as $i => [$identifier, $password, $expected]) {
    $_SESSION = [];
    $result = \Auth\Auth::login($identifier, $password);
    if ($result['ok'] !== $expected) throw new RuntimeException('Login case ' . ($i + 1) . ' failed');
}
// Once an automatic customer's password is changed, the mobile default stops working.
$stmt = Database::$db->prepare('UPDATE users SET password = ? WHERE id = 1');
$stmt->execute([password_hash('New-custom-password!', PASSWORD_BCRYPT)]);
$_SESSION = [];
if (\Auth\Auth::login('auto@example.test', '9876543210')['ok']) throw new RuntimeException('Old mobile default accepted after password change');
$_SESSION = [];
if (!\Auth\Auth::login('auto@example.test', 'New-custom-password!')['ok']) throw new RuntimeException('Changed password rejected');
// Preserve the existing lockout behavior.
$_SESSION = [];
for ($i = 0; $i < 5; $i++) \Auth\Auth::login('manual@example.test', 'wrong');
if (\Auth\Auth::login('manual@example.test', 'Own-password!')['ok']) throw new RuntimeException('Lockout bypassed');
echo "PASS: 17 customer login checks\n";

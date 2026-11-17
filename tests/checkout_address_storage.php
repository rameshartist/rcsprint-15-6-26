<?php
declare(strict_types=1);
// Run: php tests/checkout_address_storage.php (requires pdo_sqlite).
class Database
{
    public static PDO $db;
    public static function query(string $sql, array $params = []): PDOStatement
    {
        // The fixture supplies the schema; exercise runtime writes using SQLite.
        if (str_starts_with($sql, 'CREATE TABLE') || str_starts_with($sql, 'ALTER TABLE')) return self::$db->query('SELECT 1');
        $stmt = self::$db->prepare(str_replace('NOW()', 'CURRENT_TIMESTAMP', $sql));
        $stmt->execute($params);
        return $stmt;
    }
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int)self::$db->lastInsertId();
    }
}
require dirname(__DIR__) . '/app/src/Orders/OrderManager.php';
Database::$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
Database::$db->exec('CREATE TABLE user_addresses (id INTEGER PRIMARY KEY, user_id INTEGER, label TEXT, address_type TEXT, business_name TEXT, legal_name TEXT, gst_no TEXT, address_line1 TEXT, address_line2 TEXT, city TEXT, state TEXT, pincode TEXT, is_default INTEGER, is_default_shipping INTEGER, is_default_billing INTEGER, created_at TEXT)');
Database::$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, shipping_address_line1 TEXT, shipping_address_line2 TEXT, shipping_city TEXT, shipping_state TEXT, shipping_pincode TEXT, profile_updated_at TEXT)');
Database::$db->exec('INSERT INTO users (id) VALUES (1),(2)');
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$class = new ReflectionClass(\Orders\OrderManager::class);
$base = ['required'=>true, 'address_type'=>'office', 'business_name'=>'Full Name', 'legal_name'=>'Business Ltd', 'gst_no'=>'24abcde1234f1z5', 'address_line1'=>'Street', 'city'=>'Rajkot', 'state'=>'Gujarat', 'pincode'=>'360001', 'save_as_default'=>true, 'is_default_shipping'=>true, 'is_default_billing'=>false];
$shipping = $class->getMethod('sanitizeShipping')->invoke(null, $base);
$class->getMethod('saveUserShippingDefault')->invoke(null, 1, $shipping, true);
$row = Database::query('SELECT * FROM user_addresses WHERE user_id=1')->fetch(PDO::FETCH_ASSOC);
check($row['business_name']==='Full Name' && $row['legal_name']==='Business Ltd', 'Full and business names must remain distinct');
check($row['gst_no']==='24ABCDE1234F1Z5' && $row['address_type']==='office', 'GST and type must persist');
check((int)$row['is_default_shipping']===1 && (int)$row['is_default_billing']===0, 'Shipping defaults must persist');
$base['legal_name']=''; $base['gst_no']=''; $base['address_type']='home';
$base['is_default_shipping']=false; $base['is_default_billing']=true;
$billing=$class->getMethod('sanitizeBilling')->invoke(null,$base);
check($billing!==null, 'Billing must accept optional business name and GST');
$class->getMethod('saveUserBillingDefault')->invoke(null,1,$billing,true);
$rows=Database::query('SELECT * FROM user_addresses WHERE user_id=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check(count($rows)===2 && $rows[1]['legal_name']==='' && $rows[1]['business_name']==='Full Name', 'Optional billing fields must save without losing full name');
check((int)$rows[0]['is_default_shipping']===1 && (int)$rows[1]['is_default_billing']===1, 'Shipping and billing defaults must stay independent');
$class->getMethod('saveUserBillingDefault')->invoke(null,1,$billing,false);
check((int)Database::query('SELECT COUNT(*) FROM user_addresses')->fetchColumn()===2, 'Unchecked save must not insert an address');
$class->getMethod('saveUserBillingDefault')->invoke(null,2,$billing,true);
check((int)Database::query('SELECT is_default_billing FROM user_addresses WHERE user_id=1 AND id=2')->fetchColumn()===1, 'Saving another customer must not clear defaults');
echo "PASS: checkout address storage, optional billing fields, distinct names, type/GST and independent customer defaults\n";

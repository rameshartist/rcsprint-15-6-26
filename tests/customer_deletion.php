<?php
declare(strict_types=1);
// In-memory fixtures only. Never connects to the application database.
class Database
{
    public static PDO $db;
    public static function get(): PDO { return self::$db; }
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::$db->prepare(str_replace(' FOR UPDATE', '', $sql));
        $stmt->execute($params); return $stmt;
    }
    public static function row(string $sql, array $params = []): ?array { return self::query($sql,$params)->fetch(PDO::FETCH_ASSOC) ?: null; }
    public static function rows(string $sql, array $params = []): array {
        if (str_contains($sql, 'information_schema.COLUMNS')) {
            $columns=[];
            foreach (self::$db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
                foreach (self::$db->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC) as $column) $columns[]=['TABLE_NAME'=>$table,'COLUMN_NAME'=>$column['name']];
            }
            return $columns;
        }
        return self::query($sql,$params)->fetchAll(PDO::FETCH_ASSOC);
    }
}
require dirname(__DIR__).'/app/src/Customers/CustomerManager.php';
function expect(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); }
function fixture(bool $optional=true):void {
    Database::$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    Database::$db->exec('PRAGMA foreign_keys=ON');
    $tables=[
        'users'=>'id INTEGER PRIMARY KEY',
        'orders'=>'id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(id)',
        'order_items'=>'id INTEGER PRIMARY KEY,order_id INTEGER REFERENCES orders(id)',
        'carts'=>'id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(id)',
        'cart_items'=>'id INTEGER PRIMARY KEY,cart_id INTEGER REFERENCES carts(id),custom_quote_id INTEGER',
        'artwork_files'=>'id INTEGER PRIMARY KEY,uploaded_by INTEGER REFERENCES users(id),order_item_id INTEGER REFERENCES order_items(id),cart_item_id INTEGER REFERENCES cart_items(id)',
        'payments'=>'id INTEGER PRIMARY KEY,order_id INTEGER REFERENCES orders(id)',
        'coupon_uses'=>'id INTEGER PRIMARY KEY,order_id INTEGER REFERENCES orders(id),user_id INTEGER REFERENCES users(id)',
        'order_status_history'=>'id INTEGER PRIMARY KEY,order_id INTEGER REFERENCES orders(id)',
    ];
    if($optional)$tables += [
        'user_addresses'=>'id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(id)',
        'wishlists'=>'id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(id)',
        'custom_quote_requests'=>'id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(id),order_id INTEGER',
        'product_reviews'=>'id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(id),order_id INTEGER REFERENCES orders(id),order_item_id INTEGER REFERENCES order_items(id)',
        'order_design_approvals'=>'id INTEGER PRIMARY KEY,order_id INTEGER REFERENCES orders(id),order_item_id INTEGER REFERENCES order_items(id)',
        'order_design_events'=>'id INTEGER PRIMARY KEY,order_id INTEGER REFERENCES orders(id),order_item_id INTEGER REFERENCES order_items(id)',
    ];
    foreach($tables as $table=>$columns)Database::$db->exec("CREATE TABLE $table ($columns)");
    foreach([1,2] as $id){
        foreach($tables as $table=>$columns){
            $names=array_map(fn($c)=>$c['name'],Database::$db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC));
            Database::$db->exec('INSERT INTO '.$table.' VALUES ('.implode(',',array_fill(0,count($names),$id)).')');
        }
    }
}
foreach([true,false] as $optional){
    fixture($optional);
    expect(\Customers\CustomerManager::delete(1),'Deletion failed');
    foreach(Database::$db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $table){
        expect((int)Database::$db->query("SELECT COUNT(*) FROM $table WHERE id=1")->fetchColumn()===0,"Orphan in $table");
        expect((int)Database::$db->query("SELECT COUNT(*) FROM $table WHERE id=2")->fetchColumn()===1,"Other customer changed: $table");
    }
    expect(!\Customers\CustomerManager::delete(999),'Missing customer should return false');
    expect(!\Customers\CustomerManager::delete(0),'Invalid customer should return false');
}
fixture();
Database::$db->exec("CREATE TRIGGER fail_delete BEFORE DELETE ON users BEGIN SELECT RAISE(ABORT,'blocked'); END");
try { \Customers\CustomerManager::delete(1); throw new RuntimeException('Failure not propagated'); }
catch(PDOException $e) {}
expect(!Database::$db->inTransaction(),'Transaction left open');
expect((int)Database::$db->query('SELECT COUNT(*) FROM order_items WHERE id=1')->fetchColumn()===1,'Failed deletion did not roll back children');
expect((int)Database::$db->query('SELECT COUNT(*) FROM users WHERE id=1')->fetchColumn()===1,'Failed deletion removed user');
echo "PASS: permanent deletion, optional tables, foreign keys, customer isolation, missing IDs and atomic rollback\n";

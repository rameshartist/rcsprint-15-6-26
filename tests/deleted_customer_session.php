<?php
declare(strict_types=1);
class Database {
    public static function row(string $sql, array $params=[]): ?array { return $params[0] === 2 ? ['id'=>2] : null; }
}
require dirname(__DIR__).'/app/src/Auth/Auth.php';
function expect(bool $ok):void { if(!$ok)throw new RuntimeException('Session assertion failed'); }
$_SESSION=['user'=>['id'=>1,'name'=>'Deleted account'],'cart'=>[['id'=>1]]];
expect(\Auth\Auth::user() === null);
expect(!\Auth\Auth::check());
expect(!isset($_SESSION['user'],$_SESSION['cart']));
$_SESSION['user']=['id'=>2,'name'=>'Active account'];
expect(\Auth\Auth::check());
expect(\Auth\Auth::user()['name']==='Active account');
echo "PASS: deleted-account sessions are rejected; active customers remain signed in\n";

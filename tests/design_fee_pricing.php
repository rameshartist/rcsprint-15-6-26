<?php
declare(strict_types=1);
class Database {
    public static function row(string $sql,array $params=[]):?array {
        if(str_contains($sql,'product_quantity_tiers'))return ['quantity'=>$params[1],'price'=>250];
        if(str_contains($sql,'design_fee FROM products'))return ['design_fee'=>75];
        throw new RuntimeException('Unexpected query');
    }
    public static function setting(string $key,mixed $fallback=null):mixed { return $fallback; }
}
require dirname(__DIR__).'/app/src/Cart/Pricing.php';
foreach(['rcs'=>325,'upload'=>250] as $choice=>$total) {
    $price=\Cart\Pricing::calculate(1,1,100,[],$choice);
    if(!$price['ok']||$price['total']!=$total||$price['breakdown']['design_fee']!=($choice==='rcs'?75:0))throw new RuntimeException('Design fee mismatch');
}
echo "PASS: server pricing charges a design fee only for the RCS option\n";

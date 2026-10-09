<?php
declare(strict_types=1);

namespace Combos;

final class ComboOfferManager
{
    public static function ensureSchema(): void
    {
        \Database::query("CREATE TABLE IF NOT EXISTS combo_offers (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(180) NOT NULL,slug VARCHAR(190) NOT NULL UNIQUE,badge VARCHAR(100) NULL,short_description VARCHAR(500) NULL,description TEXT NULL,banner_image VARCHAR(500) NULL,regular_price DECIMAL(12,2) NOT NULL DEFAULT 0,discount_percent DECIMAL(6,2) NOT NULL DEFAULT 0,combo_price DECIMAL(12,2) NOT NULL DEFAULT 0,layout_slot ENUM('large','wide','square') NOT NULL DEFAULT 'square',cta_text VARCHAR(80) NOT NULL DEFAULT 'View Offer',sort_order INT NOT NULL DEFAULT 0,show_on_home TINYINT(1) NOT NULL DEFAULT 1,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,KEY idx_combo_home (is_active,show_on_home,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { \Database::query("ALTER TABLE combo_offers ADD COLUMN discount_percent DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER regular_price"); } catch (\Throwable) {}
        foreach (['show_title','show_badge','show_cta','show_short_description','show_description','show_title_home','show_title_detail','show_badge_home','show_badge_detail','show_cta_home','show_cta_detail','show_short_description_home','show_short_description_detail','show_description_home','show_description_detail'] as $column) {
            try { \Database::query("ALTER TABLE combo_offers ADD COLUMN {$column} TINYINT(1) NOT NULL DEFAULT 1"); } catch (\Throwable) {}
        }
        try { \Database::query("ALTER TABLE combo_offers MODIFY layout_slot ENUM('large','wide','square','square_3','square_4') NOT NULL DEFAULT 'square_3'"); } catch (\Throwable) {}
        foreach ([
            "video_url VARCHAR(500) NULL",
            "video_path VARCHAR(500) NULL",
            "show_delivery_info TINYINT(1) NOT NULL DEFAULT 1",
            "delivery_info VARCHAR(255) NULL DEFAULT 'Delivery in 3 - 5 Working Days'",
            "show_free_delivery_info TINYINT(1) NOT NULL DEFAULT 1",
            "free_delivery_info VARCHAR(255) NULL DEFAULT 'Free Delivery on Orders Above ₹999'",
        ] as $definition) { try { \Database::query("ALTER TABLE combo_offers ADD COLUMN {$definition}"); } catch (\Throwable) {} }
        \Database::query("CREATE TABLE IF NOT EXISTS combo_offer_images (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,combo_offer_id INT UNSIGNED NOT NULL,image_path VARCHAR(500) NOT NULL,alt_text VARCHAR(255) NULL,is_primary TINYINT(1) NOT NULL DEFAULT 0,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_combo_images (combo_offer_id,is_primary,sort_order),CONSTRAINT fk_combo_images_offer FOREIGN KEY (combo_offer_id) REFERENCES combo_offers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        \Database::query("CREATE TABLE IF NOT EXISTS combo_offer_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,combo_offer_id INT UNSIGNED NOT NULL,product_id INT UNSIGNED NOT NULL,quantity INT NOT NULL DEFAULT 1,regular_price DECIMAL(12,2) NOT NULL DEFAULT 0,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_combo_items (combo_offer_id,sort_order),CONSTRAINT fk_combo_items_offer FOREIGN KEY (combo_offer_id) REFERENCES combo_offers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        \Database::query("CREATE TABLE IF NOT EXISTS combo_offer_custom_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,combo_offer_id INT UNSIGNED NOT NULL,item_name VARCHAR(180) NOT NULL,item_price DECIMAL(12,2) NOT NULL DEFAULT 0,thumbnail_path VARCHAR(500) NULL,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_combo_custom_items (combo_offer_id,sort_order),CONSTRAINT fk_combo_custom_offer FOREIGN KEY (combo_offer_id) REFERENCES combo_offers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { \Database::query("ALTER TABLE combo_offer_custom_items ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER item_name"); } catch (\Throwable) {}
        try { \Database::query("ALTER TABLE combo_offer_custom_items ADD COLUMN description TEXT NULL AFTER thumbnail_path"); } catch (\Throwable) {}
    }

    public static function all(bool $public = false, string $search = ''): array
    {
        self::ensureSchema();
        $clauses = $public ? ['c.is_active=1'] : []; $params = [];
        if ($search !== '') { $clauses[] = '(c.title LIKE ? OR c.slug LIKE ? OR c.badge LIKE ? OR c.short_description LIKE ?)'; $like='%'.$search.'%'; $params=[$like,$like,$like,$like]; }
        $where = $clauses ? 'WHERE '.implode(' AND ', $clauses) : '';
        return \Database::rows("SELECT c.*,(SELECT COUNT(*) FROM combo_offer_items ci WHERE ci.combo_offer_id=c.id)+(SELECT COUNT(*) FROM combo_offer_custom_items cc WHERE cc.combo_offer_id=c.id) item_count FROM combo_offers c $where ORDER BY c.sort_order,c.id DESC", $params);
    }

    public static function home(): array
    {
        self::ensureSchema();
        $rows = \Database::rows("SELECT * FROM combo_offers WHERE is_active=1 AND show_on_home=1 ORDER BY updated_at DESC,id DESC");
        $slots = [];
        foreach ($rows as $row) {
            $slot = ($row['layout_slot'] ?? '') === 'square' ? 'square_3' : (string)($row['layout_slot'] ?? '');
            if (!in_array($slot, ['large','wide','square_3','square_4'], true) || isset($slots[$slot])) continue;
            $row['layout_slot'] = $slot;
            $slots[$slot] = $row;
        }
        $ordered = [];
        foreach (['large','wide','square_3','square_4'] as $slot) if (isset($slots[$slot])) $ordered[] = $slots[$slot];
        return $ordered;
    }

    public static function positionOccupant(string $slot, int $exceptId = 0): ?array
    {
        self::ensureSchema();
        if (!in_array($slot, ['large','wide','square_3','square_4'], true)) return null;
        return \Database::row("SELECT id,title,slug FROM combo_offers WHERE is_active=1 AND show_on_home=1 AND layout_slot=? AND id<>? LIMIT 1", [$slot,$exceptId]);
    }

    public static function productsForAdmin(): array
    {
        $products = \Database::rows("SELECT p.id,p.name,p.slug,p.category_id,c.name category_name,(SELECT COALESCE(pi.image_path,pi.url) FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC,pi.id LIMIT 1) thumbnail FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY c.name,p.name");
        foreach ($products as &$product) {
            $pricing = \Cart\Pricing::productPricingData((int)$product['id']);
            $product['tiers'] = array_values(array_filter($pricing['tiers'] ?? [], static fn(array $tier): bool => (int)$tier['quantity'] >= 1000 && (int)$tier['quantity'] <= 10000));
            if (!$product['tiers']) $product['tiers'] = $pricing['tiers'] ?? [];
        }
        return $products;
    }

    public static function findBySlug(string $slug): ?array
    {
        self::ensureSchema();
        $offer = \Database::row("SELECT * FROM combo_offers WHERE slug=? AND is_active=1 LIMIT 1", [$slug]);
        return $offer ? self::withItems($offer) : null;
    }

    public static function find(int $id): ?array
    {
        self::ensureSchema();
        $offer = \Database::row("SELECT * FROM combo_offers WHERE id=?", [$id]);
        return $offer ? self::withItems($offer) : null;
    }

    public static function related(int $excludeId, int $limit = 3): array
    {
        self::ensureSchema();
        $limit=max(1,min(6,$limit));
        return \Database::rows("SELECT id,title,slug,badge,short_description,banner_image,regular_price,discount_percent,combo_price FROM combo_offers WHERE is_active=1 AND id<>? ORDER BY show_on_home DESC,sort_order,id DESC LIMIT {$limit}",[$excludeId]);
    }

    private static function withItems(array $offer): array
    {
        $offer['items'] = \Database::rows("SELECT ci.*,p.name product_name,p.slug product_slug,p.description product_description,p.product_code,p.design_fee,(SELECT COALESCE(pi.image_path,pi.url) FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC,pi.id LIMIT 1) product_image FROM combo_offer_items ci JOIN products p ON p.id=ci.product_id WHERE ci.combo_offer_id=? ORDER BY ci.sort_order,ci.id", [(int)$offer['id']]);
        $defaultDesignFee=(float)\Database::setting('design_fee','0');
        $specsByProduct=[];$productIds=array_values(array_unique(array_map(static fn($item)=>(int)$item['product_id'],$offer['items'])));
        if($productIds){$marks=implode(',',array_fill(0,count($productIds),'?'));foreach(\Database::rows("SELECT product_id,label,value,sort_order FROM product_specs WHERE product_id IN ({$marks}) ORDER BY product_id,sort_order",$productIds) as $spec)$specsByProduct[(int)$spec['product_id']][]=$spec;}
        foreach ($offer['items'] as &$item) {
            $item['specs'] = $specsByProduct[(int)$item['product_id']] ?? [];
            $item['design_fee'] = ($item['design_fee']!==null&&$item['design_fee']!=='')?(float)$item['design_fee']:$defaultDesignFee;
        }
        unset($item);
        $offer['custom_items'] = \Database::rows("SELECT * FROM combo_offer_custom_items WHERE combo_offer_id=? ORDER BY sort_order,id", [(int)$offer['id']]);
        foreach($offer['custom_items'] as &$item)$item['design_fee']=$defaultDesignFee;unset($item);
        $offer['images'] = \Database::rows("SELECT id,image_path,alt_text,is_primary,sort_order FROM combo_offer_images WHERE combo_offer_id=? ORDER BY is_primary DESC,sort_order,id", [(int)$offer['id']]);
        if (!$offer['images'] && !empty($offer['banner_image'])) $offer['images'][]=['id'=>0,'image_path'=>$offer['banner_image'],'alt_text'=>$offer['title'],'is_primary'=>1,'sort_order'=>0];
        return $offer;
    }

    public static function save(array $data, int $id = 0): array
    {
        self::ensureSchema();
        $title = trim((string)($data['title'] ?? ''));
        $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', (string)($data['slug'] ?? $title)), '-'));
        if ($title === '' || $slug === '') return ['ok'=>false,'msg'=>'Title and slug are required.'];
        if(trim((string)($data['video_url']??''))!==''&&self::normalizeVideoUrl((string)$data['video_url'])==='')return ['ok'=>false,'msg'=>'Enter a valid YouTube video URL.'];

        $items = [];
        $subtotal = 0.0;
        foreach ((array)($data['products'] ?? []) as $row) {
            $productId = (int)($row['product_id'] ?? 0); $quantity = (int)($row['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) continue;
            $price = \Cart\Pricing::calculate($productId, 1, $quantity, [], 'upload');
            if (empty($price['ok'])) return ['ok'=>false,'msg'=>'Selected product quantity does not have valid pricing.'];
            $linePrice = (float)$price['total']; $subtotal += $linePrice;
            $items[] = ['product_id'=>$productId,'quantity'=>$quantity,'regular_price'=>$linePrice];
        }

        $customItems = [];
        foreach ((array)($data['custom_items'] ?? []) as $row) {
            $name = trim((string)($row['item_name'] ?? '')); $price = round(max(0, (float)($row['item_price'] ?? 0)), 2); $quantity=max(1,(int)($row['quantity']??1));
            if ($name === '' && $price <= 0) continue;
            if ($name === '' || $price <= 0) return ['ok'=>false,'msg'=>'Every custom item needs a name and price.'];
            $customItems[] = ['item_name'=>$name,'quantity'=>$quantity,'item_price'=>$price,'thumbnail_path'=>trim((string)($row['thumbnail_path'] ?? '')),'description'=>trim((string)($row['description'] ?? ''))];
            $subtotal += $price * $quantity;
        }
        if (!$items && !$customItems) return ['ok'=>false,'msg'=>'Select at least one product or add a custom item.'];

        $discount = max(0, min(100, (float)($data['discount_percent'] ?? 0)));
        $offerPrice = round(max(0, (float)($data['combo_price'] ?? 0)), 2);
        if ($offerPrice <= 0 && $discount > 0) $offerPrice = round($subtotal * (1 - $discount / 100), 2);
        if ($offerPrice <= 0) return ['ok'=>false,'msg'=>'Offer price is required.'];
        if ($offerPrice > $subtotal) return ['ok'=>false,'msg'=>'Offer price cannot exceed the products subtotal.'];
        $discount = $subtotal > 0 ? round((1 - ($offerPrice / $subtotal)) * 100, 2) : 0;

        $gallery=array_values(array_unique(array_filter(array_map(static fn($path)=>trim((string)$path),(array)($data['gallery_images']??[])),static fn($path)=>$path!==''&&str_starts_with($path,'/uploads/combos/'))));
        $banner=trim((string)($data['banner_image']??''));if($gallery)$banner=$gallery[0];
        $videoUrl=self::normalizeVideoUrl((string)($data['video_url']??''));$videoPath=trim((string)($data['video_path']??''));if($videoPath!==''&&!str_starts_with($videoPath,'/uploads/combos/'))$videoPath='';
        $values = [$title,$slug,trim((string)($data['badge']??'')),trim((string)($data['short_description']??'')),trim((string)($data['description']??'')),$banner,$subtotal,$discount,$offerPrice,in_array(($data['layout_slot']??''),['large','wide','square_3','square_4'],true)?$data['layout_slot']:'square_3',trim((string)($data['cta_text']??'View Offer'))?:'View Offer',!empty($data['show_title_home'])?1:0,!empty($data['show_title_detail'])?1:0,!empty($data['show_badge_home'])?1:0,!empty($data['show_badge_detail'])?1:0,!empty($data['show_cta_home'])?1:0,!empty($data['show_cta_detail'])?1:0,!empty($data['show_short_description_home'])?1:0,!empty($data['show_short_description_detail'])?1:0,!empty($data['show_description_home'])?1:0,!empty($data['show_description_detail'])?1:0];
        $current = $id ? \Database::row("SELECT is_active,show_on_home FROM combo_offers WHERE id=?", [$id]) : null;
        if ($current && !empty($current['is_active']) && !empty($current['show_on_home'])) {
            $occupant = self::positionOccupant((string)$values[9], $id);
            if ($occupant) return ['ok'=>false,'msg'=>'That homepage position is already used by “'.$occupant['title'].'”. Deactivate it or choose another position.'];
        }
        $db = \Database::get();
        try {
            $db->beginTransaction();
            if ($id) {
                \Database::query("UPDATE combo_offers SET title=?,slug=?,badge=?,short_description=?,description=?,banner_image=?,regular_price=?,discount_percent=?,combo_price=?,layout_slot=?,cta_text=?,show_title_home=?,show_title_detail=?,show_badge_home=?,show_badge_detail=?,show_cta_home=?,show_cta_detail=?,show_short_description_home=?,show_short_description_detail=?,show_description_home=?,show_description_detail=?,updated_at=NOW() WHERE id=?", [...$values,$id]);
            } else {
                $id = \Database::insert("INSERT INTO combo_offers(title,slug,badge,short_description,description,banner_image,regular_price,discount_percent,combo_price,layout_slot,cta_text,show_title_home,show_title_detail,show_badge_home,show_badge_detail,show_cta_home,show_cta_detail,show_short_description_home,show_short_description_detail,show_description_home,show_description_detail,sort_order,show_on_home,is_active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,1,0,NOW())", $values);
            }
            \Database::query("DELETE FROM combo_offer_items WHERE combo_offer_id=?", [$id]);
            \Database::query("DELETE FROM combo_offer_custom_items WHERE combo_offer_id=?", [$id]);
            foreach ($items as $i => $row) \Database::insert("INSERT INTO combo_offer_items(combo_offer_id,product_id,quantity,regular_price,sort_order,created_at) VALUES(?,?,?,?,?,NOW())", [$id,$row['product_id'],$row['quantity'],$row['regular_price'],$i]);
            foreach ($customItems as $i => $row) \Database::insert("INSERT INTO combo_offer_custom_items(combo_offer_id,item_name,quantity,item_price,thumbnail_path,description,sort_order,created_at) VALUES(?,?,?,?,?,?,?,NOW())", [$id,$row['item_name'],$row['quantity'],$row['item_price'],$row['thumbnail_path'],$row['description'],$i]);
            \Database::query("UPDATE combo_offers SET video_url=?,video_path=?,show_delivery_info=?,delivery_info=?,show_free_delivery_info=?,free_delivery_info=? WHERE id=?",[$videoUrl,$videoPath,!empty($data['show_delivery_info'])?1:0,trim((string)($data['delivery_info']??'')),!empty($data['show_free_delivery_info'])?1:0,trim((string)($data['free_delivery_info']??'')),$id]);
            \Database::query("DELETE FROM combo_offer_images WHERE combo_offer_id=?",[$id]);
            foreach($gallery as $i=>$path)\Database::insert("INSERT INTO combo_offer_images(combo_offer_id,image_path,alt_text,is_primary,sort_order,created_at) VALUES(?,?,?,?,?,NOW())",[$id,$path,$title,$i===0?1:0,$i]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) return ['ok'=>false,'msg'=>'This slug is already in use.'];
            throw $e;
        }
        return ['ok'=>true,'id'=>$id,'subtotal'=>$subtotal,'discount_percent'=>$discount,'combo_price'=>$offerPrice];
    }

    private static function normalizeVideoUrl(string $url): string
    {
        $url=trim($url);if($url==='')return '';
        if(!preg_match('~^https://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[&?].*)?$~i',$url,$m))return '';
        return 'https://www.youtube.com/embed/'.$m[1];
    }

    public static function delete(int $id): void
    {
        self::ensureSchema();
        \Database::query("DELETE FROM combo_offers WHERE id=?", [$id]);
    }
}

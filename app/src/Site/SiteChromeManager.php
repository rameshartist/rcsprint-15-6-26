<?php
declare(strict_types=1);

namespace Site;

final class SiteChromeManager
{
    public static function ensureSchema(): void
    {
        \Database::query("CREATE TABLE IF NOT EXISTS site_chrome_settings (`key` VARCHAR(80) PRIMARY KEY, value TEXT NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        \Database::query("CREATE TABLE IF NOT EXISTS site_navigation_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, location VARCHAR(40) NOT NULL, label VARCHAR(120) NOT NULL, url VARCHAR(500) NOT NULL, sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, KEY idx_site_nav (location,is_active,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::seedNavigation();
    }

    private static function seedNavigation(): void
    {
        $ready=\Database::row("SELECT value FROM site_chrome_settings WHERE `key`='navigation_v2_initialized'");
        if($ready)return;
        $defaults=[
            'header'=>[['Home','/'],['About','/about'],['Products','@products'],['Portfolio','/portfolio'],['Blog','/blogs'],['Contact','/contact']],
            'footer_quick'=>[['Home','/'],['About Us','/about'],['Products','/categories'],['Blog','/blogs'],['My Account','/profile'],['Contact Us','/contact']],
            'footer_products'=>[['Visiting Card','/category/visiting-cards'],['Brochure','/category/brochures'],['Flyer','/category/flyers'],['Diary','/category/diaries'],['Calendar','/category/calendars'],['Flex Banner','/category/banners']],
            'footer_service'=>[['My Account','/profile'],['Track Order','/my-orders'],['Shipping Policy','/shipping-policy'],['Refund & Return','/refund-return-policy'],['Terms & Conditions','/terms-and-conditions'],['Privacy Policy','/privacy-policy']],
        ];
        foreach($defaults as $location=>$items){
            $existing=(int)(\Database::row("SELECT COUNT(*) c FROM site_navigation_items WHERE location=?",[$location])['c']??0);
            if($existing&&$location!=='header')continue;
            foreach($items as $i=>[$label,$url]){
                if($existing&&\Database::row("SELECT id FROM site_navigation_items WHERE location=? AND url=? LIMIT 1",[$location,$url]))continue;
                $order=($existing&&$location==='header'&&$i<3)?(-30+$i*10):(($i+1)*10);
                \Database::insert("INSERT INTO site_navigation_items(location,label,url,sort_order,is_active,created_at) VALUES(?,?,?,?,1,NOW())",[$location,$label,$url,$order]);
            }
        }
        \Database::query("INSERT INTO site_chrome_settings(`key`,value,updated_at) VALUES('navigation_v2_initialized','1',NOW()) ON DUPLICATE KEY UPDATE value='1',updated_at=NOW()");
    }

    public static function settings(): array
    {
        self::ensureSchema();
        $defaults = [
            'top_bar_text' => 'Free Delivery in Rajkot on All Orders Above ₹999',
            'top_bar_url' => '',
            'top_bar_active' => '1',
            'header_logo' => '/assets/images/rcs-graphic-logo.png',
            'header_quote_text' => 'Get Custom Quote',
            'footer_logo' => '/assets/images/RCS PRINT LOGO-white.png',
            'footer_description' => 'Your one-stop solution for all your printing needs. Quality prints that represent your brand perfectly.',
            'footer_contact_heading'=>'Contact Us','footer_hours'=>'Mon - Sat: 10:00 AM - 7:00 PM',
            'footer_newsletter_heading'=>'Newsletter','footer_newsletter_text'=>'Subscribe to get special offers, free giveaways, and useful print updates.',
            'footer_copyright'=>'© {year} {business}. All Rights Reserved.','footer_developer'=>'Developed By Prakash Karena',
            'social_facebook'=>'','social_instagram'=>'','social_linkedin'=>'','social_youtube'=>'',
        ];
        try { return array_replace($defaults, array_column(\Database::rows("SELECT `key`,value FROM site_chrome_settings"), 'value', 'key')); }
        catch (\Throwable) { return $defaults; }
    }

    public static function navigation(): array
    {
        self::ensureSchema();
        $result = ['header' => [], 'footer_quick' => [], 'footer_products' => [], 'footer_service' => []];
        foreach (\Database::rows("SELECT id,location,label,url,sort_order,is_active FROM site_navigation_items ORDER BY location,sort_order,id") as $row) {
            if (isset($result[$row['location']])) $result[$row['location']][] = $row;
        }
        return $result;
    }

    public static function payload(): array { return ['settings' => self::settings(), 'navigation' => self::navigation()]; }

    public static function save(array $data): void
    {
        self::ensureSchema();
        $allowed = ['top_bar_text','top_bar_url','top_bar_active','header_logo','header_quote_text','footer_logo','footer_description','footer_contact_heading','footer_hours','footer_newsletter_heading','footer_newsletter_text','footer_copyright','footer_developer','social_facebook','social_instagram','social_linkedin','social_youtube'];
        $old = self::settings();
        $db=\Database::get();
        try {
            $db->beginTransaction();
            foreach ($allowed as $key) {
                $value = trim((string)($data['settings'][$key] ?? $old[$key] ?? ''));
                if($key==='top_bar_url'||str_starts_with($key,'social_'))$value=self::validUrl($value);
                \Database::query("INSERT INTO site_chrome_settings (`key`,value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=NOW()", [$key,$value]);
            }
            \Database::query("DELETE FROM site_navigation_items");
            foreach ((array)($data['navigation'] ?? []) as $location => $items) {
                if (!in_array($location, ['header','footer_quick','footer_products','footer_service'], true)) continue;
                foreach ((array)$items as $i => $item) {
                    $label = trim((string)($item['label'] ?? '')); $url = self::validUrl((string)($item['url'] ?? ''));
                    if ($label === '' || $url === '') continue;
                    \Database::insert("INSERT INTO site_navigation_items(location,label,url,sort_order,is_active,created_at) VALUES(?,?,?,?,?,NOW())", [$location,mb_substr($label,0,120),$url,($i+1)*10,!empty($item['is_active'])?1:0]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
        foreach (['header_logo','footer_logo'] as $key) self::removeReplacedUpload((string)($old[$key] ?? ''), (string)($data['settings'][$key] ?? ''));
    }

    private static function validUrl(string $url): string
    {
        $url=trim($url); if($url==='@products')return $url;
        if($url===''||preg_match('/[\x00-\x1F\x7F]/',$url))return '';
        if(str_starts_with($url,'/')||str_starts_with($url,'#'))return mb_substr($url,0,500);
        $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
        return in_array($scheme,['https','http','mailto','tel'],true)?mb_substr($url,0,500):'';
    }

    private static function removeReplacedUpload(string $old, string $new): void
    {
        if ($old === '' || $old === $new || !str_starts_with($old, '/uploads/site/')) return;
        $path = PUBLIC_PATH . $old;
        if (is_file($path)) @unlink($path);
    }
}

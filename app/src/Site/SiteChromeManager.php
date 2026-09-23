<?php
declare(strict_types=1);

namespace Site;

final class SiteChromeManager
{
    public static function ensureSchema(): void
    {
        \Database::query("CREATE TABLE IF NOT EXISTS site_chrome_settings (`key` VARCHAR(80) PRIMARY KEY, value TEXT NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        \Database::query("CREATE TABLE IF NOT EXISTS site_navigation_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, location VARCHAR(40) NOT NULL, label VARCHAR(120) NOT NULL, url VARCHAR(500) NOT NULL, sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, KEY idx_site_nav (location,is_active,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function settings(): array
    {
        self::ensureSchema();
        $defaults = [
            'top_bar_text' => 'Free Delivery in Rajkot on All Orders Above ₹999',
            'header_logo' => '/assets/images/rcs-graphic-logo.png',
            'footer_logo' => '/assets/images/RCS PRINT LOGO-white.png',
            'footer_description' => 'Your one-stop solution for all your printing needs. Quality prints that represent your brand perfectly.',
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
        $allowed = ['top_bar_text','header_logo','footer_logo','footer_description'];
        $old = self::settings();
        foreach ($allowed as $key) {
            $value = trim((string)($data['settings'][$key] ?? $old[$key] ?? ''));
            \Database::query("INSERT INTO site_chrome_settings (`key`,value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=NOW()", [$key,$value]);
        }
        \Database::query("DELETE FROM site_navigation_items");
        foreach ((array)($data['navigation'] ?? []) as $location => $items) {
            if (!in_array($location, ['header','footer_quick','footer_products','footer_service'], true)) continue;
            foreach ((array)$items as $i => $item) {
                $label = trim((string)($item['label'] ?? '')); $url = trim((string)($item['url'] ?? ''));
                if ($label === '' || $url === '') continue;
                \Database::insert("INSERT INTO site_navigation_items(location,label,url,sort_order,is_active,created_at) VALUES(?,?,?,?,1,NOW())", [$location,$label,$url,$i]);
            }
        }
        foreach (['header_logo','footer_logo'] as $key) self::removeReplacedUpload((string)($old[$key] ?? ''), (string)($data['settings'][$key] ?? ''));
    }

    private static function removeReplacedUpload(string $old, string $new): void
    {
        if ($old === '' || $old === $new || !str_starts_with($old, '/uploads/site/')) return;
        $path = PUBLIC_PATH . $old;
        if (is_file($path)) @unlink($path);
    }
}

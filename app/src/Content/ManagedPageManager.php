<?php
declare(strict_types=1);
namespace Content;

final class ManagedPageManager
{
    private const PAGES=['shipping-policy'=>'Shipping Policy','refund-return-policy'=>'Refund & Return','terms-and-conditions'=>'Terms & Conditions','privacy-policy'=>'Privacy Policy'];

    public static function all(): array
    {
        self::ensureSchema();$rows=\Database::rows("SELECT page_key,title,content_html,updated_at FROM managed_pages ORDER BY FIELD(page_key,'shipping-policy','refund-return-policy','terms-and-conditions','privacy-policy')");
        return array_map(static fn(array $row)=>$row+['label'=>self::PAGES[$row['page_key']]??$row['title']],$rows);
    }

    public static function get(string $key): ?array
    {
        if(!isset(self::PAGES[$key]))return null;self::ensureSchema();return \Database::row('SELECT page_key,title,content_html,updated_at FROM managed_pages WHERE page_key=? LIMIT 1',[$key]);
    }

    public static function save(string $key,string $html): array
    {
        if(!isset(self::PAGES[$key]))return ['ok'=>false,'msg'=>'Invalid page.'];
        $html=self::sanitize($html);if(trim(strip_tags($html))==='')$html='<p></p>';
        self::ensureSchema();\Database::query('UPDATE managed_pages SET content_html=?,updated_at=NOW() WHERE page_key=?',[$html,$key]);
        return ['ok'=>true,'msg'=>self::PAGES[$key].' updated.'];
    }

    private static function ensureSchema(): void
    {
        \Database::query("CREATE TABLE IF NOT EXISTS managed_pages (page_key VARCHAR(80) PRIMARY KEY,title VARCHAR(180) NOT NULL,content_html MEDIUMTEXT NOT NULL,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $source=require APP_PATH.'/data/site_pages.php';
        foreach(self::PAGES as $key=>$title){$page=$source[$key]??[];$html='';foreach((array)($page['sections']??[]) as $section){$html.='<h2>'.htmlspecialchars((string)($section['title']??''),ENT_QUOTES,'UTF-8').'</h2>';foreach((array)($section['body']??[]) as $paragraph)$html.='<p>'.htmlspecialchars((string)$paragraph,ENT_QUOTES,'UTF-8').'</p>';if(!empty($section['bullets'])){$html.='<ul>';foreach((array)$section['bullets'] as $bullet)$html.='<li>'.htmlspecialchars((string)$bullet,ENT_QUOTES,'UTF-8').'</li>';$html.='</ul>';}}\Database::query('INSERT IGNORE INTO managed_pages(page_key,title,content_html,updated_at) VALUES(?,?,?,NOW())',[$key,$title,$html?:'<p>'.$title.'</p>']);}
    }

    private static function sanitize(string $html): string
    {
        $html=strip_tags($html,'<h2><h3><p><br><strong><b><em><i><u><ul><ol><li><blockquote><a>');
        $html=preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html)??$html;
        $html=preg_replace('/\s+(style|class|id)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html)??$html;
        $html=preg_replace_callback('/<a\s+([^>]*)>/i',static function(array $m):string{if(!preg_match('/href\s*=\s*(["\'])(.*?)\1/i',$m[1],$href))return '<a>'; $url=trim($href[2]);if(!preg_match('#^(https?://|mailto:|tel:|/)#i',$url))return '<a>';return '<a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">';},$html)??$html;
        return trim($html);
    }
}

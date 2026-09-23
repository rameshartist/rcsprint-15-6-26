<?php
$sfSettings = is_array($settingsMap ?? null) ? $settingsMap : [];
if ($sfSettings === []) {
    try {
        $rows = Database::rows("SELECT `key`, value FROM settings");
        $sfSettings = array_column($rows, 'value', 'key');
    } catch (\Throwable) {
        $sfSettings = [];
    }
}
$sfBizName = htmlspecialchars($sfSettings['biz_name'] ?? 'RCS Graphic', ENT_QUOTES, 'UTF-8');
$sfPhoneRaw = (string)($sfSettings['biz_phone'] ?? '+91 98765 43210');
$sfPhone = htmlspecialchars($sfPhoneRaw, ENT_QUOTES, 'UTF-8');
$sfPhoneHref = preg_replace('/\D+/', '', $sfPhoneRaw);
$sfWa = htmlspecialchars($sfSettings['biz_whatsapp'] ?? '919876543210', ENT_QUOTES, 'UTF-8');
$sfEmail = htmlspecialchars($sfSettings['biz_email'] ?? 'hello@rcsgraphic.in', ENT_QUOTES, 'UTF-8');
$sfAddr = htmlspecialchars($sfSettings['biz_address'] ?? 'Rajkot, Gujarat', ENT_QUOTES, 'UTF-8');
try { $sfChrome = \Site\SiteChromeManager::payload(); } catch (\Throwable) { $sfChrome=['settings'=>[],'navigation'=>[]]; }
$sfChromeSettings=$sfChrome['settings']??[]; $sfChromeNav=$sfChrome['navigation']??[];

$sfFooterCategories = [];
try {
    $sfFooterCategories = \Catalog\ProductCatalog::categories();
} catch (\Throwable) {
    $sfFooterCategories = [];
}
$sfNormalizeCategory = static fn(string $value): string => preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
$sfCategoryHref = static function (string $label, array $aliases = [], string $fallbackSlug = '') use ($sfFooterCategories, $sfNormalizeCategory): string {
    $terms = array_filter(array_map('strval', array_merge([$label], $aliases)));
    $normalizedTerms = array_map($sfNormalizeCategory, $terms);

    foreach ($sfFooterCategories as $category) {
        $name = (string)($category['name'] ?? '');
        $slug = (string)($category['slug'] ?? '');
        $normalizedName = $sfNormalizeCategory($name);
        $normalizedSlug = $sfNormalizeCategory($slug);
        foreach ($normalizedTerms as $term) {
            if ($term === '') continue;
            if ($term === $normalizedName || $term === $normalizedSlug || str_contains($normalizedName, $term) || str_contains($term, $normalizedName)) {
                return '/category/' . rawurlencode($slug);
            }
        }
    }

    $fallbackSlug = trim($fallbackSlug) !== '' ? $fallbackSlug : strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $label) ?? '', '-'));
    return $fallbackSlug !== '' ? '/category/' . rawurlencode($fallbackSlug) : '/categories';
};
?>
<footer class="footer" aria-label="Site footer" data-design-target="footer.section">
  <div class="footer-container">
    <div class="footer-main">
      <div class="footer-brand-col">
        <a href="/" class="footer-logo" aria-label="<?= $sfBizName ?> home">
          <img src="<?= htmlspecialchars((string)($sfChromeSettings['footer_logo'] ?? '/assets/images/RCS PRINT LOGO-white.png'), ENT_QUOTES) ?>" alt="<?= htmlspecialchars($sfBizName) ?> Logo" class="footer-logo-img" loading="lazy" decoding="async">
        </a>
        <p class="footer-desc" data-design-target="footer.links"><?= htmlspecialchars((string)($sfChromeSettings['footer_description'] ?? 'Your one-stop solution for all your printing needs. Quality prints that represent your brand perfectly.')) ?></p>
      </div>

      <nav class="footer-col" aria-label="Quick links">
        <h3>Quick Links</h3>
        <?php if (!empty($sfChromeNav['footer_quick'])): foreach ($sfChromeNav['footer_quick'] as $navItem): ?><a href="<?= htmlspecialchars((string)$navItem['url'], ENT_QUOTES) ?>"><?= htmlspecialchars((string)$navItem['label']) ?></a><?php endforeach; else: ?>
        <a href="/">Home</a>
        <a href="/about">About Us</a>
        <a href="/categories">Products</a>
        <a href="/blogs">Blog</a>
        <a href="<?= ($user ?? null) ? '/profile' : '/login' ?>">My Account</a>
        <a href="/contact">Contact Us</a>
        <?php endif; ?>
      </nav>

      <nav class="footer-col" aria-label="Products">
        <h3>Products</h3>
        <?php if (!empty($sfChromeNav['footer_products'])): foreach ($sfChromeNav['footer_products'] as $navItem): ?><a href="<?= htmlspecialchars((string)$navItem['url'], ENT_QUOTES) ?>"><?= htmlspecialchars((string)$navItem['label']) ?></a><?php endforeach; else: ?>
        <a href="<?= $sfCategoryHref('Visiting Card', ['Visiting Cards', 'Business Cards'], 'visiting-cards') ?>">Visiting Card</a>
        <a href="<?= $sfCategoryHref('Brochure', ['Brochures'], 'brochures') ?>">Brochure</a>
        <a href="<?= $sfCategoryHref('Flyer', ['Flyers'], 'flyers') ?>">Flyer</a>
        <a href="<?= $sfCategoryHref('Diary', ['Diaries'], 'diaries') ?>">Diary</a>
        <a href="<?= $sfCategoryHref('Calendar', ['Calendars'], 'calendars') ?>">Calendar</a>
        <a href="<?= $sfCategoryHref('Flex Banner', ['Flex Banners', 'Banner', 'Banners'], 'banners') ?>">Flex Banner</a>
        <?php endif; ?>
      </nav>

      <nav class="footer-col" aria-label="Customer service">
        <h3>Customer Service</h3>
        <?php if (!empty($sfChromeNav['footer_service'])): foreach ($sfChromeNav['footer_service'] as $navItem): ?><a href="<?= htmlspecialchars((string)$navItem['url'], ENT_QUOTES) ?>"><?= htmlspecialchars((string)$navItem['label']) ?></a><?php endforeach; else: ?>
        <a href="<?= ($user ?? null) ? '/profile' : '/login' ?>">My Account</a>
        <a href="/my-orders">Track Order</a>
        <a href="/shipping-policy">Shipping Policy</a>
        <a href="/refund-return-policy">Refund &amp; Return</a>
        <a href="/terms-and-conditions">Terms &amp; Conditions</a>
        <a href="/privacy-policy">Privacy Policy</a>
        <?php endif; ?>
      </nav>

      <div class="footer-col footer-contact-col">
        <h3>Contact Us</h3>
        <div class="footer-contact-item">
          <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
          <span><?= $sfAddr ?></span>
        </div>
        <a class="footer-contact-item" href="tel:<?= htmlspecialchars($sfPhoneHref, ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-phone" aria-hidden="true"></i>
          <span><?= $sfPhone ?></span>
        </a>
        <a class="footer-contact-item" href="mailto:<?= $sfEmail ?>">
          <i class="fa-regular fa-envelope" aria-hidden="true"></i>
          <span><?= $sfEmail ?></span>
        </a>
        <div class="footer-contact-item">
          <i class="fa-regular fa-clock" aria-hidden="true"></i>
          <span>Mon - Sat: 10:00 AM - 7:00 PM</span>
        </div>
      </div>

      <div class="footer-col footer-newsletter-col">
        <h3>Newsletter</h3>
        <p>Subscribe to get special offers, free giveaways, and useful print updates.</p>
        <form class="footer-newsletter" action="/categories" method="get">
          <label class="sr-only" for="footerEmail">Enter your email</label>
          <input id="footerEmail" name="email" type="email" placeholder="Enter your email" autocomplete="email">
          <button type="submit">Subscribe</button>
        </form>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="footer-copy">© <?= date('Y') ?> <?= $sfBizName ?>. All Rights Reserved.</div>
      <div class="footer-developed">Developed By Prakash Karena</div>
    </div>
  </div>
</footer>

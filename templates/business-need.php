<?php
/** Business/Sector product listing page. */
$businessNeed = is_array($businessNeed ?? null) ? $businessNeed : [];
$businessProducts = is_array($businessProducts ?? null) ? $businessProducts : [];
$businessNeeds = is_array($businessNeeds ?? null) ? $businessNeeds : [];
$settingsMap = is_array($settingsMap ?? null) ? $settingsMap : [];
$needName = trim((string)($businessNeed['name'] ?? 'Business Need')) ?: 'Business Need';
$needIcon = trim((string)($businessNeed['icon'] ?? '🏢')) ?: '🏢';
$pageTitle = $needName . ' Products — RCS Graphic';
$pageDesc = trim((string)($businessNeed['description'] ?? 'Browse selected printing products for this business sector.'));
$bizPhone = htmlspecialchars($settingsMap['biz_phone'] ?? '+91 98765 43210');
$bizWa = htmlspecialchars($settingsMap['biz_whatsapp'] ?? '919876543210');
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
?>
<main class="all-cat-page business-page">
  <?php
  $pageHero = [
    'key' => 'business_' . trim((preg_replace('/[^a-z0-9_-]+/i', '-', (string)($businessNeed['slug'] ?? 'sector')) ?? 'sector'), '-'),
    'title' => $needIcon . ' ' . $needName,
    'subtitle' => $pageDesc !== '' ? $pageDesc : 'Curated print products for this sector.',
    'eyebrow' => 'Shop by Business Need',
    'breadcrumbs' => [
      ['label' => 'Home', 'url' => '/'],
      ['label' => 'Business Sectors', 'url' => '/business'],
      ['label' => $needName, 'url' => null],
    ],
    'fallback_image' => trim((string)($businessNeed['image_path'] ?? '')) ?: '/assets/img/categories/print-category.svg',
  ];
  include INCLUDE_PATH . '/partials/page-hero.php';
  ?>
  <div class="container all-cat-content">
    <section class="all-cat-shop business-sector-shop" aria-label="<?= htmlspecialchars($needName, ENT_QUOTES, 'UTF-8') ?> products">
      <?php if (!empty($businessNeeds)): ?>
      <details class="all-cat-filter-panel" open>
        <summary><span>Filters</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
        <aside class="all-cat-sidebar" aria-label="Business sector filters">
          <div class="all-cat-side-box all-cat-side-categories">
            <h2>Sectors</h2>
            <nav class="all-cat-side-list" aria-label="Business sector quick links">
              <?php foreach ($businessNeeds as $need):
                $sideName = trim((string)($need['name'] ?? 'Business Sector'));
                $sideSlug = trim((string)($need['slug'] ?? ''));
                if ($sideSlug === '') continue;
                $isActive = $sideSlug === (string)($businessNeed['slug'] ?? '');
              ?>
                <a class="<?= $isActive ? 'is-active' : '' ?>" href="/business/<?= htmlspecialchars($sideSlug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sideName, ENT_QUOTES, 'UTF-8') ?></a>
              <?php endforeach; ?>
            </nav>
          </div>
        </aside>
      </details>
      <?php endif; ?>

      <div class="all-cat-results">
        <div class="all-cat-toolbar"><p>Showing <?= count($businessProducts) ?> product<?= count($businessProducts) === 1 ? '' : 's' ?> for <?= htmlspecialchars($needName, ENT_QUOTES, 'UTF-8') ?></p></div>
        <?php if (empty($businessProducts)): ?>
          <div class="business-empty-state"><strong>No products assigned yet</strong><span>Please check back soon or contact us for a custom quote.</span><a href="https://wa.me/<?= htmlspecialchars($bizWa) ?>" class="btn btn-green" target="_blank" rel="noopener">WhatsApp Us</a></div>
        <?php else: ?>
          <div class="all-cat-grid" id="businessProductsGrid">
            <?php foreach ($businessProducts as $idx => $product):
              $name = (string)($product['name'] ?? 'Print Product');
              $slug = (string)($product['slug'] ?? '');
              $href = $slug !== '' ? '/product/' . rawurlencode($slug) : '/categories';
              $img = trim((string)($product['primary_image'] ?? ($product['image_path'] ?? '')));
              $category = trim((string)($product['category_name'] ?? 'Print Product'));
              $price = (float)($product['min_price'] ?? 0);
            ?>
            <a class="all-cat-card all-cat-card-<?= htmlspecialchars(['purple','orange','green'][$idx%3]) ?>" href="<?= htmlspecialchars($href) ?>" data-reveal data-reveal-delay="<?= ($idx % 3) * 60 ?>">
              <div class="all-cat-img"><?php if($img!==''):?><img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy" onerror="this.src='https://placehold.co/400x300/EEF3FD/1A56E8?text=<?= urlencode($name) ?>'"><?php else:?><div class="shop-cat-fallback" aria-hidden="true">📦</div><?php endif;?></div>
              <div class="all-cat-body"><h2><?= htmlspecialchars($name) ?></h2><strong><?= $price>0?'Starting from ₹'.number_format($price):'Price on request' ?></strong></div>
            </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
<section class="quick-help-section" id="quick-help-sec" aria-label="Quick help and bulk order actions" data-reveal>
  <div class="quick-help-container"><div class="quick-help-bar"><a class="quick-help-item quick-help-call" href="tel:<?= preg_replace('/\D+/', '', $bizPhone) ?>"><span class="quick-help-icon"><i class="fa-solid fa-phone-volume" aria-hidden="true"></i></span><span class="quick-help-copy"><span>Need Help? Call Us</span><strong><?= $bizPhone ?></strong></span></a><button class="quick-help-item quick-help-whatsapp" type="button" onclick="window.open('https://wa.me/<?= $bizWa ?>','_blank')"><span class="quick-help-icon"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span><span class="quick-help-copy"><strong>Chat with us on WhatsApp</strong><span>We are here to help!</span></span></button><a class="quick-help-item quick-help-download" href="/categories"><span class="quick-help-icon"><i class="fa-solid fa-download" aria-hidden="true"></i></span><span class="quick-help-copy"><strong>Download Our Brochure</strong><span>For All Products</span></span></a></div></div>
</section>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

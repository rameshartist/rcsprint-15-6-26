<?php
/**
 * categories.php — All Categories Page
 * Shows every active category with its square category image.
 * Route: /categories
 */
$pageTitle = 'All Categories — RCS Graphic';
$pageDesc  = 'Browse all printing categories at RCS Graphic — visiting cards, brochures, flyers, posters and more.';

$activeCategories = array_values(array_filter($categories ?? [], static fn($cat) => (int)($cat['is_active'] ?? 1) === 1));
$bizName  = htmlspecialchars($settingsMap['biz_name']    ?? 'RCS Graphic');
$bizPhone = htmlspecialchars($settingsMap['biz_phone']   ?? '+91 98765 43210');
$bizWa    = htmlspecialchars($settingsMap['biz_whatsapp']?? '919876543210');
$bizEmail = htmlspecialchars($settingsMap['biz_email']   ?? 'hello@rcsgraphic.in');
$bizAddr  = htmlspecialchars($settingsMap['biz_address'] ?? 'Rajkot, Gujarat');
$visibleCount = count($activeCategories);
$categoryThemeClasses = ['purple', 'orange', 'orange', 'orange', 'purple', 'orange', 'purple', 'green'];

include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
?>

<main class="all-cat-page">
  <?php
  $pageHero = [
    'key' => 'categories',
    'title' => 'All Categories',
    'subtitle' => 'Premium quality printing for every need.',
    'eyebrow' => 'Shop by Category',
    'breadcrumbs' => [
      ['label' => 'Home', 'url' => '/'],
      ['label' => 'All Categories', 'url' => null],
    ],
    'fallback_image' => '/assets/img/categories/all-categories-hero.svg',
  ];
  include INCLUDE_PATH . '/partials/page-hero.php';
  ?>

  <div class="container all-cat-content">
    <?php if (!empty($businessNeed ?? null)): ?>
      <section class="ym-section business-need-products-page" aria-labelledby="businessNeedPageTitle">
        <div class="ym-head">
          <h2 id="businessNeedPageTitle" class="ym-title"><?= htmlspecialchars((string)($businessNeed['icon'] ?? '🏢')) ?> <?= htmlspecialchars((string)($businessNeed['name'] ?? 'Business Need')) ?> <span>Products</span></h2>
          <a href="/categories" class="ym-view-all">Back to All Categories</a>
        </div>
        <?php if (!empty($businessNeed['description'])): ?><p class="business-need-page-desc"><?= htmlspecialchars((string)$businessNeed['description']) ?></p><?php endif; ?>
        <?php if (empty($businessProducts ?? [])): ?>
          <div class="adm-empty">No products are assigned to this business need yet.</div>
        <?php else: ?>
          <div class="ym-grid ym-product-grid">
            <?php foreach ($businessProducts as $idx => $relatedProduct):
              $relatedName = (string)($relatedProduct['name'] ?? 'Print Product');
              $relatedSlug = (string)($relatedProduct['slug'] ?? '');
              $relatedHref = $relatedSlug !== '' ? '/product/' . rawurlencode($relatedSlug) : '/categories';
              $relatedImg = trim((string)($relatedProduct['primary_image'] ?? ($relatedProduct['image_path'] ?? '')));
              $relatedCategory = trim((string)($relatedProduct['category_name'] ?? 'Print Product'));
              $relatedMinPrice = (float)($relatedProduct['min_price'] ?? 0);
            ?>
            <article class="ym-card ym-product-card" data-reveal data-reveal-delay="<?= ($idx % 3) * 60 ?>">
              <a class="ym-img ym-product-img" href="<?= htmlspecialchars($relatedHref) ?>">
                <?php if ($relatedImg !== ''): ?><img src="<?= htmlspecialchars($relatedImg) ?>" alt="<?= htmlspecialchars($relatedName) ?>" loading="lazy"><span class="ym-product-fallback" hidden><i class="fa-solid fa-print" aria-hidden="true"></i></span><?php else: ?><span class="ym-product-fallback"><i class="fa-solid fa-print" aria-hidden="true"></i></span><?php endif; ?>
              </a>
              <div class="ym-body"><div class="ym-cat"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><?= htmlspecialchars($relatedCategory) ?></div><h3 class="ym-name"><?= htmlspecialchars($relatedName) ?></h3><div class="ym-foot"><div><div class="ym-from">Starting from</div><div class="ym-price">₹<?= $relatedMinPrice > 0 ? number_format($relatedMinPrice) : '—' ?></div></div><a href="<?= htmlspecialchars($relatedHref) ?>" class="ym-order">VIEW</a></div></div>
            </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if (empty($activeCategories)): ?>
      <div style="text-align:center;padding:80px 20px;background:#fff;border:1px solid var(--border);border-radius:18px">
        <div style="font-size:48px;margin-bottom:12px">🗂️</div>
        <div style="font-size:17px;font-weight:700;color:var(--ink);margin-bottom:6px">No categories yet</div>
        <p style="font-size:13px;color:var(--text2);margin-bottom:20px">Please check back soon or contact us on WhatsApp.</p>
        <a href="https://wa.me/<?= htmlspecialchars($bizWa) ?>" target="_blank" rel="noopener" class="btn btn-green">💬 WhatsApp Us</a>
      </div>
    <?php else: ?>
      <section class="all-cat-shop" aria-label="Browse all categories">
        <details class="all-cat-filter-panel" open>
          <summary><span>Categories</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
          <aside class="all-cat-sidebar" aria-label="Category filters">
          <div class="all-cat-side-box all-cat-side-categories">
            <h2>Categories</h2>
            <nav class="all-cat-side-list" aria-label="Category quick links">
              <?php foreach ($activeCategories as $cat): ?>
                <a href="/category/<?= htmlspecialchars($cat['slug'] ?? '') ?>">
                  <?= htmlspecialchars($cat['name'] ?? 'Category') ?>
                </a>
              <?php endforeach; ?>
            </nav>
          </div>
          </aside>
        </details>

        <div class="all-cat-results">
          <div class="all-cat-toolbar">
            <p>Showing <?= (int)$visibleCount ?> categor<?= $visibleCount === 1 ? 'y' : 'ies' ?></p>
            <label>Sort by:
              <select aria-label="Sort categories">
                <option>Popularity</option>
                <option>Newest</option>
                <option>Name A-Z</option>
              </select>
            </label>
          </div>

          <div class="all-cat-grid" aria-label="Printing categories">
            <?php foreach ($activeCategories as $idx => $cat):
              $name = (string)($cat['name'] ?? 'Category');
              $slug = (string)($cat['slug'] ?? '');
              $image = trim((string)($cat['image_path'] ?? ''));
              $productCount = (int)($cat['product_count'] ?? 0);
              $theme = $categoryThemeClasses[$idx % count($categoryThemeClasses)];
            ?>
              <a class="all-cat-card all-cat-card-<?= htmlspecialchars($theme) ?>" href="/category/<?= htmlspecialchars($slug) ?>">
                <div class="all-cat-img">
                  <?php if ($image !== ''): ?>
                    <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy" onerror="this.src='https://placehold.co/600x600/EEF3FD/1A56E8?text=<?= urlencode($name) ?>'">
                  <?php else: ?>
                    <div class="shop-cat-fallback" aria-hidden="true"></div>
                  <?php endif; ?>
                </div>
                <div class="all-cat-body">
                  <h2><?= htmlspecialchars($name) ?></h2>
                  <p><?= $productCount ?> product<?= $productCount === 1 ? '' : 's' ?></p>
                  <span class="all-cat-cta">View Products <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="all-cat-usp" aria-label="RCS Print benefits">
        <div class="why-print-panel">
          <article class="why-print-item"><div class="why-print-icon why-print-green"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div><div class="why-print-copy"><h3>Premium Quality</h3><p>Best quality materials and printing.</p></div></article>
          <article class="why-print-item"><div class="why-print-icon why-print-orange"><i class="fa-regular fa-thumbs-up" aria-hidden="true"></i></div><div class="why-print-copy"><h3>100% Satisfaction</h3><p>Your happiness matters.</p></div></article>
          <article class="why-print-item"><div class="why-print-icon why-print-purple"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i></div><div class="why-print-copy"><h3>Free Design Support</h3><p>Professional design support at no extra cost.</p></div></article>
          <article class="why-print-item"><div class="why-print-icon why-print-purple"><i class="fa-solid fa-tags" aria-hidden="true"></i></div><div class="why-print-copy"><h3>Affordable Pricing</h3><p>Low price with the best value.</p></div></article>
          <article class="why-print-item"><div class="why-print-icon why-print-orange"><i class="fa-solid fa-cube" aria-hidden="true"></i></div><div class="why-print-copy"><h3>Bulk Order Specialist</h3><p>Special prices for bulk requirements.</p></div></article>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>

<!-- QUICK HELP STRIP -->
<?php include INCLUDE_PATH . '/partials/quick-help.php'; ?>

<!-- FOOTER -->
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>

<script>
(() => {
  const panel = document.querySelector('.all-cat-filter-panel');
  if (!panel) return;
  const mobileQuery = window.matchMedia('(max-width: 820px)');
  const syncFilterPanel = (event) => {
    if (mobileQuery.matches) {
      if (!event) panel.open = false;
    } else {
      panel.open = true;
    }
  };
  syncFilterPanel();
  if (typeof mobileQuery.addEventListener === 'function') {
    mobileQuery.addEventListener('change', syncFilterPanel);
  } else if (typeof mobileQuery.addListener === 'function') {
    mobileQuery.addListener(syncFilterPanel);
  }
})();
</script>

<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

<?php
/**
 * category.php — Category Page
 * Shows all products belonging to a single category
 * Route: /category/{slug}
 * Variables: $category (array), $products (array), $categories (array), $settingsMap (array)
 */
$pageTitle = htmlspecialchars($category['name']) . ' Printing — RCS Graphic';
$pageDesc  = 'Browse all ' . htmlspecialchars($category['name']) . ' products at RCS Graphic. Premium quality printing in Rajkot.';

$settingsMap = [];
try {
    $settings    = Database::rows("SELECT `key`, value FROM settings");
    $settingsMap = array_column($settings, 'value', 'key');
} catch (\Throwable) {}

$bizName  = htmlspecialchars($settingsMap['biz_name']    ?? 'RCS Graphic');
$bizPhone = htmlspecialchars($settingsMap['biz_phone']   ?? '+91 98765 43210');
$bizWa    = htmlspecialchars($settingsMap['biz_whatsapp']?? '919876543210');
$bizEmail = htmlspecialchars($settingsMap['biz_email']   ?? 'hello@rcsgraphic.in');
$bizAddr  = htmlspecialchars($settingsMap['biz_address'] ?? 'Rajkot, Gujarat');
$productCount = count($products ?? []);
$categoryName = (string)($category['name'] ?? 'Category');
$categorySlug = (string)($category['slug'] ?? '');
$categoryDescription = trim((string)($category['description'] ?? ''));
$categoryHeroKey = $categorySlug !== '' ? 'category_' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower($categorySlug)) : 'category_detail';
$filterOptions = is_array($filterOptions ?? null) ? $filterOptions : [];
$selectedFilters = is_array($selectedFilters ?? null) ? $selectedFilters : [];
$hasSelectedFilters = !empty($selectedFilters);
$categoryThemeClasses = ['purple', 'orange', 'orange', 'orange', 'purple', 'orange', 'purple', 'green'];

include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
?>

<main class="all-cat-page subcat-page">
  <?php
  $pageHero = [
    'key' => $categoryHeroKey,
    'title' => $categoryName,
    'subtitle' => $categoryDescription !== '' ? $categoryDescription : 'Premium quality printing products for every business need.',
    'eyebrow' => 'Product Category',
    'breadcrumbs' => [
      ['label' => 'Home', 'url' => '/'],
      ['label' => 'All Categories', 'url' => '/categories'],
      ['label' => $categoryName, 'url' => null],
    ],
    'fallback_image' => '/assets/img/categories/all-categories-hero.svg',
  ];
  include INCLUDE_PATH . '/partials/page-hero.php';
  ?>

  <div class="container all-cat-content">
    <?php if (empty($products) && !$hasSelectedFilters): ?>
      <div class="cat-detail-empty">
        <div class="cat-detail-empty-icon">🖨️</div>
        <div class="cat-detail-empty-title">No products yet in <?= htmlspecialchars($categoryName) ?></div>
        <p>We're adding products soon. Contact us for custom requirements.</p>
        <div class="cat-detail-empty-actions">
          <a href="/categories" class="btn btn-outline">← Back to Categories</a>
          <a href="https://wa.me/<?= htmlspecialchars($bizWa) ?>" target="_blank" rel="noopener" class="btn btn-green">💬 WhatsApp Us</a>
        </div>
      </div>
    <?php else: ?>
      <section class="all-cat-shop" aria-label="Browse <?= htmlspecialchars($categoryName) ?> products">
        <details class="all-cat-filter-panel" open>
          <summary><span>Filters</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
          <aside class="all-cat-sidebar" aria-label="Product filters">
            <form class="all-cat-side-box all-cat-filter-box" method="get" action="/category/<?= htmlspecialchars($categorySlug) ?>">
              <h2>Filter By</h2>
              <?php foreach ($filterOptions as $group): ?>
                <?php if (empty($group['options'])) continue; ?>
                <div class="all-cat-filter-group">
                  <h3><?= htmlspecialchars($group['label'] ?? 'Filter') ?></h3>
                  <?php foreach ($group['options'] as $option):
                    $groupSlug = (string)($group['slug'] ?? '');
                    $optionSlug = (string)($option['slug'] ?? '');
                    $checked = in_array($optionSlug, $selectedFilters[$groupSlug] ?? [], true);
                  ?>
                    <label>
                      <input type="checkbox" name="filters[<?= htmlspecialchars($groupSlug) ?>][]" value="<?= htmlspecialchars($optionSlug) ?>" <?= $checked ? 'checked' : '' ?>>
                      <?= htmlspecialchars($option['label'] ?? $optionSlug) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
              <button type="submit" class="all-cat-apply-btn">Apply Filters <i class="fa-solid fa-sliders" aria-hidden="true"></i></button>
              <?php if (!empty($selectedFilters)): ?>
                <a href="/category/<?= htmlspecialchars($categorySlug) ?>" class="all-cat-clear-btn">Clear Filters</a>
              <?php endif; ?>
            </form>
          </aside>
        </details>

        <div class="all-cat-results">
          <div class="all-cat-toolbar">
            <p><?= $productCount > 0 ? 'Showing 1–' . (int)$productCount . ' of ' . (int)$productCount . ' products' : 'Showing 0 products' ?></p>
            <label>Sort by:
              <select aria-label="Sort <?= htmlspecialchars($categoryName) ?> products">
                <option>Popularity</option>
                <option>Newest</option>
                <option>Price Low to High</option>
              </select>
            </label>
          </div>

          <div class="all-cat-grid" id="catProductsGrid">
            <?php foreach ($products as $idx => $p):
              $img = trim((string)($p['primary_image'] ?? ''));
              $name = (string)($p['name'] ?? 'Product');
              $slug = (string)($p['slug'] ?? '');
              $minP = (float)($p['min_price'] ?? 0);
              $theme = $categoryThemeClasses[$idx % count($categoryThemeClasses)];
            ?>
              <a class="all-cat-card all-cat-card-<?= htmlspecialchars($theme) ?>" href="/product/<?= htmlspecialchars($slug) ?>">
                <div class="all-cat-img">
                  <?php if ($img !== ''): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy" onerror="this.src='https://placehold.co/400x300/EEF3FD/1A56E8?text=<?= urlencode($name) ?>'">
                  <?php else: ?>
                    <div class="shop-cat-fallback" aria-hidden="true">📦</div>
                  <?php endif; ?>
                </div>
                <div class="all-cat-body">
                  <h2><?= htmlspecialchars($name) ?></h2>
                  <strong><?= $minP > 0 ? ('Starting from ₹' . number_format($minP)) : 'Price on request' ?></strong>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
          <?php if (empty($products)): ?>
            <div style="padding:42px 20px;border:1px dashed var(--border);border-radius:16px;background:#fff;text-align:center;color:var(--text2)">
              <strong style="display:block;color:var(--ink);font-size:18px;margin-bottom:6px">No matching products found</strong>
              <span>Try removing one or more filters.</span>
            </div>
          <?php endif; ?>

          <?php if ($productCount > 0): ?>
            <nav class="all-cat-pagination" aria-label="<?= htmlspecialchars($categoryName) ?> pagination">
              <span class="is-muted">←</span><strong>1</strong><span>2</span><span>3</span><span>4</span><span>→</span>
            </nav>
          <?php endif; ?>
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

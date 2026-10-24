<?php
$businessNeeds = is_array($businessNeeds ?? null) ? $businessNeeds : [];
$settingsMap = is_array($settingsMap ?? null) ? $settingsMap : [];
$pageTitle = 'All Sectors — RCS Graphic';
$pageDesc = 'Browse printing solutions by business sector and find products matched to your industry needs.';
$activeBusinessNeeds = array_values(array_filter($businessNeeds, static fn($need) => (int)($need['is_active'] ?? 1) === 1));
$visibleCount = count($activeBusinessNeeds);
$sectorThemeClasses = ['purple', 'orange', 'orange', 'orange', 'purple', 'orange', 'purple', 'green'];
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
?>
<main class="all-cat-page business-page all-business-page">
  <?php
  $pageHero = [
    'key' => 'business_sectors',
    'title' => 'All Sectors',
    'subtitle' => 'Choose your industry and discover print products curated for your daily business needs.',
    'eyebrow' => 'Business Printing Solutions',
    'breadcrumbs' => [
      ['label' => 'Home', 'url' => '/'],
      ['label' => 'All Sectors', 'url' => null],
    ],
    'fallback_image' => '/assets/img/categories/print-category.svg',
  ];
  include INCLUDE_PATH . '/partials/page-hero.php';
  ?>

  <div class="container all-cat-content">
    <?php if (!$activeBusinessNeeds): ?>
      <div style="text-align:center;padding:80px 20px;background:#fff;border:1px solid var(--border);border-radius:18px">
        <div style="font-size:48px;margin-bottom:12px">🏢</div>
        <div style="font-size:17px;font-weight:700;color:var(--ink);margin-bottom:6px">No sectors yet</div>
        <p style="font-size:13px;color:var(--text2);margin-bottom:20px">Please check back soon for curated print collections.</p>
      </div>
    <?php else: ?>
      <section class="all-cat-shop business-sector-shop" aria-label="Browse all business sectors">
        <details class="all-cat-filter-panel" open>
          <summary><span>Filters</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
          <aside class="all-cat-sidebar" aria-label="Business sector filters">
            <div class="all-cat-side-box all-cat-side-categories">
              <h2>Sectors</h2>
              <nav class="all-cat-side-list" aria-label="Business sector quick links">
                <?php foreach ($activeBusinessNeeds as $need):
                  $name = trim((string)($need['name'] ?? 'Business Sector'));
                  $slug = trim((string)($need['slug'] ?? ''));
                  if ($slug === '') continue;
                ?>
                  <a href="/business/<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
              </nav>
            </div>
          </aside>
        </details>

        <div class="all-cat-results">
          <div class="all-cat-toolbar">
            <p>Showing <?= (int)$visibleCount ?> sector<?= $visibleCount === 1 ? '' : 's' ?></p>
            <label>Sort by:
              <select aria-label="Sort sectors">
                <option>Popularity</option>
                <option>Newest</option>
                <option>Name A-Z</option>
              </select>
            </label>
          </div>

          <div class="all-cat-grid business-sector-grid" aria-label="Business sectors">
            <?php foreach ($activeBusinessNeeds as $idx => $need):
              $name = trim((string)($need['name'] ?? 'Business Sector'));
              $slug = trim((string)($need['slug'] ?? ''));
              $img = trim((string)($need['image_path'] ?? '')) ?: '/assets/img/categories/print-category.svg';
              $productCount = (int)($need['product_count'] ?? 0);
              $theme = $sectorThemeClasses[$idx % count($sectorThemeClasses)];
            ?>
              <a class="all-cat-card business-sector-card all-cat-card-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>" href="/business/<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                <div class="all-cat-img">
                  <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" onerror="this.src='/assets/img/categories/print-category.svg'">
                </div>
                <div class="all-cat-body">
                  <div><h2><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h2><p><?= $productCount ?> product<?= $productCount === 1 ? '' : 's' ?></p></div>
                  <span class="all-cat-btn">View Products</span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

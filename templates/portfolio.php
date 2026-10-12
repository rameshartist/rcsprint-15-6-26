<?php
$pageTitle = 'Portfolio — RCS Print';
$pageDesc = 'Explore RCS Print portfolio work across business cards, flyers, brochures, posters, stationery, packaging and custom print projects.';
$pageSchema = [[
  '@context' => 'https://schema.org',
  '@type' => 'CollectionPage',
  'name' => 'RCS Print Portfolio',
  'description' => $pageDesc,
  'url' => (defined('APP_URL') ? rtrim((string)APP_URL, '/') : '') . '/portfolio',
]];
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';

$settingsMap = is_array($settingsMap ?? null) ? $settingsMap : [];
$bizPhoneRaw = trim((string)($settingsMap['biz_phone'] ?? '+91 98765 43210'));
$bizPhone = htmlspecialchars($bizPhoneRaw, ENT_QUOTES, 'UTF-8');
$bizPhoneHref = htmlspecialchars(preg_replace('/\D+/', '', $bizPhoneRaw), ENT_QUOTES, 'UTF-8');
$bizWa = htmlspecialchars(preg_replace('/\D+/', '', (string)($settingsMap['biz_whatsapp'] ?? $bizPhoneRaw)), ENT_QUOTES, 'UTF-8');
$waText = rawurlencode('Hello RCS Print, I want to discuss a portfolio-style print project.');

$fallbackPortfolioCategories = [
  ['label' => 'Visiting Card', 'name' => 'Visiting Card', 'slug' => 'visiting-card', 'icon' => 'fa-id-card-clip'],
  ['label' => 'Brochure', 'name' => 'Brochure', 'slug' => 'brochure', 'icon' => 'fa-images'],
  ['label' => 'Flyer', 'name' => 'Flyer', 'slug' => 'flyer', 'icon' => 'fa-file-image'],
  ['label' => 'Calender', 'name' => 'Calender', 'slug' => 'calender', 'icon' => 'fa-calendar-days'],
  ['label' => 'Rough Pad', 'name' => 'Rough Pad', 'slug' => 'rough-pad', 'icon' => 'fa-note-sticky'],
  ['label' => 'Flex Banner', 'name' => 'Flex Banner', 'slug' => 'flex-banner', 'icon' => 'fa-panorama'],
  ['label' => 'Poster', 'name' => 'Poster', 'slug' => 'poster', 'icon' => 'fa-newspaper'],
  ['label' => 'Stationery', 'name' => 'Stationery', 'slug' => 'stationery', 'icon' => 'fa-file-lines'],
  ['label' => 'Packaging', 'name' => 'Packaging', 'slug' => 'packaging', 'icon' => 'fa-cube'],
];
$fallbackPortfolioItems = [
  ['title' => 'Creative Visiting Card Design', 'category' => 'Visiting Card', 'category_slug' => 'visiting-card', 'image' => '/assets/images/sample-products/business-cards/business-cards-1.svg'],
  ['title' => 'Company Brochure Design', 'category' => 'Brochure', 'category_slug' => 'brochure', 'image' => '/assets/images/sample-products/brochures/brochures-1.svg'],
  ['title' => 'Corporate Flyer Design', 'category' => 'Flyer', 'category_slug' => 'flyer', 'image' => '/assets/images/sample-products/flyers/flyers-1.svg'],
  ['title' => 'Custom Calendar Design', 'category' => 'Calender', 'category_slug' => 'calender', 'image' => '/assets/images/sample-products/stationery/stationery-2.svg'],
  ['title' => 'Premium Rough Pad Design', 'category' => 'Rough Pad', 'category_slug' => 'rough-pad', 'image' => '/assets/images/sample-products/stationery/stationery-1.svg'],
  ['title' => 'Roll-Up Flex Banner Design', 'category' => 'Flex Banner', 'category_slug' => 'flex-banner', 'image' => '/assets/images/sample-products/banners/banners-1.svg'],
  ['title' => 'Event Poster Design', 'category' => 'Poster', 'category_slug' => 'poster', 'image' => '/assets/images/sample-products/posters/posters-1.svg'],
  ['title' => 'Product Packaging Design', 'category' => 'Packaging', 'category_slug' => 'packaging', 'image' => '/assets/images/sample-products/business-cards/business-cards-3.svg'],
];
$portfolioCategories = is_array($portfolioCategories ?? null) ? $portfolioCategories : $fallbackPortfolioCategories;
$portfolioItems = is_array($portfolioItems ?? null) ? $portfolioItems : $fallbackPortfolioItems;
$portfolioCategory = trim((string)($portfolioCategory ?? ''));
$portfolioPage = max(1, (int)($portfolioPage ?? 1));
$portfolioTotalPages = max(1, (int)($portfolioTotalPages ?? 1));
$portfolioPrimaryCategories = [
  ['label' => 'Visiting Card', 'name' => 'Visiting Card', 'slug' => 'visiting-card', 'icon' => 'fa-id-card-clip', 'aliases' => ['visiting-card','visiting-cards','business-card','business-cards','cards']],
  ['label' => 'Brochure', 'name' => 'Brochure', 'slug' => 'brochure', 'icon' => 'fa-images', 'aliases' => ['brochure','brochures']],
  ['label' => 'Flyer', 'name' => 'Flyer', 'slug' => 'flyer', 'icon' => 'fa-file-image', 'aliases' => ['flyer','flyers']],
  ['label' => 'Calender', 'name' => 'Calender', 'slug' => 'calender', 'icon' => 'fa-calendar-days', 'aliases' => ['calender','calendar','calenders','calendars']],
  ['label' => 'Rough Pad', 'name' => 'Rough Pad', 'slug' => 'rough-pad', 'icon' => 'fa-note-sticky', 'aliases' => ['rough-pad','rough-pads','notepad','notepads']],
  ['label' => 'Flex Banner', 'name' => 'Flex Banner', 'slug' => 'flex-banner', 'icon' => 'fa-panorama', 'aliases' => ['flex-banner','flex-banners','banner','banners']],
];
$portfolioNormalizeKey = static function (string $value): string {
  $value = strtolower(trim($value));
  $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
  return trim($value, '-');
};
$portfolioCategoryMap = [];
foreach ($portfolioCategories as $cat) {
  $slug = $portfolioNormalizeKey((string)($cat['slug'] ?? ''));
  if ($slug === '' || isset($portfolioCategoryMap[$slug])) {
    continue;
  }
  $portfolioCategoryMap[$slug] = $cat;
}
$portfolioPrimaryLookup = [];
foreach ($portfolioPrimaryCategories as $index => $primaryCat) {
  $aliases = array_map($portfolioNormalizeKey, array_merge([(string)$primaryCat['slug'], (string)$primaryCat['name'], (string)$primaryCat['label']], $primaryCat['aliases'] ?? []));
  foreach ($portfolioCategoryMap as $slug => $cat) {
    $catKeys = [
      $slug,
      $portfolioNormalizeKey((string)($cat['name'] ?? '')),
      $portfolioNormalizeKey((string)($cat['label'] ?? '')),
    ];
    if (array_intersect($aliases, $catKeys)) {
      $portfolioPrimaryCategories[$index] = array_merge($primaryCat, $cat, ['icon' => $primaryCat['icon'], 'aliases' => $primaryCat['aliases']]);
      $portfolioPrimaryCategories[$index]['slug'] = (string)($cat['slug'] ?? $primaryCat['slug']);
      break;
    }
  }
  foreach ($aliases as $alias) {
    if ($alias !== '') $portfolioPrimaryLookup[$alias] = true;
  }
  $portfolioPrimaryLookup[$portfolioNormalizeKey((string)$portfolioPrimaryCategories[$index]['slug'])] = true;
  $portfolioPrimaryLookup[$portfolioNormalizeKey((string)($portfolioPrimaryCategories[$index]['name'] ?? ''))] = true;
  $portfolioPrimaryLookup[$portfolioNormalizeKey((string)($portfolioPrimaryCategories[$index]['label'] ?? ''))] = true;
}
$portfolioOtherCategories = [];
foreach (array_values($portfolioCategoryMap) as $cat) {
  $slugKey = $portfolioNormalizeKey((string)($cat['slug'] ?? ''));
  $nameKey = $portfolioNormalizeKey((string)($cat['name'] ?? $cat['label'] ?? ''));
  if ($slugKey === '' || isset($portfolioPrimaryLookup[$slugKey]) || isset($portfolioPrimaryLookup[$nameKey])) {
    continue;
  }
  $portfolioOtherCategories[] = $cat;
}
$pageHero = [
  'key' => 'portfolio',
  'title' => 'Our Portfolio',
  'subtitle' => 'Explore real printing work created for businesses and brands.',
  'eyebrow' => 'RCS PRINT',
  'fallback_image' => '/assets/images/sample-products/brochures/brochures-2.svg',
  'breadcrumbs' => [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Portfolio', 'url' => null],
  ],
];
?>

<main class="portfolio-page">
  <?php include INCLUDE_PATH . '/partials/page-hero.php'; ?>

  <section class="portfolio-work" aria-labelledby="portfolioWorkTitle">
    <div class="portfolio-container">
      <div class="portfolio-section-head">
        <h2 id="portfolioWorkTitle">Our Work Speaks for <span>Itself</span></h2>
        <div class="portfolio-dots" aria-hidden="true"><span></span><span></span><span></span></div>
      </div>

      <div class="portfolio-filter-row" aria-label="Portfolio categories">
        <a href="/portfolio" class="portfolio-filter <?= $portfolioCategory === '' ? 'active' : '' ?>" data-portfolio-filter="">
          <i class="fa-solid fa-border-all" aria-hidden="true"></i>
          <span>All Works</span>
        </a>
        <?php foreach ($portfolioPrimaryCategories as $cat): ?>
          <?php
            $catLabel = (string)($cat['label'] ?? $cat['name'] ?? 'Category');
            $catSlug = (string)($cat['slug'] ?? '');
            $catIcon = (string)($cat['icon'] ?? 'fa-folder-open');
          ?>
          <a href="/portfolio?category=<?= rawurlencode($catSlug) ?>" class="portfolio-filter <?= $portfolioCategory === $catSlug ? 'active' : '' ?>" data-portfolio-filter="<?= htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid <?= htmlspecialchars($catIcon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        <?php endforeach; ?>
        <?php if ($portfolioOtherCategories): ?>
          <?php $otherActive = in_array($portfolioCategory, array_map(static fn($cat) => (string)($cat['slug'] ?? ''), $portfolioOtherCategories), true); ?>
          <div class="portfolio-filter-more <?= $otherActive ? 'active' : '' ?>">
            <button type="button" class="portfolio-filter" aria-haspopup="true" aria-expanded="false">
              <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
              <span>Others</span>
            </button>
            <div class="portfolio-filter-menu" role="menu">
              <?php foreach ($portfolioOtherCategories as $cat): ?>
                <?php $catLabel = (string)($cat['label'] ?? $cat['name'] ?? 'Category'); $catSlug = (string)($cat['slug'] ?? ''); ?>
                <a role="menuitem" href="/portfolio?category=<?= rawurlencode($catSlug) ?>" class="<?= $portfolioCategory === $catSlug ? 'active' : '' ?>" data-portfolio-filter="<?= htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($portfolioItems): ?>
      <div class="portfolio-grid" data-portfolio-results>
        <?php foreach ($portfolioItems as $item): ?>
          <?php
            $itemImage = (string)($item['image'] ?? $item['main_image'] ?? '/assets/images/sample-products/business-cards/business-cards-1.svg');
            $itemTitle = (string)($item['title'] ?? 'Portfolio Work');
            $itemAlt = (string)($item['image_alt'] ?? $itemTitle);
            $itemCategory = (string)($item['category'] ?? $item['category_name'] ?? 'Portfolio');
            $itemCategorySlug = (string)($item['category_slug'] ?? 'portfolio');
          ?>
          <article class="portfolio-card portfolio-gallery-card" data-portfolio-card="<?= htmlspecialchars($itemCategorySlug, ENT_QUOTES, 'UTF-8') ?>">
            <button class="portfolio-card-img portfolio-lightbox-trigger" type="button" data-full="<?= htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8') ?>" data-category="<?= htmlspecialchars($itemCategory, ENT_QUOTES, 'UTF-8') ?>" data-category-slug="<?= htmlspecialchars($itemCategorySlug, ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($itemAlt, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
              <span><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i> View Large</span>
            </button>
            <div class="portfolio-card-body">
              <h3><?= htmlspecialchars($itemCategory, ENT_QUOTES, 'UTF-8') ?></h3>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="portfolio-empty-state" data-portfolio-empty>No portfolio work found for this category yet. Please check all works or add new portfolio items from admin.</div>
      <?php endif; ?>

      <?php if ($portfolioPage < $portfolioTotalPages): ?>
        <div class="portfolio-load-wrap"><a class="portfolio-load-btn" href="/portfolio?<?= http_build_query(array_filter(['category' => $portfolioCategory ?: null, 'page' => $portfolioPage + 1])) ?>">Load More Works <i class="fa-solid fa-rotate-right" aria-hidden="true"></i></a></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="portfolio-cta" aria-label="Portfolio project call to action">
    <div class="portfolio-container">
      <div class="portfolio-project-card">
        <div><h2>Need Bulk Printing for Your Business?</h2><p>Get visiting cards, brochures, flyers, banners and business stationery printed with consistent quality and reliable support.</p></div>
        <div class="portfolio-project-actions"><a href="/categories">View Products</a><a href="/contact" class="outline">Get Bulk Quote</a></div>
        <span class="portfolio-gift" aria-hidden="true"><i class="fa-solid fa-gift"></i></span>
      </div>
    </div>
  </section>

  <section class="portfolio-usp" aria-label="RCS Print benefits">
    <div class="portfolio-container portfolio-usp-bar">
      <article><i class="fa-solid fa-lightbulb" aria-hidden="true"></i><div><h3>Creative Print Solutions</h3><p>Smart ideas for standout print results.</p></div></article>
      <article><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i><div><h3>Free Design Support</h3><p>Professional design support at no extra cost.</p></div></article>
      <article><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><div><h3>Premium Quality</h3><p>Best quality materials and printing.</p></div></article>
      <article><i class="fa-solid fa-tags" aria-hidden="true"></i><div><h3>Affordable Pricing</h3><p>Low price with the best value.</p></div></article>
      <article><i class="fa-solid fa-cube" aria-hidden="true"></i><div><h3>Bulk Order Specialist</h3><p>Special prices for bulk requirements.</p></div></article>
    </div>
  </section>

  <?php $quickHelpExtraClass='portfolio-quick-help'; include INCLUDE_PATH . '/partials/quick-help.php'; ?>

  <div class="portfolio-lightbox" id="portfolioLightbox" aria-hidden="true">
    <button class="portfolio-lightbox-close" type="button" aria-label="Close image gallery"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    <button class="portfolio-lightbox-nav portfolio-lightbox-prev" type="button" aria-label="Show previous portfolio image"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
    <img id="portfolioLightboxImage" src="" alt="" role="dialog" aria-modal="true" aria-label="Portfolio image preview">
    <button class="portfolio-lightbox-nav portfolio-lightbox-next" type="button" aria-label="Show next portfolio image"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
  </div>
  <script>
  (function(){
    const bindPortfolioLightbox = () => {
      const box = document.getElementById('portfolioLightbox');
      const img = document.getElementById('portfolioLightboxImage');
      const triggers = Array.from(document.querySelectorAll('.portfolio-lightbox-trigger'));
      let gallery = [];
      let currentIndex = 0;
      const close = () => { box?.classList.remove('open'); box?.setAttribute('aria-hidden', 'true'); document.body.classList.remove('portfolio-lightbox-open'); };
      const show = (index) => {
        if (!box || !img || !gallery.length) return;
        currentIndex = (index + gallery.length) % gallery.length;
        const active = gallery[currentIndex];
        img.src = active.dataset.full || '';
        img.alt = active.dataset.title || 'Portfolio image';
        box.classList.add('open');
        box.setAttribute('aria-hidden', 'false');
        document.body.classList.add('portfolio-lightbox-open');
      };
      const move = (step) => show(currentIndex + step);
      triggers.forEach(btn => {
        if (btn.dataset.lightboxBound === '1') return;
        btn.dataset.lightboxBound = '1';
        btn.addEventListener('click', () => {
          const categorySlug = btn.dataset.categorySlug || '';
          gallery = Array.from(document.querySelectorAll('.portfolio-lightbox-trigger')).filter(item => (item.dataset.categorySlug || '') === categorySlug);
          if (!gallery.length) gallery = Array.from(document.querySelectorAll('.portfolio-lightbox-trigger'));
          show(gallery.indexOf(btn));
        });
      });
      if (box && box.dataset.lightboxShellBound !== '1') {
        box.dataset.lightboxShellBound = '1';
        box.addEventListener('click', e => { if (e.target === box) close(); });
        box.querySelector('.portfolio-lightbox-close')?.addEventListener('click', close);
        box.querySelector('.portfolio-lightbox-prev')?.addEventListener('click', () => move(-1));
        box.querySelector('.portfolio-lightbox-next')?.addEventListener('click', () => move(1));
        document.addEventListener('keydown', e => {
          if (!box.classList.contains('open')) return;
          if (e.key === 'Escape') close();
          if (e.key === 'ArrowLeft') move(-1);
          if (e.key === 'ArrowRight') move(1);
        });
      }
    };

    const syncActiveFilters = (category) => {
      document.querySelectorAll('[data-portfolio-filter]').forEach(link => {
        const active = (link.dataset.portfolioFilter || '') === category;
        link.classList.toggle('active', active);
      });
      const more = document.querySelector('.portfolio-filter-more');
      if (more) {
        more.classList.toggle('active', !!more.querySelector('.portfolio-filter-menu a.active'));
      }
    };

    const replacePortfolioResults = (doc) => {
      const nextGrid = doc.querySelector('[data-portfolio-results]');
      const currentGrid = document.querySelector('[data-portfolio-results]');
      const nextEmpty = doc.querySelector('[data-portfolio-empty]');
      const currentEmpty = document.querySelector('[data-portfolio-empty]');
      const nextLoad = doc.querySelector('.portfolio-load-wrap');
      const currentLoad = document.querySelector('.portfolio-load-wrap');

      if (nextGrid && currentGrid) {
        currentGrid.replaceWith(nextGrid);
      } else if (nextGrid && currentEmpty) {
        currentEmpty.replaceWith(nextGrid);
      } else if (nextEmpty && currentGrid) {
        currentGrid.replaceWith(nextEmpty);
      } else if (nextEmpty && currentEmpty) {
        currentEmpty.replaceWith(nextEmpty);
      }

      if (currentLoad && nextLoad) currentLoad.replaceWith(nextLoad);
      else if (currentLoad && !nextLoad) currentLoad.remove();
      else if (!currentLoad && nextLoad) (document.querySelector('[data-portfolio-results], [data-portfolio-empty]')?.after(nextLoad));

      bindPortfolioLightbox();
    };

    const loadPortfolioCategory = async (url, category, push = true) => {
      try {
        const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Portfolio request failed');
        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        replacePortfolioResults(doc);
        syncActiveFilters(category);
        if (push) history.pushState({ portfolioCategory: category }, '', url);
      } catch (error) {
        window.location.href = url;
      }
    };

    document.querySelectorAll('[data-portfolio-filter]').forEach(link => {
      link.addEventListener('click', event => {
        event.preventDefault();
        const url = link.getAttribute('href') || '/portfolio';
        const category = link.dataset.portfolioFilter || '';
        loadPortfolioCategory(url, category, true);
      });
    });

    window.addEventListener('popstate', () => {
      const params = new URLSearchParams(window.location.search);
      const category = params.get('category') || '';
      loadPortfolioCategory(window.location.pathname + window.location.search, category, false);
    });

    bindPortfolioLightbox();
  })();
  </script>
</main>

<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

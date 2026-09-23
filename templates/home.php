<?php
/**
 * home.php — Home page
 * Includes: head.php (<!DOCTYPE + <head>), then header.php (full header + drawer + cart)
 * Note: Do NOT re-include cart-drawer.php here — header.php already does it.
 */
$pageTitle = ($settingsMap['biz_name'] ?? 'RCS Graphic') . ' — Premium Print Ordering';
$pageDesc  = 'Professional printing services in Rajkot — business cards, brochures, banners and more. Fast delivery, GST invoice, secure payment.';
$pageImage = '/assets/images/rcs-graphic-logo.png';
if (!empty($homeBanners ?? [])) {
  foreach ($homeBanners as $bannerSeo) {
    $bannerSeoImage = trim((string)($bannerSeo['image_path'] ?? ''));
    if ($bannerSeoImage !== '') {
      $pageImage = $bannerSeoImage;
      $pagePreloadImage = $bannerSeoImage;
      break;
    }
  }
}
include INCLUDE_PATH . '/partials/head.php';    // outputs <!DOCTYPE><html><head>...</head><body>
include INCLUDE_PATH . '/partials/header.php';  // outputs header + cart drawer + global JS

$bizName  = htmlspecialchars($settingsMap['biz_name']    ?? 'RCS Graphic');
$bizPhone = htmlspecialchars($settingsMap['biz_phone']   ?? '+91 98765 43210');
$bizWa    = htmlspecialchars($settingsMap['biz_whatsapp']?? '919876543210');
$bizEmail = htmlspecialchars($settingsMap['biz_email']   ?? 'hello@rcsgraphic.in');
$bizAddr  = htmlspecialchars($settingsMap['biz_address'] ?? 'Rajkot, Gujarat');
?>

<!-- ═══════════════════════════════════════════════════════════
     BANNER SLIDER
     ─────────────────────────────────────────────────────────
     Admin-managed, image-first banner slider. Optional text/CTA fields
     render only when filled, so a designed clickable banner image can
     stand on its own across desktop and mobile.
═══════════════════════════════════════════════════════════════ -->
<div class="banner-slider" id="bannerSlider" data-design-target="home.banner" data-slide-interval="3000">
  <?php
  $fallbackBanners = [
    [
      'eyebrow' => 'Premium Print Studio',
      'title' => 'Print That Grows<br>Your Business',
      'subtitle' => 'Business cards, flyers, brochures, posters and more with fast Rajkot delivery.',
      'image_path' => 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=1400&q=85&fit=crop',
      'image_alt' => 'Premium Business Card Printing',
      'cta_primary_text' => 'Order Now',
      'cta_primary_url' => '/categories',
      'cta_secondary_text' => 'Get Free Design',
      'cta_secondary_type' => 'url',
      'cta_secondary_url' => '/#quick-help-sec',
    ],
  ];
  $bannerSlides = array_values(array_filter(!empty($homeBanners ?? []) ? $homeBanners : $fallbackBanners, static function ($slide) {
    return trim((string)($slide['image_path'] ?? '')) !== '';
  }));
  $formatBannerHtml = static function ($value): string {
    $safe = htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
    return preg_replace('/&lt;br\s*\/?&gt;/i', '<br>', $safe) ?? $safe;
  };
  foreach ($bannerSlides as $i => $slide):
    $rawImg = trim((string)($slide['image_path'] ?? ''));
    $img = htmlspecialchars($rawImg, ENT_QUOTES, 'UTF-8');
    $altText = trim((string)($slide['image_alt'] ?? '')) ?: ('RCS Graphic banner ' . ($i + 1));
    $alt = htmlspecialchars($altText, ENT_QUOTES, 'UTF-8');
    $eyebrow = trim((string)($slide['eyebrow'] ?? ''));
    $title = $formatBannerHtml($slide['title'] ?? '');
    $subtitle = $formatBannerHtml($slide['subtitle'] ?? '');
    $ctaPrimaryTextRaw = trim((string)($slide['cta_primary_text'] ?? ''));
    $ctaPrimaryUrlRaw = trim((string)($slide['cta_primary_url'] ?? ''));
    $ctaPrimaryText = htmlspecialchars($ctaPrimaryTextRaw, ENT_QUOTES, 'UTF-8');
    $ctaPrimaryUrl = htmlspecialchars($ctaPrimaryUrlRaw, ENT_QUOTES, 'UTF-8');
    $ctaSecondaryTextRaw = trim((string)($slide['cta_secondary_text'] ?? ''));
    $ctaSecondaryText = htmlspecialchars($ctaSecondaryTextRaw, ENT_QUOTES, 'UTF-8');
    $ctaSecondaryType = strtolower(trim((string)($slide['cta_secondary_type'] ?? 'whatsapp')));
    $ctaSecondaryUrlRaw = trim((string)($slide['cta_secondary_url'] ?? ''));
    $ctaSecondaryUrl = htmlspecialchars($ctaSecondaryUrlRaw, ENT_QUOTES, 'UTF-8');
    $hasPrimaryCta = $ctaPrimaryTextRaw !== '' && $ctaPrimaryUrlRaw !== '' && $ctaPrimaryUrlRaw !== '#';
    $hasSecondaryCta = $ctaSecondaryTextRaw !== '' && ($ctaSecondaryType !== 'url' || ($ctaSecondaryUrlRaw !== '' && $ctaSecondaryUrlRaw !== '#'));
    $hasContent = $eyebrow !== '' || $title !== '' || $subtitle !== '' || $hasPrimaryCta || $hasSecondaryCta;
    $hasClickSetting = array_key_exists('image_click_enabled', $slide);
    $imageClickEnabled = $hasClickSetting ? ((int)($slide['image_click_enabled'] ?? 0) === 1) : $hasPrimaryCta;
    $imageClickUrlRaw = trim((string)($slide['image_click_url'] ?? ''));
    $slideClickUrlRaw = $imageClickEnabled ? ($imageClickUrlRaw !== '' ? $imageClickUrlRaw : ($hasClickSetting ? '' : $ctaPrimaryUrlRaw)) : '';
    $slideClickUrl = $slideClickUrlRaw !== '' && $slideClickUrlRaw !== '#' ? htmlspecialchars($slideClickUrlRaw, ENT_QUOTES, 'UTF-8') : '';
  ?>
  <div class="bs-slide <?= $hasContent ? 'has-content' : 'image-only' ?>">
    <?php if ($slideClickUrl !== ''): ?>
      <a class="bs-image-link" href="<?= $slideClickUrl ?>" aria-label="<?= $alt ?>">
        <img src="<?= $img ?>" alt="<?= $alt ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async" fetchpriority="<?= $i === 0 ? 'high' : 'auto' ?>">
      </a>
    <?php else: ?>
      <img src="<?= $img ?>" alt="<?= $alt ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async" fetchpriority="<?= $i === 0 ? 'high' : 'auto' ?>">
    <?php endif; ?>
    <?php if ($hasContent): ?>
      <div class="bs-overlay" aria-hidden="true"></div>
      <div class="bs-content">
        <?php if ($eyebrow !== ''): ?><div class="bs-eyebrow"><?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($title !== ''): ?><div class="bs-title" data-design-target="home.banner.title"><?= $title ?></div><?php endif; ?>
        <?php if ($subtitle !== ''): ?><div class="bs-sub" data-design-target="home.banner.subtitle"><?= $subtitle ?></div><?php endif; ?>
        <?php if ($hasPrimaryCta || $hasSecondaryCta): ?>
          <div class="bs-actions">
            <?php if ($hasPrimaryCta): ?><a href="<?= $ctaPrimaryUrl ?>" class="bs-cta-primary" data-design-target="home.banner.buttons"><?= $ctaPrimaryText ?></a><?php endif; ?>
            <?php if ($hasSecondaryCta): ?>
              <?php if ($ctaSecondaryType === 'url'): ?>
                <a href="<?= $ctaSecondaryUrl ?>" class="bs-cta-wa" data-design-target="home.banner.buttons"><?= $ctaSecondaryText ?></a>
              <?php else: ?>
                <button class="bs-cta-wa" data-design-target="home.banner.buttons" onclick="window.open('https://wa.me/<?= $bizWa ?>','_blank')" type="button"><?= $ctaSecondaryText ?></button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if (count($bannerSlides) > 1): ?>
    <!-- Prev / Next arrows -->
    <button class="bs-prev" onclick="document.getElementById('bannerSlider')._sliderPrev()" aria-label="Previous slide">
      <svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
    </button>
    <button class="bs-next" onclick="document.getElementById('bannerSlider')._sliderNext()" aria-label="Next slide">
      <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
    </button>

    <!-- Dot indicators -->
    <div class="bs-dots">
      <?php foreach ($bannerSlides as $i => $_): ?>
        <button class="bs-dot" onclick="document.getElementById('bannerSlider')._sliderGoTo(<?= (int)$i ?>)" aria-label="Slide <?= (int)$i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>


<?php
$catSpot = [];
foreach ($categories as $cat) {
  $cid = (int)($cat['id'] ?? 0);
  if ($cid <= 0 || (int)($cat['is_active'] ?? 1) !== 1) continue;
  $catProducts = array_values(array_filter($products, fn($p) => (int)($p['category_id'] ?? 0) === $cid));
  usort($catProducts, fn($a, $b) => ((float)($a['min_price'] ?? 0) <=> (float)($b['min_price'] ?? 0)));
  $first = $catProducts[0] ?? [];
  $catImage = trim((string)($cat['image_path'] ?? ''));
  if ($catImage === '') {
    $catImage = trim((string)($first['primary_image'] ?? ''));
  }
  $catSpot[] = [
    'name' => $cat['name'] ?? 'Category',
    'slug' => $cat['slug'] ?? '',
    'icon' => $cat['icon'] ?? '📦',
    'image' => $catImage,
    'image_alt' => trim((string)($cat['image_alt'] ?? '')) ?: ($cat['name'] ?? 'Category'),
    'start' => (float)($first['min_price'] ?? 0),
    'count' => count($catProducts),
  ];
}
?>

<?php if (!empty($catSpot)): ?>
<section class="shop-cat-section" data-design-target="home.categories.section" aria-labelledby="shopCatTitle" data-reveal>
  <div class="shop-cat-container">
    <div class="shop-cat-head">
      <h2 class="shop-cat-title" id="shopCatTitle" data-design-target="home.categories.title">Shop By <span>Category</span></h2>
      <a href="/categories" class="shop-cat-all">View All Categories</a>
    </div>

    <div class="shop-cat-track" id="shopCatTrack" aria-label="Product categories" data-auto-slide="true">
      <?php foreach ($catSpot as $i => $c): ?>
        <article class="shop-cat-card">
          <a href="/category/<?= htmlspecialchars($c['slug']) ?>" class="shop-cat-link" data-design-target="home.category.card">
            <div class="shop-cat-img">
              <?php if (!empty($c['image'])): ?>
                <img src="<?= htmlspecialchars($c['image']) ?>" alt="<?= htmlspecialchars($c['image_alt'] ?? $c['name']) ?>" loading="lazy">
              <?php else: ?>
                <div class="shop-cat-fallback" aria-hidden="true"><?= htmlspecialchars($c['icon']) ?></div>
              <?php endif; ?>
            </div>
            <div class="shop-cat-body">
              <span class="shop-cat-icon" aria-hidden="true"><?= htmlspecialchars($c['icon']) ?></span>
              <span class="shop-cat-name" data-design-target="home.category.name"><?= htmlspecialchars($c['name']) ?></span>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- WHY CHOOSE -->
<section class="why-print-section" id="why-sec" data-reveal>
  <div class="why-print-container">
    <h2 class="why-print-heading">Why Choose <span>RCS PRINT?</span></h2>

    <div class="why-print-panel" aria-label="Why choose RCS Print">
      <article class="why-print-item">
        <div class="why-print-icon why-print-green"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
        <div class="why-print-copy">
          <h3>Premium Quality</h3>
          <p>Best quality materials and printing.</p>
        </div>
      </article>

      <article class="why-print-item">
        <div class="why-print-icon why-print-orange"><i class="fa-regular fa-thumbs-up" aria-hidden="true"></i></div>
        <div class="why-print-copy">
          <h3>100% Satisfaction</h3>
          <p>Your happiness matters.</p>
        </div>
      </article>

      <article class="why-print-item">
        <div class="why-print-icon why-print-purple"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i></div>
        <div class="why-print-copy">
          <h3>Free Design Support</h3>
          <p>Professional design support at no extra cost.</p>
        </div>
      </article>

      <article class="why-print-item">
        <div class="why-print-icon why-print-purple"><i class="fa-solid fa-tags" aria-hidden="true"></i></div>
        <div class="why-print-copy">
          <h3>Affordable Pricing</h3>
          <p>Low price with the best value.</p>
        </div>
      </article>

      <article class="why-print-item">
        <div class="why-print-icon why-print-orange"><i class="fa-solid fa-cube" aria-hidden="true"></i></div>
        <div class="why-print-copy">
          <h3>Bulk Order Specialist</h3>
          <p>Special prices for bulk requirements.</p>
        </div>
      </article>
    </div>
  </div>
</section>

<?php if (!empty($comboOffers ?? [])): ?>
<section class="home-combos" aria-labelledby="homeComboTitle" data-reveal>
  <div class="container">
    <h2 id="homeComboTitle">Scale Your Order, <span>Maximize Savings</span></h2>
    <div class="home-combo-mosaic">
      <?php foreach (array_slice($comboOffers,0,4) as $index=>$offer): $slot=$index===0?'large':($index===1?'wide':'square'); ?>
      <a class="home-combo-card home-combo-card--<?= $slot ?>" href="/combo/<?= htmlspecialchars((string)$offer['slug']) ?>" style="--combo-image:url('<?= htmlspecialchars((string)($offer['banner_image']??''),ENT_QUOTES) ?>')">
        <span class="home-combo-shade"></span><span class="home-combo-copy">
          <?php if (!empty($offer['badge'])): ?><small><?= htmlspecialchars((string)$offer['badge']) ?></small><?php endif; ?>
          <strong><?= htmlspecialchars((string)$offer['title']) ?></strong>
          <?php if (!empty($offer['short_description'])): ?><em><?= htmlspecialchars((string)$offer['short_description']) ?></em><?php endif; ?>
          <b><?= htmlspecialchars((string)($offer['cta_text'] ?: 'View Offer')) ?> →</b>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- BEST DEALS -->
<section class="best-deals-section" aria-labelledby="bestDealsTitle" data-reveal>
  <div class="best-deals-container">
    <h2 class="best-deals-heading" id="bestDealsTitle">Our <span>Best Deals</span></h2>

    <?php
    $fallbackDeals = [
      [
        'deal_type' => 'deal',
        'title' => '500 Visiting Cards',
        'subtitle' => 'Starting from',
        'price_text' => '₹199',
        'image_path' => 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=700&q=85&fit=crop',
        'image_alt' => '500 visiting cards printing deal',
        'cta_text' => 'Order Now',
        'cta_url' => '/categories',
        'color_theme' => 'green',
      ],
      [
        'deal_type' => 'deal',
        'title' => '1000 Flyers',
        'subtitle' => 'Starting from',
        'price_text' => '₹499',
        'image_path' => 'https://images.unsplash.com/photo-1541746972996-4e0b0f43e02a?w=700&q=85&fit=crop',
        'image_alt' => '1000 flyers printing deal',
        'cta_text' => 'Order Now',
        'cta_url' => '/categories',
        'color_theme' => 'orange',
      ],
      [
        'deal_type' => 'deal',
        'title' => 'Brochure (A4)',
        'subtitle' => 'Starting from',
        'price_text' => '₹799',
        'image_path' => 'https://images.unsplash.com/photo-1600172454284-934feca24de6?w=700&q=85&fit=crop',
        'image_alt' => 'A4 brochure printing deal',
        'cta_text' => 'Order Now',
        'cta_url' => '/categories',
        'color_theme' => 'purple',
      ],
      [
        'deal_type' => 'promo',
        'title' => 'Get',
        'highlight_text' => 'FREE Design',
        'subtitle' => 'on Your First Order!',
        'image_path' => '',
        'image_alt' => 'Free design offer',
        'cta_text' => 'Get Free Design',
        'cta_url' => '/#quick-help-sec',
        'color_theme' => 'purple',
      ],
    ];
    $bestDeals = !empty($homeDeals ?? []) ? $homeDeals : $fallbackDeals;
    $dealThemes = ['green', 'orange', 'purple'];
    $formatDealText = static function ($value): string {
      $safe = htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
      return preg_replace('/&lt;br\s*\/?&gt;/i', '<br>', $safe) ?? $safe;
    };
    ?>

    <div class="best-deals-grid">
      <?php foreach ($bestDeals as $deal):
        $dealType = strtolower(trim((string)($deal['deal_type'] ?? 'deal')));
        $themeRaw = strtolower(trim((string)($deal['color_theme'] ?? 'green')));
        $theme = in_array($themeRaw, $dealThemes, true) ? $themeRaw : 'green';
        $titleRaw = trim((string)($deal['title'] ?? ''));
        $highlightRaw = trim((string)($deal['highlight_text'] ?? ''));
        $subtitleRaw = trim((string)($deal['subtitle'] ?? ''));
        $priceRaw = trim((string)($deal['price_text'] ?? ''));
        $imageRaw = trim((string)($deal['image_path'] ?? ''));
        $imageAltRaw = trim((string)($deal['image_alt'] ?? '')) ?: ($titleRaw !== '' ? $titleRaw : 'Best deal');
        $ctaTextRaw = trim((string)($deal['cta_text'] ?? '')) ?: ($dealType === 'promo' ? 'Get Offer' : 'Order Now');
        $ctaUrlRaw = trim((string)($deal['cta_url'] ?? '')) ?: '/categories';
        $title = $formatDealText($titleRaw);
        $highlight = htmlspecialchars($highlightRaw, ENT_QUOTES, 'UTF-8');
        $subtitle = $formatDealText($subtitleRaw);
        $price = htmlspecialchars($priceRaw, ENT_QUOTES, 'UTF-8');
        $image = htmlspecialchars($imageRaw, ENT_QUOTES, 'UTF-8');
        $imageAlt = htmlspecialchars($imageAltRaw, ENT_QUOTES, 'UTF-8');
        $ctaText = htmlspecialchars($ctaTextRaw, ENT_QUOTES, 'UTF-8');
        $ctaUrl = htmlspecialchars($ctaUrlRaw, ENT_QUOTES, 'UTF-8');
      ?>
        <?php if ($dealType === 'promo'): ?>
          <article class="deal-promo-card" data-design-target="home.deal.card">
            <div class="deal-confetti" aria-hidden="true"></div>
            <div class="deal-promo-copy">
              <h3>
                <?= $title ?><?php if ($highlight !== ''): ?> <span><?= $highlight ?></span><?php endif; ?>
                <?php if ($subtitle !== ''): ?><br><?= $subtitle ?><?php endif; ?>
              </h3>
              <a href="<?= $ctaUrl ?>" class="deal-promo-btn"><?= $ctaText ?></a>
            </div>
            <?php if ($imageRaw !== ''): ?>
              <div class="deal-gift deal-gift-image">
                <img src="<?= $image ?>" alt="<?= $imageAlt ?>" loading="lazy">
              </div>
            <?php else: ?>
              <div class="deal-gift" aria-hidden="true">
                <div class="deal-gift-bow"></div>
                <div class="deal-gift-box"></div>
              </div>
            <?php endif; ?>
          </article>
        <?php else: ?>
          <article class="deal-card deal-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
            <a href="<?= $ctaUrl ?>" class="deal-card-link" data-design-target="home.deal.card">
              <div class="deal-card-img">
                <?php if ($imageRaw !== ''): ?>
                  <img src="<?= $image ?>" alt="<?= $imageAlt ?>" loading="lazy">
                <?php endif; ?>
              </div>
              <div class="deal-card-band">
                <div class="deal-copy">
                  <h3><?= $title ?></h3>
                  <?php if ($subtitle !== ''): ?><p><?= $subtitle ?></p><?php endif; ?>
                  <?php if ($price !== ''): ?><strong><?= $price ?></strong><?php endif; ?>
                </div>
                <span class="deal-order-btn"><?= $ctaText ?></span>
              </div>
            </a>
          </article>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<?php
$businessNeedCards = [];
foreach (($businessNeeds ?? []) as $need) {
  $needName = trim((string)($need['name'] ?? ''));
  $needSlug = trim((string)($need['slug'] ?? ''));
  if ($needName === '' || $needSlug === '') continue;
  $businessNeedCards[] = $need;
}
?>
<?php if ($businessNeedCards): ?>
<section class="shop-cat-section business-needs-section" aria-labelledby="businessNeedsTitle" data-reveal>
  <div class="shop-cat-container">
    <div class="shop-cat-head">
      <h2 class="shop-cat-title" id="businessNeedsTitle">Shop by <span>Business Needs</span></h2>
      <a href="/business" class="shop-cat-all">View All Business</a>
    </div>
    <div class="shop-cat-track business-needs-track" aria-label="Business sector collections" data-auto-slide="true">
      <?php foreach ($businessNeedCards as $need):
        $needName = trim((string)($need['name'] ?? 'Business Sector'));
        $needSlug = trim((string)($need['slug'] ?? ''));
        $needIcon = trim((string)($need['icon'] ?? '🏢')) ?: '🏢';
        $needImage = trim((string)($need['image_path'] ?? '')) ?: '/assets/img/categories/print-category.svg';
        $needUrl = '/business/' . rawurlencode($needSlug);
      ?>
      <article class="shop-cat-card business-need-card">
        <a href="<?= htmlspecialchars($needUrl, ENT_QUOTES, 'UTF-8') ?>" class="shop-cat-link business-need-link">
          <div class="shop-cat-img business-need-img">
            <img src="<?= htmlspecialchars($needImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($needName, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" onerror="this.src='/assets/img/categories/print-category.svg'">
          </div>
          <div class="shop-cat-body business-need-body compact">
            <span class="shop-cat-icon" aria-hidden="true"><?= htmlspecialchars($needIcon, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="shop-cat-name"><?= htmlspecialchars($needName, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- HOW IT WORKS -->
<section class="how-works-section" aria-labelledby="howWorksTitle" data-reveal>
  <div class="how-works-container">
    <h2 class="how-works-title" id="howWorksTitle">How It <span>Works</span></h2>
    <div class="how-works-panel">
      <div class="how-works-track" role="list">
        <article class="how-step" role="listitem">
          <div class="how-icon how-icon-white"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></div>
          <div class="how-copy">
            <span>01</span>
            <h3>Upload or Request<br> Your Design</h3>
          </div>
        </article>

        <div class="how-arrow" aria-hidden="true">→</div>

        <article class="how-step" role="listitem">
          <div class="how-icon how-icon-orange"><i class="fa-solid fa-pencil" aria-hidden="true"></i></div>
          <div class="how-copy">
            <span>02</span>
            <h3>Approve<br> Your Design</h3>
          </div>
        </article>

        <div class="how-arrow" aria-hidden="true">→</div>

        <article class="how-step" role="listitem">
          <div class="how-icon how-icon-green"><i class="fa-solid fa-print" aria-hidden="true"></i></div>
          <div class="how-copy">
            <span>03</span>
            <h3>We Print<br> Your Order</h3>
          </div>
        </article>

        <div class="how-arrow" aria-hidden="true">→</div>

        <article class="how-step" role="listitem">
          <div class="how-icon how-icon-white"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></div>
          <div class="how-copy">
            <span>04</span>
            <h3>We Deliver<br> At Your Doorstep</h3>
          </div>
        </article>
      </div>
    </div>
  </div>
</section>


<!-- CUSTOMER TESTIMONIALS -->
<section class="customer-say-section" aria-labelledby="customerSayTitle" data-reveal>
  <div class="customer-say-container">
    <h2 class="customer-say-title" id="customerSayTitle">What Our <span>Customers</span> Say</h2>

    <div class="customer-say-shell">
      <div class="customer-say-track" id="customerSayTrack" role="list" data-auto-slide="true">
        <?php if (!empty($homeReviews ?? [])): ?>
          <?php foreach ($homeReviews as $review):
            $productName = trim((string)($review['product_name'] ?? 'RCS Product')) ?: 'RCS Product';
            $productUrl = !empty($review['product_slug']) ? '/product/' . rawurlencode((string)$review['product_slug']) : '/categories';
          ?>
          <article class="customer-card" role="listitem">
            <i class="fa-solid fa-quote-left customer-quote" aria-hidden="true"></i>
            <p class="customer-text"><?= htmlspecialchars($review['comment'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <div class="customer-stars" aria-label="<?= (int)($review['rating'] ?? 0) ?> out of 5 stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="fa-<?= $i <= (int)($review['rating'] ?? 0) ? 'solid' : 'regular' ?> fa-star" aria-hidden="true"></i>
              <?php endfor; ?>
            </div>
            <div class="customer-profile">
              <span class="customer-initials" aria-hidden="true"><?= htmlspecialchars($review['customer_initials'] ?? 'RC', ENT_QUOTES, 'UTF-8') ?></span>
              <div>
                <h3>– <?= htmlspecialchars($review['customer_name'] ?? 'RCS Customer', ENT_QUOTES, 'UTF-8') ?></h3>
                <span><a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?></a></span>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        <?php else: ?>
          <article class="customer-card customer-card-empty" role="listitem">
            <i class="fa-solid fa-quote-left customer-quote" aria-hidden="true"></i>
            <p class="customer-text">Verified customer reviews will appear here after delivered orders are reviewed and approved.</p>
            <div class="customer-stars" aria-label="0 out of 5 stars">
              <i class="fa-regular fa-star" aria-hidden="true"></i>
              <i class="fa-regular fa-star" aria-hidden="true"></i>
              <i class="fa-regular fa-star" aria-hidden="true"></i>
              <i class="fa-regular fa-star" aria-hidden="true"></i>
              <i class="fa-regular fa-star" aria-hidden="true"></i>
            </div>
            <div class="customer-profile"><span class="customer-initials" aria-hidden="true">★</span><div><h3>No approved reviews yet</h3><span>Verified customers only</span></div></div>
          </article>
        <?php endif; ?>
      </div>
    </div>

    <div class="customer-dots" aria-label="Testimonials pagination">
      <span class="customer-dot customer-dot-green"></span>
      <span class="customer-dot customer-dot-active"></span>
      <span class="customer-dot customer-dot-green"></span>
    </div>
  </div>
</section>


<!-- BLOGS -->
<section class="blog-section" id="blogs-sec" aria-labelledby="blogTitle" data-reveal>
  <div class="blog-container">
    <div class="blog-head">
      <h2 class="blog-title" id="blogTitle"><span>Blogs</span></h2>
      <a class="blog-view-all" href="/blogs">View All</a>
    </div>

    <?php
    $fallbackBlogs = [
      [
        'title' => 'How to Choose the Perfect Business Card Finish',
        'slug' => 'how-to-choose-the-perfect-business-card-finish',
        'excerpt' => 'Learn when to pick matte, gloss, textured or premium laminated cards for a stronger first impression.',
        'featured_image' => 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=900&q=85&fit=crop',
        'image_alt' => 'Premium printed business cards arranged on a desk',
        'category' => 'Print Tips',
        'badge_theme' => 'purple',
        'published_at' => '2026-05-09 10:00:00',
      ],
      [
        'title' => '5 Flyer Design Ideas That Get More Customers',
        'slug' => '5-flyer-design-ideas-that-get-more-customers',
        'excerpt' => 'Simple layout, color and copy tips to make your next flyer campaign clear, attractive and conversion focused.',
        'featured_image' => 'https://images.unsplash.com/photo-1541746972996-4e0b0f43e02a?w=900&q=85&fit=crop',
        'image_alt' => 'Creative flyer and brochure design samples',
        'category' => 'Design Ideas',
        'badge_theme' => 'orange',
        'published_at' => '2026-05-05 10:00:00',
      ],
      [
        'title' => 'Bulk Printing Checklist for Events and Shops',
        'slug' => 'bulk-printing-checklist-for-events-and-shops',
        'excerpt' => 'Plan quantities, paper type, delivery timing and finishing options before placing your next large print order.',
        'featured_image' => 'https://images.unsplash.com/photo-1600172454284-934feca24de6?w=900&q=85&fit=crop',
        'image_alt' => 'Stacks of brochures and colorful printed material',
        'category' => 'Bulk Orders',
        'badge_theme' => 'green',
        'published_at' => '2026-05-02 10:00:00',
      ],
      [
        'title' => 'How Square Category Images Improve Product Browsing',
        'slug' => 'how-square-category-images-improve-product-browsing',
        'excerpt' => 'See why clean square thumbnails make product discovery faster and help customers compare print categories easily.',
        'featured_image' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=900&q=85&fit=crop',
        'image_alt' => 'Designer arranging print category thumbnails on a screen',
        'category' => 'Product Guide',
        'badge_theme' => 'purple',
        'published_at' => '2026-04-28 10:00:00',
      ],
    ];
    $blogCards = !empty($homeBlogs ?? []) ? $homeBlogs : $fallbackBlogs;
    $blogBadgeClass = static function ($theme): string {
      $theme = strtolower(trim((string)$theme));
      return match ($theme) {
        'orange' => ' blog-badge-orange',
        'green' => ' blog-badge-green',
        default => '',
      };
    };
    $formatBlogDate = static function ($value): string {
      $time = strtotime((string)$value);
      return $time ? date('d M, Y', $time) : date('d M, Y');
    };
    ?>

    <div class="blog-grid" id="blogGrid" role="list" data-auto-slide="true">
      <?php foreach ($blogCards as $blog):
        $blogTitleRaw = trim((string)($blog['title'] ?? 'Blog'));
        $blogSlugRaw = trim((string)($blog['slug'] ?? ''));
        $blogUrl = $blogSlugRaw !== '' ? '/blog/' . rawurlencode($blogSlugRaw) : '/#blogs-sec';
        $blogImageRaw = trim((string)($blog['featured_image'] ?? ''));
        $blogAltRaw = trim((string)($blog['image_alt'] ?? '')) ?: $blogTitleRaw;
        $blogCategoryRaw = trim((string)($blog['category'] ?? 'Print Tips')) ?: 'Print Tips';
        $blogExcerptRaw = trim((string)($blog['excerpt'] ?? ''));
        $blogTitle = htmlspecialchars($blogTitleRaw, ENT_QUOTES, 'UTF-8');
        $blogImage = htmlspecialchars($blogImageRaw, ENT_QUOTES, 'UTF-8');
        $blogAlt = htmlspecialchars($blogAltRaw, ENT_QUOTES, 'UTF-8');
        $blogCategory = htmlspecialchars($blogCategoryRaw, ENT_QUOTES, 'UTF-8');
        $blogExcerpt = htmlspecialchars($blogExcerptRaw, ENT_QUOTES, 'UTF-8');
        $blogDate = htmlspecialchars($formatBlogDate($blog['published_at'] ?? ''), ENT_QUOTES, 'UTF-8');
        $badgeClass = $blogBadgeClass($blog['badge_theme'] ?? 'purple');
      ?>
      <article class="blog-card" role="listitem">
        <a href="<?= htmlspecialchars($blogUrl, ENT_QUOTES, 'UTF-8') ?>" class="blog-card-link" data-design-target="home.blog.card" aria-label="Read blog: <?= $blogTitle ?>">
          <div class="blog-image">
            <?php if ($blogImageRaw !== ''): ?>
              <img src="<?= $blogImage ?>" alt="<?= $blogAlt ?>" loading="lazy">
            <?php endif; ?>
            <span class="blog-badge<?= $badgeClass ?>"><?= $blogCategory ?></span>
          </div>
          <div class="blog-content">
            <div class="blog-meta"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= $blogDate ?></div>
            <h3><?= $blogTitle ?></h3>
            <?php if ($blogExcerpt !== ''): ?><p><?= $blogExcerpt ?></p><?php endif; ?>
            <span class="blog-read-more">Read More <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
          </div>
        </a>
      </article>
      <?php endforeach; ?>
    </div>


  </div>
</section>


<section class="ym-section recent-products-section" id="recentProductsSection" aria-labelledby="recentProductsTitle" hidden>
  <div class="ym-head">
    <h2 id="recentProductsTitle" class="ym-title">Recently Viewed <span>Products</span></h2>
    <a href="/categories" class="ym-view-all">View All Products</a>
  </div>
  <div class="ym-grid ym-product-grid" id="recentProductsGrid"></div>
</section>

<!-- QUICK HELP STRIP -->
<section class="quick-help-section" id="quick-help-sec" aria-label="Quick help and bulk order actions" data-reveal>
  <div class="quick-help-container">
    <div class="quick-help-bar">
      <a class="quick-help-item quick-help-call" href="tel:<?= preg_replace('/\D+/', '', $bizPhone) ?>">
        <span class="quick-help-icon"><i class="fa-solid fa-phone-volume" aria-hidden="true"></i></span>
        <span class="quick-help-copy">
          <span>Need Help? Call Us</span>
          <strong><?= $bizPhone ?></strong>
        </span>
      </a>

      <button class="quick-help-item quick-help-whatsapp" type="button" onclick="window.open('https://wa.me/<?= $bizWa ?>','_blank')">
        <span class="quick-help-icon"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
        <span class="quick-help-copy">
          <strong>Chat with us on WhatsApp</strong>
          <span>We are here to help!</span>
        </span>
      </button>

      <a class="quick-help-item quick-help-download" href="/categories" aria-label="Download our brochure for all products">
        <span class="quick-help-icon"><i class="fa-solid fa-download" aria-hidden="true"></i></span>
        <span class="quick-help-copy">
          <strong>Download Our Brochure</strong>
          <span>For All Products</span>
        </span>
      </a>
    </div>
  </div>
</section>


<!-- FOOTER -->
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>

<script>

(() => {
  const track = document.querySelector('.business-needs-track');
  if (!track) return;
  let timer = null;
  const getStep = () => {
    const card = track.querySelector('.business-need-card');
    if (!card) return Math.max(180, Math.round(track.clientWidth * 0.7));
    const gap = parseFloat(getComputedStyle(track).gap || '0');
    return Math.max(120, card.getBoundingClientRect().width + gap);
  };
  const slideNext = () => {
    const maxScroll = track.scrollWidth - track.clientWidth;
    if (maxScroll <= 4) return;
    if (track.scrollLeft >= maxScroll - 8) { track.scrollTo({ left: 0, behavior: 'smooth' }); return; }
    track.scrollBy({ left: getStep(), behavior: 'smooth' });
  };
  const start = () => { stop(); timer = window.setInterval(slideNext, 3500); };
  const stop = () => { if (timer) window.clearInterval(timer); timer = null; };
  track.addEventListener('mouseenter', stop); track.addEventListener('mouseleave', start);
  track.addEventListener('focusin', stop); track.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) start();
})();

(() => {
  const section = document.getElementById('recentProductsSection');
  const grid = document.getElementById('recentProductsGrid');
  if (!section || !grid) return;
  let ids = [];
  try { ids = JSON.parse(localStorage.getItem('rcs_recent_products') || '[]'); } catch (e) { ids = []; }
  ids = ids.map(Number).filter(Boolean).slice(0, 10);
  if (!ids.length) return;
  const esc = (s) => String(s || '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  fetch('/api/recent-products?ids=' + encodeURIComponent(ids.join(',')))
    .then(r => r.json())
    .then(data => {
      const items = data.products || [];
      if (!items.length) return;
      grid.innerHTML = items.map((p, idx) => {
        const href = p.slug ? `/product/${encodeURIComponent(p.slug)}` : '/categories';
        const img = p.primary_image || p.image_path || '';
        const price = Number(p.min_price || 0);
        return `<article class="ym-card ym-product-card" data-reveal data-reveal-delay="${(idx % 3) * 60}"><a class="ym-img ym-product-img" href="${href}">${img ? `<img src="${esc(img)}" alt="${esc(p.name)}" loading="lazy" onerror="this.style.display='none';if(this.nextElementSibling){this.nextElementSibling.removeAttribute('hidden');}"><span class="ym-product-fallback" hidden><i class="fa-solid fa-print" aria-hidden="true"></i></span>` : `<span class="ym-product-fallback"><i class="fa-solid fa-print" aria-hidden="true"></i></span>`}</a><div class="ym-body"><div class="ym-cat"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>${esc(p.category_name || 'Print Product')}</div><h3 class="ym-name">${esc(p.name || 'Print Product')}</h3><div class="ym-foot"><div><div class="ym-from">Starting from</div><div class="ym-price">₹${price > 0 ? price.toLocaleString('en-IN') : '—'}</div></div><a href="${href}" class="ym-order">VIEW</a></div></div></article>`;
      }).join('');
      section.hidden = false;
    })
    .catch(() => {});
})();

(() => {
  const track = document.getElementById('shopCatTrack');
  if (!track) return;
  let timer = null;
  const getStep = () => {
    const card = track.querySelector('.shop-cat-card');
    if (!card) return Math.max(180, Math.round(track.clientWidth * 0.7));
    const gap = parseFloat(getComputedStyle(track).gap || '0');
    return Math.max(120, card.getBoundingClientRect().width + gap);
  };
  const slideNext = () => {
    const maxScroll = track.scrollWidth - track.clientWidth;
    if (maxScroll <= 4) return;
    if (track.scrollLeft >= maxScroll - 8) {
      track.scrollTo({ left: 0, behavior: 'smooth' });
      return;
    }
    track.scrollBy({ left: getStep(), behavior: 'smooth' });
  };
  const start = () => {
    stop();
    timer = window.setInterval(slideNext, 3500);
  };
  const stop = () => {
    if (timer) window.clearInterval(timer);
    timer = null;
  };
  track.addEventListener('mouseenter', stop);
  track.addEventListener('mouseleave', start);
  track.addEventListener('focusin', stop);
  track.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  start();
})();

(() => {
  const track = document.getElementById('blogGrid');
  if (!track) return;
  let timer = null;
  const getStep = () => {
    const card = track.querySelector('.blog-card');
    if (!card) return Math.max(220, Math.round(track.clientWidth * 0.7));
    const gap = parseFloat(getComputedStyle(track).gap || '0');
    return Math.max(160, card.getBoundingClientRect().width + gap);
  };
  const slideNext = () => {
    const maxScroll = track.scrollWidth - track.clientWidth;
    if (maxScroll <= 4) return;
    if (track.scrollLeft >= maxScroll - 8) {
      track.scrollTo({ left: 0, behavior: 'smooth' });
      return;
    }
    track.scrollBy({ left: getStep(), behavior: 'smooth' });
  };
  const start = () => {
    stop();
    timer = window.setInterval(slideNext, 3600);
  };
  const stop = () => {
    if (timer) window.clearInterval(timer);
    timer = null;
  };
  track.addEventListener('mouseenter', stop);
  track.addEventListener('mouseleave', start);
  track.addEventListener('focusin', stop);
  track.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  start();
})();

(() => {
  const track = document.getElementById('customerSayTrack');
  if (!track) return;
  let timer = null;
  const getStep = () => {
    const card = track.querySelector('.customer-card');
    if (!card) return Math.max(220, Math.round(track.clientWidth * 0.7));
    const gap = parseFloat(getComputedStyle(track).gap || '0');
    return Math.max(160, card.getBoundingClientRect().width + gap);
  };
  const slideNext = () => {
    const maxScroll = track.scrollWidth - track.clientWidth;
    if (maxScroll <= 4) return;
    if (track.scrollLeft >= maxScroll - 8) {
      track.scrollTo({ left: 0, behavior: 'smooth' });
      return;
    }
    track.scrollBy({ left: getStep(), behavior: 'smooth' });
  };
  const start = () => {
    stop();
    timer = window.setInterval(slideNext, 3000);
  };
  const stop = () => {
    if (timer) window.clearInterval(timer);
    timer = null;
  };
  track.addEventListener('mouseenter', stop);
  track.addEventListener('mouseleave', start);
  track.addEventListener('focusin', stop);
  track.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  start();
})();

</script>

<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

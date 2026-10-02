<?php
/**
 * product.php — Product Detail Page
 * FIX: class names aligned with CSS (pd-gallery, pd-thumbs)
 * FIX: pricing based only on quantity + design choice
 * FIX: design fee from admin settings
 * IMPROVEMENT: larger title, better spacing, related products with CTA
 */
$pageTitle = trim((string)($product['meta_title'] ?? '')) ?: ((string)($product['name'] ?? 'Product') . ' Printing — RCS Graphic');
$settingsMap = [];
try {
    $settings    = Database::rows("SELECT `key`, value FROM settings");
    $settingsMap = array_column($settings, 'value', 'key');
} catch (\Throwable) {}

// Design fee from admin settings (Admin → Settings → design_fee)
$designFee = (float)($product['design_fee'] ?? ($settingsMap['design_fee'] ?? 0));

// Gallery
$imgs       = $product['images'] ?? [];
$primaryImg = '';
foreach ($imgs as $img) {
    $imgUrl = $img['image_path'] ?? ($img['url'] ?? '');
    if (!empty($img['is_primary']) && $imgUrl) { $primaryImg = $imgUrl; break; }
}
if (!$primaryImg && $imgs) $primaryImg = ($imgs[0]['image_path'] ?? ($imgs[0]['url'] ?? '')); 
if (!$primaryImg) $primaryImg = 'https://placehold.co/600x600/EEF3FD/1A56E8?text=' . urlencode($product['name']);

$specs      = $product['specs']      ?? [];
$qualities  = $product['qualities']  ?? [];
$attrGroups = []; // Attribute pricing retired from customer flow
$bizWa      = preg_replace('/\D+/', '', (string)($settingsMap['biz_whatsapp'] ?? '919876543210'));
$startingPrice = (float)($product['min_price'] ?? 0);
if ($startingPrice <= 0 && $qualities) {
    foreach ($qualities as $q) {
        $qMin = (float)($q['min_price'] ?? 0);
        if ($qMin > 0 && ($startingPrice <= 0 || $qMin < $startingPrice)) {
            $startingPrice = $qMin;
        }
    }
}
$comparePrice = (float)($product['original_price'] ?? 0);
$discountPct  = ($startingPrice > 0 && $comparePrice > $startingPrice)
    ? max(1, (int)round((($comparePrice - $startingPrice) / $comparePrice) * 100))
    : 0;
$rawProductCode = strtoupper(trim((string)($product['product_code'] ?? '')));
$categoryCodePrefix = strtoupper(trim((string)($product['category_code_prefix'] ?? '')));
$categoryCodePrefix = preg_replace('/[^A-Z0-9]/', '', $categoryCodePrefix) ?: '';
$productCode = $rawProductCode;
if ($productCode !== '' && $categoryCodePrefix !== '') {
    $normalizedCode = preg_replace('/[^A-Z0-9]/', '', $productCode) ?: '';
    if ($normalizedCode !== '' && strncmp($normalizedCode, $categoryCodePrefix, strlen($categoryCodePrefix)) !== 0) {
        $productCode = $categoryCodePrefix . $normalizedCode;
    }
}
$categoryName = trim((string)($product['category_name'] ?? 'Products'));
$categorySlug = trim((string)($product['category_slug'] ?? ''));
$categoryUrl = $categorySlug !== '' ? '/category/' . rawurlencode($categorySlug) : '/categories';
$reviewSummary = is_array($reviewSummary ?? null) ? $reviewSummary : ['average' => 0, 'average_display' => '0.0', 'count' => 0, 'breakdown' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
$productReviews = is_array($productReviews ?? null) ? $productReviews : [];
$reviewAverage = (float)($reviewSummary['average'] ?? 0);
$reviewAverageDisplay = (string)($reviewSummary['average_display'] ?? number_format($reviewAverage, 1));
$reviewCount = (int)($reviewSummary['count'] ?? 0);
if ($reviewCount === 0 && !empty($productReviews)) {
    $reviewCount = count($productReviews);
    $ratingTotal = array_sum(array_map(static fn($review) => (int)($review['rating'] ?? 0), $productReviews));
    $reviewAverage = $reviewCount > 0 ? round($ratingTotal / $reviewCount, 1) : 0.0;
    $reviewAverageDisplay = number_format($reviewAverage, 1);
    $reviewSummary['count'] = $reviewCount;
    $reviewSummary['average'] = $reviewAverage;
    $reviewSummary['average_display'] = $reviewAverageDisplay;
    $reviewSummary['breakdown'] = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($productReviews as $review) {
        $rating = max(1, min(5, (int)($review['rating'] ?? 0)));
        $reviewSummary['breakdown'][$rating]++;
    }
}
$reviewStarCount = $reviewCount > 0 ? max(1, min(5, (int)round($reviewAverage))) : 0;
$reviewStars = str_repeat('★', $reviewStarCount) . str_repeat('☆', 5 - $reviewStarCount);
$productFaqs = [];
try { $productFaqs = \Faq\FaqManager::listByPage('product_detail'); } catch (\Throwable) { $productFaqs = []; }
if (!$productFaqs) {
    $productFaqs = [
        ['question' => 'Can I upload my own design?', 'answer' => 'Yes, you can upload PDF, AI, PSD, PNG, JPG and other supported artwork files up to 50MB.'],
        ['question' => 'Can RCS Graphic create the design for me?', 'answer' => 'Yes, select the free design option and our team will connect with you for the design brief and confirmation.'],
        ['question' => 'How long does delivery take?', 'answer' => 'Standard delivery usually takes 3 - 5 working days after artwork and order confirmation.'],
    ];
}
$productDescription = trim(strip_tags((string)($product['description'] ?? '')));
$pageDesc = trim((string)($product['meta_description'] ?? '')) ?: ($productDescription !== '' ? (function_exists('mb_substr') ? mb_substr($productDescription, 0, 155) : substr($productDescription, 0, 155)) : ('Order ' . (string)($product['name'] ?? 'printing products') . ' online from RCS Graphic with premium quality printing and support.'));
$pageImage = $primaryImg;
$pageOgType = 'product';
$productSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => (string)($product['name'] ?? 'Product'),
    'description' => $pageDesc,
    'image' => preg_match('#^https?://#i', $primaryImg) ? $primaryImg : ((defined('APP_URL') ? rtrim((string)APP_URL, '/') : '') . '/' . ltrim($primaryImg, '/')),
    'brand' => ['@type' => 'Brand', 'name' => 'RCS Graphic'],
    'sku' => $productCode !== '' ? $productCode : (string)($product['id'] ?? ''),
];
if ($startingPrice > 0) {
    $productSchema['offers'] = [
        '@type' => 'Offer',
        'url' => (defined('APP_URL') ? rtrim((string)APP_URL, '/') : '') . '/product/' . rawurlencode((string)($product['slug'] ?? '')),
        'priceCurrency' => 'INR',
        'price' => number_format($startingPrice, 2, '.', ''),
        'availability' => 'https://schema.org/InStock',
    ];
}
if ($reviewCount > 0 && $reviewAverage > 0) {
    $productSchema['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format($reviewAverage, 1, '.', ''),
        'reviewCount' => $reviewCount,
    ];
}
$pageSchema = [$productSchema];
if (!empty($productFaqs)) {
    $pageSchema[] = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn($faq) => [
            '@type' => 'Question',
            'name' => (string)($faq['question'] ?? ''),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string)($faq['answer'] ?? ''))],
        ], $productFaqs),
    ];
}
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
// Note: cart-drawer is already included by header.php — do NOT include again
?>

<div class="pd-page-wrap">
<div class="container">

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <a href="/">Home</a><span>/</span>
    <a href="/categories">Products</a><span>/</span>
    <a href="<?= htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($categoryName) ?></a><span>/</span>
    <span style="color:var(--ink);font-weight:600"><?= htmlspecialchars($product['name']) ?></span>
  </div>

  <!-- ══════════════════════════════════════════════════
       MAIN 2-COLUMN GRID
       Left: Gallery | Right: Info + Configurator
  ═══════════════════════════════════════════════════ -->
  <div class="pd-grid">

    <!-- ════ LEFT — GALLERY ════ -->
    <div class="pd-gallery" data-reveal>

      <div class="pd-main" id="pdMainWrap">
        <button class="pd-wishlist-btn <?= !empty($wishlistActive) ? 'is-active' : '' ?>" type="button" onclick="toggleWishlist(this)" aria-label="<?= !empty($wishlistActive) ? 'Remove from wishlist' : 'Add to wishlist' ?>" aria-pressed="<?= !empty($wishlistActive) ? 'true' : 'false' ?>">
          <i class="<?= !empty($wishlistActive) ? 'fa-solid' : 'fa-regular' ?> fa-heart" aria-hidden="true"></i>
        </button>
        <img id="pdMainImg"
             src="<?= htmlspecialchars($primaryImg) ?>"
             alt="<?= htmlspecialchars($product['name']) ?>"
             onerror="this.src='https://placehold.co/600x600/EEF3FD/1A56E8?text=<?= urlencode($product['name']) ?>'">
      </div>

      <?php if (count($imgs) > 1): ?>
      <div class="pd-thumbs-shell" aria-label="Product image gallery">
        <button type="button" class="pd-gallery-nav pd-gallery-prev" onclick="slideProductGallery(-1)" aria-label="Previous product image">‹</button>
        <div class="pd-thumbs" id="pdThumbs">
          <?php foreach ($imgs as $i => $img): ?>
          <div class="pd-th <?= $i === 0 ? 'act' : '' ?>"
               onclick="switchImg('<?= htmlspecialchars($img['image_path'] ?? ($img['url'] ?? '')) ?>',this)"
               title="<?= htmlspecialchars($img['alt_text'] ?: $product['name']) ?>">
            <img src="<?= htmlspecialchars($img['image_path'] ?? ($img['url'] ?? '')) ?>" loading="lazy"
                 alt="<?= htmlspecialchars($img['alt_text'] ?: $product['name']) ?>"
                 onerror="this.style.opacity=.3">
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="pd-gallery-nav pd-gallery-next" onclick="slideProductGallery(1)" aria-label="Next product image">›</button>
      </div>
      <?php endif; ?>

      <div class="pd-gallery-actions" aria-label="Product previews">
        <button type="button"><i class="fa-solid fa-rotate" aria-hidden="true"></i> 360° View</button>
        <button type="button"><i class="fa-regular fa-circle-play" aria-hidden="true"></i> Video Preview</button>
      </div>

    </div><!-- /pd-gallery -->

    <!-- ════ RIGHT — INFO + CONFIGURATOR ════ -->
    <div class="pd-info-col" data-reveal data-reveal-delay="80">

      <h1 class="pd-name"><?= htmlspecialchars($product['name']) ?></h1>
      <div class="pd-rating-row" aria-label="Product rating">
        <span class="pd-stars" aria-hidden="true"><?= htmlspecialchars($reviewStars) ?></span>
        <?php if ($reviewCount > 0): ?>
          <strong><?= htmlspecialchars($reviewAverageDisplay) ?></strong>
          <span>(<?= number_format($reviewCount) ?> Reviews)</span>
        <?php else: ?>
          <strong>New</strong>
          <span>(No reviews yet)</span>
        <?php endif; ?>
      </div>


      <div class="pd-price-strip">
        <span class="pd-price-now" id="heroPrice"><?= $startingPrice > 0 ? '₹' . number_format($startingPrice) : '₹ —' ?></span>
        <span class="pd-price-label">Starting Price</span>
        <?php if ($comparePrice > 0): ?>
        <span class="pd-price-old">₹<?= number_format($comparePrice) ?></span>
        <?php endif; ?>
        <?php if ($discountPct > 0): ?>
        <span class="pd-discount">Save <?= $discountPct ?>%</span>
        <?php endif; ?>
      </div>

      <!-- ── SPECIFICATIONS ── -->
      <?php
      // Only show specs that have a value filled in
      $filledSpecs = array_filter($specs, fn($s) => !empty(trim($s['value'] ?? '')));
      ?>
      <?php if ($filledSpecs || $productCode): ?>
      <div class="pd-spec-table" aria-label="Product details">
        <?php if ($productCode): ?>
        <div class="pd-spec-row">
          <div class="pd-spec-label">Product Code</div>
          <div class="pd-spec-value"><?= htmlspecialchars($productCode) ?></div>
        </div>
        <?php endif; ?>
        <?php foreach ($filledSpecs as $spec): ?>
        <div class="pd-spec-row">
          <div class="pd-spec-label"><?= htmlspecialchars($spec['label']) ?></div>
          <div class="pd-spec-value"><?= htmlspecialchars($spec['value']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- ── QUALITY SELECTOR (shown only when multiple qualities exist) ── -->
      <?php if (count($qualities) > 1): ?>
      <div class="cfg pd-quality-panel" style="margin-bottom:10px">
        <div class="cfg-title">Paper / Quality</div>
        <?php foreach ($qualities as $qi => $q): ?>
        <div class="qual-opt <?= $qi === 0 ? 'sel' : '' ?>"
             onclick="selQual(<?= $qi ?>, <?= (int)$q['id'] ?>, this)"
             id="qual-<?= (int)$q['id'] ?>">
          <div class="qual-radio"><div class="qr-dot"></div></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:700;color:var(--ink);margin-bottom:2px">
              <?= htmlspecialchars($q['name']) ?>
            </div>
            <?php if (!empty($q['description'])): ?>
            <div style="font-size:11px;color:var(--text2)"><?= htmlspecialchars($q['description']) ?></div>
            <?php endif; ?>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div style="font-size:10px;color:var(--text3);margin-bottom:1px">from</div>
            <div style="font-family:var(--fd);font-size:14px;font-weight:700;color:var(--blue)"
                 id="qprice-<?= (int)$q['id'] ?>">
              <?= $q['min_price'] > 0 ? '₹'.number_format((float)$q['min_price']) : '—' ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- ── QUANTITY ── -->
      <div class="pd-qty-row">
        <label class="pd-qty-label" for="pdQty">QUANTITY</label>
        <div class="pd-qty-control">
          <select class="fi fi-sel" id="pdQty" onchange="onQtyChange()">
            <option value="">Select Quantity</option>
            <!-- Populated by JS from API based on selected quality -->
          </select>
          <span>Price varies by quantity - more pieces = better rate per unit</span>
        </div>
      </div>


      <!-- Attribute groups hidden in customer flow -->

      <!-- ── DESIGN OPTION ── -->
      <div class="pd-design-section">
        <div class="pd-design-heading">Upload Your Design</div>
        <div class="pd-design-grid">

          <div class="design-opt sel" id="dopt-upload" onclick="selDesignOpt('upload')">
            <div id="panel-upload">
              <div class="upload-zone" id="uploadZone"
                   onclick="event.stopPropagation();document.getElementById('artworkFile').click()"
                   ondragover="event.preventDefault();this.classList.add('drag')"
                   ondragleave="this.classList.remove('drag')"
                   ondrop="handleFileDrop(event)">
                <input type="file" id="artworkFile"
                       accept=".pdf,.ai,.eps,.png,.jpg,.jpeg,.psd,.cdr,.svg,.tif,.tiff,.zip"
                       onchange="handleFileSelect(event)">
                <div class="design-opt-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></div>
                <div class="design-opt-title">Upload File</div>
                <div class="design-opt-copy">PDF, AI, PSD, PNG, JPG (Max 50MB)</div>
              </div>
              <label class="design-later-check" onclick="event.stopPropagation()">
                <input type="checkbox" id="uploadLaterCheck" onchange="toggleUploadLater(this.checked)">
                <span>I will Upload Design Later</span>
              </label>
              <div class="design-later-note" id="uploadLaterNote" hidden>
                No problem. Your order will be saved as <strong>Customer Upload</strong>, and you can upload the design later from <strong>My Account &gt; My Orders</strong>.
              </div>
              <div id="uploadPreview"></div>
            </div>
          </div>

          <div class="pd-design-or">OR</div>

          <div class="design-opt" id="dopt-rcs" onclick="selDesignOpt('rcs')">
            <div class="design-opt-icon"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></div>
            <div class="design-opt-title">Design by RCS Graphic</div>
            <div class="design-opt-copy">Let our experts prepare your artwork for print</div>
            <?php if ($designFee > 0): ?>
            <div class="design-opt-note is-paid">+₹<?= number_format($designFee) ?> design fee</div>
            <?php else: ?>
            <div class="design-opt-note is-free">Design support included</div>
            <?php endif; ?>
          </div>

        </div>
        <div id="panel-rcs" style="display:none"></div>
      </div>

      <!-- Notes area intentionally empty — kept for spacing -->

      <!-- ── PRICE PANEL ── -->
      <div class="price-panel" id="pricePanel">
        <div class="pp-row">
          <span class="pp-l">Base Price (Qty × Quality)</span>
          <span class="pp-v" id="ppBase">—</span>
        </div>
        <div class="pp-row" id="ppDesignRow" style="display:none">
          <span class="pp-l">Design Fee (RCS Graphic)</span>
          <span class="pp-v" id="ppDesignFee">₹0</span>
        </div>
        <div class="pp-row">
          <span class="pp-tl">Total Price</span>
          <span class="pp-tv" id="ppTotal">₹ —</span>
        </div>
      </div>

      <!-- ── ACTION BUTTONS ── -->
      <div class="pd-action-stack">
        <div class="pd-action-row">
          <button class="btn btn-blue btn-full" onclick="addToCart()" id="addCartBtn">
            <i class="fa-solid fa-cart-plus" aria-hidden="true"></i> ADD TO CART
          </button>
          <button class="btn btn-outline" onclick="waOrder()" title="Order via WHATSAPP SUPPORT">
            <svg viewBox="0 0 24 24" style="width:17px;height:17px;fill:currentColor" aria-hidden="true">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            WHATSAPP SUPPORT
          </button>
        </div>
        <div class="pd-checkout-note pd-delivery-row">
          <span><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> Delivery in 3 - 5 Working Days</span>
          <small><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Free Delivery on Orders Above ₹999</small>
          <em id="orderHint" class="pd-order-hint" aria-live="polite"></em>
        </div>
      </div>

    </div><!-- /pd-info-col -->
  </div><!-- /pd-grid -->

  <!-- PRODUCT DETAILS / REVIEWS SECTION -->
  <section class="pd-tabs-section" data-reveal data-reveal-delay="120" aria-label="Product information and customer reviews">
    <div class="pd-tabs-card">
      <div class="pd-tabs-nav" role="tablist" aria-label="Product detail tabs">
        <button type="button" class="pd-tab-btn is-active" id="pd-tab-description" role="tab" aria-selected="true" aria-controls="pd-panel-description" onclick="switchProductTab('description', this)">Description</button>
        <button type="button" class="pd-tab-btn" id="pd-tab-specifications" role="tab" aria-selected="false" aria-controls="pd-panel-specifications" onclick="switchProductTab('specifications', this)">Specifications</button>
        <button type="button" class="pd-tab-btn" id="pd-tab-faqs" role="tab" aria-selected="false" aria-controls="pd-panel-faqs" onclick="switchProductTab('faqs', this)">FAQs</button>
      </div>

      <div class="pd-tabs-content">
        <div class="pd-tabs-left">
          <div class="pd-tab-panel is-active" id="pd-panel-description" role="tabpanel" aria-labelledby="pd-tab-description" data-tab-panel="description">
            <?php $dbDescription = trim((string)($product['description'] ?? '')); ?>
            <?php if ($dbDescription !== ''): ?>
              <p><?= nl2br(htmlspecialchars($dbDescription)) ?></p>
            <?php endif; ?>
          </div>

          <div class="pd-tab-panel" id="pd-panel-specifications" role="tabpanel" aria-labelledby="pd-tab-specifications" data-tab-panel="specifications" hidden>
            <h2>Specifications</h2>
            <?php if ($filledSpecs || $productCode): ?>
            <div class="pd-tab-spec-grid">
              <?php if ($productCode): ?>
              <div><span>Product Code</span><strong><?= htmlspecialchars($productCode) ?></strong></div>
              <?php endif; ?>
              <?php foreach ($filledSpecs as $spec): ?>
              <div><span><?= htmlspecialchars($spec['label']) ?></span><strong><?= htmlspecialchars($spec['value']) ?></strong></div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p>Specifications for this product will be confirmed by our print expert after your enquiry.</p>
            <?php endif; ?>
          </div>

          <div class="pd-tab-panel" id="pd-panel-faqs" role="tabpanel" aria-labelledby="pd-tab-faqs" data-tab-panel="faqs" hidden>
            <h2>FAQs</h2>
            <div class="pd-faq-list">
              <?php foreach ($productFaqs as $idx => $faq): ?>
              <details <?= $idx === 0 ? 'open' : '' ?>>
                <summary><?= htmlspecialchars((string)($faq['question'] ?? ''), ENT_QUOTES, 'UTF-8') ?></summary>
                <p><?= nl2br(htmlspecialchars((string)($faq['answer'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
              </details>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <aside class="pd-reviews-panel" aria-label="What our customers say">
          <div class="pd-reviews-head">
            <h2>What Our Customers Say</h2>
          </div>
          <div class="pd-review-cards">
            <?php if (!empty($productReviews)): ?>
              <?php foreach (array_slice($productReviews, 0, 3) as $review): ?>
              <article class="pd-review-card">
                <div class="pd-review-person">
                  <span class="pd-review-avatar" aria-hidden="true"><?= htmlspecialchars($review['customer_initials'] ?? 'RC') ?></span>
                  <div><strong><?= htmlspecialchars($review['customer_name'] ?? 'RCS Customer') ?></strong><span>Verified Customer</span></div>
                </div>
                <div class="pd-review-stars" aria-label="<?= (int)($review['rating'] ?? 0) ?> out of 5 stars"><?= htmlspecialchars($review['stars'] ?? '') ?></div>
                <p><?= htmlspecialchars($review['comment'] ?? '') ?></p>
              </article>
              <?php endforeach; ?>
            <?php else: ?>
              <article class="pd-review-card pd-review-card-empty">
                <div class="pd-review-person"><span class="pd-review-avatar" aria-hidden="true">★</span><div><strong>No reviews yet</strong><span>Verified customer feedback</span></div></div>
                <div class="pd-review-stars" aria-label="0 out of 5 stars">☆☆☆☆☆</div>
                <p>Reviews from customers who purchased this product will appear here after approval.</p>
              </article>
            <?php endif; ?>
          </div>
          <button type="button" class="pd-review-next" aria-label="Next review" onclick="document.querySelector('.pd-review-cards')?.scrollBy({left:220, behavior:'smooth'})">›</button>
        </aside>
      </div>
    </div>
  </section>

  <!-- RANDOM RELATED PRODUCTS -->
  <?php $relatedProductCards = is_array($relatedProducts ?? null) ? array_slice($relatedProducts, 0, 5) : []; ?>
  <?php if ($relatedProductCards): ?>
  <section class="ym-section" aria-labelledby="relatedProductTitle">
    <div class="ym-head">
      <h2 id="relatedProductTitle" class="ym-title">You May <span>Also Like</span></h2>
      <a href="/categories" class="ym-view-all">View All Products</a>
    </div>

    <div class="ym-grid ym-product-grid">
      <?php foreach ($relatedProductCards as $idx => $relatedProduct):
        $relatedName = (string)($relatedProduct['name'] ?? 'Print Product');
        $relatedSlug = (string)($relatedProduct['slug'] ?? '');
        $relatedHref = $relatedSlug !== '' ? '/product/' . rawurlencode($relatedSlug) : '/categories';
        $relatedImg = trim((string)($relatedProduct['primary_image'] ?? ($relatedProduct['image_path'] ?? '')));
        $relatedCategory = trim((string)($relatedProduct['category_name'] ?? 'Print Product'));
        $relatedMinPrice = (float)($relatedProduct['min_price'] ?? 0);
      ?>
      <article class="ym-card ym-product-card" data-reveal data-reveal-delay="<?= ($idx % 3) * 60 ?>">
        <a class="ym-img ym-product-img" href="<?= htmlspecialchars($relatedHref) ?>">
          <?php if ($relatedImg !== ''): ?>
            <img src="<?= htmlspecialchars($relatedImg) ?>" alt="<?= htmlspecialchars($relatedName) ?>" loading="lazy"
                 onerror="this.style.display='none';if(this.nextElementSibling){this.nextElementSibling.removeAttribute('hidden');}">
            <span class="ym-product-fallback" hidden><i class="fa-solid fa-print" aria-hidden="true"></i></span>
          <?php else: ?>
            <span class="ym-product-fallback"><i class="fa-solid fa-print" aria-hidden="true"></i></span>
          <?php endif; ?>
        </a>
        <div class="ym-body">
          <div class="ym-cat"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><?= htmlspecialchars($relatedCategory) ?></div>
          <h3 class="ym-name"><?= htmlspecialchars($relatedName) ?></h3>
          <div class="ym-foot">
            <div>
              <div class="ym-from">Starting from</div>
              <div class="ym-price">₹<?= $relatedMinPrice > 0 ? number_format($relatedMinPrice) : '—' ?></div>
            </div>
            <a href="<?= htmlspecialchars($relatedHref) ?>" class="ym-order">VIEW</a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

</div><!-- /container -->
</div><!-- /page-wrap -->

<script>
// ── Data from PHP ──────────────────────────────────────────────
const PRODUCT_ID  = <?= (int)$product['id'] ?>;
const BIZ_WA      = '<?= htmlspecialchars($bizWa, ENT_QUOTES, 'UTF-8') ?>';
const PRODUCT_NAME = <?= json_encode((string)($product['name'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const PRODUCT_CODE = <?= json_encode((string)$productCode, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const CATEGORY_NAME = <?= json_encode((string)$categoryName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const CSRF        = '<?= htmlspecialchars($csrf ?? '') ?>';
const IS_LOGGED_IN = <?= ($user ?? null) ? 'true' : 'false' ?>;
const QUALITIES   = <?= json_encode($qualities) ?>;
const DESIGN_FEE  = <?= (float)$designFee ?>;
const PRODUCT_SLUG = <?= json_encode((string)($product['slug'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

try {
  const key = 'rcs_recent_products';
  const current = { id: Number(PRODUCT_ID), slug: PRODUCT_SLUG, name: PRODUCT_NAME, at: Date.now() };
  const list = JSON.parse(localStorage.getItem(key) || '[]').filter((item) => Number(item?.id) !== current.id);
  localStorage.setItem(key, JSON.stringify([current, ...list].slice(0, 12)));
} catch (e) {}

// ── State ──────────────────────────────────────────────────────
let selectedQualityId  = <?= $qualities ? (int)$qualities[0]['id'] : 1 ?>;
let selectedQualityIdx = 0;
// Ensure first quality card is visually selected on load
document.addEventListener('DOMContentLoaded', () => {
  const firstQual = document.querySelector('.qual-opt');
  if (firstQual && !firstQual.classList.contains('sel')) firstQual.classList.add('sel');
});
let selectedQty        = null;
let artworkId          = null;
let uploadedFileName   = null;
let designChoice       = 'upload';
let uploadDesignLater  = false;
let currentBasePrice   = 0;

function refreshOrderReadiness() {
  const hasQty = !!selectedQty;
  const addBtn = document.getElementById('addCartBtn');
  const hint = document.getElementById('orderHint');

  if (addBtn) addBtn.disabled = !hasQty;

  if (hint) {
    hint.textContent = hasQty
      ? 'Looks good. You can now add to cart.'
      : 'Select quantity to enable Add to Cart.';
  }
}

// Gallery
function switchImg(url, el) {
  const img = document.getElementById('pdMainImg');
  img.style.opacity = '0.6';
  setTimeout(() => { img.src = url; img.style.opacity = '1'; }, 150);
  document.querySelectorAll('.pd-th').forEach(t => t.classList.remove('act'));
  if (el) {
    el.classList.add('act');
    el.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
  }
}

function slideProductGallery(dir) {
  const thumbs = Array.from(document.querySelectorAll('.pd-th'));
  if (!thumbs.length) return;
  const activeIdx = Math.max(0, thumbs.findIndex(t => t.classList.contains('act')));
  const nextIdx = (activeIdx + dir + thumbs.length) % thumbs.length;
  const next = thumbs[nextIdx];
  const nextImg = next?.querySelector('img');
  if (!next || !nextImg) return;
  switchImg(nextImg.currentSrc || nextImg.src, next);
}

// Quality Selection
function selQual(idx, qualId, clickedEl) {
  selectedQualityIdx = idx;
  selectedQualityId  = qualId;
  document.querySelectorAll('.qual-opt').forEach(el => el.classList.remove('sel'));
  if (clickedEl) clickedEl.classList.add('sel');
  reloadQtySlabs();
  refreshOrderReadiness();
}

// Quantity Slabs
async function reloadQtySlabs() {
  if (!PRODUCT_ID) return;
  try {
    const resp = await fetch(`/api/products/${PRODUCT_ID}/pricing`);
    const data = await resp.json();
    const list = data.qualities || [];
    if (!list.length) return;
    const quality = list.find(q => q.id == selectedQualityId) || list[0];
    selectedQualityId = quality.id;

    const sel  = document.getElementById('pdQty');
    const prev = sel.value;
    sel.innerHTML = '<option value="">— Select Quantity —</option>';

    (quality.slabs || []).forEach(slab => {
      const opt = document.createElement('option');
      opt.value = slab.quantity;
      opt.textContent = Number(slab.quantity).toLocaleString('en-IN')
                      + ' pieces — ₹' + Number(slab.price).toLocaleString('en-IN');
      opt.dataset.price = slab.price;
      if (slab.quantity == prev) opt.selected = true;
      sel.appendChild(opt);
    });

    if (prev) {
      selectedQty = parseInt(prev) || null;
    }

    calcPrice();
  } catch (e) { /* silent */ }
}

function onQtyChange() {
  selectedQty = parseInt(document.getElementById('pdQty').value) || null;
  calcPrice();
  refreshOrderReadiness();
}

// Price Calculation
function calcPrice() {
  if (!selectedQualityId || !selectedQty) {
    document.getElementById('ppBase').textContent  = '—';
    document.getElementById('ppTotal').textContent = '₹ —';
    currentBasePrice = 0;
    refreshOrderReadiness();
    return;
  }

  const sel    = document.getElementById('pdQty');
  const selOpt = sel.options[sel.selectedIndex];
  const base   = selOpt ? parseFloat(selOpt.dataset.price || 0) : 0;
  currentBasePrice = base;

  const fee   = designChoice === 'rcs' ? DESIGN_FEE : 0;
  const total = base + fee;

  const fmt = n => '₹' + Number(n).toLocaleString('en-IN');
  document.getElementById('ppBase').textContent  = fmt(base);
  document.getElementById('ppTotal').textContent = fmt(total);
  const panel = document.getElementById('pricePanel');
  if (panel) {
    panel.classList.remove('flash');
    requestAnimationFrame(() => {
      panel.classList.add('flash');
      setTimeout(() => panel.classList.remove('flash'), 320);
    });
  }

  const feeRow = document.getElementById('ppDesignRow');
  if (feeRow) feeRow.style.display = fee > 0 ? '' : 'none';

  const feeEl = document.getElementById('ppDesignFee');
  if (feeEl) feeEl.textContent = fmt(fee);

  QUALITIES.forEach(q => {
    const badge = document.getElementById('qprice-' + q.id);
    if (!badge) return;
    const slab = (q.slabs || []).find(s => parseInt(s.quantity) === selectedQty);
    badge.textContent = slab ? '₹' + Number(slab.price).toLocaleString('en-IN') : '';
  });
  refreshOrderReadiness();
}

// Design Option
function selDesignOpt(choice) {
  designChoice = choice === 'rcs' ? 'rcs' : 'upload';
  if (designChoice === 'rcs' && uploadDesignLater) toggleUploadLater(false);
  const uploadOpt = document.getElementById('dopt-upload');
  const rcsOpt = document.getElementById('dopt-rcs');
  const uploadPanel = document.getElementById('panel-upload');
  const rcsPanel = document.getElementById('panel-rcs');

  if (uploadOpt) uploadOpt.classList.toggle('sel', designChoice === 'upload');
  if (rcsOpt) rcsOpt.classList.toggle('sel', designChoice === 'rcs');
  if (uploadPanel) uploadPanel.style.display = 'block';
  if (rcsPanel) rcsPanel.style.display = designChoice === 'rcs' ? 'block' : 'none';

  calcPrice();
  refreshOrderReadiness();
}

// File Upload
function handleFileSelect(e) {
  const f = e.target.files[0];
  if (f) processFile(f);
}

function handleFileDrop(e) {
  e.preventDefault();
  document.getElementById('uploadZone').classList.remove('drag');
  const f = e.dataTransfer.files[0];
  if (f) processFile(f);
}

async function processFile(file) {
  if (file.size > 52428800) {
    toast('File too large. Max 50MB', 'error');
    return;
  }

  if (uploadDesignLater) toggleUploadLater(false);

  const fd = new FormData();
  fd.append('artwork', file);
  toast('Uploading…', 'info');

  try {
    const resp = await fetch('/api/upload/artwork', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF },
      credentials: 'same-origin',
      body: fd
    });

    const data = await resp.json();

    if (data.ok) {
      artworkId        = data.artwork_id;
      uploadedFileName = data.filename;
      document.getElementById('uploadPreview').innerHTML = `
        <div class="upload-done">
          <div style="font-size:24px">📄</div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:700;color:var(--green)">${file.name}</div>
            <div style="font-size:11px;color:var(--text2);margin-top:2px">${(file.size/1024/1024).toFixed(2)} MB</div>
          </div>
          <button onclick="removeFile()" style="color:var(--red);font-size:18px;background:none;border:none;cursor:pointer">✕</button>
        </div>`;
      toast('Artwork uploaded!', 'success');
    } else {
      toast(data.msg || 'Upload failed', 'error');
    }
  } catch {
    toast('Upload failed. Please try again.', 'error');
  }
}

function removeFile() {
  artworkId = null;
  uploadedFileName = null;
  document.getElementById('uploadPreview').innerHTML = '';
  document.getElementById('artworkFile').value = '';
}

function toggleUploadLater(checked) {
  uploadDesignLater = !!checked;
  const checkbox = document.getElementById('uploadLaterCheck');
  const note = document.getElementById('uploadLaterNote');
  const zone = document.getElementById('uploadZone');
  if (checkbox) checkbox.checked = uploadDesignLater;
  if (note) note.hidden = !uploadDesignLater;
  if (zone) zone.classList.toggle('is-muted', uploadDesignLater);
  if (uploadDesignLater) {
    selDesignOpt('upload');
    artworkId = null;
    uploadedFileName = null;
    document.getElementById('uploadPreview').innerHTML = '';
    document.getElementById('artworkFile').value = '';
    toast('You can upload your design later from My Account after placing the order.', 'info');
  }
}

// Add to Cart
async function addToCart(opts = {}) {
  const v = validateOrder();
  if (!v.ok) {
    toast(v.msg, 'error');
    return false;
  }

  const btn = document.getElementById('addCartBtn');
  btn.disabled = true;
  btn.textContent = 'Adding…';

  try {
    const resp = await fetch('/api/cart/add', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        product_id: PRODUCT_ID,
        quality_id: selectedQualityId,
        quantity: selectedQty,
        attribute_selections: {},
        design_choice: designChoice,
        design_brief: uploadDesignLater ? 'Customer selected: I will upload design later.' : '',
        notes: uploadDesignLater ? 'User will upload design later from My Account order detail.' : '',
        artwork_id: uploadDesignLater ? null : artworkId
      })
    });

    const data = await resp.json();

    if (data.ok) {
      toast('Added to cart! 🛒', 'success');
      updateCartCount();
      if (!opts.silent) openCart();
      return true;
    }
    toast(data.msg || 'Could not add to cart', 'error');
    return false;
  } catch {
    toast('Error. Please try again.', 'error');
    return false;
  } finally {
    btn.disabled = false;
    btn.textContent = 'ADD TO CART';
  }
}

async function buyNow() {
  const v = validateOrder();
  if (!v.ok) {
    toast(v.msg, 'error');
    return false;
  }

  const ok = await addToCart({ silent: true });
  if (!ok) return;
  closeCart();
  location.href = '/checkout';
}

async function toggleWishlist(btn) {
  if (!IS_LOGGED_IN) {
    toast('Please login to add products to your wishlist', 'info');
    const next = encodeURIComponent(window.location.pathname + window.location.search);
    setTimeout(() => { window.location.href = `/login?redirect=${next}`; }, 450);
    return;
  }

  btn.disabled = true;
  try {
    const resp = await fetch('/api/wishlist/toggle', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF
      },
      credentials: 'same-origin',
      body: JSON.stringify({product_id: PRODUCT_ID})
    });
    const data = await resp.json();
    if (!data.ok) {
      toast(data.msg || 'Could not update wishlist', 'error');
      return;
    }

    const active = !!data.wishlisted;
    btn.classList.toggle('is-active', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    btn.setAttribute('aria-label', active ? 'Remove from wishlist' : 'Add to wishlist');
    const icon = btn.querySelector('i');
    if (icon) {
      icon.classList.toggle('fa-solid', active);
      icon.classList.toggle('fa-regular', !active);
    }
    toast(data.msg || (active ? 'Added to wishlist' : 'Removed from wishlist'), active ? 'success' : 'info');
  } catch (err) {
    console.error(err);
    toast('Could not update wishlist', 'error');
  } finally {
    btn.disabled = false;
  }
}

function validateOrder() {
  if (!selectedQty) {
    return { ok: false, msg: 'Please select a quantity' };
  }

  return { ok: true };
}

// WHATSAPP SUPPORT Quick Order
function waOrder() {
  const totalEl = document.getElementById('ppTotal')?.textContent || '₹ —';
  const baseEl = document.getElementById('ppBase')?.textContent || '—';
  const qname = QUALITIES[selectedQualityIdx]?.name || 'Standard';
  const qtyText = selectedQty ? Number(selectedQty).toLocaleString('en-IN') + ' pcs' : 'Not selected';
  const artworkText = uploadedFileName ? uploadedFileName : (artworkId ? 'Artwork uploaded' : 'Not uploaded yet');
  const designText = designChoice === 'rcs' ? 'Design by RCS Graphic' : 'Customer artwork upload';
  const now = new Date().toLocaleString('en-IN');
  const pageUrl = window.location.href;

  const msg = [
    '🧾 *Product Enquiry - RCS Graphic*',
    `🕒 ${now}`,
    '',
    '*Product Details*',
    `• Product: ${PRODUCT_NAME}`,
    PRODUCT_CODE ? `• Product Code: ${PRODUCT_CODE}` : '',
    `• Category: ${CATEGORY_NAME || 'Products'}`,
    `• Quantity: ${qtyText}`,
    `• Quality: ${qname}`,
    `• Design Option: ${designText}`,
    `• Base Price: ${baseEl}`,
    `• Estimated Total: ${totalEl}`,
    `• Artwork File: ${artworkText}`,
    '',
    '*Page Link*',
    pageUrl,
    '',
    'I am interested in this product. Please confirm final costing, artwork requirements and next steps.'
  ].filter(Boolean).join('\n');

  const waNumber = String(BIZ_WA || '').replace(/\D+/g, '');
  if (!waNumber) {
    toast('WhatsApp number is not configured', 'error');
    return;
  }
  window.open(`https://wa.me/${waNumber}?text=${encodeURIComponent(msg)}`, '_blank', 'noopener');
}

function switchProductTab(tab, btn) {
  document.querySelectorAll('.pd-tab-btn').forEach(el => {
    const active = el === btn;
    el.classList.toggle('is-active', active);
    el.setAttribute('aria-selected', active ? 'true' : 'false');
  });
  document.querySelectorAll('[data-tab-panel]').forEach(panel => {
    const active = panel.dataset.tabPanel === tab;
    panel.classList.toggle('is-active', active);
    panel.hidden = !active;
  });
}

// Init
reloadQtySlabs();
refreshOrderReadiness();
</script>

<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

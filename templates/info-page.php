<?php
$page = is_array($page ?? null) ? $page : [];
$pageTitle = $page['meta_title'] ?? (($page['title'] ?? 'Information') . ' — RCS Graphic');
$pageDesc = $page['meta_description'] ?? 'RCS Graphic information page.';
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';

$bizWa = htmlspecialchars(($settingsMap['biz_whatsapp'] ?? '919876543210'), ENT_QUOTES, 'UTF-8');
$title = htmlspecialchars((string)($page['title'] ?? 'Information'), ENT_QUOTES, 'UTF-8');
$eyebrow = htmlspecialchars((string)($page['eyebrow'] ?? 'RCS Graphic'), ENT_QUOTES, 'UTF-8');
$heading = htmlspecialchars((string)($page['heading'] ?? $title), ENT_QUOTES, 'UTF-8');
$intro = htmlspecialchars((string)($page['intro'] ?? ''), ENT_QUOTES, 'UTF-8');
$breadcrumb = htmlspecialchars((string)($page['breadcrumb'] ?? $title), ENT_QUOTES, 'UTF-8');
$badges = is_array($page['hero_badges'] ?? null) ? $page['hero_badges'] : [];
$highlights = is_array($page['highlights'] ?? null) ? $page['highlights'] : [];
$sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
$steps = is_array($page['steps'] ?? null) ? $page['steps'] : [];
$ctaTitle = htmlspecialchars((string)($page['cta_title'] ?? 'Need help?'), ENT_QUOTES, 'UTF-8');
$ctaText = htmlspecialchars((string)($page['cta_text'] ?? 'Contact our team for guidance.'), ENT_QUOTES, 'UTF-8');
$visualTitle = htmlspecialchars((string)($page['visual_title'] ?? 'Premium print support'), ENT_QUOTES, 'UTF-8');
$visualText = htmlspecialchars((string)($page['visual_text'] ?? 'Professional print guidance for your business.'), ENT_QUOTES, 'UTF-8');
$mockups = is_array($page['mockups'] ?? null) ? array_values($page['mockups']) : [];
if (empty($mockups)) {
    $mockups = [(string)($page['visual_image'] ?? '/assets/img/categories/print-category.svg')];
}
$isSimple = (($page['layout'] ?? '') === 'simple') || !empty($page['simple']);
$isAboutShowcase = (($page['layout'] ?? '') === 'about-showcase');
if ($isAboutShowcase):
$phoneRaw = (string)($settingsMap['biz_phone'] ?? '+91 98765 43210');
$phone = htmlspecialchars($phoneRaw, ENT_QUOTES, 'UTF-8');
$phoneHref = htmlspecialchars(preg_replace('/\D+/', '', $phoneRaw), ENT_QUOTES, 'UTF-8');
$wa = htmlspecialchars((string)($settingsMap['biz_whatsapp'] ?? '919876543210'), ENT_QUOTES, 'UTF-8');
$aboutReviews = is_array($aboutReviews ?? null) ? array_slice($aboutReviews, 0, 3) : [];
?>
<main class="about-showcase-page">
  <?php
  $pageHero = [
    'key' => 'about',
    'title' => 'About RCS PRINT',
    'subtitle' => 'Where ideas get printed to perfection with premium quality, design support and value-focused pricing.',
    'eyebrow' => 'About Us',
    'breadcrumbs' => [
      ['label' => 'Home', 'url' => '/'],
      ['label' => 'About', 'url' => null],
    ],
    'fallback_image' => '/assets/images/sample-products/brochures/brochures-2.svg',
  ];
  include INCLUDE_PATH . '/partials/page-hero.php';
  ?>
  <section class="about-showcase-hero">
    <div class="about-showcase-container about-hero-grid">
      <div class="about-hero-copy">
        <p class="about-lead about-lead-no-repeat">At RCS PRINT, we transform your ideas into premium quality prints that speak for your brand. From business cards to brochures and beyond, we deliver quality you can see and feel. Our team supports every order with careful artwork review, practical material guidance and print-ready finishing suggestions. Whether you need corporate stationery, marketing flyers, packaging labels or bulk promotional prints, we focus on sharp details, consistent colors and a professional final result. We make the complete process simple for businesses by combining design support, reliable print production and value-focused pricing under one roof.</p>
        <div class="about-hero-features" aria-label="RCS PRINT strengths">
          <div><i class="fa-solid fa-gear" aria-hidden="true"></i><strong>Premium<br>Quality</strong></div>
          <div><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><strong>Custom Print<br>Solutions</strong></div>
          <div><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i><strong>Free Design<br>Support</strong></div>
          <div><i class="fa-solid fa-shield-heart" aria-hidden="true"></i><strong>Affordable<br>Pricing</strong></div>
        </div>
      </div>
      <div class="about-hero-visual" aria-label="RCS PRINT product mockups">
        <div class="about-dots about-dots-right" aria-hidden="true"></div>
        <div class="about-plant" aria-hidden="true"><span></span></div>
        <div class="about-box-card about-brand-card"><strong><span class="rcs-word"><span>R</span><span>C</span><span>S</span></span></strong><small>PRINT</small></div>
        <div class="about-brochure about-brochure-main">
          <span>Your Brand<br>Our Printing<br>Perfect Impact</span>
          <img src="/assets/images/sample-products/brochures/brochures-2.svg" alt="" loading="eager" decoding="async">
        </div>
        <div class="about-shopping-bag">
          <span class="bag-handle" aria-hidden="true"></span>
          <strong><span class="rcs-word"><span>R</span><span>C</span><span>S</span></span></strong>
          <small>PRINT</small>
        </div>
        <div class="about-business-card"><img src="/assets/images/sample-products/business-cards/business-cards-1.svg" alt="" loading="lazy" decoding="async"></div>
        <div class="about-brochure about-brochure-small"><img src="/assets/images/sample-products/brochures/brochures-3.svg" alt="" loading="lazy" decoding="async"></div>
      </div>
    </div>
  </section>

  <section class="about-story-section">
    <div class="about-showcase-container about-story-grid about-story-clean-grid">
      <div class="about-story-copy about-story-clean-copy">
        <span class="about-story-mark" aria-hidden="true"></span>
        <h2>Our Story</h2>
        <p>RCS PRINT was founded with a simple goal — to deliver premium printing at affordable prices without compromising on quality.</p>
        <p>What started as a small printing service in Rajkot has grown into a trusted brand known for reliability, creativity, and customer satisfaction.</p>
        <p>We combine advanced technology, skilled professionals, and dedicated support to make every project a success.</p>
      </div>
      <div class="about-story-cards" aria-label="RCS PRINT mission and vision">
        <article class="about-story-info-card about-story-mission">
          <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
          <div>
            <h3>Our Mission</h3>
            <p>To empower businesses and individuals through high-quality, innovative, and affordable printing solutions that help their brand stand out.</p>
          </div>
        </article>
        <article class="about-story-info-card about-story-vision">
          <i class="fa-regular fa-eye" aria-hidden="true"></i>
          <div>
            <h3>Our Vision</h3>
            <p>To be India's most trusted online printing platform, recognized for quality, innovation, and customer delight.</p>
          </div>
        </article>
      </div>
    </div>
  </section>

  <section class="why-print-section about-choose-section">
    <div class="why-print-container">
      <h2 class="why-print-heading">Why Choose <span>RCS PRINT?</span></h2>
      <div class="why-print-panel" aria-label="Why choose RCS Print">
        <article class="why-print-item"><div class="why-print-icon why-print-green"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div><div class="why-print-copy"><h3>Premium Quality</h3><p>Best quality materials and printing.</p></div></article>
        <article class="why-print-item"><div class="why-print-icon why-print-orange"><i class="fa-regular fa-thumbs-up" aria-hidden="true"></i></div><div class="why-print-copy"><h3>100% Satisfaction</h3><p>Your happiness matters.</p></div></article>
        <article class="why-print-item"><div class="why-print-icon why-print-purple"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i></div><div class="why-print-copy"><h3>Free Design Support</h3><p>Professional design support at no extra cost.</p></div></article>
        <article class="why-print-item"><div class="why-print-icon why-print-purple"><i class="fa-solid fa-tags" aria-hidden="true"></i></div><div class="why-print-copy"><h3>Affordable Pricing</h3><p>Low price with the best value.</p></div></article>
        <article class="why-print-item"><div class="why-print-icon why-print-orange"><i class="fa-solid fa-cube" aria-hidden="true"></i></div><div class="why-print-copy"><h3>Bulk Order Specialist</h3><p>Special prices for bulk requirements.</p></div></article>
      </div>
    </div>
  </section>

  <section class="about-process-section">
    <div class="about-showcase-container">
      <div class="about-section-head">
        <p class="about-kicker">Our Process</p>
        <h2>Simple Process, <span>Perfect Results</span></h2>
      </div>
      <div class="about-process-row">
        <article><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i><div><span>01</span><h3>Upload &amp; Request</h3><p>Share your design or requirement.</p></div></article>
        <article><i class="fa-solid fa-pencil" aria-hidden="true"></i><div><span>02</span><h3>Approve Design</h3><p>We create &amp; share design for approval.</p></div></article>
        <article><i class="fa-solid fa-print" aria-hidden="true"></i><div><span>03</span><h3>We Print Your Order</h3><p>High-quality printing with perfect finish.</p></div></article>
        <article><i class="fa-solid fa-truck" aria-hidden="true"></i><div><span>04</span><h3>We Deliver To Your Doorstep</h3><p>Safe &amp; fast delivery right at your place.</p></div></article>
      </div>
    </div>
  </section>

  <section class="about-testimonials-section">
    <div class="about-showcase-container">
      <div class="about-section-head about-section-head-compact">
        <p class="about-kicker">What Our Customers Say</p>
      </div>
      <div class="about-testimonial-wrap">
        <button class="about-slider-btn about-slider-prev" type="button" aria-label="Previous testimonial"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
        <div class="about-testimonial-grid">
          <?php if ($aboutReviews): ?>
            <?php foreach ($aboutReviews as $review):
              $rating = max(1, min(5, (int)($review['rating'] ?? 5)));
              $productName = trim((string)($review['product_name'] ?? 'Verified Customer'));
              $productSlug = trim((string)($review['product_slug'] ?? ''));
              $productUrl = $productSlug !== '' ? '/product/' . rawurlencode($productSlug) : '/products';
            ?>
              <article>
                <i class="fa-solid fa-quote-left" aria-hidden="true"></i>
                <p><?= htmlspecialchars((string)($review['comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="about-stars" aria-label="<?= $rating ?> out of 5 stars">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fa-<?= $i <= $rating ? 'solid' : 'regular' ?> fa-star" aria-hidden="true"></i>
                  <?php endfor; ?>
                </div>
                <div class="about-customer">
                  <span><?php if(!empty($review['customer_avatar'])):?><img src="<?= htmlspecialchars((string)$review['customer_avatar'],ENT_QUOTES,'UTF-8') ?>" alt=""><?php else:?><?= htmlspecialchars((string)($review['customer_initials'] ?? 'RC'), ENT_QUOTES, 'UTF-8') ?><?php endif;?></span>
                  <strong><?= htmlspecialchars((string)($review['customer_name'] ?? 'RCS Customer'), ENT_QUOTES, 'UTF-8') ?><small><a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?></a></small></strong>
                </div>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <article class="about-testimonial-empty"><i class="fa-solid fa-quote-left" aria-hidden="true"></i><p>Approved customer reviews will appear here once verified customers share their printing experience.</p><div class="about-stars" aria-label="0 out of 5 stars"><i class="fa-regular fa-star" aria-hidden="true"></i><i class="fa-regular fa-star" aria-hidden="true"></i><i class="fa-regular fa-star" aria-hidden="true"></i><i class="fa-regular fa-star" aria-hidden="true"></i><i class="fa-regular fa-star" aria-hidden="true"></i></div><div class="about-customer"><span>★</span><strong>No approved reviews yet<small>Verified customers only</small></strong></div></article>
          <?php endif; ?>
        </div>
        <button class="about-slider-btn about-slider-next" type="button" aria-label="Next testimonial"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
      </div>
      <?php if (count($aboutReviews) > 1): ?><div class="about-slider-dots" aria-hidden="true"><span></span><span></span><span></span></div><?php endif; ?>
    </div>
  </section>

  <?php $quickHelpExtraClass='about-quick-help-section'; include INCLUDE_PATH . '/partials/quick-help.php'; ?>
</main>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>
<?php return; endif; ?>
<?php if ($isSimple):
?>
<main class="info-page info-page-simple">
  <section class="info-simple-hero">
    <div class="info-container info-simple-wrap">
      <nav class="info-breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span>/</span><span><?= $breadcrumb ?></span></nav>
      <div class="info-simple-hero-copy">
        <div class="sec-ey"><?= $eyebrow ?></div>
        <h1><?= $heading ?></h1>
        <?php if ($intro !== ''): ?><p><?= $intro ?></p><?php endif; ?>
      </div>
    </div>
  </section>

  <?php if (!empty($page['managed_html']) || !empty($sections)): ?>
  <section class="info-simple-section">
    <div class="info-container info-simple-wrap">
      <article class="info-simple-document" aria-label="<?= $title ?> details">
        <?php if (!empty($page['managed_html'])): ?>
          <?= $page['managed_html'] ?>
        <?php else: ?>
        <?php foreach ($sections as $idx => $section): ?>
          <section class="info-simple-block">
            <h2><span><?= str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT) ?></span><?= htmlspecialchars((string)($section['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
            <?php foreach ((array)($section['body'] ?? []) as $paragraph): ?>
              <p><?= htmlspecialchars((string)$paragraph, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
            <?php if (!empty($section['bullets']) && is_array($section['bullets'])): ?>
              <ul>
                <?php foreach ($section['bullets'] as $bullet): ?>
                  <li><?= htmlspecialchars((string)$bullet, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
        <?php endif; ?>
      </article>

      <aside class="info-simple-help">
        <div>
          <h2><?= $ctaTitle ?></h2>
          <p><?= $ctaText ?></p>
        </div>
        <div class="info-simple-actions">
          <a href="/contact" class="btn btn-blue">Contact Us</a>
          <a href="https://wa.me/<?= $bizWa ?>" class="btn btn-outline" target="_blank" rel="noopener">WhatsApp Us</a>
        </div>
      </aside>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>
<?php return; endif; ?>
<main class="info-page">
  <section class="info-hero">
    <div class="info-container">
      <nav class="info-breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span>/</span><span><?= $breadcrumb ?></span></nav>
      <div class="info-hero-grid">
        <div>
          <div class="sec-ey"><?= $eyebrow ?></div>
          <h1><?= $heading ?></h1>
          <?php if ($intro !== ''): ?><p><?= $intro ?></p><?php endif; ?>
          <?php if (!empty($badges)): ?>
            <div class="info-badges">
              <?php foreach ($badges as $badge): ?><span><?= htmlspecialchars((string)$badge, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <aside class="info-visual-card" aria-label="Page visual">
          <div class="info-print-stack" aria-hidden="true">
            <?php foreach ($mockups as $idx => $mockup): ?>
              <img class="info-mockup info-mockup-<?= $idx + 1 ?>" src="<?= htmlspecialchars((string)$mockup, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>" decoding="async">
            <?php endforeach; ?>
          </div>
          <div class="info-visual-caption">
            <strong><?= $visualTitle ?></strong>
            <p><?= $visualText ?></p>
          </div>
          <div class="info-actions">
            <a href="/categories" class="btn btn-blue">Explore Products</a>
            <a href="https://wa.me/<?= $bizWa ?>" class="btn btn-outline" target="_blank" rel="noopener">WhatsApp Us</a>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <?php if (!empty($highlights)): ?>
  <section class="info-section info-section-wide">
    <div class="info-container info-card-grid">
      <?php foreach ($highlights as $item): ?>
        <article class="info-feature-card">
          <div class="info-feature-icon"><?= htmlspecialchars((string)($item['icon'] ?? '•'), ENT_QUOTES, 'UTF-8') ?></div>
          <h2><?= htmlspecialchars((string)($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
          <p><?= htmlspecialchars((string)($item['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($sections)): ?>
  <section class="info-section info-section-soft info-section-wide">
    <div class="info-container info-content-layout">
      <div class="info-content-aside">
        <div class="sec-ey">Page guide</div>
        <h2><?= $title ?></h2>
        <p><?= $intro ?></p>
      </div>
      <div class="info-content-wrap">
      <?php foreach ($sections as $section): ?>
        <article class="info-content-card">
          <h2><?= htmlspecialchars((string)($section['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
          <?php foreach ((array)($section['body'] ?? []) as $paragraph): ?>
            <p><?= htmlspecialchars((string)$paragraph, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endforeach; ?>
          <?php if (!empty($section['bullets']) && is_array($section['bullets'])): ?>
            <ul class="info-content-list">
              <?php foreach ($section['bullets'] as $bullet): ?>
                <li><?= htmlspecialchars((string)$bullet, ENT_QUOTES, 'UTF-8') ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($steps)): ?>
  <section class="info-section">
    <div class="info-container">
      <div class="info-section-head">
        <div class="sec-ey">How it works</div>
        <h2>Simple process, clear communication</h2>
      </div>
      <div class="info-steps">
        <?php foreach ($steps as $idx => $step): ?>
          <article class="info-step">
            <span><?= str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <h3><?= htmlspecialchars((string)($step['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars((string)($step['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="info-section info-cta-section">
    <div class="info-container">
      <div class="info-cta-card">
        <div>
          <h2><?= $ctaTitle ?></h2>
          <p><?= $ctaText ?></p>
        </div>
        <div class="info-actions">
          <a href="/contact" class="btn btn-blue">Contact Us</a>
          <a href="/categories" class="btn btn-outline">View Products</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

<?php
$pageTitle = 'Contact RCS Graphic — Print Order Help & Bulk Quotes';
$pageDesc = 'Contact RCS Graphic for printing support, bulk quotes, design guidance, order help, WhatsApp support and delivery questions.';

$settingsMap = is_array($settingsMap ?? null) ? $settingsMap : [];
$bizName = htmlspecialchars($settingsMap['biz_name'] ?? 'RCS Print', ENT_QUOTES, 'UTF-8');
$bizPhoneRaw = (string)($settingsMap['biz_phone'] ?? '+91 98765 43210');
$bizPhone = htmlspecialchars($bizPhoneRaw, ENT_QUOTES, 'UTF-8');
$bizPhoneHref = htmlspecialchars(preg_replace('/\D+/', '', $bizPhoneRaw), ENT_QUOTES, 'UTF-8');
$bizWa = htmlspecialchars($settingsMap['biz_whatsapp'] ?? '919876543210', ENT_QUOTES, 'UTF-8');
$bizEmail = htmlspecialchars($settingsMap['biz_email'] ?? 'info@rcsprint.in', ENT_QUOTES, 'UTF-8');
$bizAddrRaw = trim((string)($settingsMap['biz_address'] ?? '150ft Ring Road, Rajkot - 360005, Gujarat, India'));
$bizAddr = htmlspecialchars($bizAddrRaw, ENT_QUOTES, 'UTF-8');
$mapQuery = trim(($settingsMap['biz_name'] ?? 'RCS Graphic') . ' ' . ($bizAddrRaw ?: 'Rajkot Gujarat'));
$mapsSearchUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapQuery);
$mapsEmbedUrl = 'https://maps.google.com/maps?q=' . rawurlencode($mapQuery) . '&output=embed';
$waText = rawurlencode('Hello RCS Print, I need help with a printing requirement.');
$contactFaqs = [];
try { $contactFaqs = \Faq\FaqManager::listByPage('contact'); } catch (\Throwable) { $contactFaqs = []; }
if (!$contactFaqs) {
    $contactFaqs = [
        ['question' => 'What is your minimum order quantity?', 'answer' => 'Minimum quantity depends on the product, material and print process. Share your requirement and our team will guide you.'],
        ['question' => 'How long does delivery take?', 'answer' => 'Delivery time depends on artwork approval, product type, quantity, finishing and location.'],
        ['question' => 'Do you offer design support?', 'answer' => 'Yes, we can help with design guidance and artwork preparation for many print products.'],
        ['question' => 'Can I get a sample before placing a bulk order?', 'answer' => 'For selected products and bulk requirements, sample or proof options can be discussed with our team.'],
    ];
}
$pageSchema = [[
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn($faq) => [
        '@type' => 'Question',
        'name' => (string)($faq['question'] ?? ''),
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string)($faq['answer'] ?? ''))],
    ], $contactFaqs),
]];
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
?>
<main class="contact-showcase-page">
  <?php
  $pageHero = [
    'key' => 'contact',
    'title' => 'Contact Us',
    'subtitle' => 'Have a question, need a quote, or want to discuss your printing needs? Our team is here to help.',
    'eyebrow' => 'We’d Love to Hear From You',
    'breadcrumbs' => [
      ['label' => 'Home', 'url' => '/'],
      ['label' => 'Contact', 'url' => null],
    ],
    'fallback_image' => '/assets/images/sample-products/stationery/stationery-1.svg',
  ];
  include INCLUDE_PATH . '/partials/page-hero.php';
  ?>

  <section class="contact-main-section">
    <div class="contact-showcase-container contact-main-grid">
      <form class="contact-message-card" id="contactQuickForm">
        <h2>Send Us a Message</h2>
        <div class="contact-form-grid">
          <label>Your Name <b>*</b><input type="text" name="name" placeholder="Enter your full name" required></label>
          <label>Email Address <b>*</b><input type="email" name="email" placeholder="Enter your email" required></label>
          <label>Phone Number<input type="tel" name="phone" placeholder="Enter your phone number"></label>
          <label>Subject <b>*</b><input type="text" name="subject" placeholder="How can we help you?" required></label>
          <label class="contact-full-field">Your Message <b>*</b><textarea name="message" rows="5" placeholder="Type your message here..." required></textarea></label>
          <input type="text" name="website_url" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0" aria-hidden="true">
        </div>
        <button class="contact-send-btn" type="submit"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send Message</button>
        <div id="contactFormMsg" class="contact-form-message" role="status" aria-live="polite"></div>
      </form>

      <aside class="contact-touch-card">
        <h2>Get in Touch</h2>
        <p>Choose the best way to reach us.</p>
        <div class="contact-touch-list">
          <a href="tel:<?= $bizPhoneHref ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i><span><strong>Call Us</strong><b><?= $bizPhone ?></b><small>Mon - Sat: 10:00 AM - 7:00 PM</small></span></a>
          <a href="https://wa.me/<?= $bizWa ?>?text=<?= $waText ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span><strong>WhatsApp Us</strong><b><?= $bizPhone ?></b><small>We reply within minutes</small></span></a>
          <a href="mailto:<?= $bizEmail ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i><span><strong>Email Us</strong><b><?= $bizEmail ?></b><small>We reply within 24 hours</small></span></a>
          <a href="#contact-location"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><strong>Visit Our Office</strong><b><?= $bizName ?></b><small><?= $bizAddr ?></small></span></a>
        </div>
      </aside>
    </div>
  </section>

  <section class="contact-benefits-section">
    <div class="contact-showcase-container contact-benefits-bar">
      <div><i class="fa-regular fa-clock" aria-hidden="true"></i><span><strong>Fast Response</strong><small>We respond within 24 hours</small></span></div>
      <div><i class="fa-solid fa-headset" aria-hidden="true"></i><span><strong>Expert Support</strong><small>Professional guidance for your printing needs</small></span></div>
      <div><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span><strong>Secure &amp; Reliable</strong><small>Your information is safe with us</small></span></div>
      <div><i class="fa-regular fa-star" aria-hidden="true"></i><span><strong>Customer Focused</strong><small>Your satisfaction is our priority</small></span></div>
    </div>
  </section>

  <section class="contact-location-section" id="contact-location">
    <div class="contact-showcase-container">
      <div class="contact-section-title">
        <h2>Our Location</h2>
        <a href="<?= htmlspecialchars($mapsSearchUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Get Directions</a>
      </div>
      <div class="contact-map-card">
        <iframe class="contact-map-embed"
                src="<?= htmlspecialchars($mapsEmbedUrl, ENT_QUOTES, 'UTF-8') ?>"
                title="<?= $bizName ?> location map"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen></iframe>
        <div class="contact-map-info">
          <h3><?= $bizName ?></h3>
          <p><?= $bizAddr ?></p>
          <ul>
            <li><i class="fa-solid fa-phone" aria-hidden="true"></i> Phone: <?= $bizPhone ?></li>
            <li><i class="fa-solid fa-envelope" aria-hidden="true"></i> Email: <?= $bizEmail ?></li>
            <li><i class="fa-solid fa-clock" aria-hidden="true"></i> Mon - Sat: 10:00 AM - 7:00 PM</li>
          </ul>
          <a href="<?= htmlspecialchars($mapsSearchUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">View on Google Maps</a>
        </div>
      </div>
    </div>
  </section>

  <section class="contact-faq-section">
    <div class="contact-showcase-container contact-faq-grid">
      <div>
        <div class="contact-section-title contact-section-title-compact">
          <h2>Frequently Asked Questions</h2>
          <a href="/contact">View All FAQs</a>
        </div>
        <div class="contact-showcase-faqs">
          <?php foreach ($contactFaqs as $idx => $faq): ?>
            <details <?= $idx === 0 ? 'open' : '' ?>><summary><?= htmlspecialchars((string)($faq['question'] ?? ''), ENT_QUOTES, 'UTF-8') ?></summary><p><?= nl2br(htmlspecialchars((string)($faq['answer'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p></details>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
</main>
<script>
(function(){
  const form = document.getElementById('contactQuickForm');
  if (!form) return;
  form.addEventListener('submit', async function(event) {
    event.preventDefault();
    const data = new FormData(form);
    const msg = document.getElementById('contactFormMsg');
    const btn = form.querySelector('button[type="submit"]');
    const payload = Object.fromEntries(data.entries());
    if (msg) { msg.textContent = 'Sending your enquiry...'; msg.className = 'contact-form-message'; }
    if (btn) btn.disabled = true;
    try {
      const res = await fetch('/api/contact-leads', {
        method: 'POST',
        headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN':'<?= htmlspecialchars($csrf ?? '') ?>'},
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.msg || 'Could not submit enquiry.');
      if (msg) { msg.textContent = json.msg || 'Thank you! Your enquiry has been saved. Our team will contact you soon.'; msg.classList.add('ok'); }
      form.reset();
    } catch (err) {
      if (msg) { msg.textContent = err.message || 'Could not submit enquiry.'; msg.classList.add('bad'); }
    } finally {
      if (btn) btn.disabled = false;
    }
  });
})();
</script>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

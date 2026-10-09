<?php
$quickHelpClass = trim((string)($quickHelpExtraClass ?? ''));
$quickHelpPhoneRaw = trim((string)Database::setting('quick_help_phone', Database::setting('biz_phone', '+91 98765 43210')));
$quickHelpWhatsappRaw = preg_replace('/\D+/', '', (string)Database::setting('quick_help_whatsapp', Database::setting('biz_whatsapp', $quickHelpPhoneRaw)));
$quickHelpBrochure = trim((string)Database::setting('quick_help_brochure', ''));
if ($quickHelpBrochure !== '' && !str_starts_with($quickHelpBrochure, '/uploads/brochures/')) $quickHelpBrochure = '';
$quickHelpBrochureHref = $quickHelpBrochure !== '' ? $quickHelpBrochure : '/categories';
?>
<section class="quick-help-section<?= $quickHelpClass !== '' ? ' '.htmlspecialchars($quickHelpClass,ENT_QUOTES) : '' ?>" aria-label="Quick help and bulk order actions" data-reveal>
  <div class="quick-help-container"><div class="quick-help-bar">
    <a class="quick-help-item quick-help-call" href="tel:<?= htmlspecialchars(preg_replace('/\D+/', '', $quickHelpPhoneRaw),ENT_QUOTES) ?>"><span class="quick-help-icon"><i class="fa-solid fa-phone-volume" aria-hidden="true"></i></span><span class="quick-help-copy"><span>Need Help? Call Us</span><strong><?= htmlspecialchars($quickHelpPhoneRaw) ?></strong></span></a>
    <button class="quick-help-item quick-help-whatsapp" type="button" onclick="window.open('https://wa.me/<?= htmlspecialchars($quickHelpWhatsappRaw,ENT_QUOTES) ?>','_blank')"><span class="quick-help-icon"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span><span class="quick-help-copy"><strong>Chat with us on WhatsApp</strong><span>We are here to help!</span></span></button>
    <a class="quick-help-item quick-help-download" href="<?= htmlspecialchars($quickHelpBrochureHref,ENT_QUOTES) ?>"<?= $quickHelpBrochure !== '' ? ' download' : '' ?> aria-label="Download our brochure for all products"><span class="quick-help-icon"><i class="fa-solid fa-download" aria-hidden="true"></i></span><span class="quick-help-copy"><strong>Download Our Brochure</strong><span>For All Products</span></span></a>
  </div></div>
</section>
<?php unset($quickHelpExtraClass); ?>

<?php
$pageTitle = 'Settings — RCS Admin';
$currentAdmPage = 'settings';
include __DIR__ . '/layout.php';
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
$settingsError = trim((string)($_GET['err'] ?? ''));
$numberingMessage = trim((string)($_GET['message'] ?? ''));
$numberingOk = ($_GET['numbering'] ?? '') === 'reset';
$defaultDocumentYear = \Documents\DocumentNumberManager::defaultYearLabel();
try {
  $orderNumbering = \Documents\DocumentNumberManager::preview('order');
  $quoteNumbering = \Documents\DocumentNumberManager::preview('quote');
} catch (\Throwable $e) {
  $orderNumbering = ['preview' => 'Unavailable'];
  $quoteNumbering = ['preview' => 'Unavailable'];
}
?>
<?php if ($saved): ?>
<div style="background:var(--green-bg);border:1px solid var(--green-mid);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--green);font-weight:600">
  ✅ Settings saved successfully!
</div>
<?php endif; ?>
<?php if ($settingsError !== ''): ?>
<div style="background:#fff7ed;border:1px solid #fdba74;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#9a3412;font-weight:600"><?= htmlspecialchars($settingsError) ?></div>
<?php endif; ?>
<?php if ($numberingMessage !== ''): ?>
<div style="background:<?= $numberingOk ? 'var(--green-bg)' : '#fff7ed' ?>;border:1px solid <?= $numberingOk ? 'var(--green-mid)' : '#fdba74' ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:14px;color:<?= $numberingOk ? 'var(--green)' : '#9a3412' ?>;font-weight:600">
  <?= htmlspecialchars($numberingMessage) ?>
</div>
<?php endif; ?>
<div class="adm-pt">Settings</div>
<form method="POST" action="/admin/settings/save">
  <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

  <div class="fsec">
    <div class="fsec-t">🏢 Business Info</div>
    <div class="f2">
      <div class="fg"><label>Business Name</label><input name="biz_name" class="fi" value="<?= htmlspecialchars($settingsMap['biz_name'] ?? 'RCS Graphic') ?>"></div>
      <div class="fg"><label>Tagline</label><input name="biz_tagline" class="fi" value="<?= htmlspecialchars($settingsMap['biz_tagline'] ?? '') ?>"></div>
    </div>
    <div class="f2">
      <div class="fg"><label>Phone</label><input name="biz_phone" class="fi" value="<?= htmlspecialchars($settingsMap['biz_phone'] ?? '') ?>"></div>
      <div class="fg"><label>WhatsApp (country code, no +)</label><input name="biz_whatsapp" class="fi" value="<?= htmlspecialchars($settingsMap['biz_whatsapp'] ?? '') ?>" placeholder="919876543210"></div>
    </div>
    <div class="f2">
      <div class="fg"><label>Email</label><input name="biz_email" class="fi" value="<?= htmlspecialchars($settingsMap['biz_email'] ?? '') ?>"></div>
      <div class="fg"><label>GSTIN</label><input name="biz_gst_no" class="fi" value="<?= htmlspecialchars($settingsMap['biz_gst_no'] ?? '') ?>" placeholder="24XXXXX0000X1ZX"></div>
    </div>
    <div class="fg"><label>Address</label><input name="biz_address" class="fi" value="<?= htmlspecialchars($settingsMap['biz_address'] ?? '') ?>"></div>
    <div class="f2">
      <div class="fg"><label>GST Rate (%)</label><input type="number" name="gst_percent" class="fi" value="<?= htmlspecialchars($settingsMap['gst_percent'] ?? '18') ?>" style="max-width:120px"></div>
      <div class="fg"><label>Default Design Fee (₹)</label><input type="number" name="design_fee" class="fi" value="<?= htmlspecialchars($settingsMap['design_fee'] ?? '0') ?>" style="max-width:160px" min="0"></div>
    </div>
  </div>

  <div class="fsec">
    <div class="fsec-t">🚚 Shipping Settings</div>
    <div class="f2">
      <div class="fg">
        <label>Shipping Mode</label>
        <select name="shipping_mode" class="fi fi-sel">
          <?php $sm = $settingsMap['shipping_mode'] ?? 'flat'; ?>
          <option value="free" <?= $sm === 'free' ? 'selected' : '' ?>>Free Shipping</option>
          <option value="flat" <?= $sm === 'flat' ? 'selected' : '' ?>>Flat Shipping</option>
          <option value="threshold" <?= $sm === 'threshold' ? 'selected' : '' ?>>Free Above Order Amount</option>
        </select>
      </div>
      <div class="fg"><label>Flat Shipping Fee (₹)</label><input type="number" name="shipping_flat_fee" class="fi" value="<?= htmlspecialchars($settingsMap['shipping_flat_fee'] ?? '0') ?>" min="0"></div>
    </div>
    <div class="f2">
      <div class="fg"><label>Free Shipping Above (₹)</label><input type="number" name="shipping_free_above" class="fi" value="<?= htmlspecialchars($settingsMap['shipping_free_above'] ?? '0') ?>" min="0"></div>
      <div class="fg"><label>Shipping Label / Note</label><input name="shipping_note" class="fi" value="<?= htmlspecialchars($settingsMap['shipping_note'] ?? 'Delivery in 2-4 days') ?>"></div>
    </div>
  </div>

  <div class="fsec">
    <div class="fsec-t">💳 Razorpay Payment Gateway</div>
    <div class="fg"><label>Razorpay Key ID</label><input name="razorpay_key_id" class="fi" value="<?= htmlspecialchars($settingsMap['razorpay_key_id'] ?? '') ?>" placeholder="rzp_live_XXXXXXXXXX"></div>
    <div class="fg"><label>Razorpay Key Secret</label><input type="password" name="razorpay_key_secret" class="fi" value="<?= htmlspecialchars($settingsMap['razorpay_key_secret'] ?? '') ?>" placeholder="Your secret key"></div>
  </div>

  <div class="fsec">
    <div class="fsec-t">📁 File Upload Settings</div>
    <div class="f2">
      <div class="fg"><label>Max Upload Size (MB)</label><input type="number" name="upload_max_mb" class="fi" value="<?= htmlspecialchars($settingsMap['upload_max_mb'] ?? '50') ?>"></div>
      <div class="fg"><label>Allowed Extensions</label><input name="upload_allowed_ext" class="fi" value="<?= htmlspecialchars($settingsMap['upload_allowed_ext'] ?? 'pdf,ai,eps,png,jpg,jpeg,psd,cdr') ?>"></div>
    </div>
  </div>

  <div class="fsec">
    <div class="fsec-t">☎ Quick Help Section</div>
    <div class="f2">
      <div class="fg"><label>Need Help Phone</label><input name="quick_help_phone" class="fi" value="<?= htmlspecialchars($settingsMap['quick_help_phone'] ?? $settingsMap['biz_phone'] ?? '') ?>" placeholder="+91 8980000024"></div>
      <div class="fg"><label>Quick Help WhatsApp (country code, no +)</label><input name="quick_help_whatsapp" class="fi" value="<?= htmlspecialchars($settingsMap['quick_help_whatsapp'] ?? $settingsMap['biz_whatsapp'] ?? '') ?>" placeholder="918980000024"></div>
    </div>
    <div class="fg"><label>Brochure PDF (max 15MB)</label><input type="file" id="quickHelpBrochure" class="fi" accept="application/pdf,.pdf"><small id="quickHelpBrochureStatus"><?= !empty($settingsMap['quick_help_brochure']) ? 'Brochure is available for download.' : 'Select a PDF to upload it instantly.' ?></small></div>
  </div>

  <div class="fsec document-numbering-settings">
    <div class="fsec-t">🔢 Order &amp; Quote Numbering</div>
    <p class="document-numbering-help">Each financial year has its own counter. Changing 26-27 to 27-28 preserves all previous records and safely starts the new year from 001.</p>
    <div class="document-numbering-grid">
      <section class="document-numbering-column"><h3>Order ID</h3><p>Example: <strong>26-27RCS001</strong></p>
        <div class="fg"><label>Order Financial Year</label><input name="order_id_year_label" class="fi" value="<?= htmlspecialchars($settingsMap['order_id_year_label'] ?? $settingsMap['document_year_label'] ?? $defaultDocumentYear) ?>" pattern="\d{2}-\d{2}" maxlength="5" placeholder="26-27" required></div>
        <div class="fg"><label>Order Prefix</label><input name="order_id_prefix" class="fi" value="<?= htmlspecialchars($settingsMap['order_id_prefix'] ?? 'RCS') ?>" pattern="[A-Za-z0-9]{1,12}" maxlength="12" required></div>
        <div class="fg"><label>Order Number Digits</label><input type="number" name="order_id_padding" class="fi" min="2" max="8" value="<?= htmlspecialchars($settingsMap['order_id_padding'] ?? '3') ?>" required></div>
        <div class="fg"><label>Next Order ID</label><div class="fi document-numbering-preview"><?= htmlspecialchars((string)$orderNumbering['preview']) ?></div><button type="submit" form="reset-order-sequence" class="btn btn-outline btn-sm" onclick="return confirm('Reset the order counter to 1? This is allowed only when this year/prefix has no issued IDs.')">Reset Order Counter</button></div>
      </section>
      <section class="document-numbering-column"><h3>Quote ID</h3><p>Example: <strong>26-27CQ001</strong></p>
        <div class="fg"><label>Quote Financial Year</label><input name="quote_id_year_label" class="fi" value="<?= htmlspecialchars($settingsMap['quote_id_year_label'] ?? $settingsMap['document_year_label'] ?? $defaultDocumentYear) ?>" pattern="\d{2}-\d{2}" maxlength="5" placeholder="26-27" required></div>
        <div class="fg"><label>Quote Prefix</label><input name="quote_id_prefix" class="fi" value="<?= htmlspecialchars($settingsMap['quote_id_prefix'] ?? 'CQ') ?>" pattern="[A-Za-z0-9]{1,12}" maxlength="12" required></div>
        <div class="fg"><label>Quote Number Digits</label><input type="number" name="quote_id_padding" class="fi" min="2" max="8" value="<?= htmlspecialchars($settingsMap['quote_id_padding'] ?? '3') ?>" required></div>
        <div class="fg"><label>Next Quote ID</label><div class="fi document-numbering-preview"><?= htmlspecialchars((string)$quoteNumbering['preview']) ?></div><button type="submit" form="reset-quote-sequence" class="btn btn-outline btn-sm" onclick="return confirm('Reset the quote counter to 1? This is allowed only when this year/prefix has no issued IDs.')">Reset Quote Counter</button></div>
      </section>
    </div>
  </div>

  <div class="fsec">
    <div class="fsec-t">🔐 Admin Security</div>
    <div class="fg"><label>New Admin Password (leave blank to keep current)</label><input type="password" name="new_admin_password" class="fi" placeholder="Enter new password (min 6 chars)"></div>
  </div>

  <button type="submit" class="btn btn-blue" style="padding:13px 28px;border-radius:10px">Save All Settings ✓</button>
</form>
<form id="reset-order-sequence" method="POST" action="/admin/settings/document-numbering/reset"><input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>"><input type="hidden" name="document_type" value="order"></form>
<form id="reset-quote-sequence" method="POST" action="/admin/settings/document-numbering/reset"><input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>"><input type="hidden" name="document_type" value="quote"></form>
<script>
document.getElementById('quickHelpBrochure')?.addEventListener('change',async event=>{const input=event.currentTarget,file=input.files?.[0],status=document.getElementById('quickHelpBrochureStatus');if(!file)return;if(file.type!=='application/pdf'&&!file.name.toLowerCase().endsWith('.pdf')){status.textContent='Choose a PDF file.';input.value='';return}if(file.size>15*1024*1024){status.textContent='Brochure must be under 15MB.';input.value='';return}input.disabled=true;status.textContent='Uploading brochure…';const body=new FormData();body.append('brochure',file);try{const response=await fetch('/admin/settings/brochure-upload',{method:'POST',headers:{'X-CSRF-TOKEN':'<?= htmlspecialchars($csrf??'',ENT_QUOTES) ?>'},body});const data=await response.json();status.textContent=data.ok?'Brochure uploaded successfully.':(data.msg||'Upload failed.');if(data.ok)input.value='';}catch(error){status.textContent='Upload failed. Please try again.';}finally{input.disabled=false;}});
</script>
    </div></div></div>
</body></html>

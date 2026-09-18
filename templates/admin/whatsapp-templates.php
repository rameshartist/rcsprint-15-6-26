<?php
$pageTitle = 'WhatsApp Templates — RCS Admin';
$currentAdmPage = 'whatsapp-templates';
include __DIR__ . '/layout.php';
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$templates = [];
try {
    $rows = Database::rows("SELECT template_key, title, description, body, is_active FROM whatsapp_message_templates ORDER BY FIELD(template_key,'order_confirmation','proof_ready','design_approved','customer_reorder','customer_upsell','customer_welcome','lead_followup'), template_key ASC");
    foreach ($rows as $row) $templates[(string)$row['template_key']] = $row;
} catch (Throwable) {
    $templates = [];
}
$defaults = [
    'order_confirmation' => ['title' => 'Send Order Confirmation', 'description' => 'Sent after a new order is received.', 'body' => "Hello {customer_name}, 👋

Thank you for your order. We have received Order #{order_id}.

Order value: {order_total}
Products:
{products}

Our team will review the details and start processing your order shortly.

You can login to your account to check order details, current status, design approval status and future updates here:
{account_order_url}

Thank you,
{business_name}
For any query, call or WhatsApp: {business_phone}"],
    'proof_ready' => ['title' => 'Send Proof Ready Message', 'description' => 'Sent when admin uploads a corrected/proof file for customer review.', 'body' => "Hello {customer_name}, 👋

Your corrected design proof for Order #{order_id} is ready for review.

Product:
{products}

Please login to your account and check My Orders to view the proof. You can approve the design or request a revision here:
{account_order_url}

Thank you,
{business_name}
For any query, call or WhatsApp: {business_phone}"],
    'design_approved' => ['title' => 'Send Design Approved Message', 'description' => 'Sent after design approval to explain printing/production next steps.', 'body' => "Hello {customer_name}, 👋

Your design for Order #{order_id} has been approved.

Product:
{products}

Your order will now move to the next step: Printing / Production.

Please note: once the design is approved, design changes or order cancellation may not be possible.

You can login to your account to check order details and further updates here:
{account_order_url}

Thank you,
{business_name}
For any query, call or WhatsApp: {business_phone}"],
    'customer_reorder' => ['title' => 'Customer Reorder Reminder', 'description' => 'Sent from Customers when a repeat order may be useful.', 'body' => "Hello {customer_name}, 👋

If you would like to reorder your previous print items, we can process it quickly using your saved order details.

Last product: {last_product}
Total orders: {order_count}

Reply here and our team will help you with the reorder.

Thank you,
{business_name}"],
    'customer_upsell' => ['title' => 'Customer Upsell Message', 'description' => 'Sent from Customers to suggest related products.', 'body' => "Hello {customer_name}, 👋

Based on your previous print requirement, this may be useful for you:
{suggestion}

Last product: {last_product}

Reply here and we will share details and pricing.

Thank you,
{business_name}"],
    'customer_welcome' => ['title' => 'Customer Welcome Offer', 'description' => 'Sent to customers with no orders yet.', 'body' => "Hello {customer_name}, 👋

Welcome to {business_name}. Please share your print requirement and our team will guide you with suitable options, pricing and artwork support.

Thank you,
{business_name}"],
    'lead_followup' => ['title' => 'Lead Follow-up Message', 'description' => 'Sent after a contact form enquiry is received.', 'body' => "Hello {lead_name}, 👋

Thank you for contacting {business_name}. We received your enquiry:
{lead_subject}

Please share any artwork, size, quantity or reference details here so our team can guide you quickly.

Thank you,
{business_name}"],
    'custom_quote_sent' => ['title' => 'Send Custom Quote', 'description' => 'Sent after the quoted amount and quote note are saved.', 'body' => "Hello {customer_name}, 👋\n\nThank you for your custom quotation request {quote_id}.\n\nProduct: {product_name}\nSize: {size_dimension}\nMaterial: {material_type}\nQuantity: {quantity}\nQuoted Amount: {quoted_amount}\n\n{quote_note}\n\nPlease reply APPROVE to confirm this quote.\n\nThank you,\n{business_name}"],
    'custom_quote_payment' => ['title' => 'Send Custom Order Payment Link', 'description' => 'Sent after approval, account linking and payment-link generation.', 'body' => "Hello {customer_name}, 👋\n\nYour custom order {quote_id} is ready for payment.\n\nAmount: {quoted_amount}\n\nLogin here: {login_url}\nLogin with: {login_identifier}\nPassword: {login_password}\n\nYour custom order is already added to your cart. Open your secure payment link to continue: {payment_link}\n\nThank you,\n{business_name}"],
];
$labels = [
    'order_confirmation' => ['badge' => 'Order', 'heading' => 'New Order Confirmation'],
    'proof_ready' => ['badge' => 'Proof', 'heading' => 'Proof File Uploaded'],
    'design_approved' => ['badge' => 'Approval', 'heading' => 'Design Approved Update'],
    'customer_reorder' => ['badge' => 'Customer', 'heading' => 'Reorder Reminder'],
    'customer_upsell' => ['badge' => 'Customer', 'heading' => 'Upsell Message'],
    'customer_welcome' => ['badge' => 'Customer', 'heading' => 'Welcome / First Order'],
    'lead_followup' => ['badge' => 'Lead', 'heading' => 'Lead Follow-up'],
    'custom_quote_sent' => ['badge' => 'Custom Quote', 'heading' => 'Quote Sent to Customer'],
    'custom_quote_payment' => ['badge' => 'Custom Payment', 'heading' => 'Account & Payment Link'],
];
$placeholders = [
    'customer_name' => 'Customer name',
    'customer_phone' => 'Customer phone',
    'customer_email' => 'Customer email',
    'order_id' => 'Order number',
    'order_total' => 'Order total',
    'order_status' => 'Order status',
    'products' => 'Product list with quantities',
    'product_name' => 'Single product name',
    'quantity' => 'Single product quantity',
    'design_status' => 'Design approval status',
    'account_order_url' => 'Customer account order link',
    'proof_url' => 'Proof/review link',
    'business_name' => 'Business name',
    'business_phone' => 'Business phone',
    'business_whatsapp' => 'Business WhatsApp',
    'customer_code' => 'Customer unique ID',
    'order_count' => 'Customer order count',
    'last_product' => 'Customer last product',
    'suggestion' => 'Suggested product/message',
    'lead_name' => 'Lead name',
    'lead_phone' => 'Lead phone',
    'lead_email' => 'Lead email',
    'lead_subject' => 'Lead subject',
    'lead_message' => 'Lead message',
    'quote_id' => 'Custom quote ID',
    'size_dimension' => 'Requested size / dimension',
    'material_type' => 'Requested material',
    'quoted_amount' => 'Final quoted amount',
    'quote_note' => 'Admin quote note',
    'login_url' => 'Customer login URL',
    'login_identifier' => 'Customer login email / mobile',
    'login_password' => 'Customer login password',
    'payment_link' => 'Custom order payment link',
    'custom_cart_url' => 'Custom order cart / checkout link',
];
?>
<div class="wa-template-page">
  <section class="wa-template-hero">
    <div>
      <span>Customer messaging</span>
      <h1>WhatsApp Templates</h1>
      <p>Prepare reusable WhatsApp messages for orders, customer CRM follow-ups and lead enquiries. Use placeholders to auto-fill customer, order and lead details.</p>
    </div>
    <strong>9 templates</strong>
  </section>

  <section class="wa-placeholder-panel">
    <div>
      <strong>Dynamic placeholders</strong>
      <p>Click any placeholder to copy it, or use the dropdown inside each template card to insert it into the message.</p>
    </div>
    <div class="wa-placeholder-list">
      <?php foreach ($placeholders as $key => $label): ?>
        <button type="button" data-copy-placeholder="{<?= $h($key) ?>}" title="<?= $h($label) ?>">{<?= $h($key) ?>}</button>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="wa-template-grid">
    <?php foreach ($defaults as $key => $default):
      $row = $templates[$key] ?? $default;
      $label = $labels[$key];
    ?>
      <article class="wa-template-card" data-template-key="<?= $h($key) ?>">
        <div class="wa-template-card-head">
          <span><?= $h($label['badge']) ?></span>
          <div>
            <h2><?= $h($label['heading']) ?></h2>
            <p><?= $h($row['description'] ?? $default['description']) ?></p>
          </div>
        </div>
        <label>Button / template title</label>
        <input class="wa-template-title" value="<?= $h($row['title'] ?? $default['title']) ?>">
        <label>Insert dynamic detail</label>
        <select class="wa-template-placeholder">
          <option value="">Select placeholder…</option>
          <?php foreach ($placeholders as $ph => $phLabel): ?><option value="{<?= $h($ph) ?>}"><?= $h($phLabel) ?> — {<?= $h($ph) ?>}</option><?php endforeach; ?>
        </select>
        <label>WhatsApp message body</label>
        <textarea class="wa-template-body" rows="13"><?= $h($row['body'] ?? $default['body']) ?></textarea>
        <div class="wa-template-actions">
          <button type="button" class="btn btn-blue wa-save-template">Save Template</button>
          <button type="button" class="btn btn-light wa-preview-template">Preview</button>
          <button type="button" class="btn btn-light wa-reset-template">Reset Default</button>
        </div>
        <pre class="wa-template-preview" hidden></pre>
      </article>
    <?php endforeach; ?>
  </section>
</div>
<script>
const WA_DEFAULTS = <?= json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const WA_SAMPLE = {
  customer_name: 'Rahul Customer', customer_phone: '919876543210', customer_email: 'customer@example.com',
  order_id: 'RCS-1024', order_total: '₹2,450', order_status: 'Received',
  products: '• Business Cards — 500 qty\n• Brochure — 100 qty', product_name: 'Business Cards', quantity: '500',
  design_status: 'Approved', account_order_url: `${location.origin}/profile#orders-RCS-1024`, proof_url: `${location.origin}/profile#orders-RCS-1024`,
  business_name: 'RCS Print', business_phone: '+91 8980000024', business_whatsapp: '+91 8980000024',
  customer_code: 'PK0001', order_count: '3', last_product: 'Business Cards', suggestion: 'Letterhead + Envelope',
  lead_name: 'Prakash Karena', lead_phone: '919876543210', lead_email: 'lead@example.com', lead_subject: 'Need brochure printing', lead_message: 'Please share quote for 500 brochures.', quote_id: 'CQ-0001', size_dimension: '500 × 400 mm', material_type: 'Standard', quoted_amount: '₹2,500.00', quote_note: 'Final price includes printing and finishing.', login_url: `${location.origin}/login?next=/cart`, login_identifier: 'customer@example.com', login_password: '9876543210', payment_link: `${location.origin}/custom-checkout/example`
};
function fillWaTemplate(body, data = WA_SAMPLE) {
  return String(body || '').replace(/\{([a-z0-9_]+)\}/gi, (_, key) => Object.prototype.hasOwnProperty.call(data, key) ? data[key] : `{${key}}`);
}
function toastWa(msg, type = 'success') {
  if (typeof toast === 'function') toast(msg, type); else alert(msg);
}
document.querySelectorAll('[data-copy-placeholder]').forEach(btn => btn.addEventListener('click', async () => {
  const text = btn.dataset.copyPlaceholder || '';
  try { await navigator.clipboard.writeText(text); toastWa(`${text} copied`); } catch { toastWa(text); }
}));
document.querySelectorAll('.wa-template-card').forEach(card => {
  const key = card.dataset.templateKey;
  const title = card.querySelector('.wa-template-title');
  const body = card.querySelector('.wa-template-body');
  const select = card.querySelector('.wa-template-placeholder');
  const preview = card.querySelector('.wa-template-preview');
  select.addEventListener('change', () => {
    if (!select.value) return;
    const start = body.selectionStart ?? body.value.length;
    const end = body.selectionEnd ?? body.value.length;
    body.value = body.value.slice(0, start) + select.value + body.value.slice(end);
    body.focus();
    body.selectionStart = body.selectionEnd = start + select.value.length;
    select.value = '';
  });
  card.querySelector('.wa-preview-template').addEventListener('click', () => {
    preview.hidden = false;
    preview.textContent = fillWaTemplate(body.value);
  });
  card.querySelector('.wa-reset-template').addEventListener('click', () => {
    if (!confirm('Reset this template to default text?')) return;
    title.value = WA_DEFAULTS[key]?.title || title.value;
    body.value = WA_DEFAULTS[key]?.body || body.value;
    preview.hidden = true;
  });
  card.querySelector('.wa-save-template').addEventListener('click', async () => {
    const btn = card.querySelector('.wa-save-template');
    btn.disabled = true;
    btn.textContent = 'Saving…';
    try {
      const res = await fetch('/admin/api/whatsapp-templates/' + encodeURIComponent(key), {
        method: 'PUT', headers: {'Content-Type': 'application/json'}, credentials: 'same-origin',
        body: JSON.stringify({title: title.value, body: body.value, is_active: 1})
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) throw new Error(data.msg || 'Could not save template');
      toastWa('WhatsApp template saved');
    } catch (err) {
      toastWa(err.message || 'Could not save template', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Save Template';
    }
  });
});
</script>
    </div></div></div>
</body></html>

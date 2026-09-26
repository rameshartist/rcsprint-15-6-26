<?php
$pageTitle = 'Your Cart — RCS Graphic';
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
$bizWa = Database::setting('biz_whatsapp', env('BIZ_WHATSAPP', ''));
$itemCount = count($cartItems ?? []);
$cartSubtotal = (float)($totals['subtotal'] ?? 0);
$cartDiscount = (float)($totals['discount'] ?? 0);
$cartGstPct = (float)($totals['gst_pct'] ?? 18);
$cartGstAmt = (float)($totals['gst_amt'] ?? 0);
$cartShipping = (float)($totals['shipping'] ?? 0);
$cartTotal = (float)($totals['total'] ?? 0);
$checkoutUrl = '/checkout';
?>
<div class="cartp-wrap">
  <div class="container cartp-page">
    <div class="cartp-head">
      <h1>Your Cart <span><?= (int)$itemCount ?> Items</span></h1>
      <p>Review your items and proceed to checkout.</p>
    </div>

    <?php if (empty($cartItems)): ?>
      <section class="cartp-empty">
        <div class="cartp-empty-icon">🛒</div>
        <h2>Your cart is empty</h2>
        <p>Browse our products and add your print items to continue.</p>
        <a href="/categories" class="btn btn-blue">Browse Products</a>
      </section>
    <?php else: ?>
    <div class="cartp-grid">
      <section class="cartp-main" aria-label="Shopping cart items">
        <div class="cartp-table-head">
          <span>Product</span><span>Price</span><span>Quantity</span><span>Total</span><span></span>
        </div>
        <?php foreach ($cartItems as $item):
          $itemId = (string)($item['id'] ?? '');
          $itemQty = (int)($item['quantity'] ?? 0);
          $lineTotal = (float)($item['total_price'] ?? 0);
          $breakdownRaw = $item['price_breakdown'] ?? '{}';
          $priceBreakdown = is_array($breakdownRaw) ? $breakdownRaw : (json_decode((string)$breakdownRaw, true) ?: []);
          $basePrice = (float)($priceBreakdown['base_price'] ?? $lineTotal);
          $designFee = (float)($priceBreakdown['design_fee'] ?? 0);
          $linePrice = $basePrice;
          $quality = trim((string)($item['quality_name'] ?? ($priceBreakdown['quality_name'] ?? '')));
          $designChoice = (string)($item['design_choice'] ?? ($priceBreakdown['design_choice'] ?? 'upload'));
          $isCustomQuote = (($item['item_type'] ?? 'product') === 'custom_quote');
          $isComboOffer = (($item['item_type'] ?? 'product') === 'combo_offer');
          try {
              if ($isCustomQuote) {
                  $qtyOptions = [1];
              } else {
                  $pricingData = \Cart\Pricing::productPricingData((int)($item['product_id'] ?? 0));
                  $qtyOptions = array_values(array_unique(array_map('intval', array_column($pricingData['tiers'] ?? [], 'quantity'))));
                  sort($qtyOptions);
              }
          } catch (\Throwable) {
              $qtyOptions = [];
          }
          if (!$qtyOptions) $qtyOptions = range(1000, 10000, 1000);
          if ($itemQty > 0 && !in_array($itemQty, $qtyOptions, true)) {
              $qtyOptions[] = $itemQty;
              sort($qtyOptions);
          }
        ?>
        <article class="cartp-row">
          <div class="cartp-prod" data-label="Product">
            <?php if ($isCustomQuote || $isComboOffer): ?><span class="cartp-img cartp-img-custom"><?php else: ?><a class="cartp-img" href="/product/<?= htmlspecialchars($item['slug'] ?? '') ?>"><?php endif; ?>
              <img src="<?= htmlspecialchars($item['product_image'] ?? '') ?>" alt="<?= htmlspecialchars($item['product_name'] ?? '') ?>" onerror="this.style.display='none'">
            <?php if ($isCustomQuote || $isComboOffer): ?></span><?php else: ?></a><?php endif; ?>
            <div class="cartp-prod-copy">
              <h3><?= htmlspecialchars($item['product_name'] ?? '') ?></h3>
              <?php if ($isCustomQuote): ?>
                <p><strong>Custom Quote<?= !empty($item['custom_quote_code']) ? ' #' . htmlspecialchars((string)$item['custom_quote_code']) : '' ?></strong></p>
                <small><?= !empty($item['custom_requested_quantity']) ? 'Requested Qty: ' . htmlspecialchars((string)$item['custom_requested_quantity']) . ' · ' : '' ?><?= !empty($item['custom_size_dimension']) ? 'Size: ' . htmlspecialchars((string)$item['custom_size_dimension']) . ' · ' : '' ?><?= !empty($item['custom_material_type']) ? 'Material: ' . htmlspecialchars((string)$item['custom_material_type']) : 'Custom print requirement' ?></small>
              <?php elseif ($isComboOffer): ?>
                <p><strong>Combo Offer</strong></p><small>Multiple printing products included</small>
              <?php else: ?>
                <p><?= number_format($itemQty) ?> pcs<?= $quality !== '' ? ', ' . htmlspecialchars($quality) : '' ?></p>
                <?php if ($designChoice === 'rcs'): ?>
                  <small>Design by RCS Graphic<?= $designFee > 0 ? ' (+₹' . number_format($designFee) . ')' : '' ?></small>
                <?php else: ?>
                  <small>Customer artwork upload (No design fee)</small>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="cartp-price" data-label="Price">
            <strong>₹<?= number_format($linePrice) ?></strong>
            <small>Base price</small>
          </div>
          <div class="cartp-qty" data-label="Quantity">
            <?php if ($isCustomQuote || $isComboOffer): ?>
              <span class="cartp-fixed-qty"><?= $isComboOffer ? 'Combo' : 'Custom' ?></span>
            <?php else: ?>
              <select class="cartp-qty-select" onchange="updateCartQty('<?= htmlspecialchars($itemId, ENT_QUOTES) ?>', this.value, this)" aria-label="Select quantity for <?= htmlspecialchars($item['product_name'] ?? '', ENT_QUOTES) ?>">
                <?php foreach ($qtyOptions as $qty): ?>
                  <option value="<?= (int)$qty ?>" <?= $qty === $itemQty ? 'selected' : '' ?>><?= number_format((int)$qty) ?> pcs</option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <div class="cartp-total" data-label="Total">
            <strong>₹<?= number_format($lineTotal) ?></strong>
            <?php if ($designFee > 0): ?><small>Includes ₹<?= number_format($designFee) ?> design fee</small><?php endif; ?>
          </div>
          <?php if ($isCustomQuote): ?>
            <span class="cartp-fixed-qty" title="Custom orders remain in cart until payment">Payment pending</span>
          <?php else: ?>
            <button class="cartp-del" onclick="removeCartItem('<?= htmlspecialchars($itemId, ENT_QUOTES) ?>')" aria-label="Remove <?= htmlspecialchars($item['product_name'] ?? 'item', ENT_QUOTES) ?>">
              <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
            </button>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>

        <div class="cartp-actions">
          <a href="/categories" class="cartp-continue"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Continue Shopping</a>
          <button class="cartp-clear" onclick="clearCartPage()"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Clear Cart</button>
        </div>
      </section>

      <aside class="cartp-side" aria-label="Order summary">
        <div class="cartp-card cartp-summary-card">
          <h2>Order Summary</h2>
          <div class="r"><span>Subtotal (<?= (int)$itemCount ?> Items)</span><strong id="cartSummarySubtotal">₹<?= number_format($cartSubtotal) ?></strong></div>
          <div class="r cartp-discount-row" id="cartSummaryDiscountRow"<?= $cartDiscount > 0 ? '' : ' hidden' ?>><span>Discount</span><strong id="cartSummaryDiscount">-₹<?= number_format($cartDiscount) ?></strong></div>
          <div class="r"><span>GST (<span id="cartSummaryGstPct"><?= htmlspecialchars((string)$cartGstPct, ENT_QUOTES, 'UTF-8') ?></span>%)</span><strong id="cartSummaryGst">₹<?= number_format($cartGstAmt) ?></strong></div>
          <div class="r"><span>Shipping</span><strong id="cartSummaryShipping" class="<?= $cartShipping > 0 ? '' : 'is-free' ?>"><?= $cartShipping > 0 ? '₹' . number_format($cartShipping) : 'Free' ?></strong></div>
          <div class="rt"><span>Total</span><strong id="cartSummaryTotal">₹<?= number_format($cartTotal) ?></strong></div>
          <a href="<?= htmlspecialchars($checkoutUrl, ENT_QUOTES) ?>" class="cartp-checkout">Proceed to Checkout <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
          <div class="cartp-secure"><i class="fa-solid fa-lock" aria-hidden="true"></i> Secure Checkout</div>
        </div>

        <div class="cartp-card cartp-promo-card">
          <div class="cartp-promo-head">
            <span><i class="fa-solid fa-ticket" aria-hidden="true"></i></span>
            <div><h3>Have a Promo Code?</h3><p>Enter code and get exciting discounts!</p></div>
          </div>
          <div class="coupon-row"><input id="promoInp" placeholder="Enter promo code"><button class="btn btn-outline btn-sm" onclick="applyPromoOnCartPage()">APPLY</button></div>
          <div id="promoMsg"></div>
        </div>

        <div class="cartp-why-card">
          <h3>Why Shop With <span>RCS PRINT?</span></h3>
          <div class="cartp-why-list">
            <div><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i><p><strong>Premium Quality Prints</strong><span>Top-notch materials and printing.</span></p></div>
            <div><i class="fa-solid fa-truck-fast" aria-hidden="true"></i><p><strong>Fast & Free Delivery</strong><span>On orders above ₹999 in Rajkot.</span></p></div>
            <div><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><p><strong>100% Secure Checkout</strong><span>Your payment information is safe.</span></p></div>
            <div><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><p><strong>Easy Returns</strong><span>Hassle-free returns & refunds.</span></p></div>
          </div>
          <div class="cartp-gift" aria-hidden="true">🎁</div>
        </div>
      </aside>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
const CSRF='<?= $csrf ?>';
async function removeCartItem(id){
  if(!id) return;
  await fetch(`/api/cart/remove/${encodeURIComponent(id)}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF},credentials:'same-origin'});
  location.reload();
}
async function updateCartQty(id, quantity, el){
  if(!id || !quantity) return;
  const previous = el?.dataset?.previous || '';
  if (el) el.disabled = true;
  try {
    const resp = await fetch(`/api/cart/update/${encodeURIComponent(id)}`,{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
      credentials:'same-origin',
      body:JSON.stringify({quantity:parseInt(quantity,10)})
    });
    const data = await resp.json();
    if(!data.ok){
      if (previous && el) el.value = previous;
      alert(data.msg || 'Could not update quantity');
      return;
    }
    location.reload();
  } catch(e) {
    if (previous && el) el.value = previous;
    alert('Could not update quantity. Please try again.');
  } finally {
    if (el) el.disabled = false;
  }
}
document.querySelectorAll('.cartp-qty-select').forEach(sel => { sel.dataset.previous = sel.value; });
async function clearCartPage(){ await fetch('/api/cart/clear',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF},credentials:'same-origin'}); location.reload(); }
function formatCartMoney(value){ return '₹' + Number(value || 0).toLocaleString('en-IN'); }
function updateCartPageTotals(totals){
  if(!totals) return;
  const discount = Number(totals.discount || 0);
  document.getElementById('cartSummarySubtotal').textContent = formatCartMoney(totals.subtotal);
  document.getElementById('cartSummaryGstPct').textContent = totals.gst_pct || 0;
  document.getElementById('cartSummaryGst').textContent = formatCartMoney(totals.gst_amt);
  const shippingEl = document.getElementById('cartSummaryShipping');
  const shipping = Number(totals.shipping || 0);
  shippingEl.textContent = shipping > 0 ? formatCartMoney(shipping) : 'Free';
  shippingEl.classList.toggle('is-free', shipping <= 0);
  document.getElementById('cartSummaryTotal').textContent = formatCartMoney(totals.total);
  const discountRow = document.getElementById('cartSummaryDiscountRow');
  document.getElementById('cartSummaryDiscount').textContent = '-' + formatCartMoney(discount);
  discountRow.hidden = discount <= 0;
}
async function applyPromoOnCartPage(){
  const input = document.getElementById('promoInp');
  const code = input.value.trim().toUpperCase();
  if(!code) return;
  input.value = code;
  const msg = document.getElementById('promoMsg');
  msg.innerHTML = '';
  try {
    const r = await fetch('/api/coupon/validate',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},credentials:'same-origin',body:JSON.stringify({code})});
    const d = await r.json();
    if(!d.ok){
      msg.innerHTML = `<div style="font-size:12px;color:var(--red)">${d.msg||'Invalid coupon code'}</div>`;
      return;
    }
    const totalsResp = await fetch('/api/cart?coupon=' + encodeURIComponent(code), {credentials:'same-origin'});
    const totalsData = await totalsResp.json();
    if(totalsData.ok && totalsData.totals) updateCartPageTotals(totalsData.totals);
    msg.innerHTML = `<div class="coupon-applied">Applied: ${code}</div>`;
  } catch(e) {
    msg.innerHTML = `<div style="font-size:12px;color:var(--red)">Could not apply coupon. Please try again.</div>`;
  }
}
</script>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

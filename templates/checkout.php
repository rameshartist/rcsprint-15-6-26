<?php
$isCustomCheckout=!empty($isCustomCheckout); $customQuoteId=(int)($quote['id']??0);
$pageTitle = $isCustomCheckout ? 'Custom Order Checkout — RCS Graphic' : 'Checkout — RCS Graphic';
$loadRazorpay = true;
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
// cart-drawer is included by header.php — do not include again
$razKeyId = Database::setting('razorpay_key_id', env('RAZORPAY_KEY_ID', ''));
$bizWa = Database::setting('biz_whatsapp', env('BIZ_WHATSAPP', ''));
$itemCount = count($cartItems ?? []);
$checkoutSubtotal = (float)($totals['subtotal'] ?? 0);
$checkoutDiscount = (float)($totals['discount'] ?? 0);
$checkoutGstPct = (float)($totals['gst_pct'] ?? 18);
$checkoutGstAmt = (float)($totals['gst_amt'] ?? 0);
$checkoutShipping = (float)($totals['shipping'] ?? 0);
$checkoutShippingMode = (string)($totals['shipping_mode'] ?? 'manual');
$checkoutShippingLabel = $checkoutShipping > 0 ? '₹' . number_format($checkoutShipping) : ($checkoutShippingMode === 'manual' ? 'To be calculated' : 'Free');
$checkoutTotal = (float)($totals['total'] ?? 0);
?>
<main class="checkout-showcase-page">
  <div class="checkout-showcase-container">
    <header class="checkout-page-head">
      <h1><?= $isCustomCheckout ? 'Custom Order Checkout' : 'Checkout' ?></h1>
      <nav aria-label="Breadcrumb"><a href="/">Home</a><span>›</span><span>Checkout</span></nav>
    </header>

    <ol class="checkout-steps" aria-label="Checkout progress">
      <li class="is-active"><span>1</span><strong>Shipping Details</strong></li>
      <li><span>2</span><strong>Review Order</strong></li>
      <li><span>3</span><strong>Payment</strong></li>
      <li><span>4</span><strong>Order Complete</strong></li>
    </ol>

    <div class="checkout-layout-grid">
      <div class="checkout-main-col">
        <?php if (empty($user['id'])): ?>
        <section class="checkout-card checkout-contact-card">
          <div class="checkout-card-title"><i class="fa-regular fa-user" aria-hidden="true"></i><h2>Contact Information</h2></div>
          <div class="checkout-form-grid checkout-form-grid-3">
            <label>Full Name <b>*</b><input id="g-name" class="checkout-input" placeholder="Enter your full name" autocomplete="name"></label>
            <label>Email Address <b>*</b><input id="g-email" type="email" class="checkout-input" placeholder="youremail@gmail.com" autocomplete="email"></label>
            <label>Phone Number <b>*</b><input id="g-phone" type="tel" class="checkout-input" placeholder="+91 98765 43210" autocomplete="tel"></label>
          </div>
          <label class="checkout-checkline"><input type="checkbox" checked> <span>Keep me updated on offers and order status</span></label>
          <p class="checkout-login-note">Already have an account? <a href="/login?next=/checkout">Login here</a></p>
          <div id="guestErr" class="checkout-error" style="display:none"></div>
        </section>
        <?php endif; ?>

        <section class="checkout-card checkout-shipping-card">
          <div class="checkout-card-title"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i><h2>Shipping Address</h2></div>
          <?php if (!empty($user['id'])): ?><label class="checkout-saved-address-select" id="saved-address-wrap" hidden>Choose a saved address<select id="saved-address-select" class="checkout-input" onchange="selectCheckoutAddress(this.value)"><option value="">Enter a new address</option></select></label><?php endif; ?>
          <div class="checkout-form-grid">
            <label class="checkout-full-field">Business / Full Name <b>*</b><input id="s-business" class="checkout-input" placeholder="Enter business or full name" autocomplete="organization"></label>
            <label>Address Line 1 <b>*</b><input id="s-add1" class="checkout-input" placeholder="House / Flat / Building / Street" autocomplete="address-line1"></label>
            <label>Address Line 2 <span>(Optional)</span><input id="s-add2" class="checkout-input" placeholder="Landmark / Area / Apartment" autocomplete="address-line2"></label>
            <label>City <b>*</b><input id="s-city" class="checkout-input" placeholder="Enter your city" autocomplete="address-level2"></label>
            <label>State <b>*</b><input id="s-state" class="checkout-input" placeholder="Select State" autocomplete="address-level1"></label>
            <label>PIN Code <b>*</b><input id="s-pin" class="checkout-input" placeholder="Enter PIN code" autocomplete="postal-code"></label>
          </div>
          <?php if (!empty($user['id'])): ?>
          <label id="ship-save-wrap" class="checkout-checkline" style="display:none"><input type="checkbox" id="ship-save-default"> <span>Save this address in My Account for future use</span></label>
          <?php endif; ?>
          <div id="shipErr" class="checkout-error" style="display:none"></div>
        </section>

        <section class="checkout-card checkout-billing-card">
          <div class="checkout-card-title"><i class="fa-regular fa-file-lines" aria-hidden="true"></i><h2>Billing Details <small>Tax Invoice</small></h2></div>
          <label class="checkout-checkline"><input type="checkbox" id="bill-same-ship" onchange="syncBillingFromShipping()"> <span>Same as shipping address</span></label>
          <div id="billingFields" class="checkout-form-grid checkout-billing-grid">
            <label>Legal Business Name <b>*</b><input id="b-legal" class="checkout-input" placeholder="ABC Pvt Ltd"></label>
            <label>GSTIN <span>(Optional)</span><input id="b-gst" class="checkout-input" placeholder="24ABCDE1234F1Z5" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"></label>
            <label>Billing Address Line 1 <b>*</b><input id="b-add1" class="checkout-input" placeholder="Street / Building"></label>
            <label>Billing Address Line 2 <span>(Optional)</span><input id="b-add2" class="checkout-input" placeholder="Area / Landmark"></label>
            <label>City <b>*</b><input id="b-city" class="checkout-input" placeholder="Rajkot"></label>
            <label>State <b>*</b><input id="b-state" class="checkout-input" placeholder="Gujarat"></label>
            <label>PIN Code <b>*</b><input id="b-pin" class="checkout-input" placeholder="360001"></label>
          </div>
          <div id="billErr" class="checkout-error" style="display:none"></div>
        </section>

        <section class="checkout-consent-actions">
          <label class="checkout-consent"><input type="checkbox" id="ship-consent"><span><b>Shipping charges are extra</b> and will be calculated based on package weight and delivery location. Final charges will be shared before dispatch. <b>Customer needs to collect the parcel from the transport office.</b></span></label>
          <div id="shipConsentErr" class="checkout-error" style="display:none"></div>
          <label class="checkout-consent"><input type="checkbox" id="terms-consent"><span>I have read and agree to the <a href="/terms-and-conditions" target="_blank">Terms &amp; Conditions</a>.</span></label>
          <div id="termsConsentErr" class="checkout-error" style="display:none"></div>
          <div class="checkout-action-buttons">
            <?php if ($razKeyId): ?>
            <button class="checkout-pay-btn" onclick="doCheckout()"><i class="fa-solid fa-lock" aria-hidden="true"></i> Pay Securely with Razorpay</button>
            <?php else: ?>
            <div class="checkout-pay-warning">⚠️ Online payment not configured. Please use WhatsApp to confirm your order.</div>
            <?php endif; ?>
          </div>
        </section>
      </div>

      <aside class="checkout-side-col">
        <section class="checkout-card checkout-summary-card">
          <div class="checkout-summary-head"><div class="checkout-card-title"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><h2>Order Summary</h2></div><span><?= (int)$itemCount ?> Items in Cart</span></div>
          <div class="checkout-items-list">
            <?php foreach ($cartItems as $item): ?>
            <article class="checkout-item">
              <div class="checkout-item-img"><img src="<?= htmlspecialchars($item['product_image'] ?? '') ?>" alt="<?= htmlspecialchars($item['product_name'] ?? '') ?>" onerror="this.style.display='none'"></div>
              <div class="checkout-item-copy">
                <h3><?= htmlspecialchars($item['product_name'] ?? '') ?><?= (($item['item_type'] ?? 'product') === 'custom_quote') ? ' — Custom Quote' : '' ?></h3>
                <?php if (($item['item_type'] ?? 'product') === 'custom_quote'): ?>
                  <p><?= !empty($item['custom_requested_quantity']) ? 'Requested Qty: ' . htmlspecialchars((string)$item['custom_requested_quantity']) : 'Custom quantity' ?><?= !empty($item['custom_size_dimension']) ? ' | Size: ' . htmlspecialchars((string)$item['custom_size_dimension']) : '' ?></p>
                <?php else: ?>
                  <p><?= number_format((int)($item['quantity'] ?? 0)) ?> pcs<?= !empty($item['quality_name']) ? ' | ' . htmlspecialchars((string)$item['quality_name']) : '' ?></p>
                <?php endif; ?>
                <?php $itemBreakdown = is_array($item['price_breakdown'] ?? null) ? $item['price_breakdown'] : (json_decode((string)($item['price_breakdown'] ?? '{}'), true) ?: []); $itemDesignFee = (float)($itemBreakdown['design_fee'] ?? 0); ?>
                <strong>₹<?= number_format((float)($item['total_price'] ?? 0)) ?></strong>
                <?php if ($itemDesignFee > 0): ?><small>Includes ₹<?= number_format($itemDesignFee) ?> design fee</small><?php endif; ?>
              </div>
              <div class="checkout-item-side">
                <span><?= number_format((int)($item['quantity'] ?? 0)) ?></span>
                <?php if (!$isCustomCheckout && !empty($item['id'])): ?><button type="button" onclick="removeCheckoutItem('<?= htmlspecialchars((string)$item['id'], ENT_QUOTES) ?>')" aria-label="Remove <?= htmlspecialchars($item['product_name'] ?? 'item', ENT_QUOTES) ?>">×</button><?php endif; ?>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
          <?php if (!$isCustomCheckout): ?>
          <div class="checkout-coupon-mini">
            <div><i class="fa-solid fa-tag" aria-hidden="true"></i> Have a coupon?</div>
            <div class="coupon-row"><input id="couponInp" placeholder="Enter coupon code" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"><button class="btn btn-outline btn-sm" onclick="applyCouponCheckout()">Apply</button></div>
            <div id="couponMsg"></div>
          </div>
          <?php endif; ?>
          <div class="checkout-totals" id="totalsBox">
            <div><span>Subtotal</span><strong>₹<?= number_format($checkoutSubtotal) ?></strong></div>
            <?php if ($checkoutDiscount > 0): ?><div class="is-discount"><span>Discount</span><strong>-₹<?= number_format($checkoutDiscount) ?></strong></div><?php endif; ?>
            <div><span>Shipping / Delivery</span><strong class="<?= $checkoutShipping > 0 ? '' : ($checkoutShippingMode === 'manual' ? 'is-manual' : 'is-free') ?>"><?= $checkoutShippingLabel ?></strong></div>
            <div><span>Tax (<?= htmlspecialchars((string)$checkoutGstPct, ENT_QUOTES, 'UTF-8') ?>% GST)</span><strong>₹<?= number_format($checkoutGstAmt) ?></strong></div>
            <div class="checkout-total-row"><span>Total Amount</span><strong>₹<?= number_format($checkoutTotal) ?></strong></div>
          </div>
        </section>

        <section class="checkout-card checkout-why-card">
          <div class="checkout-card-title"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i><h2>Why Shop With Us?</h2></div>
          <div class="checkout-why-list">
            <div><i class="fa-solid fa-award" aria-hidden="true"></i><span><strong>Premium Quality</strong><small>Best quality materials and printing</small></span></div>
            <div><i class="fa-solid fa-lock" aria-hidden="true"></i><span><strong>Secure Payment</strong><small>100% secure and encrypted payments</small></span></div>
            <div><i class="fa-solid fa-truck-fast" aria-hidden="true"></i><span><strong>Fast Delivery</strong><small>Quick and reliable delivery service</small></span></div>
            <div><i class="fa-regular fa-heart" aria-hidden="true"></i><span><strong>Satisfaction Guaranteed</strong><small>100% customer satisfaction promise</small></span></div>
          </div>
        </section>
      </aside>
    </div>
  </div>
</main>
<script>
const CSRF = '<?= $csrf ?>';
const BIZ_WA = '<?= htmlspecialchars($bizWa) ?>'; const CUSTOM_QUOTE_ID=<?= $isCustomCheckout?$customQuoteId:0 ?>;
let checkoutCoupon = null;
let checkoutProfile = { shipping: null, billing: null };
let checkoutAddresses = [];

async function applyCouponCheckout() {
  const code = document.getElementById('couponInp').value.trim().toUpperCase();
  if (!code) return;
  const resp = await fetch('/api/coupon/validate', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ code })
  });
  const data = await resp.json();
  const msg = document.getElementById('couponMsg');
  if (data.ok) {
    checkoutCoupon = code;
    msg.innerHTML = `<div class="coupon-applied" style="margin-top:8px">🎟️ ${code} applied! Discount: ₹${data.discount}</div>`;
    // Reload totals display
    const t = await fetch('/api/cart?coupon=' + code).then(r => r.json());
    if (t.totals) {
      const tot = t.totals;
      const fmt = n => '₹' + Number(n).toLocaleString('en-IN');
      const shipping = Number(tot.shipping || 0);
      const shippingMode = tot.shipping_mode || 'manual';
      const shippingText = shipping > 0 ? fmt(shipping) : (shippingMode === 'manual' ? 'To be calculated' : 'Free');
      document.getElementById('totalsBox').innerHTML = `
        <div><span>Subtotal</span><strong>${fmt(tot.subtotal)}</strong></div>
        ${Number(tot.discount || 0) > 0 ? `<div class="is-discount"><span>Discount</span><strong>-${fmt(tot.discount)}</strong></div>` : ''}
        <div><span>Shipping / Delivery</span><strong class="${shipping > 0 ? '' : (shippingMode === 'manual' ? 'is-manual' : 'is-free')}">${shippingText}</strong></div>
        <div><span>Tax (${tot.gst_pct}% GST)</span><strong>${fmt(tot.gst_amt)}</strong></div>
        <div class="checkout-total-row"><span>Total Amount</span><strong>${fmt(tot.total)}</strong></div>`;
    }
  } else {
    msg.innerHTML = `<div style="font-size:12px;color:var(--red);margin-top:6px">${data.msg}</div>`;
  }
}

async function doCheckout() {
  const consentErr = document.getElementById('shipConsentErr');
  if (!document.getElementById('ship-consent')?.checked) {
    if (consentErr) {
      consentErr.textContent = 'Please confirm shipping charge acknowledgement to continue.';
      consentErr.style.display = 'block';
    }
    return;
  }
  if (consentErr) consentErr.style.display = 'none';
  const termsErr = document.getElementById('termsConsentErr');
  if (!document.getElementById('terms-consent')?.checked) {
    if (termsErr) {
      termsErr.textContent = 'Please accept Terms & Conditions to continue.';
      termsErr.style.display = 'block';
    }
    return;
  }
  if (termsErr) termsErr.style.display = 'none';
  const customer = getCheckoutCustomer();
  if (customer === false) return;
  if (!(await validateGuestAccountForCheckout(customer))) return;
  const shipping = getCheckoutShipping();
  if (shipping === false) return;
  const billing = getCheckoutBilling();
  if (billing === false) return;
  initiateCheckout(checkoutCoupon, customer, billing, shipping, CUSTOM_QUOTE_ID||null);
}

async function doWhatsAppOrder() {
  const consentErr = document.getElementById('shipConsentErr');
  if (!document.getElementById('ship-consent')?.checked) {
    if (consentErr) {
      consentErr.textContent = 'Please confirm shipping charge acknowledgement to continue.';
      consentErr.style.display = 'block';
    }
    return;
  }
  if (consentErr) consentErr.style.display = 'none';
  const termsErr = document.getElementById('termsConsentErr');
  if (!document.getElementById('terms-consent')?.checked) {
    if (termsErr) {
      termsErr.textContent = 'Please accept Terms & Conditions to continue.';
      termsErr.style.display = 'block';
    }
    return;
  }
  if (termsErr) termsErr.style.display = 'none';
  const customer = getCheckoutCustomer();
  if (customer === false) return;
  if (!(await validateGuestAccountForCheckout(customer))) return;
  const shipping = getCheckoutShipping();
  if (shipping === false) return;
  const billing = getCheckoutBilling();
  if (billing === false) return;
  const notes = '';
  await placeWhatsappOrder(checkoutCoupon, notes, customer, billing, shipping);
}

async function removeCheckoutItem(itemId) {
  if (!itemId) return;
  try {
    const resp = await fetch(`/api/cart/remove/${encodeURIComponent(itemId)}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF },
      credentials: 'same-origin'
    });
    const data = await resp.json();
    if (!data.ok) {
      alert(data.msg || 'Could not remove item.');
      return;
    }
    window.location.reload();
  } catch (e) {
    alert('Could not remove item right now.');
  }
}

function toggleBillingFields() {
  const err = document.getElementById('billErr');
  if (err) err.style.display = 'none';
}

function toggleShippingFields() {
  const err = document.getElementById('shipErr');
  const saveWrap = document.getElementById('ship-save-wrap');
  if (saveWrap) saveWrap.style.display = 'flex';
  if (err) err.style.display = 'none';
}

function syncBillingFromShipping() {
  const same = !!document.getElementById('bill-same-ship')?.checked;
  if (!same) return;
  setField('b-add1', document.getElementById('s-add1')?.value || '');
  setField('b-add2', document.getElementById('s-add2')?.value || '');
  setField('b-city', document.getElementById('s-city')?.value || '');
  setField('b-state', document.getElementById('s-state')?.value || '');
  setField('b-pin', document.getElementById('s-pin')?.value || '');
}

function getCheckoutCustomer() {
  <?php if (!empty($user['id'])): ?>
  return {};
  <?php else: ?>
  const name = document.getElementById('g-name')?.value.trim() || '';
  const email = document.getElementById('g-email')?.value.trim() || '';
  const phone = document.getElementById('g-phone')?.value.trim() || '';
  const err = document.getElementById('guestErr');
  const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  if (!name || !email || !phone) {
    err.textContent = 'Please fill name, email and phone to continue checkout.';
    err.style.display = 'block';
    return false;
  }
  if (!emailOk) {
    err.textContent = 'Please enter a valid email address.';
    err.style.display = 'block';
    return false;
  }
  err.style.display = 'none';
  return { name, email, phone };
  <?php endif; ?>
}

async function validateGuestAccountForCheckout(customer) {
  <?php if (!empty($user['id'])): ?>
  return true;
  <?php else: ?>
  const err = document.getElementById('guestErr');
  try {
    const resp = await fetch('/api/auth/account-exists', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ email: customer.email, phone: customer.phone }),
    });
    const data = await resp.json();
    if (!data.ok) {
      if (err) {
        err.textContent = data.msg || 'Could not validate account details.';
        err.style.display = 'block';
      }
      return false;
    }
    if (data.exists) {
      if (err) {
        err.innerHTML = `An account already exists with this email/phone. Please <a href="/login?next=/checkout" style="color:var(--blue);font-weight:700">login to continue checkout</a>.`;
        err.style.display = 'block';
      }
      return false;
    }
    if (err) err.style.display = 'none';
    return true;
  } catch (e) {
    if (err) {
      err.textContent = 'Could not validate account details right now.';
      err.style.display = 'block';
    }
    return false;
  }
  <?php endif; ?>
}

function getCheckoutBilling() {
  const legal = document.getElementById('b-legal')?.value.trim() || '';
  const gst = (document.getElementById('b-gst')?.value || '').trim().toUpperCase();
  const add1 = document.getElementById('b-add1')?.value.trim() || '';
  const add2 = document.getElementById('b-add2')?.value.trim() || '';
  const city = document.getElementById('b-city')?.value.trim() || '';
  const state = document.getElementById('b-state')?.value.trim() || '';
  const pin = document.getElementById('b-pin')?.value.trim() || '';

  const err = document.getElementById('billErr');
  const gstOk = /^[0-9]{2}[A-Z0-9]{10}[0-9A-Z]{3}$/.test(gst);
  const pinOk = /^[1-9][0-9]{5}$/.test(pin);

  if (!legal || !add1 || !city || !state || !pin) {
    if (err) { err.textContent = 'Please fill all required billing address fields.'; err.style.display = 'block'; }
    return false;
  }
  if (gst && !gstOk) {
    if (err) { err.textContent = 'Please enter a valid GSTIN.'; err.style.display = 'block'; }
    return false;
  }
  if (!pinOk) {
    if (err) { err.textContent = 'Please enter a valid 6-digit pincode.'; err.style.display = 'block'; }
    return false;
  }
  if (err) err.style.display = 'none';

  return {
    required: true,
    legal_name: legal,
    gst_no: gst,
    address_line1: add1,
    address_line2: add2,
    city,
    state,
    pincode: pin,
  };
}

function getCheckoutShipping() {
  const business = document.getElementById('s-business')?.value.trim() || '';
  const add1 = document.getElementById('s-add1')?.value.trim() || '';
  const add2 = document.getElementById('s-add2')?.value.trim() || '';
  const city = document.getElementById('s-city')?.value.trim() || '';
  const state = document.getElementById('s-state')?.value.trim() || '';
  const pin = document.getElementById('s-pin')?.value.trim() || '';
  const err = document.getElementById('shipErr');
  const pinOk = /^[1-9][0-9]{5}$/.test(pin);

  if (!business || !add1 || !city || !state || !pin) {
    if (err) { err.textContent = 'Please fill business name and delivery address details to continue.'; err.style.display = 'block'; }
    return false;
  }
  if (!pinOk) {
    if (err) { err.textContent = 'Please enter a valid 6-digit delivery pincode.'; err.style.display = 'block'; }
    return false;
  }
  if (err) err.style.display = 'none';

  return {
    business_name: business,
    address_line1: add1,
    address_line2: add2,
    city,
    state,
    pincode: pin,
    save_as_default: !!document.getElementById('ship-save-default')?.checked,
  };
}

function setField(id, val = '') {
  const el = document.getElementById(id);
  if (el) el.value = val || '';
}
function selectCheckoutAddress(value){const address=checkoutAddresses.find(item=>String(item.id)===String(value));if(!address)return;setField('s-business',address.business_name);setField('s-add1',address.address_line1);setField('s-add2',address.address_line2);setField('s-city',address.city);setField('s-state',address.state);setField('s-pin',address.pincode);syncBillingFromShipping();}

async function prefillCheckoutFromProfile() {
  <?php if (empty($user['id'])): ?>
  return;
  <?php else: ?>
  try {
    const resp = await fetch('/api/profile', { credentials: 'same-origin' });
    const data = await resp.json();
    if (!data.ok || !data.profile) return;

    checkoutProfile.shipping = data.profile.shipping || null;
    checkoutProfile.billing = data.profile.billing || null;
    checkoutAddresses = Array.isArray(data.profile.addresses) ? data.profile.addresses : [];
    const addressWrap=document.getElementById('saved-address-wrap'),addressSelect=document.getElementById('saved-address-select');
    if(addressWrap&&addressSelect&&checkoutAddresses.length){addressWrap.hidden=false;addressSelect.innerHTML='<option value="">Enter a new address</option>'+checkoutAddresses.map(a=>`<option value="${a.id}">${String(a.label||'Address')} — ${String(a.address_line1||'')}</option>`).join('');const preferred=checkoutAddresses.find(a=>Number(a.is_default)===1)||checkoutAddresses[0];addressSelect.value=String(preferred.id);selectCheckoutAddress(preferred.id);}
    const profileName = data.profile.name || '';
    const profileCompany = data.profile.company || '';

    setField('g-name', profileName);
    setField('g-email', data.profile.email || '');
    setField('g-phone', data.profile.phone || '');

    if (checkoutProfile.shipping) {
      setField('s-business', checkoutProfile.shipping.business_name || profileCompany || profileName);
      setField('s-add1', checkoutProfile.shipping.address_line1);
      setField('s-add2', checkoutProfile.shipping.address_line2);
      setField('s-city', checkoutProfile.shipping.city);
      setField('s-state', checkoutProfile.shipping.state);
      setField('s-pin', checkoutProfile.shipping.pincode);
    } else {
      setField('s-business', profileCompany || profileName);
    }

    if (checkoutProfile.billing) {
      setField('b-legal', checkoutProfile.billing.legal_name);
      setField('b-gst', checkoutProfile.billing.gst_no);
      setField('b-add1', checkoutProfile.billing.address_line1);
      setField('b-add2', checkoutProfile.billing.address_line2);
      setField('b-city', checkoutProfile.billing.city);
      setField('b-state', checkoutProfile.billing.state);
      setField('b-pin', checkoutProfile.billing.pincode);
      toggleBillingFields();
    }

    toggleShippingFields();
  } catch (e) { /* ignore prefill failures */ }
  <?php endif; ?>
}

document.addEventListener('DOMContentLoaded', () => {
  ['s-business','s-add1','s-add2','s-city','s-state','s-pin'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', syncBillingFromShipping);
  });
  prefillCheckoutFromProfile();
});
</script>
<script src="/assets/js/app.js"></script>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>

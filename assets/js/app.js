// ═══════════════════════════════════════════════════════════════
//  RCS Graphic — app.js
//  Shared JS: toast, mobile drawer, cart, checkout, product page
// ═══════════════════════════════════════════════════════════════

// ── Header height sync ───────────────────────────────────────
// The site header changes height across mobile breakpoints. Keep the
// content offset equal to the rendered header height so the home banner
// starts immediately after the fixed header without a mobile gap.
function syncSiteHeaderHeight() {
  const header = document.getElementById('siteHeader');
  if (!header) return;
  const height = Math.ceil(header.getBoundingClientRect().height);
  if (height > 0) {
    const value = `${height}px`;
    document.documentElement.style.setProperty('--site-hh', value);
    document.body?.style.setProperty('--site-hh', value);
  }
}

syncSiteHeaderHeight();
window.addEventListener('load', syncSiteHeaderHeight);
window.addEventListener('resize', syncSiteHeaderHeight);

// ── Toast ─────────────────────────────────────────────────────
function toast(msg, type = 'info') {
  const w = document.getElementById('tw');
  if (!w) return;
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.textContent = msg;
  w.appendChild(t);
  requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2800);
}

// ── Mobile Drawer ─────────────────────────────────────────────
// FIX: header calls toggleDrawer(); we define both names
let _drawerOpen = false;

function toggleDrawer() {          // ← called by ham button in header.php
  _drawerOpen ? closeDrawer() : openDrawer();
}
function toggleMobDrawer() { toggleDrawer(); } // alias for legacy calls

function openDrawer() {
  _drawerOpen = true;
  document.getElementById('mobDrawer')?.classList.add('open');
  document.getElementById('mobBack')?.classList.add('show');
  document.getElementById('hamBtn')?.setAttribute('aria-expanded', 'true');
  document.body.classList.add('drawer-open');
  // Update icon to X
  const btn = document.getElementById('hamBtn');
  if (btn) btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:19px;height:19px;fill:currentColor">
    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
  </svg>`;
}

function closeDrawer() {
  _drawerOpen = false;
  document.getElementById('mobDrawer')?.classList.remove('open');
  document.getElementById('mobBack')?.classList.remove('show');
  document.getElementById('hamBtn')?.setAttribute('aria-expanded', 'false');
  document.body.classList.remove('drawer-open');
  // Restore hamburger icon
  const btn = document.getElementById('hamBtn');
  if (btn) btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:19px;height:19px;fill:currentColor">
    <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
  </svg>`;
}

function toggleMobProds() {
  const list  = document.getElementById('mobProdList');
  const arrow = document.querySelector('#mobProdToggle .md-acc-arrow');
  if (!list) return;
  const isOpen = list.style.display !== 'none';
  list.style.display = isOpen ? 'none' : 'block';
  if (arrow) arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
}

function toggleMobCatProducts(btn) {
  const block = btn?.closest('.md-cat-block');
  const list = block?.querySelector('.md-cat-products');
  if (!block || !list) return;
  const isOpen = !list.hidden;
  list.hidden = isOpen;
  block.classList.toggle('open', !isOpen);
  btn.setAttribute('aria-expanded', String(!isOpen));
}

// ── Desktop Dropdown (JS-assisted for stability) ───────────────
(function () {
  const wrap  = document.getElementById('ddWrap');
  const panel = document.getElementById('ddPanel');
  const btn   = document.getElementById('ddBtn');
  if (!wrap || !panel) return;

  let hideTimer = null;
  const catGroups = [...panel.querySelectorAll('.dd-cat-group')];

  function closeFlyouts() {
    catGroups.forEach(group => group.classList.remove('is-open'));
  }

  function showPanel() {
    clearTimeout(hideTimer);
    panel.classList.add('open');
    if (btn) btn.setAttribute('aria-expanded', 'true');
  }
  function scheduleHide() {
    hideTimer = setTimeout(() => {
      panel.classList.remove('open');
      closeFlyouts();
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }, 180); // grace period keeps flyouts open while crossing tiny gaps
  }

  catGroups.forEach(group => {
    let flyoutTimer = null;
    const openGroup = () => {
      clearTimeout(flyoutTimer);
      catGroups.forEach(other => { if (other !== group) other.classList.remove('is-open'); });
      group.classList.add('is-open');
      showPanel();
    };
    const scheduleGroupClose = () => {
      flyoutTimer = setTimeout(() => group.classList.remove('is-open'), 220);
    };
    group.addEventListener('mouseenter', openGroup);
    group.addEventListener('focusin', openGroup);
    group.addEventListener('mouseleave', scheduleGroupClose);
    group.addEventListener('focusout', () => {
      flyoutTimer = setTimeout(() => {
        if (!group.contains(document.activeElement)) group.classList.remove('is-open');
      }, 220);
    });
  });

  wrap.addEventListener('mouseenter', showPanel);
  wrap.addEventListener('mouseleave', scheduleHide);
  panel.addEventListener('mouseenter', showPanel);
  panel.addEventListener('mouseleave', scheduleHide);
  // Close on Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      panel.classList.remove('open');
      closeFlyouts();
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }
  });
})();

// ── Search helpers ─────────────────────────────────────────────
function handleSearchInline(q) {
  // Filter visible product cards on the home page
  const cards = document.querySelectorAll('.pc');
  if (!cards.length) return;
  q = q.toLowerCase().trim();
  cards.forEach(card => {
    const text = (card.querySelector('.pc-name')?.textContent || '') +
                 (card.dataset.cat || '');
    card.style.display = (!q || text.toLowerCase().includes(q)) ? '' : 'none';
  });
}
function doSearch() {
  const q = document.getElementById('searchInp')?.value?.trim();
  if (q) window.location.href = '/?q=' + encodeURIComponent(q);
}

// ── Pay Overlay ───────────────────────────────────────────────
function showPayOv(title = 'Processing…', sub = "Please don't close this window") {
  const el = document.getElementById('payOv');
  if (el) el.classList.add('show');
  const t = document.getElementById('payTxt');
  if (t) t.textContent = title;
  const s = el?.querySelector('.pay-sub');
  if (s) s.textContent = sub;
}
function hidePayOv() {
  document.getElementById('payOv')?.classList.remove('show');
}

// ── Cart ──────────────────────────────────────────────────────
let _cartData = { items: [], totals: {} };
let _couponCode     = null;
let _couponApplied  = null;

async function loadCart() {
  try {
    const url  = _couponCode ? `/api/cart?coupon=${encodeURIComponent(_couponCode)}` : '/api/cart';
    const resp = await fetch(url, { credentials: 'same-origin' });
    const data = await resp.json();
    if (data.ok) {
      _cartData = data;
      updateCartCount();
      renderCartDrawer();
    }
  } catch (e) { /* silent */ }
}

function updateCartCount() {
  const n = _cartData.items?.length || 0;
  document.querySelectorAll('#cartCount, .rcs-cart-badge, .md-cart-badge').forEach(cnt => {
    cnt.textContent = n;
    cnt.classList.toggle('hidden', n === 0);
  });
}

function openCart()  {
  document.getElementById('cartDrawer')?.classList.add('open');
  document.getElementById('cartBack')?.classList.add('show');
  loadCart();
}
function closeCart() {
  document.getElementById('cartDrawer')?.classList.remove('open');
  document.getElementById('cartBack')?.classList.remove('show');
}
function toggleCart() {
  document.getElementById('cartDrawer')?.classList.contains('open') ? closeCart() : openCart();
}

async function removeFromCart(itemId) {
  await fetch(`/api/cart/remove/${itemId}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': APP.csrfToken },
    credentials: 'same-origin'
  });
  loadCart();
  toast('Item removed', 'info');
}

async function applyCoupon() {
  const code = document.getElementById('couponInp')?.value?.trim().toUpperCase();
  if (!code) return;
  const resp = await fetch('/api/coupon/validate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrfToken },
    credentials: 'same-origin',
    body: JSON.stringify({ code })
  });
  const data = await resp.json();
  if (data.ok) {
    _couponCode = code;
    _couponApplied = data.coupon;
    toast('Coupon applied! 🎟️', 'success');
    loadCart();
  } else {
    toast(data.msg || 'Invalid coupon', 'error');
  }
}
function removeCoupon() { _couponCode = null; _couponApplied = null; loadCart(); }

function renderCartDrawer() {
  const body   = document.getElementById('cartBody');
  const footer = document.getElementById('cartFooter');
  if (!body || !footer) return;

  const items  = _cartData.items  || [];
  const totals = _cartData.totals || {};
  const fmt    = n => '₹' + Number(n || 0).toLocaleString('en-IN');

  if (!items.length) {
    body.innerHTML = `<div style="text-align:center;padding:48px 20px;color:var(--text2)">
      <div style="font-size:40px;margin-bottom:10px">🛒</div>
      <div style="font-size:15px;font-weight:600;margin-bottom:4px">Your cart is empty</div>
      <div style="font-size:13px">Browse products and add items to cart</div></div>`;
    footer.innerHTML = `<a href="/categories" class="btn btn-blue btn-full" style="border-radius:10px;padding:13px">Browse Products</a>`;
    return;
  }

  body.innerHTML = items.map(item => {
    const isCustom = item.item_type === 'custom_quote';
    const attrs = isCustom
      ? `${item.custom_requested_quantity ? 'Qty: ' + _esc(item.custom_requested_quantity) + ' · ' : ''}${item.custom_size_dimension ? 'Size: ' + _esc(item.custom_size_dimension) : 'Custom print requirement'}`
      : `${Number(item.quantity).toLocaleString('en-IN')} pcs · ${_esc(item.quality_name)}`;
    return `
    <div class="cart-item ${isCustom ? 'cart-item-custom' : ''}">
      <div class="ci-img"><img src="${item.product_image || ''}" alt="${_esc(item.product_name)}" onerror="this.style.display='none'"></div>
      <div class="ci-info">
        <div class="ci-name">${_esc(item.product_name)}${isCustom ? ' · Custom Quote' : ''}</div>
        <div class="ci-attrs">${attrs}</div>
        ${isCustom && item.custom_material_type ? `<div style="font-size:11px;color:var(--blue);margin-bottom:2px">Material: ${_esc(item.custom_material_type)}</div>` : ''}
        ${!isCustom && item.design_choice === 'rcs' ? `<div style="font-size:11px;color:var(--blue);margin-bottom:2px">🎨 Design by RCS Graphic</div>` : ''}
        ${item.notes ? `<div style="font-size:11px;color:var(--amber)">📝 ${_esc(item.notes)}</div>` : ''}
        <div class="ci-price">${fmt(item.total_price)}</div>
      </div>
      <button onclick="removeFromCart('${item.id}')" style="color:var(--text3);font-size:18px;padding:2px;align-self:flex-start">✕</button>
    </div>`;
  }).join('');

  const disc = totals.discount || 0;
  footer.innerHTML = `
    <div style="margin-bottom:10px">
      <div class="coupon-row">
        <input id="couponInp" placeholder="Coupon code" style="text-transform:uppercase"
               oninput="this.value=this.value.toUpperCase()"
               ${_couponApplied ? 'disabled' : ''} value="${_couponCode || ''}">
        <button class="btn btn-outline btn-sm" onclick="${_couponApplied ? 'removeCoupon()' : 'applyCoupon()'}">
          ${_couponApplied ? '✕ Remove' : 'Apply'}
        </button>
      </div>
      ${_couponApplied ? `<div class="coupon-applied">🎟️ ${_couponApplied.code} — ${_couponApplied.discount_type === 'percent' ? _couponApplied.discount_value + '% off' : '₹' + _couponApplied.discount_value + ' off'}</div>` : ''}
    </div>
    <div class="cart-summary">
      <div class="cart-sum-row"><span style="color:var(--text2)">Subtotal</span><span>${fmt(totals.subtotal)}</span></div>
      ${disc > 0 ? `<div class="cart-sum-row" style="color:var(--green)"><span>Discount</span><span>-${fmt(disc)}</span></div>` : ''}
      <div class="cart-sum-row"><span style="color:var(--text2)">GST (${totals.gst_pct || APP.gstPercent}%)</span><span>${fmt(totals.gst_amt)}</span></div>
      <div class="cart-sum-row"><span style="color:var(--text2)">Shipping</span><span>${Number(totals.shipping||0) > 0 ? fmt(totals.shipping) : 'Free'}</span></div>
      <div class="cart-sum-row total"><span>Total</span><span style="color:var(--blue)">${fmt(totals.total)}</span></div>
    </div>
    <a href="/cart" class="btn btn-blue btn-full" style="border-radius:10px;padding:14px;font-size:15px">View Cart</a>
    <button onclick="closeCart()" class="btn btn-ghost btn-full" style="padding:10px;margin-top:6px;font-size:13px">Continue Shopping</button>`;
}

// ── Razorpay Checkout ─────────────────────────────────────────
async function initiateCheckout(couponCode = null, customer = null, billing = null, shipping = null, customQuoteId = null) {
  if (!APP.razorpayKey) {
    toast('Payment not configured. Please contact us via WhatsApp.', 'warn'); return;
  }
  showPayOv('Creating order…', 'Connecting to Razorpay');
  try {
    const oResp = await fetch('/api/payment/create-order', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrfToken },
      credentials: 'same-origin',
      body: JSON.stringify({ coupon_code: couponCode, customer, billing, shipping, custom_quote_id: customQuoteId })
    });
    const oData = await oResp.json();
    if (!oData.ok) { hidePayOv(); toast(oData.msg || 'Payment setup failed', 'error'); return; }

    hidePayOv();
    const rzp = new Razorpay({
      key:      oData.key_id,
      amount:   oData.amount,
      currency: oData.currency || 'INR',
      order_id: oData.razorpay_order_id,
      name:     'RCS Graphic',
      description: customQuoteId ? 'Custom Print Order' : 'Print Order',
      theme:    { color: '#1A56E8' },
      modal:    { ondismiss: () => { hidePayOv(); toast('Payment cancelled', 'warn'); } },
      handler: async resp => {
        showPayOv('Verifying payment…', 'Almost done!');
        const vResp = await fetch('/api/payment/verify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrfToken },
          credentials: 'same-origin',
          body: JSON.stringify({ ...resp, coupon_code: couponCode, customer, billing, shipping, custom_quote_id: customQuoteId })
        });
        const vData = await vResp.json();
        hidePayOv();
        if (vData.ok) {
          const oid = vData?.order?.order_id;
          if (oid) {
            window.location.href = '/order/confirm/' + oid;
            return;
          }
          toast('Payment captured, but confirmation is loading. Opening My Orders…', 'warn');
          setTimeout(() => { window.location.href = customQuoteId ? '/profile#custom-orders' : '/my-orders'; }, 900);
        } else {
          toast(vData.msg || 'Verification failed. Contact support.', 'error');
        }
      }
    });
    rzp.on('payment.failed', r => { hidePayOv(); toast('Payment failed: ' + (r.error?.description || 'Error'), 'error'); });
    rzp.open();
  } catch (e) { hidePayOv(); toast('Payment error. Please try again.', 'error'); }
}

async function placeWhatsappOrder(couponCode = null, notes = '', customer = null, billing = null, shipping = null) {
  try {
    const url = couponCode ? `/api/cart?coupon=${encodeURIComponent(couponCode)}` : '/api/cart';
    const resp = await fetch(url, { credentials: 'same-origin' });
    const data = await resp.json();

    if (!data.ok) {
      toast(data.msg || 'Could not load cart details for WhatsApp', 'error');
      return;
    }

    const items = data.items || [];
    const totals = data.totals || {};
    if (!items.length) {
      toast('Your cart is empty', 'warn');
      return;
    }

    const fmt = n => '₹' + Number(n || 0).toLocaleString('en-IN');
    const now = new Date().toLocaleString('en-IN');

    const lines = [
      '🧾 *New Order Enquiry*',
      `🕒 ${now}`,
      '',
      '*Customer Details*',
      `• Name: ${customer?.name || APP.user?.name || 'Guest'}`,
      `• Phone: ${customer?.phone || APP.user?.phone || 'Not provided'}`,
      `• Email: ${customer?.email || APP.user?.email || 'Not provided'}`,
      '',
      ...(shipping ? [
        '*Delivery Address*',
        ...(shipping.business_name ? [`• Business: ${shipping.business_name}`] : []),
        `• ${shipping.address_line1 || ''}${shipping.address_line2 ? ', ' + shipping.address_line2 : ''}`,
        `• ${shipping.city || ''}, ${shipping.state || ''} - ${shipping.pincode || ''}`,
        '',
      ] : []),
      ...(billing && billing.required ? [
        '*Billing Details*',
        `• Legal Name: ${billing.legal_name || 'Not provided'}`,
        `• GSTIN: ${billing.gst_no || 'Not provided'}`,
        `• Address: ${billing.address_line1 || ''}${billing.address_line2 ? ', ' + billing.address_line2 : ''}, ${billing.city || ''}, ${billing.state || ''} - ${billing.pincode || ''}`,
        '',
      ] : []),
      '',
      '*Order Items*'
    ];

    items.forEach((item, idx) => {
      const design = item.design_choice === 'rcs' ? 'Design by RCS' : 'Customer Upload';
      lines.push(`${idx + 1}) ${item.product_name || 'Product'}`);
      lines.push(`   • Qty: ${Number(item.quantity || 0).toLocaleString('en-IN')} pcs`);
      lines.push(`   • Quality: ${item.quality_name || 'Standard'}`);
      lines.push(`   • Design: ${design}`);
      if (item.design_brief) lines.push(`   • Design Brief: ${item.design_brief}`);
      if (item.notes) lines.push(`   • Notes: ${item.notes}`);
      lines.push(`   • Line Total: ${fmt(item.total_price)}`);
      lines.push('');
    });

    lines.push('*Summary*');
    lines.push(`• Subtotal: ${fmt(totals.subtotal)}`);
    if (Number(totals.discount || 0) > 0) lines.push(`• Discount: -${fmt(totals.discount)}`);
    lines.push(`• GST (${totals.gst_pct || APP.gstPercent}%): ${fmt(totals.gst_amt)}`);
    lines.push(`• Shipping: ${Number(totals.shipping || 0) > 0 ? fmt(totals.shipping) : 'Free'}`);
    lines.push(`• *Grand Total: ${fmt(totals.total)}*`);

    if (couponCode) lines.push(`• Coupon: ${couponCode}`);
    if (notes) lines.push(`• Extra Note: ${notes}`);
    lines.push('');
    lines.push(`Source: Checkout (${window.location.href})`);

    const waUrl = `https://wa.me/${APP.whatsapp}?text=${encodeURIComponent(lines.join('\n'))}`;
    window.open(waUrl, '_blank');
    toast('Opening WhatsApp with full order details…', 'success');
  } catch (e) {
    toast('Could not prepare WhatsApp message. Please try again.', 'error');
  }
}

// ── Banner Slider ─────────────────────────────────────────────
/**
 * initBannerSlider(containerId)
 * Auto-advances every 3 seconds. Pauses on hover.
 */
function initBannerSlider(id = 'bannerSlider') {
  const wrap   = document.getElementById(id);
  if (!wrap) return;
  const slides = wrap.querySelectorAll('.bs-slide');
  const dots   = wrap.querySelectorAll('.bs-dot');
  if (!slides.length) return;

  let cur = 0;
  let timer = null;
  const intervalMs = Math.max(2000, Number(wrap.dataset.slideInterval || 3000));

  function goTo(n) {
    slides[cur].classList.remove('active');
    dots[cur]?.classList.remove('active');
    cur = (n + slides.length) % slides.length;
    slides[cur].classList.add('active');
    dots[cur]?.classList.add('active');
  }

  function next() { goTo(cur + 1); }
  function prev() { goTo(cur - 1); }

  function startTimer() {
    if (timer) clearInterval(timer);
    if (slides.length > 1) timer = setInterval(next, intervalMs);
  }

  // Init
  slides[0].classList.add('active');
  dots[0]?.classList.add('active');
  startTimer();

  // Expose for HTML buttons
  wrap._sliderNext = next;
  wrap._sliderPrev = prev;
  wrap._sliderGoTo = goTo;

  // Swipe support (mobile)
  let startX = 0;
  wrap.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
  wrap.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) dx < 0 ? next() : prev();
  }, { passive: true });
}

// ── Product Carousel ──────────────────────────────────────────
/**
 * initProductCarousel(containerId)
 * Scrolls through product cards with prev/next arrows.
 */
function initProductCarousel(id = 'prodCarousel') {
  const wrap  = document.getElementById(id);
  if (!wrap) return;
  const track = wrap.querySelector('.pc-track');
  if (!track) return;

  let idx = 0;
  const getVisible = () => {
    const w = wrap.offsetWidth;
    if (w < 480)  return 1;
    if (w < 768)  return 2;
    if (w < 1100) return 3;
    return 4;
  };

  function getCards() { return track.querySelectorAll('.pc-carousel-card'); }

  function slideTo(n) {
    const cards   = getCards();
    const visible = getVisible();
    const max     = Math.max(0, cards.length - visible);
    idx = Math.max(0, Math.min(n, max));
    const cardW = cards[0]?.offsetWidth || 280;
    const gap   = 20;
    track.style.transform = `translateX(-${idx * (cardW + gap)}px)`;
    // Update arrow states
    wrap.querySelector('.pc-prev')?.toggleAttribute('disabled', idx === 0);
    wrap.querySelector('.pc-next')?.toggleAttribute('disabled', idx >= max);
  }

  wrap.querySelector('.pc-prev')?.addEventListener('click', () => slideTo(idx - 1));
  wrap.querySelector('.pc-next')?.addEventListener('click', () => slideTo(idx + 1));
  window.addEventListener('resize', () => slideTo(idx));
  slideTo(0);

  // Touch swipe
  let sx = 0;
  track.addEventListener('touchstart', e => { sx = e.touches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - sx;
    if (Math.abs(dx) > 60) dx < 0 ? slideTo(idx + 1) : slideTo(idx - 1);
  }, { passive: true });
}

// ── Category Spotlight Carousel ───────────────────────────────
let _catSpotApi = null;
function initCategorySpotlight(id = 'catSpotWrap') {
  const wrap = document.getElementById(id);
  const track = document.getElementById('catSpotTrack');
  if (!wrap || !track) return;

  const cards = () => track.querySelectorAll('.cat-spot-card');
  let idx = 0;
  let timer = null;

  const mobile = () => window.matchMedia('(max-width: 768px)').matches;
  const visible = () => (mobile() ? 1 : (window.innerWidth < 1100 ? 2 : 4));

  function slideTo(n) {
    const list = cards();
    if (!list.length) return;
    const max = Math.max(0, list.length - visible());
    idx = Math.max(0, Math.min(n, max));
    list.forEach((c, i) => c.classList.toggle('is-active', i === idx));
    if (mobile()) {
      list[idx]?.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
    } else {
      const shift = (list[0]?.offsetWidth || 0) + 14;
      track.style.transform = `translateX(-${idx * shift}px)`;
    }
  }

  function next() { slideTo(idx + 1); }
  function prev() { slideTo(idx - 1); }

  function start() { timer = setInterval(() => next(), 4500); }
  function stop() { clearInterval(timer); }

  wrap.addEventListener('mouseenter', stop);
  wrap.addEventListener('mouseleave', start);
  window.addEventListener('resize', () => slideTo(idx));

  slideTo(0);
  start();
  _catSpotApi = { next, prev };
}

function catSpotNext() { _catSpotApi?.next?.(); }
function catSpotPrev() { _catSpotApi?.prev?.(); }

let _csApi = null;
function initCategoryShowcase(id = 'csWrap') {
  const wrap = document.getElementById(id);
  const track = document.getElementById('csTrack');
  if (!wrap || !track) return;

  const cards = () => track.querySelectorAll('.cs-card');
  let idx = 0;
  let timer = null;
  const mobile = () => window.matchMedia('(max-width: 768px)').matches;
  const visible = () => (mobile() ? 1 : (window.innerWidth < 1100 ? 2 : 4));

  function slideTo(n) {
    const list = cards();
    if (!list.length) return;
    const max = Math.max(0, list.length - visible());
    if (max <= 0) {
      idx = 0;
    } else if (n > max) {
      idx = 0;
    } else if (n < 0) {
      idx = max;
    } else {
      idx = n;
    }
    list.forEach((c, i) => c.classList.toggle('is-active', i === idx));
    const shift = (list[0]?.offsetWidth || 0) + (mobile() ? 12 : 14);
    track.style.transform = `translateX(-${idx * shift}px)`;
  }
  function next() { slideTo(idx + 1); }
  function prev() { slideTo(idx - 1); }
  function start() { timer = setInterval(() => next(), 3000); }
  function stop() { clearInterval(timer); }

  wrap.addEventListener('mouseenter', stop);
  wrap.addEventListener('mouseleave', start);
  wrap.addEventListener('touchstart', stop, { passive: true });
  wrap.addEventListener('touchend', start, { passive: true });
  window.addEventListener('resize', () => slideTo(idx));

  slideTo(0);
  start();
  _csApi = { next, prev };
}
function csNext() { _csApi?.next?.(); }
function csPrev() { _csApi?.prev?.(); }

// ── Scroll Reveal Animations ───────────────────────────────────
function initRevealAnimations() {
  const nodes = document.querySelectorAll('[data-reveal]');
  if (!nodes.length) return;

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduced) {
    nodes.forEach(el => el.classList.add('is-visible'));
    return;
  }

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const delay = parseInt(el.getAttribute('data-reveal-delay') || '0', 10);
      setTimeout(() => el.classList.add('is-visible'), Math.max(0, delay));
      io.unobserve(el);
    });
  }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });

  nodes.forEach(el => io.observe(el));
}

// ── Utility ───────────────────────────────────────────────────
function _esc(s) {
  return String(s || '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}



// ── Chatbot ───────────────────────────────────────────────────
const _chatHistory = [];

function toggleChatbot(force = null) {
  const widget = document.getElementById('chatbotWidget');
  const panel = document.getElementById('chatbotPanel');
  const toggle = document.getElementById('chatbotToggle');
  if (!widget || !panel || !toggle) return;

  const isOpen = widget.classList.contains('open');
  const willOpen = force === null ? !isOpen : !!force;

  widget.classList.toggle('open', willOpen);
  widget.dataset.open = willOpen ? '1' : '0';
  toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  panel.setAttribute('aria-hidden', willOpen ? 'false' : 'true');

  if (willOpen) {
    document.getElementById('chatbotInput')?.focus();
  }
}

function chatbotAppend(text, role = 'bot') {
  const list = document.getElementById('chatbotMsgs');
  if (!list) return;
  const el = document.createElement('div');
  el.className = `chatbot-msg ${role}`;
  el.textContent = text;
  list.appendChild(el);
  list.scrollTop = list.scrollHeight;
}

async function chatbotAsk(question) {
  const resp = await fetch('/api/chat/ask', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrfToken },
    credentials: 'same-origin',
    body: JSON.stringify({ question, history: _chatHistory })
  });
  return resp.json();
}

function chatbotHandoff() {
  const msg = encodeURIComponent('Hi, I need help with my print requirement.');
  window.open(`https://wa.me/${APP.whatsapp}?text=${msg}`, '_blank');
}

function initChatbot() {
  const widget = document.getElementById('chatbotWidget');
  const form = document.getElementById('chatbotForm');
  const input = document.getElementById('chatbotInput');
  const toggle = document.getElementById('chatbotToggle');
  if (!widget || !form || !input || !toggle) return;

  // Always start minimized.
  toggleChatbot(false);

  toggle.addEventListener('click', () => toggleChatbot());

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = (input.value || '').trim();
    if (!q) return;

    input.value = '';
    _chatHistory.push({ role: 'user', content: q });
    chatbotAppend(q, 'user');
    chatbotAppend('Typing...', 'bot');

    try {
      const data = await chatbotAsk(q);
      const list = document.getElementById('chatbotMsgs');
      list?.lastElementChild?.remove();
      if (data.ok) {
        chatbotAppend(data.answer || 'I could not find that right now.');
        _chatHistory.push({ role: 'assistant', content: data.answer || '' });
        if (data.handoff) {
          chatbotAppend('This looks important. Tap "Talk to Human" for priority help.');
        }
      } else {
        chatbotAppend(data.msg || 'Unable to process your question right now.');
      }
    } catch (e2) {
      const list = document.getElementById('chatbotMsgs');
      list?.lastElementChild?.remove();
      chatbotAppend('Network issue. Please try again or contact us on WhatsApp.');
    }
  });
}

// ── DOM Ready ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Load cart count silently
  fetch('/api/cart', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => { if (d.ok) { _cartData = d; updateCartCount(); } })
    .catch(() => {});

  // Init sliders if present
  initBannerSlider('bannerSlider');
  initCategoryShowcase('csWrap');
  initCategorySpotlight('catSpotWrap');
  initProductCarousel('prodCarousel');
  initRevealAnimations();
  initChatbot();
});

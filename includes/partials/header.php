<?php
/**
 * header.php — Site header, mobile drawer, cart drawer, global JS config
 * NOTE: Does NOT output <!DOCTYPE> / <html> / <head> — head.php does that.
 * Cart-drawer is included here once; templates must NOT re-include it.
 */

// ── Navigation data ──────────────────────────────────────────
try {
    $navCategories = \Catalog\ProductCatalog::categories();
    $navProducts   = \Catalog\ProductCatalog::all();
    $bizSettings   = [];
    $settingRows   = Database::rows(
        "SELECT `key`, value FROM settings
         WHERE `key` IN ('biz_name','biz_tagline','biz_whatsapp','biz_phone','biz_email','razorpay_key_id','gst_percent','design_fee')"
    );
    foreach ($settingRows as $r) $bizSettings[$r['key']] = $r['value'];
} catch (\Throwable) {
    $navCategories = []; $navProducts = []; $bizSettings = [];
}

$navBizName  = htmlspecialchars($bizSettings['biz_name']         ?? 'RCS Graphic');
$navPhoneRaw = (string)($bizSettings['biz_phone']                ?? '+91 98765 43210');
$navPhone    = htmlspecialchars($navPhoneRaw);
$navPhoneHref= htmlspecialchars(preg_replace('/\D+/', '', $navPhoneRaw));
$navEmail    = htmlspecialchars($bizSettings['biz_email']        ?? 'hello@rcsgraphic.in');
$navWa       = htmlspecialchars($bizSettings['biz_whatsapp']     ?? '919876543210');
$navRazKey   = htmlspecialchars($bizSettings['razorpay_key_id']  ?? '');
$navGst      = (int)($bizSettings['gst_percent'] ?? 18);
$navDesignFee= (float)($bizSettings['design_fee'] ?? 0);
try { $siteChrome = \Site\SiteChromeManager::payload(); } catch (\Throwable) { $siteChrome = ['settings'=>[], 'navigation'=>[]]; }
$chromeSettings = $siteChrome['settings'] ?? []; $headerNav = $siteChrome['navigation']['header'] ?? [];
if(!$headerNav&&empty($chromeSettings['navigation_v2_initialized']))$headerNav=[['label'=>'Home','url'=>'/','is_active'=>1],['label'=>'About','url'=>'/about','is_active'=>1],['label'=>'Products','url'=>'@products','is_active'=>1],['label'=>'Portfolio','url'=>'/portfolio','is_active'=>1],['label'=>'Blog','url'=>'/blogs','is_active'=>1],['label'=>'Contact','url'=>'/contact','is_active'=>1]];
$headerNavActive=array_values(array_filter($headerNav,static fn($item)=>(int)($item['is_active']??1)===1));
$headerNavByUrl=[];foreach($headerNavActive as $item)$headerNavByUrl[(string)$item['url']]=$item;
$headerHome=$headerNavByUrl['/']??null;$headerAbout=$headerNavByUrl['/about']??null;$headerProducts=$headerNavByUrl['@products']??null;if(!$headerNav)$headerProducts=['label'=>'Products'];
$catIcons    = ['Cards'=>'💳','Brochures'=>'📋','Flyers'=>'📄','Pamphlets'=>'📰','Stationery'=>'📝','Banners'=>'🏳️','Posters'=>'🖼️'];
$currentUri  = $uri ?? '/';
$productsActive = $currentUri === '/products' || $currentUri === '/categories' || str_starts_with($currentUri, '/category/') || str_starts_with($currentUri, '/product/');

$navProductsByCategory = [];
foreach ($navCategories as $cat) {
    $catId = (int)($cat['id'] ?? 0);
    if ($catId <= 0) continue;
    $navProductsByCategory[$catId] = [];
}
foreach ($navProducts as $p) {
    $catId = (int)($p['category_id'] ?? 0);
    if ($catId <= 0 || !array_key_exists($catId, $navProductsByCategory)) continue;
    $navProductsByCategory[$catId][] = $p;
}
?>

<!-- ── Overlays (toast, payment, cart backdrop) ────────────── -->
<div class="toast-wrap" id="tw"></div>
<div class="pay-ov" id="payOv">
  <div class="pay-spin"></div>
  <div class="pay-txt" id="payTxt">Processing…</div>
  <div class="pay-sub">Please don't close this window</div>
</div>
<div class="cart-backdrop" id="cartBack" onclick="closeCart()"></div>

<!-- ═══════════════════════════════════════════════
     SITE HEADER
══════════════════════════════════════════════════ -->
<header class="site-header rcs-site-header" id="siteHeader">
  <?php if((int)($chromeSettings['top_bar_active']??1)===1): ?>
  <div class="topbar rcs-topbar" data-design-target="header.topbar">
    <div class="header-container topbar-inner rcs-header-container rcs-topbar-inner">
      <div class="topbar-left rcs-topbar-left">
        <span>
          <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
          <?php $announcement=htmlspecialchars((string)($chromeSettings['top_bar_text'] ?? 'Free Delivery in Rajkot on All Orders Above ₹999'));$announcementUrl=trim((string)($chromeSettings['top_bar_url']??'')); ?><?php if($announcementUrl!==''): ?><a href="<?= htmlspecialchars($announcementUrl,ENT_QUOTES) ?>"><?= $announcement ?></a><?php else: ?><?= $announcement ?><?php endif; ?>
        </span>
      </div>

      <div class="topbar-right rcs-topbar-right">
        <div class="topbar-links rcs-topbar-links rcs-topbar-contact-links">
          <a href="tel:<?= $navPhoneHref ?>" aria-label="Call <?= $navPhone ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i><?= $navPhone ?></a>
          <a href="mailto:<?= $navEmail ?>" aria-label="Email <?= $navEmail ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i><?= $navEmail ?></a>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <nav class="navbar rcs-navbar" aria-label="Main navigation" data-design-target="header.navbar">
    <div class="header-container navbar-inner rcs-header-container rcs-navbar-inner">
      <a href="/" class="brand rcs-brand" aria-label="<?= $navBizName ?> Home">
        <img src="<?= htmlspecialchars((string)($chromeSettings['header_logo'] ?? '/assets/images/rcs-graphic-logo.png'), ENT_QUOTES) ?>"
             alt="<?= $navBizName ?> Logo"
             class="brand-img rcs-brand-img" data-design-target="header.logo"
             loading="eager"
             decoding="async">
      </a>

      <div class="nav-center rcs-nav-center">
        <ul class="nav-menu rcs-nav-menu">
<?php if($headerHome): ?><li><a href="/" class="nav-link rcs-nav-link <?= $currentUri === '/' ? 'active' : '' ?>" data-design-target="header.nav_links"><?= htmlspecialchars((string)$headerHome['label']) ?></a></li><?php endif; ?>
          <?php if($headerAbout): ?><li><a href="/about" class="nav-link rcs-nav-link <?= $currentUri === '/about' ? 'active' : '' ?>" data-design-target="header.nav_links"><?= htmlspecialchars((string)$headerAbout['label']) ?></a></li><?php endif; ?>
          <?php if($headerProducts): ?><li class="nav-dropdown rcs-nav-dropdown" id="ddWrap">
            <button class="nav-link nav-link-button rcs-nav-link rcs-nav-link-button <?= $productsActive ? 'active' : '' ?>" data-design-target="header.nav_links" id="ddBtn" type="button" aria-expanded="false" aria-haspopup="true">
              <?= htmlspecialchars((string)($headerProducts['label']??'Products')) ?>
              <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="dd-bridge rcs-dd-bridge"></div>
            <div class="dd-panel rcs-dd-panel" id="ddPanel" role="menu">
              <?php if (!empty($navCategories)): ?>
                <div class="dd-cat-lbl rcs-dd-cat-lbl">All Categories</div>
                <div class="dd-category-list rcs-dd-category-list">
                  <?php foreach ($navCategories as $cat): ?>
                    <?php
                      $catId = (int)($cat['id'] ?? 0);
                      $catProducts = $navProductsByCategory[$catId] ?? [];
                    ?>
                    <div class="dd-cat-group rcs-dd-cat-group">
                      <a href="/category/<?= htmlspecialchars($cat['slug']) ?>" class="dd-item dd-cat-link rcs-dd-item rcs-dd-cat-link" role="menuitem">
                        <span class="dd-item-ic rcs-dd-item-ic"><?= htmlspecialchars($cat['icon'] ?? '🖨️') ?></span>
                        <span class="dd-cat-name"><?= htmlspecialchars($cat['name']) ?></span>
                        <?php if (!empty($catProducts)): ?>
                          <span class="dd-flyout-arrow rcs-dd-flyout-arrow" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
                        <?php elseif ((int)($cat['product_count'] ?? 0) > 0): ?>
                          <span class="dd-count rcs-dd-count"><?= (int)$cat['product_count'] ?></span>
                        <?php endif; ?>
                      </a>
                      <?php if (!empty($catProducts)): ?>
                        <div class="dd-product-list rcs-dd-product-list" role="menu" aria-label="<?= htmlspecialchars($cat['name']) ?> products">
                          <div class="dd-product-head">
                            <span><?= htmlspecialchars($cat['name']) ?></span>
                            <small><?= count($catProducts) ?> Products</small>
                          </div>
                          <?php foreach ($catProducts as $p): ?>
                            <a href="/product/<?= htmlspecialchars($p['slug']) ?>" class="dd-item dd-product-link rcs-dd-item rcs-dd-product-link" role="menuitem">
                              <span><?= htmlspecialchars($p['name']) ?></span>
                              <span class="dd-product-arrow" aria-hidden="true">›</span>
                            </a>
                          <?php endforeach; ?>
                          <a href="/category/<?= htmlspecialchars($cat['slug']) ?>" class="dd-item dd-product-link rcs-dd-item rcs-dd-product-link dd-product-all" role="menuitem">
                            <span>View all <?= htmlspecialchars($cat['name']) ?></span>
                            <span class="dd-product-arrow" aria-hidden="true">→</span>
                          </a>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="dd-divider rcs-dd-divider"></div>
              <?php endif; ?>
              <a href="/categories" class="dd-item dd-item-all rcs-dd-item rcs-dd-item-all" role="menuitem">
                <span class="dd-item-ic rcs-dd-item-ic">→</span>
                <span>See All Products</span>
              </a>
            </div>
          </li><?php endif; ?>
          <?php foreach ($headerNavActive as $navItem): $href=(string)$navItem['url']; if(in_array($href,['/','/about','@products'],true))continue; ?>
          <li><a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="nav-link rcs-nav-link <?= $currentUri === $href ? 'active' : '' ?>" data-design-target="header.nav_links"><?= htmlspecialchars((string)$navItem['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="navbar-actions rcs-navbar-actions header-cta-actions">
        <button class="header-cta-btn header-quote-btn" type="button" onclick="openCustomQuoteModal()"><i class="fa-solid fa-calculator" aria-hidden="true"></i><span><?= htmlspecialchars((string)($chromeSettings['header_quote_text']??'Get Custom Quote')) ?></span></button>
        <a href="<?= ($user ?? null) ? '/profile#wishlist' : '/login?redirect=/profile%23wishlist' ?>" class="header-cta-btn header-fav-btn"><i class="fa-regular fa-heart" aria-hidden="true"></i><span>My Wishlist</span></a>
        <a href="<?= ($user ?? null) ? '/profile' : '/login' ?>" class="header-cta-btn header-login-btn" title="<?= htmlspecialchars(($user ?? null) ? (string)($user['name'] ?? 'My Account') : 'My Account') ?>"><i class="fa-regular fa-user" aria-hidden="true"></i><span><?= htmlspecialchars(($user ?? null) ? (string)($user['name'] ?? 'My Account') : 'My Account') ?></span></a>
        <a href="/cart" class="action-btn cart-btn rcs-action-btn rcs-cart-btn" aria-label="Cart">
          <i class="fa-solid fa-cart-shopping"></i>
          <span class="cart-badge rcs-cart-badge" id="cartCount">0</span>
        </a>
        <button class="menu-toggle rcs-menu-toggle" id="hamBtn" onclick="toggleDrawer()" aria-label="Toggle menu" aria-expanded="false" type="button">
          <i class="fa-solid fa-bars"></i>
        </button>
      </div>
    </div>
  </nav>
</header>

<!-- ═══════════════════════════════════════════════
     MOBILE DRAWER
══════════════════════════════════════════════════ -->
<div class="mob-drawer" id="mobDrawer">
  <div class="mob-drawer-inner">
    <?php if($headerHome): ?><a href="/" class="md-item md-home">🏠 <?= htmlspecialchars((string)$headerHome['label']) ?></a><?php endif; ?>
    <?php if($headerAbout): ?><a href="/about" class="md-item" onclick="closeDrawer()">⭐ <?= htmlspecialchars((string)$headerAbout['label']) ?></a><?php endif; ?>

    <!-- Products accordion -->
    <?php if($headerProducts): ?><div class="md-item md-acc" onclick="toggleMobProds()" id="mobProdToggle">
      <span>📦 <?= htmlspecialchars((string)($headerProducts['label']??'Products')) ?></span>
      <svg class="md-acc-arrow" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
    </div>
    <div id="mobProdList" class="md-sub-list" style="display:none">
      <?php foreach ($navCategories as $cat):
        $catId = (int)($cat['id'] ?? 0);
        $catProducts = $navProductsByCategory[$catId] ?? [];
      ?>
      <div class="md-cat-block">
        <div class="md-item md-sub md-cat-row">
          <a href="/category/<?= htmlspecialchars($cat['slug']) ?>" class="md-cat-link" onclick="closeDrawer()">
            <span><?= htmlspecialchars($cat['icon'] ?? '') ?> <?= htmlspecialchars($cat['name']) ?></span>
            <?php if ((int)($cat['product_count'] ?? 0) > 0): ?><span class="md-cat-count"><?= (int)$cat['product_count'] ?></span><?php endif; ?>
          </a>
          <?php if (!empty($catProducts)): ?>
            <button type="button" class="md-cat-expand" onclick="toggleMobCatProducts(this)" aria-label="Show <?= htmlspecialchars($cat['name']) ?> products" aria-expanded="false">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5z"/></svg>
            </button>
          <?php endif; ?>
        </div>
        <?php if (!empty($catProducts)): ?>
          <div class="md-cat-products" hidden>
            <?php foreach ($catProducts as $p): ?>
            <a href="/product/<?= htmlspecialchars($p['slug']) ?>" class="md-item md-sub md-product"
               onclick="closeDrawer()">
              <span>› <?= htmlspecialchars($p['name']) ?></span>
            </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <a href="/categories" class="md-item md-sub md-all" onclick="closeDrawer()">
        → See All Products
      </a>
    </div><?php endif; ?>

    <?php foreach($headerNavActive as $navItem): $mobileHref=(string)$navItem['url'];if(in_array($mobileHref,['/','/about','@products'],true))continue; ?><a href="<?= htmlspecialchars($mobileHref,ENT_QUOTES) ?>" class="md-item" onclick="closeDrawer()">🔗 <?= htmlspecialchars((string)$navItem['label']) ?></a><?php endforeach; ?>
    <!-- Legacy links retained only when no managed navigation exists. --><?php if(!$headerNavActive): ?><a href="/portfolio" class="md-item" onclick="closeDrawer()">🖼️ Portfolio</a>
    <a href="/blogs" class="md-item" onclick="closeDrawer()">📝 Blog</a>
    <a href="/contact" class="md-item" onclick="closeDrawer()">📞 Contact</a><?php endif; ?>
    <button class="md-item md-action md-quote-action" type="button" onclick="openCustomQuoteModal();closeDrawer()">🧾 Get Custom Quote</button>
    <a href="<?= ($user ?? null) ? '/profile#wishlist' : '/login?redirect=/profile%23wishlist' ?>" class="md-item" onclick="closeDrawer()">♡ My Wishlist</a>
    <a href="<?= ($user ?? null) ? '/profile' : '/login' ?>" class="md-item" onclick="closeDrawer()">👤 <?= htmlspecialchars(($user ?? null) ? (string)($user['name'] ?? 'My Account') : 'My Account') ?></a>
    <a href="/cart" class="md-item md-action" onclick="closeDrawer()">
      🛒 Cart <span class="md-cart-badge">0</span>
    </a>

    <?php if ($user ?? null): ?>
      <a href="/my-orders" class="md-item">📋 My Orders</a>
      <a href="/profile" class="md-item">👤 My Profile</a>
      <a href="/logout"    class="md-item">👤 <?= htmlspecialchars($user['name']) ?> (Logout)</a>
    <?php else: ?>
      <a href="/register"  class="md-item md-start" onclick="closeDrawer()">✨ New Customer? Start Here</a>
      <a href="/login"     class="md-item">👤 Login / Register</a>
    <?php endif; ?>


    <!-- WhatsApp quick action in drawer -->
    <div style="padding:14px 18px;border-top:1px solid var(--border);margin-top:4px">
      <button onclick="window.open('https://wa.me/<?= $navWa ?>','_blank');closeDrawer()"
              class="btn btn-green btn-full" style="border-radius:10px">
        💬 WhatsApp Us
      </button>
    </div>
  </div>
</div>
<!-- Drawer backdrop -->
<div class="mob-backdrop" id="mobBack" onclick="closeDrawer()"></div>


<!-- Custom Quote Modal -->
<div class="custom-quote-modal" id="customQuoteModal" aria-hidden="true">
  <div class="custom-quote-backdrop" onclick="closeCustomQuoteModal()"></div>
  <section class="custom-quote-dialog" role="dialog" aria-modal="true" aria-labelledby="customQuoteTitle">
    <button class="custom-quote-close" type="button" onclick="closeCustomQuoteModal()" aria-label="Close custom quote form">×</button>
    <header class="custom-quote-head">
      <div class="custom-quote-kicker"><span><i class="fa-solid fa-calculator" aria-hidden="true"></i></span> Request Quote</div>
      <h2 id="customQuoteTitle">Get a <strong>Custom Quote</strong></h2>
    </header>
    <form class="custom-quote-form" id="customQuoteForm" onsubmit="submitCustomQuote(event)">
      <div class="custom-quote-grid">
        <label>Your Name *<input name="customer_name" placeholder="John Doe" autocomplete="name" required></label>
        <label>WhatsApp Number *<input name="phone" placeholder="9876543210" autocomplete="tel" required></label>
        <label>Email (for account matching)<input name="email" placeholder="you@example.com" autocomplete="email"></label>
        <label>Product Name *<input name="product_name" placeholder="Eg: Business Card" required></label>
        <label>Size / Dimension<input name="size_dimension" placeholder="Eg: 3.5x2 inches"></label>
        <label>Material Type<input name="material_type" placeholder="Eg: 300gsm Board"></label>
        <label>Quantity Needed<input name="quantity" placeholder="Eg: 100"></label>
      </div>
      <label>Specific Finish / Instructions<textarea name="instructions" placeholder="Describe lamination, corners, etc..."></textarea></label>
      <div class="custom-quote-message" id="customQuoteMessage" role="status"></div>
      <button class="custom-quote-submit" id="customQuoteSubmit" type="submit">Request Quotation <span>→</span></button>
    </form>
  </section>
</div>

<!-- ═══════════════════════════════════════════════
     CART DRAWER (included once here only)
══════════════════════════════════════════════════ -->
<?php include __DIR__ . '/cart-drawer.php'; ?>

<!-- ── Global JS Config ──────────────────────────────────────── -->
<script>
/* Global app config — available to all page scripts */
const APP = {
  csrfToken:  '<?= htmlspecialchars($csrf ?? '') ?>',
  razorpayKey:'<?= $navRazKey ?>',
  whatsapp:   '<?= $navWa ?>',
  gstPercent: <?= $navGst ?>,
  designFee:  <?= $navDesignFee ?>,   // RCS design charge from admin settings
  user:       <?= json_encode($user ?? null) ?>,
  apiBase:    ''
};

function openCustomQuoteModal(){const m=document.getElementById('customQuoteModal'); if(!m)return; m.classList.add('open'); m.setAttribute('aria-hidden','false'); document.body.classList.add('quote-modal-open'); setTimeout(()=>m.querySelector('input[name="customer_name"]')?.focus(),80);}
function closeCustomQuoteModal(){const m=document.getElementById('customQuoteModal'); if(!m)return; m.classList.remove('open'); m.setAttribute('aria-hidden','true'); document.body.classList.remove('quote-modal-open');}
async function submitCustomQuote(e){
  e.preventDefault();
  const form=e.currentTarget; const btn=document.getElementById('customQuoteSubmit'); const msg=document.getElementById('customQuoteMessage');
  const payload=Object.fromEntries(new FormData(form).entries()); payload.source_page=window.location.pathname;
  btn.disabled=true; btn.innerHTML='Submitting...'; msg.className='custom-quote-message'; msg.textContent='';
  try{
    const res=await fetch('/api/custom-quotes',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':APP.csrfToken},credentials:'same-origin',body:JSON.stringify(payload)});
    const data=await res.json();
    if(!data.ok) throw new Error(data.msg||'Could not submit request');
    msg.classList.add('success'); msg.textContent='Quotation request sent successfully.'; form.reset();
    window.setTimeout(closeCustomQuoteModal,5000);
  }catch(err){msg.classList.add('error'); msg.textContent=err.message||'Could not submit request';}
  finally{btn.disabled=false; btn.innerHTML='Request Quotation <span>→</span>';}
}
document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeCustomQuoteModal(); });
</script>
<script src="/assets/js/app.js"></script>

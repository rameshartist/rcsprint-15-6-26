<?php
$pageTitle = 'Customers — RCS Admin';
$currentAdmPage = 'customers';
$admMainClass = 'adm-main--customers';
include __DIR__ . '/layout.php';
?>
<div class="crm-page">
  <section class="crm-hero">
    <div>
      <span class="crm-eyebrow">Customer CRM</span>
      <h1>Customers</h1>
      <p>Track customer value, follow up faster, and find repeat-order opportunities from one clean command center.</p>
    </div>
    <div class="crm-hero-actions">
      <button class="crm-btn crm-btn--ghost" type="button" onclick="exportCustomerCsv()">⬇ Export</button>
      <a class="crm-btn crm-btn--primary" href="/admin/orders">View Orders</a>
    </div>
  </section>

  <section class="crm-stats" id="crmStats" aria-label="Customer summary">
    <article><span>Total Customers</span><strong>--</strong><small>Registered accounts</small></article>
    <article><span>Repeat Customers</span><strong>--</strong><small>2+ orders</small></article>
    <article><span>High Value</span><strong>--</strong><small>₹25k+ spent</small></article>
    <article><span>Inactive</span><strong>--</strong><small>No order 60+ days</small></article>
    <article><span>Revenue</span><strong>--</strong><small>Customer lifetime value</small></article>
  </section>

  <section class="crm-toolbar">
    <label class="crm-search"><span>🔎</span><input id="crmSearch" type="search" placeholder="Search name, phone, email, company..." autocomplete="off"></label>
    <div class="crm-tabs" role="tablist" aria-label="Customer filters">
      <button class="act" data-filter="all" type="button">All</button>
      <button data-filter="new" type="button">New</button>
      <button data-filter="repeat" type="button">Repeat</button>
      <button data-filter="high_value" type="button">High Value</button>
      <button data-filter="inactive" type="button">Inactive</button>
      <button data-filter="no_orders" type="button">No Orders</button>
    </div>
    <select id="crmSort" class="crm-sort" aria-label="Sort customers">
      <option value="spent">Top Spent</option>
      <option value="last_order">Last Order</option>
      <option value="orders">Most Orders</option>
      <option value="newest">Newest Joined</option>
      <option value="name">Name A-Z</option>
    </select>
  </section>

  <section class="crm-content">
    <div id="custList" class="crm-grid"><div class="crm-loading"><div class="pay-spin" style="border-top-color:var(--blue);margin:0 auto 12px"></div>Loading customers…</div></div>
  </section>
</div>

<aside class="crm-drawer" id="crmDrawer" aria-hidden="true">
  <div class="crm-drawer-panel">
    <button class="crm-drawer-close" type="button" onclick="closeCustomerDrawer()" aria-label="Close">×</button>
    <div id="crmDrawerBody"></div>
  </div>
</aside>

<script>
let CRM_CUSTOMERS = [];
let CRM_FILTER = 'all';
let CRM_TEMPLATES = {};
const CRM_WA = '<?= htmlspecialchars($bizSettings['biz_whatsapp'] ?? '') ?>';
const money = n => '₹' + Number(n || 0).toLocaleString('en-IN');
const escH = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const digits = s => String(s || '').replace(/\D/g,'');
const dt = s => s ? new Date(String(s).replace(' ', 'T')).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}) : 'No orders yet';
const rel = s => {
  if (!s) return 'No orders yet';
  const d = new Date(String(s).replace(' ', 'T')).getTime();
  if (!d) return dt(s);
  const days = Math.max(0, Math.floor((Date.now() - d) / 86400000));
  if (days === 0) return 'Today';
  if (days === 1) return 'Yesterday';
  return days + ' days ago';
};
const initials = name => (String(name || '?').trim().split(/\s+/).map(w=>w[0] || '').join('').toUpperCase().slice(0,2) || '?');
const colorFor = name => {
  const colors = ['#2563eb','#0891b2','#7c3aed','#db2777','#ea580c','#059669'];
  let h = 0; for (const ch of String(name || '')) h = ch.charCodeAt(0) + ((h << 5) - h);
  return colors[Math.abs(h) % colors.length];
};

async function loadCustomerTemplates() {
  try {
    const res = await fetch('/admin/api/whatsapp-templates', {credentials:'same-origin'}).then(r=>r.json());
    (res.templates || []).forEach(t => { CRM_TEMPLATES[t.template_key] = t.body || ''; });
  } catch (e) {}
}
async function loadCustomers() {
  const res = await fetch('/admin/api/customers', {credentials:'same-origin'}).then(r=>r.json());
  CRM_CUSTOMERS = res.customers || [];
  renderStats(res.summary || buildSummary(CRM_CUSTOMERS));
  renderCustomers();
}
function buildSummary(list) {
  return {
    total_customers: list.length,
    repeat_customers: list.filter(c=>Number(c.order_count || 0) >= 2).length,
    high_value_customers: list.filter(c=>Number(c.total_spent || 0) >= 25000).length,
    inactive_customers: list.filter(c=>Number(c.order_count || 0) > 0 && String(c.segment || '').includes('inactive')).length,
    total_revenue: list.reduce((sum,c)=>sum+Number(c.total_spent||0),0),
  };
}
function renderStats(s) {
  const cards = document.querySelectorAll('#crmStats article');
  const values = [Number(s.total_customers||0).toLocaleString('en-IN'), Number(s.repeat_customers||0).toLocaleString('en-IN'), Number(s.high_value_customers||0).toLocaleString('en-IN'), Number(s.inactive_customers||0).toLocaleString('en-IN'), money(s.total_revenue||0)];
  cards.forEach((card,i)=> card.querySelector('strong').textContent = values[i]);
}
function filteredCustomers() {
  const q = document.getElementById('crmSearch').value.trim().toLowerCase();
  const sort = document.getElementById('crmSort').value;
  let list = CRM_CUSTOMERS.filter(c => {
    const hay = `${c.name||''} ${c.phone||''} ${c.email||''} ${c.company||''}`.toLowerCase();
    if (q && !hay.includes(q)) return false;
    if (CRM_FILTER === 'all') return true;
    if (CRM_FILTER === 'new') return c.segment === 'new';
    if (CRM_FILTER === 'repeat') return Number(c.order_count||0) >= 2;
    if (CRM_FILTER === 'high_value') return Number(c.total_spent||0) >= 25000;
    if (CRM_FILTER === 'inactive') return c.segment === 'inactive';
    if (CRM_FILTER === 'no_orders') return Number(c.order_count||0) === 0;
    return true;
  });
  list.sort((a,b)=> {
    if (sort === 'last_order') return new Date(b.last_order_at || 0) - new Date(a.last_order_at || 0);
    if (sort === 'orders') return Number(b.order_count||0) - Number(a.order_count||0);
    if (sort === 'newest') return new Date(b.created_at || 0) - new Date(a.created_at || 0);
    if (sort === 'name') return String(a.name||'').localeCompare(String(b.name||''));
    return Number(b.total_spent||0) - Number(a.total_spent||0);
  });
  return list;
}
function segmentLabel(c) {
  if (Number(c.order_count||0) === 0) return ['No Orders','slate'];
  if (Number(c.total_spent||0) >= 25000) return ['High Value','gold'];
  if (Number(c.order_count||0) >= 2) return ['Repeat','green'];
  if (c.segment === 'inactive') return ['Inactive','amber'];
  return ['New','blue'];
}
function upsellText(c) {
  const p = String(c.last_product || '').toLowerCase();
  if (p.includes('business') || p.includes('card')) return 'Upsell: Letterhead + Envelope';
  if (p.includes('brochure')) return 'Upsell: Flyers + Banners';
  if (p.includes('banner')) return 'Upsell: Flyers + Visiting Cards';
  if (p.includes('sticker') || p.includes('label')) return 'Upsell: Packaging Labels';
  return Number(c.order_count||0) ? 'Suggest: Reorder reminder' : 'Suggest: First-order offer';
}
function renderCustomers() {
  const list = filteredCustomers();
  const wrap = document.getElementById('custList');
  if (!list.length) { wrap.innerHTML = '<div class="crm-empty">👥<strong>No matching customers</strong><span>Try another filter or search term.</span></div>'; return; }
  wrap.innerHTML = list.map(c => {
    const [label,tone] = segmentLabel(c);
    const phone = digits(c.phone);
    return `<article class="crm-card crm-card--${tone}">
      <button class="crm-card-main" type="button" onclick="openCustomerDrawer(${Number(c.id)})">
        <span class="crm-avatar" style="background:${colorFor(c.name)}">${escH(initials(c.name))}</span>
        <span class="crm-info"><strong>${escH(c.name || 'Customer')}</strong><small>${escH(c.customer_code || 'Customer ID pending')} · ${escH(c.company || c.email || 'No company')}</small><em>${escH(c.phone || '-')} ${c.email ? '· ' + escH(c.email) : ''}</em></span>
        <span class="crm-value"><b>${money(c.total_spent)}</b><small>${Number(c.order_count||0)} order(s)</small></span>
      </button>
      <div class="crm-card-meta"><span class="crm-pill crm-pill--${tone}">${label}</span><span>${escH(rel(c.last_order_at))}</span><span>${escH(upsellText(c))}</span></div>
      <div class="crm-card-actions">
        <button type="button" onclick="openCustomerChat(${Number(c.id)})">💬 WA</button>
        <a href="tel:${phone}">📞 Call</a>
        <button type="button" class="crm-danger" onclick="deleteCustomer(${Number(c.id)})">🗑 Delete</button>
        <a href="/admin/orders?search=${encodeURIComponent(c.phone || c.email || c.name || '')}">📋 Orders</a>
      </div>
    </article>`;
  }).join('');
}
function openCustomerDrawer(id) {
  const c = CRM_CUSTOMERS.find(x => Number(x.id) === Number(id));
  if (!c) return;
  const recent = c.recent_orders || [];
  document.getElementById('crmDrawerBody').innerHTML = `<div class="crm-drawer-head"><span class="crm-avatar crm-avatar--lg" style="background:${colorFor(c.name)}">${escH(initials(c.name))}</span><div><h2>${escH(c.name || 'Customer')}</h2><p>${escH(c.company || 'No company added')}</p></div></div>
  <div class="crm-drawer-grid"><article><span>Total Spent</span><strong>${money(c.total_spent)}</strong></article><article><span>Orders</span><strong>${Number(c.order_count||0)}</strong></article><article><span>Avg Order</span><strong>${money(c.avg_order_value)}</strong></article><article><span>Last Order</span><strong>${escH(rel(c.last_order_at))}</strong></article></div>
  <section class="crm-drawer-section"><h3>Contact</h3><p><b>${escH(c.customer_code || 'Customer ID pending')}</b><br>${escH(c.phone || '-')}<br>${escH(c.email || '-')}</p><div class="crm-drawer-actions"><button onclick="openCustomerChat(${Number(c.id)})">Open WhatsApp</button><button onclick="sendWa(${Number(c.id)}, 'reorder')">Reorder WhatsApp</button><button onclick="sendWa(${Number(c.id)}, 'upsell')">Upsell Message</button><a href="/admin/orders?search=${encodeURIComponent(c.phone || c.email || c.name || '')}">View Orders</a><button class="crm-danger" onclick="deleteCustomer(${Number(c.id)})">Delete Customer</button></div></section>
  <section class="crm-drawer-section"><h3>Sales Insight</h3><p><b>${escH(upsellText(c))}</b><br>Last product: ${escH(c.last_product || 'No product yet')}<br>Joined: ${escH(dt(c.created_at))}</p></section>
  <section class="crm-drawer-section"><h3>Recent Orders</h3>${recent.length ? recent.map(o=>`<a class="crm-order-row" href="/admin/orders?search=${encodeURIComponent(o.order_id)}"><span>${escH(o.order_id)}</span><b>${money(o.total_amount)}</b><em>${escH(o.status)}</em></a>`).join('') : '<p>No orders yet. Send a first-order offer.</p>'}</section>`;
  document.getElementById('crmDrawer').classList.add('open');
  document.getElementById('crmDrawer').setAttribute('aria-hidden','false');
}
function closeCustomerDrawer(){ document.getElementById('crmDrawer').classList.remove('open'); document.getElementById('crmDrawer').setAttribute('aria-hidden','true'); }
function fillCrmTemplate(body, c, type) {
  const data = {
    customer_name: c.name || 'Customer', customer_phone: c.phone || '', customer_email: c.email || '', customer_code: c.customer_code || '',
    order_count: String(c.order_count || 0), last_product: c.last_product || 'No previous product', suggestion: upsellText(c).replace('Upsell: ', '').replace('Suggest: ', ''),
    business_name: 'RCS Graphic', business_phone: CRM_WA, business_whatsapp: CRM_WA
  };
  return String(body || '').replace(/\{([a-z0-9_]+)\}/gi, (_, key) => Object.prototype.hasOwnProperty.call(data, key) ? data[key] : `{${key}}`);
}
function openCustomerChat(id) {
  const c = CRM_CUSTOMERS.find(x=>Number(x.id)===Number(id)); if (!c) return;
  const phone = digits(c.phone || CRM_WA); if (!phone) return alert('Phone number not available');
  window.open(`https://wa.me/${phone}`, '_blank');
}
function sendWa(id, type) {
  const c = CRM_CUSTOMERS.find(x=>Number(x.id)===Number(id)); if (!c) return;
  const phone = digits(c.phone || CRM_WA); if (!phone) return alert('Phone number not available');
  const key = type === 'upsell' ? 'customer_upsell' : (Number(c.order_count||0) ? 'customer_reorder' : 'customer_welcome');
  const fallback = type === 'upsell'
    ? `Hi ${(c.name || 'Customer').split(' ')[0]} ji, ${upsellText(c).replace('Upsell: ','')} useful ho sakta hai. Details ke liye reply kare.`
    : `Hi ${(c.name || 'Customer').split(' ')[0]} ji, agar aapko print order/reorder chahiye to hum fast process kar sakte hain.`;
  const msg = fillCrmTemplate(CRM_TEMPLATES[key] || fallback, c, type);
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
}
async function deleteCustomer(id) {
  const c = CRM_CUSTOMERS.find(x=>Number(x.id)===Number(id));
  if (!confirm(`Delete/deactivate customer ${c?.name || '#' + id}?`)) return;
  const res = await fetch(`/admin/api/customers/${id}`, {method:'DELETE', credentials:'same-origin'}).then(r=>r.json());
  if (!res.ok) return alert(res.msg || 'Could not delete customer');
  CRM_CUSTOMERS = CRM_CUSTOMERS.filter(x => Number(x.id) !== Number(id));
  closeCustomerDrawer();
  renderStats(buildSummary(CRM_CUSTOMERS));
  renderCustomers();
}
function exportCustomerCsv() {
  const rows = [['Customer ID','Name','Phone','Email','Company','Orders','Total Spent','Last Order']].concat(filteredCustomers().map(c=>[c.customer_code,c.name,c.phone,c.email,c.company,c.order_count,c.total_spent,c.last_order_at]));
  const csv = '\ufeff' + rows.map(r=>r.map(v=>'"'+String(v??'').replace(/"/g,'""')+'"').join(',')).join('\n');
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], {type:'text/csv'})); a.download = 'customers.csv'; a.click(); URL.revokeObjectURL(a.href);
}
document.querySelectorAll('.crm-tabs button').forEach(btn => btn.addEventListener('click', () => { document.querySelectorAll('.crm-tabs button').forEach(b=>b.classList.remove('act')); btn.classList.add('act'); CRM_FILTER = btn.dataset.filter || 'all'; renderCustomers(); }));
document.getElementById('crmSearch').addEventListener('input', renderCustomers);
document.getElementById('crmSort').addEventListener('change', renderCustomers);
(async()=>{ await loadCustomerTemplates(); await loadCustomers(); })();
</script>

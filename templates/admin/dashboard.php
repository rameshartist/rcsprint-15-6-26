<?php
$pageTitle = 'Dashboard — RCS Admin';
$currentAdmPage = 'dashboard';
include __DIR__ . '/layout.php';
?>
<div class="dash-ref-page">
  <div class="dash-ref-titlebar">
    <div>
      <h1>Dashboard</h1>
      <p>Welcome back! Here’s what’s happening with your business today.</p>
    </div>
  </div>

  <section class="dash-ref-kpis dash-ref-kpis-top" aria-label="Primary dashboard metrics">
    <a class="dash-ref-kpi kpi-bag" href="/admin/orders"><span><svg viewBox="0 0 24 24"><path d="M7 9V7a5 5 0 0 1 10 0v2h2l1 12H4L5 9h2Zm2 0h6V7a3 3 0 0 0-6 0v2Z"/></svg></span><div><b id="ds-total-orders">—</b><strong>All Orders</strong><small>Total order list</small></div></a>
    <a class="dash-ref-kpi kpi-shield" href="/admin/orders"><span>◈</span><div><b id="ds-today-rev">—</b><strong>Today’s Revenue</strong><small id="tr-today-rev" class="pos">Loading trend…</small></div></a>
    <a class="dash-ref-kpi kpi-chart" href="/admin/orders"><span>↗</span><div><b id="ds-month-rev">—</b><strong>This Month Revenue</strong><small id="tr-month-rev" class="pos">Loading trend…</small></div></a>
    <a class="dash-ref-kpi kpi-rupee" href="/admin/orders"><span>₹</span><div><b id="ds-rev">—</b><strong>Total Revenue</strong><small class="pos">Paid orders</small></div></a>
    <a class="dash-ref-kpi kpi-aov" href="/admin/orders"><span>▥</span><div><b id="ds-aov">—</b><strong>Average Order Value</strong><small id="tr-aov" class="pos">Loading trend…</small></div></a>
    <a class="dash-ref-kpi kpi-users" href="/admin/customers"><span>●</span><div><b id="ds-customers">—</b><strong>Total Customers</strong><small id="tr-customers" class="pos">Loading trend…</small></div></a>
  </section>

  <section class="dash-ref-main-row">
    <article class="dash-ref-card dash-ref-actions dash-ref-actions--full">
      <div class="dash-ref-card-head"><div><h2>Quick Actions</h2><small id="dashUpdated">Live dashboard</small></div></div>
      <div class="dash-ref-actions-grid">
        <a href="/admin/products/new"><span class="c-blue">⬡</span><b>Add Product</b></a><a href="/admin/orders"><span class="c-green">▤</span><b>View Orders</b></a><a href="/admin/coupons"><span class="c-purple">◆</span><b>Create Coupon</b></a><a href="/admin/export/orders" target="_blank"><span class="c-orange">⇩</span><b>Export Orders</b></a><a href="/admin/customers"><span class="c-pink">●</span><b>Manage Users</b></a><a href="/admin/analytics"><span class="c-indigo">▮</span><b>Reports</b></a><a href="/admin/settings"><span class="c-slate">⚙</span><b>Settings</b></a>
      </div>
    </article>

    <article class="dash-ref-card dash-ref-queue">
      <div class="dash-ref-card-head"><div><h2>Production Queue</h2></div><a href="/admin/orders">View All</a></div>
      <div class="dash-ref-queue-list" id="prodQueue"><div class="dash-ref-empty">Loading…</div></div>
    </article>

    <article class="dash-ref-card dash-ref-products">
      <div class="dash-ref-card-head"><div><h2>Top Products</h2></div><a href="/admin/products">View All</a></div>
      <div class="dash-ref-product-list" id="topProds"><div class="dash-ref-empty">Loading…</div></div>
    </article>
  </section>

</div>

<script>
const STATUS_COLORS = {received:'st-blue',processing:'st-amber',printing:'st-orange',ready:'st-green',delivered:'st-ink',cancelled:'st-red',whatsapp_pending:'st-amber'};
const STATUS_LABELS = {received:'Received',processing:'Processing',printing:'Printing',ready:'Ready',delivered:'Delivered',cancelled:'Cancelled',whatsapp_pending:'WA Pending'};
const money = n => '₹'+Number(n||0).toLocaleString('en-IN');
const escH = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const pct = (part,total) => Math.max(3, Math.round((Number(part||0) / Math.max(Number(total||0), 1)) * 100));
const trendText = (value, label) => {
  const n = Number(value || 0);
  const arrow = n < 0 ? '↓' : '↑';
  return `${arrow} ${Math.abs(n).toFixed(2)}% ${label || ''}`.trim();
};
function setTrend(id, value, label, invert=false){
  const el = document.getElementById(id);
  if (!el) return;
  const n = Number(value || 0);
  el.textContent = trendText(n, label);
  el.classList.toggle('pos', invert ? n <= 0 : n >= 0);
  el.classList.toggle('neg', invert ? n > 0 : n < 0);
}

async function loadDash(){
  const btn = document.getElementById('dashRefresh');
  const upd = document.getElementById('dashUpdated');
  if (btn) btn.classList.add('loading');
  if (upd) upd.textContent = 'Refreshing live data…';
  let res;
  try {
    res = await fetch('/admin/api/dashboard', { credentials:'same-origin' }).then(r=>r.json());
  } catch (e) {
    if (upd) upd.textContent = 'Could not load dashboard. Check connection and retry.';
    if (btn) btn.classList.remove('loading');
    return;
  }
  if (!res.ok) { if (btn) btn.classList.remove('loading'); return; }
  const s = res.stats || {};
  document.getElementById('ds-total-orders').textContent = Number(s.total_orders||0).toLocaleString('en-IN');
  document.getElementById('ds-rev').textContent = money(s.total_revenue);
  document.getElementById('ds-today-rev').textContent = money(s.today_revenue);
  document.getElementById('ds-month-rev').textContent = money(s.month_revenue || 0);
  document.getElementById('ds-aov').textContent = money(s.avg_order_value || 0);
  document.getElementById('ds-customers').textContent = Number(s.total_customers||0).toLocaleString('en-IN');
  setTrend('tr-today-rev', s.today_revenue_trend, s.today_revenue_trend_label);
  setTrend('tr-month-rev', s.month_revenue_trend, s.month_revenue_trend_label);
  setTrend('tr-aov', s.avg_order_value_trend, s.avg_order_value_trend_label);
  setTrend('tr-customers', s.total_customers_trend, s.total_customers_trend_label);
  if (upd) upd.textContent = 'Last updated: ' + new Date().toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit'});
  if (btn) btn.classList.remove('loading');
  drawQueue(res.queue || {});
  drawProducts(res.top_products || []);
}
function drawRevenue(monthly){
  const el = document.getElementById('revChart');
  if (!monthly.length) { el.innerHTML = '<div class="dash-ref-empty">No revenue data yet.</div>'; return; }
  const w=720,h=318,padL=66,padR=22,padT=24,padB=44;
  const vals = monthly.map(m=>Number(m.revenue||0));
  const max = Math.max(...vals, 1);
  const plotW = w-padL-padR, plotH = h-padT-padB;
  const pts = monthly.map((m,i)=>[padL + (i*(plotW/Math.max(monthly.length-1,1))), padT + plotH - ((Number(m.revenue||0)/max)*plotH)]);
  const path = pts.map((p,i)=>`${i?'L':'M'}${p[0].toFixed(1)} ${p[1].toFixed(1)}`).join(' ');
  const area = `${path} L${pts.at(-1)[0].toFixed(1)} ${h-padB} L${padL} ${h-padB} Z`;
  const labels = [max, max*.75, max*.5, max*.25, 0];
  el.innerHTML = `<svg viewBox="0 0 ${w} ${h}" aria-label="Revenue overview" role="img">
    <defs><linearGradient id="dashRefRev" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#2563eb" stop-opacity=".18"/><stop offset="1" stop-color="#2563eb" stop-opacity=".03"/></linearGradient></defs>
    ${labels.map((v,i)=>{const y=padT+i*(plotH/4);return `<line x1="${padL}" y1="${y}" x2="${w-padR}" y2="${y}"/><text x="${padL-12}" y="${y+4}" text-anchor="end">${money(Math.round(v))}</text>`}).join('')}
    <path d="${area}" fill="url(#dashRefRev)"></path><path d="${path}" fill="none" stroke="#2563eb" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
    ${pts.map((p,i)=>`<circle cx="${p[0]}" cy="${p[1]}" r="5"/><text class="month" x="${p[0]}" y="${h-10}" text-anchor="middle">${escH(monthly[i].month)}</text>`).join('')}
  </svg>`;
}
function drawQueue(q){
  const rows = [
    ['New Order','Fresh paid orders', q.new_order || 0, '●','purple', 'new_order'],
    ['Received','Order received by team', q.received || 0, '▣','blue', 'received'],
    ['Design Approved','Ready after design approval', q.design_approved || 0, '✓','green', 'design_approved'],
    ['Printing','In printing process', q.printing || 0, '▤','orange', 'printing'],
    ['Other Process','Finishing / processing', q.other_process || 0, '⌘','cyan', 'other_process'],
    ['Dispatched','Ready to dispatch', q.ready || 0, '▰','green', 'ready'],
    ['Delivered','Completed orders', q.delivered || 0, '✓','blue', 'delivered']
  ];
  document.getElementById('prodQueue').innerHTML = rows.map(r=>`<a class="dash-ref-queue-row" href="/admin/orders?status=${encodeURIComponent(r[5])}"><span class="${r[4]}">${r[3]}</span><div><strong>${r[0]}</strong><small>${r[1]}</small></div><b class="${r[4]}">${r[2]}</b></a>`).join('');
}
function drawProducts(products){
  const el = document.getElementById('topProds');
  if (!products.length) { el.innerHTML = '<div class="dash-ref-empty">No product data yet.</div>'; return; }
  const total = products.reduce((sum,p)=>sum+Number(p.count||0),0);
  el.innerHTML = products.slice(0,8).map(p=>`<div class="dash-ref-product-row"><div><strong>${escH(p.product_name)}</strong><small>${Number(p.count||0)} orders</small></div><em><i style="width:${pct(p.count,total)}%"></i></em><b>${pct(p.count,total)}%</b></div>`).join('');
}
function drawOrders(orders){
  const el = document.getElementById('newOrdersList');
  if (!orders.length) { el.innerHTML = '<tr><td colspan="7"><div class="dash-ref-empty">No new orders pending review.</div></td></tr>'; return; }
  el.innerHTML = orders.map(o=>`<tr><td><a href="/admin/orders?search=${encodeURIComponent(o.order_id)}">#${escH(o.order_id)}</a></td><td>${escH(o.customer_name||'-')}</td><td>${escH(o.product_summary || (Number(o.item_count||0)+' item(s)'))}</td><td>${money(o.total_amount)}</td><td><span class="dash-ref-status ${STATUS_COLORS[o.status]||'st-blue'}">${STATUS_LABELS[o.status]||escH(o.status)}</span></td><td>${fmt(o.created_at)}</td><td><span class="dash-ref-new">NEW</span></td></tr>`).join('');
}
function fmt(d){const dt=new Date(String(d).replace(' ','T'));return Number.isNaN(dt.getTime())?escH(d):dt.toLocaleDateString('en-IN',{day:'2-digit',month:'short'})+', '+dt.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});}
document.getElementById('dashRefresh')?.addEventListener('click', loadDash);
loadDash();
</script>
    </div></div></div>
</body></html>

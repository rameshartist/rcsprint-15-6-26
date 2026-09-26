<?php
$pageTitle = 'Dashboard — RCS Admin';
$currentAdmPage = 'dashboard';
include __DIR__ . '/layout.php';
$dashboardAdminName = trim((string)($admin['name'] ?? 'Admin')) ?: 'Admin';
$dashboardFirstName = preg_split('/\s+/', $dashboardAdminName)[0] ?? $dashboardAdminName;
?>
<div class="dash-ref-page">
  <header class="dash-ref-titlebar">
    <div>
      <span class="dash-ref-eyebrow">Business overview</span>
      <h1>Welcome back, <em><?= htmlspecialchars($dashboardFirstName) ?>!</em> <span aria-hidden="true">👋</span></h1>
      <p>Here’s what’s happening with your printing business today.</p>
    </div>
    <div class="dash-ref-title-actions">
      <span class="dash-ref-date" aria-label="Current reporting period">▣ <b><?= htmlspecialchars(date('01 M Y')) ?> – <?= htmlspecialchars(date('t M Y')) ?></b></span>
      <button class="dash-ref-refresh" id="dashRefresh" type="button">↻ <span>Refresh</span></button>
      <a class="dash-ref-add" href="/admin/orders?status=new_order">＋ View Orders</a>
    </div>
  </header>

  <section class="dash-ref-kpis dash-ref-kpis-top" aria-label="Primary dashboard metrics">
    <a class="dash-ref-kpi kpi-purple" href="/admin/orders"><span class="dash-ref-kpi-icon"><svg viewBox="0 0 24 24"><path d="M7 9V7a5 5 0 0 1 10 0v2h2l1 12H4L5 9h2Zm2 0h6V7a3 3 0 0 0-6 0v2Z"/></svg></span><div><b id="ds-total-orders">—</b><strong>Total Orders</strong><small id="tr-total-orders">All recorded orders</small></div><i>▥</i></a>
    <a class="dash-ref-kpi kpi-green" href="/admin/orders"><span class="dash-ref-kpi-icon">₹</span><div><b id="ds-today-rev">—</b><strong>Today’s Revenue</strong><small id="tr-today-rev">Loading trend…</small></div><i>▥</i></a>
    <a class="dash-ref-kpi kpi-blue" href="/admin/orders"><span class="dash-ref-kpi-icon"><svg viewBox="0 0 24 24"><path d="m21 8-9-5-9 5 9 5 9-5Zm-16 3.5V16l7 4 7-4v-4.5l-7 4-7-4Z"/></svg></span><div><b id="ds-month-rev">—</b><strong>This Month Revenue</strong><small id="tr-month-rev">Loading trend…</small></div><i>▥</i></a>
    <a class="dash-ref-kpi kpi-orange" href="/admin/orders"><span class="dash-ref-kpi-icon"><svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v15H6V2Zm8 2v4h4l-4-4ZM9 13h8v-2H9v2Zm0 4h8v-2H9v2Z"/></svg></span><div><b id="ds-rev">—</b><strong>Total Revenue</strong><small class="pos">Paid orders</small></div><i>▥</i></a>
    <a class="dash-ref-kpi kpi-pink" href="/admin/customers"><span class="dash-ref-kpi-icon"><svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 1c-3 0-6 1.5-6 4v3h12v-3c0-2.5-3-4-6-4ZM8 14c-3.3 0-6 1.5-6 4v2h6v-3c0-1.1.4-2.1 1.1-2.9L8 14Z"/></svg></span><div><b id="ds-customers">—</b><strong>Total Customers</strong><small id="tr-customers">Loading trend…</small></div><i>▥</i></a>
  </section>

  <section class="dash-ref-main-row">
    <article class="dash-ref-card dash-ref-revenue">
      <div class="dash-ref-card-head"><div><h2>Revenue Overview</h2><small id="dashUpdated">Syncing live dashboard…</small></div><span class="dash-ref-select">Last 12 Months⌄</span></div>
      <div class="dash-ref-chart" id="revChart"><div class="dash-ref-empty">Loading revenue data…</div></div>
    </article>
    <article class="dash-ref-card dash-ref-queue">
      <div class="dash-ref-card-head"><div><h2>Order Status</h2><small>Live production workflow</small></div><a href="/admin/orders">View All</a></div>
      <div class="dash-ref-queue-list" id="prodQueue"><div class="dash-ref-empty">Loading statuses…</div></div>
    </article>
    <article class="dash-ref-card dash-ref-products">
      <div class="dash-ref-card-head"><div><h2>Top Products</h2><small>Ranked by order-line frequency</small></div><a href="/admin/products">View All</a></div>
      <div class="dash-ref-product-list" id="topProds"><div class="dash-ref-empty">Loading products…</div></div>
    </article>
  </section>

  <section class="dash-ref-bottom-row">
    <article class="dash-ref-card dash-ref-orders">
      <div class="dash-ref-card-head"><div><h2>Recent Orders</h2><small>Latest activity across every order type</small></div><a href="/admin/orders">View All Orders</a></div>
      <div class="dash-ref-table-wrap"><table class="dash-ref-table"><thead><tr><th>Order</th><th>Customer</th><th>Product</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody id="recentOrdersList"><tr><td colspan="7"><div class="dash-ref-empty">Loading recent orders…</div></td></tr></tbody></table></div>
    </article>
    <article class="dash-ref-card dash-ref-actions">
      <div class="dash-ref-card-head"><div><h2>Quick Actions</h2><small>Common management tasks</small></div></div>
      <div class="dash-ref-actions-grid">
        <a href="/admin/products/new" class="qa-purple"><span>⬡</span><b>Add Product</b></a>
        <a href="/admin/orders" class="qa-green"><span>▤</span><b>View Orders</b></a>
        <a href="/admin/combo-offers/new" class="qa-orange"><span>◇</span><b>Add Combo</b></a>
        <a href="/admin/customers" class="qa-blue"><span>●</span><b>Customers</b></a>
        <a href="/admin/coupons" class="qa-pink"><span>▥</span><b>Coupons</b></a>
        <a href="/admin/custom-orders#new" class="qa-slate"><span>✦</span><b>New Quote</b></a>
      </div>
    </article>
    <article class="dash-ref-card dash-ref-customers">
      <div class="dash-ref-card-head"><div><h2>Recent Customers</h2><small>Newest customer accounts</small></div><a href="/admin/customers">View All</a></div>
      <div class="dash-ref-customer-list" id="recentCustomers"><div class="dash-ref-empty">Loading customers…</div></div>
    </article>
  </section>
</div>

<script>
const STATUS_COLORS={new_order:'st-purple',received:'st-blue',design_approved:'st-green',printing:'st-orange',other_process:'st-cyan',processing:'st-cyan',ready:'st-green',delivered:'st-ink',cancelled:'st-red',whatsapp_pending:'st-amber'};
const STATUS_LABELS={new_order:'New Order',received:'Received',design_approved:'Design Approved',printing:'Printing',other_process:'Other Process',processing:'Other Process',ready:'Dispatched',delivered:'Delivered',cancelled:'Cancelled',whatsapp_pending:'WA Pending'};
const money=n=>'₹'+Number(n||0).toLocaleString('en-IN',{maximumFractionDigits:0});
const escH=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const pct=(part,total)=>Math.max(4,Math.round((Number(part||0)/Math.max(Number(total||0),1))*100));
const initials=name=>String(name||'Customer').trim().split(/\s+/).slice(0,2).map(v=>v[0]||'').join('').toUpperCase();
function fmt(value,withTime=true){const dt=new Date(String(value||'').replace(' ','T'));if(Number.isNaN(dt.getTime()))return escH(value||'—');return dt.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'})+(withTime?`<small>${dt.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})}</small>`:'');}
function setTrend(id,value,label){const el=document.getElementById(id);if(!el)return;const n=Number(value||0);el.textContent=`${n<0?'↓':'↑'} ${Math.abs(n).toFixed(1)}% ${label||''}`.trim();el.classList.toggle('pos',n>=0);el.classList.toggle('neg',n<0);}
function drawRevenue(monthly){const el=document.getElementById('revChart');if(!monthly.length){el.innerHTML='<div class="dash-ref-empty">No paid revenue recorded for this period.</div>';return;}const w=760,h=300,l=70,r=22,t=25,b=42,vals=monthly.map(m=>Number(m.revenue||0)),max=Math.max(...vals,1),pw=w-l-r,ph=h-t-b,points=monthly.map((m,i)=>[l+i*(pw/Math.max(monthly.length-1,1)),t+ph-(Number(m.revenue||0)/max)*ph]),path=points.map((p,i)=>`${i?'L':'M'}${p[0].toFixed(1)} ${p[1].toFixed(1)}`).join(' '),area=`${path} L${points.at(-1)[0]} ${h-b} L${l} ${h-b} Z`,ticks=[max,max*.75,max*.5,max*.25,0];el.innerHTML=`<svg viewBox="0 0 ${w} ${h}" role="img" aria-label="Paid revenue over the last twelve months"><defs><linearGradient id="dashRevenueFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7c3aed" stop-opacity=".26"/><stop offset="1" stop-color="#7c3aed" stop-opacity=".02"/></linearGradient></defs>${ticks.map((v,i)=>{const y=t+i*(ph/4);return `<line x1="${l}" y1="${y}" x2="${w-r}" y2="${y}"/><text x="${l-12}" y="${y+4}" text-anchor="end">${money(v)}</text>`}).join('')}<path d="${area}" fill="url(#dashRevenueFill)"/><path d="${path}" class="dash-chart-line"/>${points.map((p,i)=>`<circle cx="${p[0]}" cy="${p[1]}" r="4"><title>${escH(monthly[i].month)}: ${money(monthly[i].revenue)}</title></circle><text class="month" x="${p[0]}" y="${h-10}" text-anchor="middle">${escH(monthly[i].month)}</text>`).join('')}</svg>`;}
function drawQueue(q){const rows=[['new_order','New Order','Newly placed orders','●'],['received','Received','Order received by team','▣'],['design_approved','Design Approved','Ready after approval','✓'],['printing','Printing','In printing process','▤'],['other_process','Other Process','Finishing / processing','⌘'],['ready','Dispatched','Ready to dispatch','▰'],['delivered','Delivered','Completed orders','✓']];document.getElementById('prodQueue').innerHTML=rows.map(([key,label,desc,icon])=>`<a class="dash-ref-queue-row" href="/admin/orders?status=${encodeURIComponent(key)}"><span class="${STATUS_COLORS[key]||'st-blue'}">${icon}</span><div><strong>${label}</strong><small>${desc}</small></div><b class="${STATUS_COLORS[key]||'st-blue'}">${Number(q[key]||0)}</b></a>`).join('');}
function drawProducts(products){const el=document.getElementById('topProds');if(!products.length){el.innerHTML='<div class="dash-ref-empty">No product order data yet.</div>';return;}const max=Math.max(...products.map(p=>Number(p.count||0)),1);el.innerHTML=products.slice(0,6).map((p,i)=>`<div class="dash-ref-product-row"><span class="dash-product-avatar tone-${i%4}">${initials(p.product_name).slice(0,1)}</span><div><strong>${escH(p.product_name)}</strong><small>${Number(p.count||0).toLocaleString('en-IN')} order line(s) · ${money(p.revenue)}</small><em><i style="width:${pct(p.count,max)}%"></i></em></div><b>${pct(p.count,max)}%</b></div>`).join('');}
function drawOrders(orders){const el=document.getElementById('recentOrdersList');if(!orders.length){el.innerHTML='<tr><td colspan="7"><div class="dash-ref-empty">No orders have been placed yet.</div></td></tr>';return;}el.innerHTML=orders.slice(0,7).map(o=>`<tr><td><a href="/admin/orders?search=${encodeURIComponent(o.order_id)}">#${escH(o.order_id)}</a></td><td><span class="dash-customer-cell"><i>${initials(o.customer_name).slice(0,1)}</i><span><b>${escH(o.customer_name||'Customer')}</b><small>${escH(o.customer_email||o.customer_phone||'')}</small></span></span></td><td>${escH(o.product_summary||(Number(o.item_count||0)+' item(s)'))}</td><td>${money(o.total_amount)}</td><td><span class="dash-ref-status ${STATUS_COLORS[o.status]||'st-blue'}">${escH(STATUS_LABELS[o.status]||o.status)}</span></td><td>${fmt(o.created_at)}</td><td><a class="dash-view-order" href="/admin/orders?search=${encodeURIComponent(o.order_id)}" aria-label="View order ${escH(o.order_id)}">View</a></td></tr>`).join('');}
function drawCustomers(customers){const el=document.getElementById('recentCustomers');if(!customers.length){el.innerHTML='<div class="dash-ref-empty">No customer accounts yet.</div>';return;}el.innerHTML=customers.map((c,i)=>`<a href="/admin/customers?search=${encodeURIComponent(c.email||c.name||'')}" class="dash-ref-customer"><i class="tone-${i%4}">${initials(c.name).slice(0,1)}</i><span><strong>${escH(c.name||'Customer')}</strong><small>${escH(c.email||'No email')}</small></span><time>${fmt(c.created_at,false)}</time></a>`).join('');}
async function loadDash(){const btn=document.getElementById('dashRefresh'),updated=document.getElementById('dashUpdated');btn?.classList.add('loading');if(updated)updated.textContent='Refreshing live data…';try{const response=await fetch('/admin/api/dashboard',{credentials:'same-origin',cache:'no-store'}),res=await response.json();if(!response.ok||!res.ok)throw new Error(res.msg||'Dashboard request failed');const s=res.stats||{};document.getElementById('ds-total-orders').textContent=Number(s.total_orders||0).toLocaleString('en-IN');document.getElementById('ds-today-rev').textContent=money(s.today_revenue);document.getElementById('ds-month-rev').textContent=money(s.month_revenue);document.getElementById('ds-rev').textContent=money(s.total_revenue);document.getElementById('ds-customers').textContent=Number(s.total_customers||0).toLocaleString('en-IN');setTrend('tr-today-rev',s.today_revenue_trend,s.today_revenue_trend_label);setTrend('tr-month-rev',s.month_revenue_trend,s.month_revenue_trend_label);setTrend('tr-customers',s.total_customers_trend,s.total_customers_trend_label);drawRevenue(res.monthly||[]);drawQueue(res.queue||{});drawProducts(res.top_products||[]);drawOrders(res.recent_orders||[]);drawCustomers(res.recent_customers||[]);if(updated)updated.textContent='Updated '+new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});}catch(error){if(updated)updated.textContent='Dashboard data is temporarily unavailable.';}finally{btn?.classList.remove('loading');}}
document.getElementById('dashRefresh')?.addEventListener('click',loadDash);
window.addEventListener('admin:live-updates',event=>{const current=event.detail?.counts||{},previous=event.detail?.previous||{};if(Object.entries(current).some(([key,value])=>Number(value)!==Number(previous[key]??value)))loadDash();});
loadDash();
</script>
    </div></div></div>
</body></html>

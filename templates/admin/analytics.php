<?php
$pageTitle = 'Analytics — RCS Admin';
$currentAdmPage = 'analytics';
include __DIR__ . '/layout.php';
?>
<div class="adm-pt">Analytics</div>
<p style="font-size:13px;color:var(--text2);margin-bottom:16px">High level order and revenue analytics.</p>

<div class="a-stats" style="margin-bottom:16px">
  <div class="ast"><div class="ast-v" style="color:var(--blue)" id="an-orders">—</div><div class="ast-l">Total Orders</div></div>
  <div class="ast"><div class="ast-v" style="color:var(--green)" id="an-rev">—</div><div class="ast-l">Revenue</div></div>
  <div class="ast"><div class="ast-v" style="color:var(--amber)" id="an-month">—</div><div class="ast-l">This Month Orders</div></div>
  <div class="ast"><div class="ast-v" style="color:var(--ink)" id="an-pending">—</div><div class="ast-l">Pending</div></div>
</div>

<div class="fsec" style="margin-bottom:14px">
  <div class="fsec-t">Top Products (Recent)</div>
  <div id="anTop"></div>
</div>

<div class="fsec">
  <div class="fsec-t">Recent 10 Orders</div>
  <div id="anRecent"></div>
</div>

<script>
const fmt = n => '₹' + Number(n||0).toLocaleString('en-IN');

async function loadAnalytics() {
  const res = await fetch('/admin/api/dashboard').then(r=>r.json());
  if (!res.ok) return;
  const s = res.stats || {};
  document.getElementById('an-orders').textContent = Number(s.total_orders||0).toLocaleString('en-IN');
  document.getElementById('an-rev').textContent = fmt(s.total_revenue||0);
  document.getElementById('an-pending').textContent = Number(s.pending_orders||0).toLocaleString('en-IN');

  const month = (res.monthly||[]).slice(-1)[0] || {orders:0};
  document.getElementById('an-month').textContent = Number(month.orders||0).toLocaleString('en-IN');

  const top = res.top_products || [];
  document.getElementById('anTop').innerHTML = top.length
    ? top.map(p=>`<div class="anl-row"><span>${escH(p.product_name)}</span><span class="anl-v">${p.count} orders · ${fmt(p.revenue)}</span></div>`).join('')
    : '<div style="color:var(--text3);font-size:13px">No data yet.</div>';

  const ro = res.recent_orders || [];
  document.getElementById('anRecent').innerHTML = ro.length
    ? ro.map(o=>`<div class="aoc" style="margin-bottom:8px"><div style="display:flex;justify-content:space-between;gap:10px"><div><div style="font-weight:700">${escH(o.order_id)}</div><div style="font-size:12px;color:var(--text2)">${escH(o.customer_name||'')} · ${escH(o.customer_phone||'')}</div></div><div style="text-align:right"><div style="font-weight:700">${fmt(o.total_amount)}</div><div style="font-size:12px;color:var(--text2)">${escH(o.status||'')}</div></div></div></div>`).join('')
    : '<div style="color:var(--text3);font-size:13px">No orders yet.</div>';
}

function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
loadAnalytics();
</script>
    </div></div></div>
</body></html>

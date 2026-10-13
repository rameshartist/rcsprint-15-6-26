<?php
$pageTitle = 'Best Deals — RCS Admin';
$currentAdmPage = 'deals';
include __DIR__ . '/layout.php';
?>
<div class="adm-pt">Best Deals Manager</div>

<div class="fsec" style="max-width:1120px">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
    <div style="font-size:13px;color:var(--text2)">Manage homepage deal cards. Use a dedicated add/edit page for cleaner data entry.</div>
    <a class="btn btn-blue btn-sm" href="/admin/deals/new">+ New Deal</a>
  </div>

  <div id="dealErr" style="display:none;padding:10px 12px;border:1px solid var(--red-mid);background:var(--red-bg);color:var(--red);border-radius:10px;font-size:12px;margin-bottom:10px"></div>
  <div id="dealList"></div>
</div>

<script>
let deals = [];
const CSRF = '<?= htmlspecialchars($csrf??'') ?>';
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
function toastMsg(msg,type='info'){ const w=document.getElementById('tw'); const t=document.createElement('div'); t.className='toast '+type; t.textContent=msg; w.appendChild(t); requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('show'))); setTimeout(()=>{t.classList.remove('show'); setTimeout(()=>t.remove(),300);},2600); }
function showErr(msg=''){ const e=document.getElementById('dealErr'); if(!e) return; if(!msg){e.style.display='none';return;} e.textContent=msg; e.style.display='block'; }
async function loadDeals() {
  const res = await fetch('/admin/api/deals').then(r=>r.json());
  if (!res.ok) { showErr(res.msg || 'Could not load deals'); return; }
  showErr(''); deals = res.deals || []; renderDeals();
}
function renderDeals() {
  const box = document.getElementById('dealList');
  if (!deals.length) {
    box.innerHTML = `<div style="padding:18px;border:1px dashed var(--border);border-radius:10px;color:var(--text2);font-size:13px">No deals added yet.</div>`;
    return;
  }
  box.innerHTML = deals.map((d) => {
    const type = d.deal_type === 'promo' ? 'Promo' : 'Deal';
    const img = d.image_path ? `<img src="${esc(d.image_path)}" style="width:94px;height:62px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">` : `<div style="width:94px;height:62px;border-radius:8px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--text3);font-size:11px">Gift Art</div>`;
    return `<div style="display:grid;grid-template-columns:94px 1fr auto;gap:12px;align-items:center;padding:10px;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;background:#fff">
      ${img}<div><div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-bottom:3px"><span style="font-size:10px;font-weight:700;color:#fff;background:${d.deal_type === 'promo' ? '#6d28d9' : '#0f766e'};border-radius:999px;padding:3px 8px">${type}</span><span style="font-size:11px;color:var(--text2)">Card ${Math.max(1,Math.min(5,Number(d.sort_order||1)))}</span></div><div style="font-weight:700;font-size:13px">${esc(d.title)} ${d.highlight_text ? `<span style="color:var(--orange)">${esc(d.highlight_text)}</span>` : ''}</div><div style="font-size:11px;color:var(--text2)">${esc(d.subtitle || '')}${d.price_text ? ` · ${esc(d.price_text)}` : ''}</div></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end"><button class="deal-status-toggle ${Number(d.is_active)?'is-active':'is-inactive'}" type="button" onclick="toggleDeal(${Number(d.id)},this)" aria-label="${Number(d.is_active)?'Deactivate':'Activate'} deal"><span></span>${Number(d.is_active)?'Active':'Inactive'}</button><a class="btn btn-outline btn-sm" href="/admin/deals/edit/${Number(d.id)}">Edit</a><button class="btn btn-outline btn-sm" onclick="delDeal(${Number(d.id)})">Delete</button></div>
    </div>`;
  }).join('');
}
async function delDeal(id) {
  if (!confirm('Delete this deal?')) return;
  const res = await fetch(`/admin/api/deals/${id}`, {method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF}}).then(r=>r.json());
  if (!res.ok) { showErr(res.msg || 'Delete failed'); return; }
  toastMsg('Deal deleted', 'info'); await loadDeals();
}
async function toggleDeal(id,btn){btn.disabled=true;const res=await fetch(`/admin/api/deals/${id}/toggle`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF}}).then(r=>r.json()).catch(()=>({ok:false,msg:'Status update failed'}));btn.disabled=false;if(!res.ok){showErr(res.msg||'Status update failed');return;}toastMsg(res.is_active?'Deal activated':'Deal deactivated','success');await loadDeals();}
loadDeals();
</script>
    </div></div></div>
</body></html>

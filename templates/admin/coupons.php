<?php
$pageTitle='Special Offers — RCS Admin';$currentAdmPage='coupons';include __DIR__.'/layout.php';
?>
<div class="adm-pt">Special Offers</div>
<div class="fsec" style="max-width:1120px"><div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px"><div style="font-size:13px;color:var(--text2)">Manage regular coupons and customer order offers.</div><a class="btn btn-blue btn-sm" href="/admin/coupons/new">+ Add Special Offer</a></div><div id="offerErr" class="adm-inline-error" hidden></div><div id="couponList"></div></div>
<script>
const CSRF=<?= json_encode($csrf??'') ?>;let coupons=[];const esc=s=>String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
function err(m=''){offerErr.hidden=!m;offerErr.textContent=m}
async function loadCoupons(){const r=await fetch('/admin/api/coupons').then(r=>r.json());if(!r.ok){err(r.msg||'Could not load offers');return;}coupons=r.coupons||[];render()}
function render(){couponList.innerHTML=coupons.length?coupons.map(c=>`<div class="special-offer-row">${c.popup_image?`<img src="${esc(c.popup_image)}" alt="">`:`<div class="special-offer-placeholder">%</div>`}<div><div class="special-offer-tags"><b>${esc(c.offer_type==='regular'?'Regular Coupon':`${c.order_number}${Number(c.order_number)===1?'st':Number(c.order_number)===2?'nd':'rd'} Order Offer`)}</b><span>${esc(c.code)}</span></div><h3>${esc(c.description||'Special Offer')}</h3><small>${c.discount_type==='free_shipping'?'Free Shipping':c.discount_type==='free_design'?'Free Design':c.discount_type==='percent'?Number(c.discount_value)+'% off':'₹'+Number(c.discount_value).toLocaleString('en-IN')+' off'} · Used ${Number(c.used_count||0)} time(s)</small></div><div class="special-offer-actions"><button class="deal-status-toggle ${Number(c.is_active)?'is-active':'is-inactive'}" onclick="toggleOffer(${Number(c.id)},this)"><span></span>${Number(c.is_active)?'Active':'Inactive'}</button><a class="btn btn-outline btn-sm" href="/admin/coupons/edit/${Number(c.id)}">Edit</a><button class="btn btn-outline btn-sm" onclick="removeOffer(${Number(c.id)})">Delete</button></div></div>`).join(''):'<div class="adm-empty">No special offers added yet.</div>'}
async function toggleOffer(id,b){b.disabled=true;const r=await fetch(`/admin/api/coupons/${id}/toggle`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF}}).then(r=>r.json());b.disabled=false;if(!r.ok){err(r.msg||'Could not update status');return;}loadCoupons()}
async function removeOffer(id){if(!confirm('Delete this special offer?'))return;const r=await fetch(`/admin/api/coupons/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF}}).then(r=>r.json());if(!r.ok){err(r.msg||'Delete failed');return;}loadCoupons()}
loadCoupons();
</script>
<?php include __DIR__.'/layout-end.php'; ?>

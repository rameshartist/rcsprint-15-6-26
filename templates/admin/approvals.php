<?php
$pageTitle = 'Approvals — RCS Admin';
$currentAdmPage = 'approvals';
include __DIR__ . '/layout.php';
$canApprove = \Auth\Auth::isSuperAdmin();
?>
<div style="display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
  <div><div class="adm-pt" style="margin:0">Content Approvals</div><div style="font-size:12px;color:var(--text2);margin-top:4px">Review products, categories, coupons and deals before they go live.</div></div>
  <span id="approvalCount" style="font-size:12px;color:var(--text2)">Loading…</span>
</div>
<div class="fsec" style="margin-bottom:14px;background:linear-gradient(135deg,#111827,#2563eb);color:#fff;border:0">
  <div style="font-weight:700;font-size:20px">Quality gate for publishing</div>
  <div style="opacity:.82;margin-top:6px;font-size:13px">Admin submissions stay inactive until a Super Admin approves them. Rejections should include a clear reason.</div>
</div>
<div id="approvalWrap"><div style="text-align:center;padding:44px;color:var(--text2)">Loading approval requests…</div></div>
<script>
const CSRF = '<?= htmlspecialchars($csrf ?? '') ?>';
const CAN_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
function escA(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function approvalEditUrl(item){ const t=item.type; if(t==='products') return `/admin/products/new?id=${Number(item.id)}`; if(t==='categories') return '/admin/categories'; if(t==='coupons') return '/admin/coupons'; if(t==='home_deals') return '/admin/deals'; return '#'; }
async function loadApprovals(){
  const wrap=document.getElementById('approvalWrap');
  try{
    const res=await fetch('/admin/api/approvals',{credentials:'same-origin'}).then(r=>r.json());
    const items=res.items||[]; document.getElementById('approvalCount').textContent=items.length+' pending/rejected item(s)';
    if(!items.length){wrap.innerHTML='<div class="fsec" style="text-align:center;padding:46px;color:var(--text2)"><div style="font-size:42px;margin-bottom:8px">✅</div><b style="color:var(--ink)">No approval requests</b><br>Everything is reviewed.</div>';return;}
    wrap.innerHTML=items.map(i=>`<article class="fsec" style="margin-bottom:12px;border-left:5px solid ${i.approval_status==='rejected'?'#ef4444':'#f59e0b'}"><div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap"><div><div style="font-size:12px;color:var(--text3);font-weight:700;text-transform:uppercase">${escA(i.type_label)} · ${escA(i.approval_status)}</div><h3 style="margin:5px 0;color:var(--ink)">${escA(i.title||('Item #'+i.id))}</h3><div style="font-size:12px;color:var(--text2)">Submitted by ${escA(i.submitted_by_name||'Admin')} · ${escA(i.submitted_at||'')}</div>${i.approval_note?`<p style="margin:8px 0 0;color:#b91c1c;font-weight:700">${escA(i.approval_note)}</p>`:''}</div><div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn btn-outline btn-sm" href="${approvalEditUrl(i)}">Open</a>${CAN_APPROVE?`<button class="btn btn-green btn-sm" onclick="decide('${escA(i.type)}',${Number(i.id)},'approve')">Approve</button><button class="btn btn-red btn-sm" onclick="decide('${escA(i.type)}',${Number(i.id)},'reject')">Reject</button>`:''}</div></div></article>`).join('');
  }catch(e){wrap.innerHTML='<div class="fsec" style="color:var(--red)">Could not load approvals.</div>';}
}
async function decide(type,id,decision){
  let note=''; if(decision==='reject'){ note=await adminPrompt('Reason for rejection?','',{title:'Reject Approval Request',confirmText:'Reject'})||''; if(!note.trim()) return; }
  const res=await fetch(`/admin/api/approvals/${type}/${id}/${decision}`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},credentials:'same-origin',body:JSON.stringify({note})}).then(r=>r.json());
  if(!res.ok){alert(res.msg||'Could not update approval');return;} loadApprovals();
}
loadApprovals();
</script>
    </div></div></div>
</body></html>

<?php
$pageTitle = 'Leads — RCS Admin';
$currentAdmPage = 'leads';
$admMainClass = 'adm-main--leads';
include __DIR__ . '/layout.php';
?>
<div class="leads-page">
  <section class="leads-hero">
    <div><span>Lead inbox</span><h1>Contact Leads</h1><p>New enquiries stay highlighted until you open the card and review the details.</p></div>
    <button class="leads-export" type="button" onclick="exportLeadsCsv()">⬇ Export CSV</button>
  </section>
  <section class="leads-stats" id="leadStats">
    <button type="button" data-lead-filter="all" onclick="setLeadFilter('all')"><span>Total Leads</span><strong>--</strong></button><button type="button" class="is-active" data-lead-filter="unread" onclick="setLeadFilter('unread')"><span>Unread</span><strong>--</strong></button><button type="button" data-lead-filter="read" onclick="setLeadFilter('read')"><span>Read</span><strong>--</strong></button>
  </section>
  <div id="leadList" class="leads-grid"><div class="leads-empty">Loading leads…</div></div>
</div>
<aside class="lead-drawer" id="leadDrawer" aria-hidden="true"><div class="lead-drawer-panel"><button type="button" onclick="closeLeadDrawer()" class="lead-close">×</button><div id="leadDrawerBody"></div></div></aside>
<script>
let LEADS = [];
let LEAD_TEMPLATES = {};
let LEAD_FILTER = 'unread';
const escLead = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const leadDate = s => s ? new Date(String(s).replace(' ', 'T')).toLocaleString('en-IN',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) : '-';
const leadDigits = s => String(s || '').replace(/\D/g,'');
async function loadLeads(){
  const wrap=document.getElementById('leadList');
  if(wrap) wrap.innerHTML='<div class="leads-empty">Loading leads…</div>';
  try{
    const response = await fetch('/admin/api/leads',{credentials:'same-origin',headers:{'Accept':'application/json'}});
    const text = await response.text();
    let res;
    try { res = JSON.parse(text); } catch(e) { throw new Error('Leads API returned an invalid response. Please login again or refresh.'); }
    if(!response.ok || !res.ok) throw new Error(res.msg || 'Unable to load leads.');
    LEADS = Array.isArray(res.leads) ? res.leads : [];
    renderLeadStats();
    renderLeads();
  }catch(err){
    console.error(err);
    LEADS=[];
    renderLeadStats();
    if(wrap) wrap.innerHTML=`<div class="leads-empty"><strong>Could not load leads.</strong><br>${escLead(err.message || 'Please refresh and try again.')}</div>`;
  }
}
function renderLeadStats(){ const total=LEADS.length; const unread=LEADS.filter(l=>Number(l.is_read||0)===0).length; const vals=[total,unread,total-unread]; document.querySelectorAll('#leadStats strong').forEach((el,i)=>el.textContent=Number(vals[i]||0).toLocaleString('en-IN')); document.querySelectorAll('[data-lead-filter]').forEach(el=>el.classList.toggle('is-active',el.dataset.leadFilter===LEAD_FILTER)); const badge=document.querySelector('a[href="/admin/leads"] .adm-nav-order-count'); if(badge){if(unread)badge.textContent=unread;else badge.remove();} }
function setLeadFilter(filter){LEAD_FILTER=['all','unread','read'].includes(filter)?filter:'unread';renderLeadStats();renderLeads();}
function filteredLeads(){ return LEADS.filter(l=>LEAD_FILTER==='all'||(LEAD_FILTER==='unread'?Number(l.is_read||0)===0:Number(l.is_read||0)===1)); }
function renderLeads(){ const list=filteredLeads(); const wrap=document.getElementById('leadList'); if(!list.length){wrap.innerHTML='<div class="leads-empty">No leads found.</div>';return;} wrap.innerHTML=list.map(l=>`<article class="lead-card ${Number(l.is_read||0)===0?'lead-card--unread':''}" data-lead-id="${Number(l.id)}"><button type="button" onclick="openLead(${Number(l.id)})"><strong>${escLead(l.name)}</strong><small>${escLead(l.phone||'-')} · ${escLead(l.email||'-')}</small><b>${escLead(l.subject)}</b><p>${escLead(l.message).slice(0,150)}</p></button><div class="lead-card-foot"><span>${Number(l.is_read||0)===0?'New enquiry':'Reviewed'}</span><em>${leadDate(l.created_at)}</em></div><div class="lead-actions"><button onclick="waLead(${Number(l.id)})">💬 WA</button><a href="tel:${leadDigits(l.phone)}">📞 Call</a></div></article>`).join(''); }
async function openLead(id){ const l=LEADS.find(x=>Number(x.id)===Number(id)); if(!l)return; if(Number(l.is_read||0)===0){ const response=await fetch(`/admin/api/leads/${id}/read`,{method:'POST',headers:{'X-CSRF-TOKEN':'<?= htmlspecialchars($csrf ?? '') ?>'},credentials:'same-origin'}); const result=await response.json().catch(()=>({ok:false})); if(result.ok){l.is_read=1;renderLeadStats();renderLeads();} } document.getElementById('leadDrawerBody').innerHTML=`<div class="lead-drawer-head"><h2>${escLead(l.name)}</h2><p>${escLead(l.subject)}</p></div><section><h3>Message</h3><p>${escLead(l.message)}</p></section><section><h3>Contact</h3><p>${escLead(l.phone||'-')}<br>${escLead(l.email||'-')}</p><div class="lead-drawer-actions"><button onclick="waLead(${Number(l.id)})">WhatsApp</button><a href="tel:${leadDigits(l.phone)}">Call</a><a href="mailto:${escLead(l.email)}">Email</a></div></section><section><h3>Admin Note</h3><textarea id="leadNote">${escLead(l.admin_note||'')}</textarea><button class="lead-save-note" onclick="saveLeadNote(${Number(l.id)})">Save Note</button></section>${l.matched_customer?`<section><h3>Matched Customer</h3><p>${escLead(l.matched_customer.name)}<br>${escLead(l.matched_customer.phone||l.matched_customer.email||'')}</p><a href="/admin/customers?search=${encodeURIComponent(l.matched_customer.phone||l.matched_customer.email||'')}">Open Customer CRM</a></section>`:''}`; document.getElementById('leadDrawer').classList.add('open'); document.getElementById('leadDrawer').setAttribute('aria-hidden','false'); }
function closeLeadDrawer(){ document.getElementById('leadDrawer').classList.remove('open'); document.getElementById('leadDrawer').setAttribute('aria-hidden','true'); }
async function updateLead(id,payload){
  try{
    const response = await fetch(`/admin/api/leads/${id}`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'<?= htmlspecialchars($csrf ?? '') ?>'},credentials:'same-origin',body:JSON.stringify(payload)});
    const res = await response.json();
    if(!response.ok || !res.ok) throw new Error(res.msg||'Could not update lead');
    await loadLeads();
    const drawer=document.getElementById('leadDrawer'); if(drawer.classList.contains('open')) openLead(id);
  }catch(err){ alert(err.message || 'Could not update lead'); }
}
function quickStatus(id,status){ updateLead(id,{status}); }
function saveLeadNote(id){ updateLead(id,{admin_note:document.getElementById('leadNote')?.value||''}); }
function fillLeadTemplate(body,l){ const data={lead_name:l.name||'Customer',lead_phone:l.phone||'',lead_email:l.email||'',lead_subject:l.subject||'',lead_message:l.message||'',business_name:'RCS Graphic'}; return String(body||'').replace(/\{([a-z0-9_]+)\}/gi,(_,key)=>Object.prototype.hasOwnProperty.call(data,key)?data[key]:`{${key}}`); }
async function loadLeadTemplates(){ try{ const res=await fetch('/admin/api/whatsapp-templates',{credentials:'same-origin'}).then(r=>r.json()); (res.templates||[]).forEach(t=>{LEAD_TEMPLATES[t.template_key]=t.body||'';}); }catch(e){} }
function waLead(id){ const l=LEADS.find(x=>Number(x.id)===Number(id)); if(!l)return; const phone=leadDigits(l.phone); if(!phone)return alert('Phone number not available'); const fallback=`Hi ${String(l.name||'Customer').split(' ')[0]} ji, thanks for contacting RCS Graphic. We received your enquiry: ${l.subject}. Please share any artwork/details so we can guide you quickly.`; const msg=fillLeadTemplate(LEAD_TEMPLATES.lead_followup||fallback,l); window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`,'_blank'); }
function exportLeadsCsv(){ const rows=[['Name','Phone','Email','Subject','Status','Priority','Created']].concat(filteredLeads().map(l=>[l.name,l.phone,l.email,l.subject,l.status,l.priority,l.created_at])); const csv=rows.map(r=>r.map(v=>'"'+String(v??'').replace(/"/g,'""')+'"').join(',')).join('\n'); const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download='contact-leads.csv'; a.click(); URL.revokeObjectURL(a.href); }
loadLeadTemplates().then(loadLeads);
</script>

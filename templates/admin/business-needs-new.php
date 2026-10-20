<?php
$businessNeedEditId = (int)($businessNeedEditId ?? 0);
$pageTitle = $businessNeedEditId > 0 ? 'Edit Business Sector — RCS Admin' : 'Add Business Sector — RCS Admin';
$currentAdmPage = 'business-needs';
include __DIR__ . '/layout.php';
?>
<div class="adm-pt"><?= $businessNeedEditId > 0 ? 'Edit Business Sector' : 'Add Business Sector' ?></div>
<div class="fsec business-needs-form-card" style="max-width:860px">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px"><div style="font-size:13px;color:var(--text2)">Create website-ready business sector cards. Assign products from Add/Edit Product.</div><a class="btn btn-outline btn-sm" href="/admin/business-needs">← Back to Business Sectors</a></div>
  <div id="needErr" class="adm-inline-error" style="display:none"></div>
  <div class="grid2"><div class="fg"><label>Name *</label><input class="fi" id="needName" placeholder="Hospitals"></div><div class="fg"><label>Slug</label><input class="fi" id="needSlug" placeholder="hospitals"></div></div>
  <div class="fg"><label>Sort Order</label><input class="fi" id="needSort" type="number" value="0"></div>
  <div class="fg"><label>Description</label><input class="fi" id="needDesc" placeholder="Print products for hospitals, clinics and healthcare brands."></div>
  <div class="grid2"><div class="fg"><label>Status</label><select class="fi fi-sel" id="needActive"><option value="1">Active</option><option value="0">Hidden</option></select></div><div class="fg"><label>Sector Thumbnail (JPG/PNG/WEBP, max 5MB)</label><input class="fi" id="needImage" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></div></div>
  <div id="needImagePreview" class="business-sector-preview"><span>No thumbnail selected</span></div>
  <div class="form-actions" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap"><button class="btn btn-blue" id="needSaveBtn" type="button" onclick="saveNeed()"><?= $businessNeedEditId > 0 ? 'Update Business Sector ✓' : 'Save Business Sector ✓' ?></button><a class="btn btn-outline" href="/admin/business-needs">Cancel</a></div>
</div>
<script>
const CSRF = '<?= htmlspecialchars($csrf??'') ?>';
const EDIT_ID = <?= (int)$businessNeedEditId ?>;
let currentImage = '';
function esc(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function slugify(s){return String(s||'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');}
function showErr(msg=''){const e=document.getElementById('needErr'); if(!e)return; e.style.display=msg?'block':'none'; e.textContent=msg;}
function renderPreview(src=currentImage){document.getElementById('needImagePreview').innerHTML=src?`<img src="${esc(src)}" alt="Business sector thumbnail"><span>Thumbnail preview</span>`:'<span>No thumbnail selected</span>';}
needName.addEventListener('input',()=>{ if(!EDIT_ID && !needSlug.value.trim()) needSlug.value=slugify(needName.value); });
needImage.addEventListener('change',()=>{ const f=needImage.files?.[0]; if(f) renderPreview(URL.createObjectURL(f)); else renderPreview(); });
async function boot(){ if(!EDIT_ID){renderPreview();return;} const res=await fetch('/admin/api/business-needs').then(r=>r.json()); const n=(res.needs||[]).find(x=>Number(x.id)===EDIT_ID); if(!n){showErr('Business sector not found.');return;} needName.value=n.name||''; needSlug.value=n.slug||''; needDesc.value=n.description||''; needSort.value=n.sort_order||0; needActive.value=Number(n.is_active)?'1':'0'; currentImage=n.image_path||''; renderPreview(); }
async function uploadImage(id){ const f=needImage.files?.[0]; if(!f) return currentImage; const fd=new FormData(); fd.append('image', f); const res=await fetch(`/admin/api/business-needs/${id}/image-upload`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF},body:fd}).then(r=>r.json()); if(!res.ok) throw new Error(res.msg||'Image upload failed'); return res.image_path||currentImage; }
async function saveNeed(){ const name=needName.value.trim(); if(!name){showErr('Business sector name is required');return;} const btn=needSaveBtn; btn.disabled=true; btn.textContent='Saving...'; try{ const payload={name,slug:needSlug.value.trim()||slugify(name),description:needDesc.value.trim(),image_path:currentImage,sort_order:Number(needSort.value||0),is_active:Number(needActive.value||1)}; const url=EDIT_ID?`/admin/api/business-needs/${EDIT_ID}`:'/admin/api/business-needs'; const res=await fetch(url,{method:EDIT_ID?'PUT':'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)}).then(r=>r.json()); if(!res.ok) throw new Error(res.msg||'Save failed'); const id=EDIT_ID || Number(res.id||0); if(id) await uploadImage(id); window.location.href='/admin/business-needs'; }catch(e){showErr(e.message||'Save failed');}finally{btn.disabled=false; btn.textContent=EDIT_ID?'Update Business Sector ✓':'Save Business Sector ✓';}}
boot();
</script>
    </div></div></div>
</body></html>

<?php $pageTitle='Header & Footer — RCS Admin';$currentAdmPage='header-footer';include __DIR__.'/layout.php'; ?>
<div class="adm-pt">Header &amp; Footer Manager</div>
<div class="fsec site-chrome-admin">
  <p>Update the announcement, logos and navigation links. Changes appear across the website after saving.</p>
  <form id="chromeForm">
    <div class="combo-form-grid">
      <label>Top bar message<input class="fi" name="top_bar_text" required></label>
      <label>Header logo<div class="combo-upload-row"><input class="fi" name="header_logo" required><input type="file" data-logo="header_logo" accept="image/png,image/jpeg,image/webp"><button class="btn btn-outline" type="button" onclick="uploadLogo('header_logo')">Upload</button></div></label>
      <label>Footer logo<div class="combo-upload-row"><input class="fi" name="footer_logo" required><input type="file" data-logo="footer_logo" accept="image/png,image/jpeg,image/webp"><button class="btn btn-outline" type="button" onclick="uploadLogo('footer_logo')">Upload</button></div></label>
    </div>
    <label>Footer description<textarea class="fi" name="footer_description" rows="3"></textarea></label>
    <div id="navEditors" class="chrome-nav-grid"></div>
    <button class="btn btn-blue" type="submit">Save Header &amp; Footer</button>
  </form>
</div>
<script>
const CHROME_CSRF='<?= htmlspecialchars($csrf??'') ?>', locations={header:'Header Menu',footer_quick:'Footer Quick Links',footer_products:'Footer Products',footer_service:'Footer Customer Service'};let chromeData={settings:{},navigation:{}};
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
function renderEditors(){document.getElementById('navEditors').innerHTML=Object.entries(locations).map(([key,title])=>`<section class="chrome-nav-editor"><div class="combo-admin-head"><h3>${title}</h3><button type="button" class="btn btn-outline btn-sm" onclick="addNav('${key}')">+ Add Link</button></div><div data-nav="${key}">${(chromeData.navigation[key]||[]).map(navRow).join('')}</div></section>`).join('');}
function navRow(x={}){return `<div class="chrome-nav-row"><input class="fi" data-field="label" value="${esc(x.label)}" placeholder="Label"><input class="fi" data-field="url" value="${esc(x.url)}" placeholder="/page-link"><button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()">Remove</button></div>`;}
function addNav(key){document.querySelector(`[data-nav="${key}"]`).insertAdjacentHTML('beforeend',navRow());}
async function loadChrome(){const r=await fetch('/admin/api/site-chrome').then(x=>x.json());if(!r.ok)return;chromeData=r.data;for(const [k,v] of Object.entries(chromeData.settings))if(document.getElementById('chromeForm').elements[k])document.getElementById('chromeForm').elements[k].value=v||'';renderEditors();}
async function uploadLogo(key){const f=document.querySelector(`[data-logo="${key}"]`).files[0];if(!f)return;const fd=new FormData();fd.append('image',f);const r=await fetch('/admin/api/site-chrome/logo',{method:'POST',headers:{'X-CSRF-TOKEN':CHROME_CSRF},body:fd}).then(x=>x.json());if(r.ok)document.getElementById('chromeForm').elements[key].value=r.path;else alert(r.msg||'Upload failed.');}
document.getElementById('chromeForm').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,data={settings:{top_bar_text:f.top_bar_text.value,header_logo:f.header_logo.value,footer_logo:f.footer_logo.value,footer_description:f.footer_description.value},navigation:{}};for(const key of Object.keys(locations))data.navigation[key]=[...document.querySelectorAll(`[data-nav="${key}"] .chrome-nav-row`)].map(r=>({label:r.querySelector('[data-field="label"]').value,url:r.querySelector('[data-field="url"]').value}));const r=await fetch('/admin/api/site-chrome',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CHROME_CSRF},body:JSON.stringify(data)}).then(x=>x.json());alert(r.ok?'Header and footer saved.':(r.msg||'Save failed.'));});loadChrome();
</script></div></div></div></body></html>

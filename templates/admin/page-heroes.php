<?php
$pageTitle = 'Page Heroes — Admin';
$currentAdmPage = 'page-heroes';
include __DIR__ . '/layout.php';
?>
<div class="adm-pt">Page Heroes</div>
<section class="card page-heroes-card">
  <nav class="page-hero-groups" aria-label="Page hero groups">
    <button type="button" class="active" data-group="main">Main Pages</button>
    <button type="button" data-group="categories">Categories</button>
    <button type="button" data-group="business">Business Needs</button>
    <button type="button" data-group="offers">Special Offers</button>
  </nav>
  <div id="pageHeroList" class="page-heroes-grid" aria-live="polite"></div>
</section>

<script>
const pageHeroList=document.getElementById('pageHeroList');let heroRows=[],activeHeroGroup='main';
const toast=(msg,ok=true)=>window.showToast?showToast(msg,ok?'success':'error'):alert(msg);
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
async function api(url,opts={}){const res=await fetch(url,{headers:{'Content-Type':'application/json'},...opts}),data=await res.json().catch(()=>({}));if(!res.ok||data.ok===false)throw new Error(data.msg||'Request failed');return data;}
async function uploadHero(file){const fd=new FormData();fd.append('image',file);const res=await fetch('/admin/api/page-heroes/upload',{method:'POST',body:fd}),data=await res.json().catch(()=>({}));if(!res.ok||data.ok===false)throw new Error(data.msg||'Upload failed');return data.path;}
function groupFor(key){if(key==='categories'||key.startsWith('category_'))return'categories';if(key==='business_sectors'||key.startsWith('business_'))return'business';if(key.startsWith('offer_'))return'offers';return'main';}
function heroCard(row){const bg=row.background_image||row.fallback_image||'/assets/img/categories/all-categories-hero.svg';return `<article class="page-hero-admin-card" data-key="${esc(row.page_key)}"><div class="page-hero-admin-preview" style="background-image:url('${esc(bg)}')"></div><div class="page-hero-admin-body"><h3>${esc(row.title||row.page_key)}</h3><label>Hero Headline<input class="hero-title" value="${esc(row.title||'')}"></label><label>Hero Subtitle<textarea class="hero-desc" rows="2">${esc(row.description||'')}</textarea></label><div class="page-hero-admin-actions"><label class="btn">Choose Image<input class="hero-file" type="file" accept="image/png,image/jpeg,image/webp" hidden></label><button class="btn primary hero-save" type="button">Save</button></div><input class="hero-path" type="hidden" value="${esc(row.background_image||'')}"></div></article>`;}
function renderHeroes(){const rows=heroRows.filter(r=>groupFor(String(r.page_key||''))===activeHeroGroup);pageHeroList.innerHTML=rows.map(heroCard).join('')||'<div class="adm-muted">No pages are available in this group.</div>';}
async function loadHeroes(){pageHeroList.innerHTML='<div class="adm-muted">Loading page heroes...</div>';try{heroRows=(await api('/admin/api/page-heroes')).heroes||[];renderHeroes();}catch(err){pageHeroList.innerHTML='<div class="adm-error">'+esc(err.message)+'</div>';}}
document.querySelector('.page-hero-groups').addEventListener('click',e=>{const btn=e.target.closest('[data-group]');if(!btn)return;document.querySelectorAll('.page-hero-groups button').forEach(b=>b.classList.toggle('active',b===btn));activeHeroGroup=btn.dataset.group;renderHeroes();});
pageHeroList.addEventListener('change',async e=>{const input=e.target.closest('.hero-file');if(!input||!input.files[0])return;const card=input.closest('.page-hero-admin-card');try{const path=await uploadHero(input.files[0]);card.querySelector('.hero-path').value=path;card.querySelector('.page-hero-admin-preview').style.backgroundImage=`url('${path}')`;toast('Image uploaded');}catch(err){toast(err.message,false);}});
pageHeroList.addEventListener('click',async e=>{const btn=e.target.closest('.hero-save');if(!btn)return;const card=btn.closest('.page-hero-admin-card');try{await api('/admin/api/page-heroes/'+encodeURIComponent(card.dataset.key),{method:'PUT',body:JSON.stringify({title:card.querySelector('.hero-title').value.trim(),description:card.querySelector('.hero-desc').value.trim(),background_image:card.querySelector('.hero-path').value.trim(),is_active:true})});toast('Page hero saved');}catch(err){toast(err.message,false);}});
loadHeroes();
</script>
<?php include __DIR__ . '/layout-end.php'; ?>

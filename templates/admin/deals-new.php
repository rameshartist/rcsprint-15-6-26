<?php
$dealEditId=(int)($dealEditId??0);$pageTitle=$dealEditId?'Edit Deal — RCS Admin':'Add Deal — RCS Admin';$currentAdmPage='deals';include __DIR__.'/layout.php';
?>
<div class="adm-pt"><?= $dealEditId?'Edit Best Deal':'Add Best Deal' ?></div>
<div class="fsec" style="max-width:980px"><div id="dealErr" class="adm-inline-error" hidden></div>
  <div class="fg"><label>Card Position</label><select class="fi" id="deal-position"><option value="1">Card 1</option><option value="2">Card 2</option><option value="3">Card 3</option><option value="4">Card 4</option></select></div>
  <section id="productDealFields"><div class="f2"><div class="fg"><label>Category</label><select class="fi" id="deal-category" onchange="renderProducts()"></select></div><div class="fg"><label>Product</label><select class="fi" id="deal-product"></select></div></div></section>
  <div class="form-actions"><button class="btn btn-blue" id="saveBtn" onclick="saveDeal()">Save Deal</button><a class="btn btn-outline" href="/admin/deals">Cancel</a></div>
</div>
<script>
const EDIT_ID=<?= $dealEditId ?>,CSRF=<?= json_encode($csrf??'') ?>;let deals=[],categories=[],products=[];
const err=m=>{dealErr.hidden=!m;dealErr.textContent=m||''};
function renderProducts(){const cid=Number(dealCategory.value);dealProduct.innerHTML=products.filter(p=>Number(p.category_id)===cid).map(p=>`<option value="${p.id}">${p.name}</option>`).join('');}
async function boot(){const data=await fetch('/admin/api/deals').then(r=>r.json());deals=data.deals||[];categories=data.categories||[];products=data.products||[];dealCategory.innerHTML=categories.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');const d=deals.find(x=>Number(x.id)===EDIT_ID);if(d){dealPosition.value=String(Math.max(1,Math.min(4,Number(d.sort_order||1))));const p=products.find(x=>Number(x.id)===Number(d.product_id));if(p)dealCategory.value=String(p.category_id);renderProducts();if(p)dealProduct.value=String(p.id);}else renderProducts();}
const dealPosition=document.getElementById('deal-position'),dealCategory=document.getElementById('deal-category'),dealProduct=document.getElementById('deal-product');
async function saveDeal(){const p=products.find(x=>Number(x.id)===Number(dealProduct.value));if(!p){err('Select a product.');return;}const regular=Number(p.original_price||0),price=Number(p.min_price||0);const payload={deal_type:'deal',card_position:Number(dealPosition.value||1),product_id:Number(p.id),title:p.name,image_path:p.primary_image||p.image_path||'',image_alt:p.name,price_text:'₹'+price.toLocaleString('en-IN'),subtitle:regular>price?`Save ${Math.round((regular-price)/regular*100)}%`:'Best Deal',cta_text:'Order Now',cta_url:'/product/'+p.slug,color_theme:'green'};const data=await fetch(EDIT_ID?`/admin/api/deals/${EDIT_ID}`:'/admin/api/deals',{method:EDIT_ID?'PUT':'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)}).then(r=>r.json());if(!data.ok){err(data.msg||'Could not save deal');return;}location.href='/admin/deals';}
boot();
</script>
<?php include __DIR__.'/layout-end.php'; ?>

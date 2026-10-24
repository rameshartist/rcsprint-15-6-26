<?php $pageTitle='Add Filter Options — RCS Admin';$currentAdmPage='product-filters';include __DIR__.'/layout.php'; ?>
<div class="adm-page-head"><div><div class="adm-pt">Add Filter Options</div><p>Add or remove the options customers use to filter products.</p></div><a class="btn btn-outline" href="/admin/products">← Products</a></div>
<section class="filter-option-columns" id="filterColumns"><div class="adm-empty">Loading…</div></section>
<script>
const FILTER_CSRF='<?= htmlspecialchars($csrf??'',ENT_QUOTES) ?>';
const FILTER_GROUPS=[
  {slug:'paper_type',label:'Paper Type'},
  {slug:'lamination',label:'Lamination'},
  {slug:'finishing',label:'Finishing'}
];
let filterOptions=[];
const filterEsc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
function renderFilterColumns(){const box=document.getElementById('filterColumns');box.innerHTML=FILTER_GROUPS.map(group=>{const options=filterOptions.filter(row=>row.group_slug===group.slug);return `<article class="filter-option-card"><h2>${filterEsc(group.label)}</h2><form class="filter-option-add" onsubmit="addFilterOption(event,'${group.slug}')"><input class="fi" name="label" placeholder="Add ${filterEsc(group.label)} option" required><button class="btn btn-blue" type="submit">Add</button></form><div class="filter-option-list">${options.length?options.map(row=>`<div><span>${filterEsc(row.label)}</span><button type="button" class="filter-option-remove" onclick="deleteFilterOption(${Number(row.id)})" aria-label="Remove ${filterEsc(row.label)}" title="Remove">×</button></div>`).join(''):'<p>No options added.</p>'}</div></article>`}).join('');}
async function loadFilterOptions(){const data=await fetch('/admin/api/product-filter-options').then(r=>r.json());filterOptions=data.options||[];renderFilterColumns();}
async function addFilterOption(event,groupSlug){event.preventDefault();const form=event.currentTarget,label=form.elements.label.value.trim();if(!label)return;const data=await fetch('/admin/api/product-filter-options',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FILTER_CSRF},body:JSON.stringify({group_slug:groupSlug,label})}).then(r=>r.json());if(!data.ok){alert(data.msg||'Could not add filter option');return;}form.reset();await loadFilterOptions();}
async function deleteFilterOption(id){if(!confirm('Remove this filter option from products and category filters?'))return;const data=await fetch(`/admin/api/product-filter-options/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':FILTER_CSRF}}).then(r=>r.json());if(!data.ok){alert(data.msg||'Could not remove filter option');return;}await loadFilterOptions();}
loadFilterOptions();
</script></div></div></div></body></html>

<?php
$pageTitle = 'Add Product — RCS Admin';
$currentAdmPage = 'products-new';
include __DIR__ . '/layout.php';
$editId = (int)($_GET['id'] ?? 0);
?>
<div class="adm-pt"><?= $editId ? 'Edit Product' : 'Add Product' ?></div>

<div class="fsec product-editor-wide">
  <input type="hidden" id="ep-id" value="<?= $editId ?>">

  <div class="product-editor-grid product-editor-grid-4">
    <div class="fg"><label>Product Name *</label><input class="fi" id="ep-name"></div>
    <div class="fg"><label>Category *</label><select class="fi fi-sel" id="ep-cat"></select></div>
    <div class="fg"><label>Product Code</label><input class="fi" id="ep-code" placeholder="Auto: PREFIX-001"></div>
    <div class="fg"><label>Code Prefix (from category)</label><input class="fi" id="ep-prefix" disabled></div>
  </div>
  <div id="ep-code-help" style="font-size:12px;color:var(--text2);margin-top:-6px;margin-bottom:10px">
    Leave Product Code empty to auto-generate from selected category prefix.
  </div>
  <div class="f2">
    <div class="fg"><label>Design Fee (₹)</label><input type="number" min="0" class="fi" id="ep-design-fee" value="0"></div>
    <div class="fg"><label>Original Price / MRP (₹)</label><input type="number" min="0" step="0.01" class="fi" id="ep-original-price" placeholder="Optional, for discount badge"></div>
  </div>
  <div class="fg"><label>Status</label><select class="fi fi-sel" id="ep-active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
  <div class="fg"><label>Business / Sector Collections</label><div id="ep-business-needs" class="product-sector-picker"></div><div style="font-size:12px;color:var(--text3);margin-top:6px">Select sectors where this product should appear, e.g. Education, Healthcare, Retail.</div></div>
  <div class="fg"><label>Description</label><textarea class="fi" id="ep-desc" style="height:84px"></textarea></div>
  <div class="fg"><label>YouTube Video URL</label><input class="fi" id="ep-video-url" placeholder="https://www.youtube.com/watch?v=..."></div>
  <div class="f2">
    <div class="fg"><label><input type="checkbox" id="ep-show-delivery" checked> Show delivery information</label><input class="fi" id="ep-delivery" value="Delivery in 3 - 5 Working Days"></div>
    <div class="fg"><label><input type="checkbox" id="ep-show-free-delivery" checked> Show free-delivery information</label><input class="fi" id="ep-free-delivery" value="Free Delivery on Orders Above ₹999"></div>
  </div>
  <div class="fg"><label>Specifications (Label: Value per line)</label><textarea class="fi" id="ep-specs" style="height:96px"></textarea></div>

  <div class="fg">
    <label>Product Filters (used on All Categories page)</label>
    <div id="ep-filter-options" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px"></div>
    <div style="font-size:12px;color:var(--text3);margin-top:6px">Select every paper, lamination and finishing option this product supports.</div>
  </div>

  <div class="fg">
    <label>Product Images (multiple allowed, jpg/png/webp, max 5MB each)</label>
    <input type="file" class="fi" id="ep-images" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
    <div id="imagePreview" style="margin-top:10px;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px"></div>
  </div>

  <div class="fg" style="margin-top:8px">
    <div class="product-tier-head"><label>Quantity Tier Pricing *</label><button class="btn btn-outline btn-sm" type="button" onclick="addTierRow()">＋ Add Quantity &amp; Price</button></div>
    <div id="tierRows" class="product-tier-rows"></div>
    <div style="font-size:12px;color:var(--text3);margin-top:6px">Add any required quantity and its total price. Existing storefront calculations remain unchanged.</div>
  </div>

  <div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:16px">
    <button class="btn btn-blue" id="saveBtn" onclick="saveProd()" style="padding:12px 26px;border-radius:10px">Save Product ✓</button>
    <a href="/admin/products" class="btn btn-outline" style="padding:12px 18px;border-radius:10px">Back to All Products</a>
  </div>
</div>

<script>
let allCats = [];
let currentImages = [];
let pendingImages = [];
let autoCodePreview = '';
let productFilters = {};
let businessNeeds = [];

function renderPreview(images = currentImages, newImages = pendingImages) {
  const box = document.getElementById('imagePreview');
  box.innerHTML = '';
  const allImages = [
    ...images.map((img, i) => ({...normalizeImage(img), index: i, isNew: false})),
    ...newImages.map((img, i) => ({...normalizeImage(img), index: i, isNew: true}))
  ].filter(img => img.path);

  if (!allImages.length) {
    box.innerHTML = '<div style="grid-column:1/-1;padding:14px;border:1px dashed var(--border);border-radius:10px;color:var(--text2);font-size:12px;background:#fff">No product images yet. Upload images and save the product.</div>';
    return;
  }

  allImages.forEach((img) => {
    const div = document.createElement('div');
    div.style.cssText = 'border:1px solid var(--border);border-radius:10px;padding:8px;background:#fff;position:relative;display:grid;gap:7px';
    const badge = img.isNew ? 'New image' : (img.isPrimary ? 'Primary image' : 'Gallery image');
    const deleteButton = (!img.isNew && img.id > 0)
      ? `<button type="button" class="btn btn-red btn-xs" onclick="deleteProductImage(${img.id})" style="width:100%;justify-content:center">🗑️ Delete image</button>`
      : (img.isNew
        ? '<div style="font-size:11px;color:var(--green);font-weight:700">Will upload on save</div>'
        : '<button type="button" class="btn btn-red btn-xs" onclick="deleteLegacyProductImage()" style="width:100%;justify-content:center">🗑️ Clear main image</button>');
    div.innerHTML = `
      <img src="${escAttr(img.path)}" style="width:100%;height:88px;object-fit:cover;border-radius:8px;border:1px solid var(--border)" onerror="this.style.display='none'">
      <div style="font-size:11px;color:${img.isPrimary ? 'var(--blue)' : 'var(--text2)'};font-weight:700">${badge}</div>
      ${deleteButton}
    `;
    box.appendChild(div);
  });
}

function normalizeImage(img) {
  if (typeof img === 'string') return {id: 0, path: img, isPrimary: false};
  return {
    id: parseInt(img?.id || '0', 10),
    path: img?.image_path || img?.url || '',
    isPrimary: Number(img?.is_primary || 0) === 1,
  };
}

function collectFixedTiers() {
  const tiers = [];
  document.querySelectorAll('.tier-row').forEach(row => {
    const qty = parseInt(row.querySelector('[data-tier-qty]').value || '0', 10);
    const price = parseFloat(row.querySelector('[data-tier-price]').value || '0');
    if (price > 0) tiers.push({quantity: qty, price});
  });
  if (!tiers.length) return {ok:false, msg:'Add at least one quantity price'};
  return {ok:true, tiers};
}
function addTierRow(tier={}){document.getElementById('tierRows').insertAdjacentHTML('beforeend',`<div class="tier-row"><label>Quantity<input type="number" min="1" step="1" class="fi" data-tier-qty value="${Number(tier.quantity||'')||''}" placeholder="e.g. 500"></label><label>Price (₹)<input type="number" min="0.01" step="0.01" class="fi" data-tier-price value="${Number(tier.price||'')||''}" placeholder="Total price"></label><button type="button" class="btn btn-outline btn-sm" onclick="this.closest('.tier-row').remove()">Remove</button></div>`)}

function renderProductFilters(selected = {}) {
  const box = document.getElementById('ep-filter-options');
  if (!box) return;
  const groups = Object.values(productFilters || {});
  if (!groups.length) {
    box.innerHTML = '<div style="grid-column:1/-1;padding:12px;border:1px dashed var(--border);border-radius:10px;color:var(--text2);background:#fff;font-size:12px">Filter options unavailable.</div>';
    return;
  }
  box.innerHTML = groups.map(group => {
    const opts = group.options || [];
    return `<div style="border:1px solid var(--border);border-radius:12px;background:#fff;padding:12px">
      <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:9px">${escH(group.label || group.slug)}</div>
      <div style="display:grid;gap:8px">
        ${opts.map(opt => {
          const checked = (selected[group.slug] || []).includes(opt.slug) ? 'checked' : '';
          return `<label style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--text2);font-weight:700">
            <input type="checkbox" class="ep-filter-check" data-group="${escAttr(group.slug)}" value="${escAttr(opt.slug)}" ${checked}> ${escH(opt.label)}
          </label>`;
        }).join('')}
      </div>
    </div>`;
  }).join('');
}

function collectProductFilters() {
  const out = {};
  document.querySelectorAll('.ep-filter-check:checked').forEach(input => {
    const group = input.dataset.group || '';
    const value = input.value || '';
    if (!group || !value) return;
    if (!out[group]) out[group] = [];
    out[group].push(value);
  });
  return out;
}

function renderBusinessNeeds(selected = []) {
  const box = document.getElementById('ep-business-needs');
  if (!box) return;
  const selectedSet = new Set((selected || []).map(Number));
  if (!businessNeeds.length) {
    box.innerHTML = '<div style="padding:12px;border:1px dashed var(--border);border-radius:10px;color:var(--text2);background:#fff;font-size:12px">No business sectors yet. Create sectors from Admin → Business Needs.</div>';
    return;
  }
  box.innerHTML = businessNeeds.map(need => `<label class="product-sector-choice"><input type="checkbox" class="ep-business-check" value="${Number(need.id)}" ${selectedSet.has(Number(need.id)) ? 'checked' : ''}><span><b>${escH(need.icon || '🏢')} ${escH(need.name)}</b><small>${escH(need.description || 'Sector collection')}</small></span></label>`).join('');
}
function collectBusinessNeeds() {
  return [...document.querySelectorAll('.ep-business-check:checked')].map(input => Number(input.value)).filter(Boolean);
}

async function boot() {
  const catsRes = await fetch('/admin/api/categories').then(r=>r.json());
  allCats = catsRes.categories || [];
  const filtersRes = await fetch('/admin/api/product-filters').then(r=>r.json()).catch(()=>({filters:{}}));
  productFilters = filtersRes.filters || {};
  const needsRes = await fetch('/admin/api/business-needs').then(r=>r.json()).catch(()=>({needs:[]}));
  businessNeeds = needsRes.needs || [];
  renderProductFilters();
  renderBusinessNeeds();
  document.getElementById('ep-cat').innerHTML = allCats.map(c=>`<option value="${c.id}">${escH(c.name)}</option>`).join('');
  document.getElementById('ep-cat').addEventListener('change', updateCatPrefixHint);
  document.getElementById('ep-code').addEventListener('input', updateCodeHelp);
  await updateCatPrefixHint();

  document.getElementById('ep-images').addEventListener('change', e => {
    const files = [...(e.target.files || [])];
    if (!files.length) return;
    pendingImages.push(...files.map(f => ({image_path: URL.createObjectURL(f),file:f})));
    renderPreview();
  });

  const id = parseInt(document.getElementById('ep-id').value || '0', 10);
  if (!id) return;

  const res = await fetch(`/admin/api/products/${id}`).then(r=>r.json());
  if (!res.ok || !res.product) return;
  const p = res.product;
  document.getElementById('ep-name').value = p.name || '';
  document.getElementById('ep-cat').value = p.category_id || '';
  document.getElementById('ep-code').value = p.product_code || '';
  document.getElementById('ep-design-fee').value = p.design_fee || 0;
  document.getElementById('ep-original-price').value = p.original_price || '';
  document.getElementById('ep-active').value = p.is_active ? '1' : '0';
  document.getElementById('ep-desc').value = p.description || '';
  document.getElementById('ep-video-url').value = p.video_url || '';
  document.getElementById('ep-show-delivery').checked = Number(p.show_delivery_info ?? 1) === 1;
  document.getElementById('ep-delivery').value = p.delivery_info || 'Delivery in 3 - 5 Working Days';
  document.getElementById('ep-show-free-delivery').checked = Number(p.show_free_delivery_info ?? 1) === 1;
  document.getElementById('ep-free-delivery').value = p.free_delivery_info || 'Free Delivery on Orders Above ₹999';
  document.getElementById('ep-specs').value = (p.specs||[]).map(s=>`${s.label}: ${s.value||''}`).join('\n');
  renderProductFilters(p.filter_options || {});
  renderBusinessNeeds(p.business_need_ids || []);

  currentImages = p.images || (p.image_path ? [{image_path:p.image_path, is_primary: 1}] : []);
  pendingImages = [];
  renderPreview();

  const tiersRes = await fetch(`/admin/api/products/${id}/tiers`).then(r=>r.json());
  document.getElementById('tierRows').innerHTML='';(tiersRes.tiers||[]).forEach(addTierRow);if(!(tiersRes.tiers||[]).length)addTierRow();
  await updateCatPrefixHint();
}

async function updateCatPrefixHint() {
  const catId = parseInt(document.getElementById('ep-cat')?.value || '0', 10);
  const cat = allCats.find(c => Number(c.id) === catId);
  const prefix = (cat?.code_prefix || '').toUpperCase();
  const box = document.getElementById('ep-prefix');
  if (box) box.value = prefix || 'Not set';

  autoCodePreview = '';
  if (catId > 0) {
    try {
      const editId = parseInt(document.getElementById('ep-id')?.value || '0', 10);
      const q = editId > 0 ? `?edit_id=${editId}` : '';
      const res = await fetch(`/admin/api/categories/${catId}/next-product-code${q}`).then(r=>r.json());
      if (res?.ok && res.code) autoCodePreview = String(res.code).toUpperCase();
    } catch (e) {
      console.warn('Could not fetch auto code preview', e);
    }
  }
  updateCodeHelp();
}

function updateCodeHelp() {
  const codeInput = document.getElementById('ep-code');
  const help = document.getElementById('ep-code-help');
  if (!codeInput || !help) return;
  const typed = codeInput.value.trim().toUpperCase();
  if (typed) {
    help.textContent = /^\d+$/.test(typed) ? 'This number will be combined with the category prefix.' : 'Manual Product Code will be used as-is.';
    help.style.color = 'var(--blue)';
    return;
  }
  if (autoCodePreview) {
    help.textContent = `Auto code on save: ${autoCodePreview}`;
    help.style.color = 'var(--green)';
    return;
  }
  help.textContent = 'Auto code unavailable (check category prefix / DB migration).';
  help.style.color = 'var(--red)';
}

async function deleteProductImage(imageId) {
  const id = parseInt(document.getElementById('ep-id').value || '0', 10);
  imageId = parseInt(imageId || '0', 10);
  if (!id || !imageId) { toast('Save the product before deleting images', 'error'); return; }
  const img = currentImages.find(item => Number(item.id || 0) === imageId);
  const label = img && Number(img.is_primary || 0) === 1 ? 'primary product image' : 'product image';
  if (!confirm(`Delete this ${label}? This cannot be undone.`)) return;

  try {
    const res = await fetch(`/admin/api/products/${id}/images/${imageId}`, {
      method: 'DELETE',
      headers: {'X-CSRF-TOKEN':'<?= htmlspecialchars($csrf??'') ?>'},
      credentials: 'same-origin'
    }).then(r => r.json());

    if (!res.ok) { toast(res.msg || 'Could not delete image', 'error'); return; }
    currentImages = (res.images || []).length ? res.images : currentImages.filter(item => Number(item.id || 0) !== imageId);
    renderPreview();
    toast('Product image deleted', 'success');
  } catch (err) {
    console.error(err);
    toast('Unexpected error while deleting image', 'error');
  }
}

async function deleteLegacyProductImage() {
  const id = parseInt(document.getElementById('ep-id').value || '0', 10);
  if (!id) { toast('Save the product before deleting images', 'error'); return; }
  if (!confirm('Clear this legacy main product image? This cannot be undone.')) return;

  try {
    const res = await fetch(`/admin/api/products/${id}/image-path`, {
      method: 'DELETE',
      headers: {'X-CSRF-TOKEN':'<?= htmlspecialchars($csrf??'') ?>'},
      credentials: 'same-origin'
    }).then(r => r.json());

    if (!res.ok) { toast(res.msg || 'Could not clear main image', 'error'); return; }
    currentImages = res.images || [];
    renderPreview();
    toast('Main product image cleared', 'success');
  } catch (err) {
    console.error(err);
    toast('Unexpected error while clearing image', 'error');
  }
}

async function saveProd() {
  const id = parseInt(document.getElementById('ep-id').value || '0', 10);
  const name = document.getElementById('ep-name').value.trim();
  const catId = parseInt(document.getElementById('ep-cat').value || '0', 10);
  if (!name || !catId) { toast('Name and category required', 'error'); return; }

  const tierCheck = collectFixedTiers();
  if (!tierCheck.ok) { toast(tierCheck.msg, 'error'); return; }

  const specsRaw = document.getElementById('ep-specs').value.trim().split('\n').filter(Boolean);
  const specs = specsRaw.map(s => {
    const [label, ...rest] = s.split(':');
    return { label: label.trim(), value: rest.join(':').trim() };
  }).filter(s=>s.label);

  const payload = {
    name,
    category_id: catId,
    product_code: document.getElementById('ep-code').value.trim().toUpperCase(),
    description: document.getElementById('ep-desc').value.trim(),
    video_url: document.getElementById('ep-video-url').value.trim(),
    show_delivery_info: document.getElementById('ep-show-delivery').checked ? 1 : 0,
    delivery_info: document.getElementById('ep-delivery').value.trim(),
    show_free_delivery_info: document.getElementById('ep-show-free-delivery').checked ? 1 : 0,
    free_delivery_info: document.getElementById('ep-free-delivery').value.trim(),
    design_fee: parseFloat(document.getElementById('ep-design-fee').value || '0') || 0,
    original_price: parseFloat(document.getElementById('ep-original-price').value || '0') || 0,
    is_active: parseInt(document.getElementById('ep-active').value || '1',10),
    specs,
    quantity_tiers: tierCheck.tiers,
    filter_options: collectProductFilters(),
    business_need_ids: collectBusinessNeeds(),
  };

  const saveBtn = document.getElementById('saveBtn');
  saveBtn.disabled = true;
  const oldText = saveBtn.textContent;
  saveBtn.textContent = 'Saving...';

  try {
    const url = id ? `/admin/api/products/${id}` : '/admin/api/products';
    const method = id ? 'PUT' : 'POST';
    const res = await fetch(url, {
      method,
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'<?= htmlspecialchars($csrf??'') ?>'},
      body: JSON.stringify(payload)
    }).then(r=>r.json());

    if (!res.ok) {
      toast(res.msg || 'Failed to save product', 'error');
      console.error('Save product failed response:', res);
      return;
    }

    const prodId = res.id || id;

    const tr = await fetch(`/admin/api/products/${prodId}/tiers`, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'<?= htmlspecialchars($csrf??'') ?>'},
      body: JSON.stringify({tiers: tierCheck.tiers})
    }).then(r=>r.json());
    if (!tr.ok) { toast(tr.msg || 'Tier save failed', 'error'); return; }

    const files = pendingImages.map(item=>item.file).filter(Boolean);
    if (files.length) {
      const fd = new FormData();
      files.forEach(f => fd.append('images[]', f));
      const up = await fetch(`/admin/api/products/${prodId}/images-upload`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN':'<?= htmlspecialchars($csrf??'') ?>'},
        body: fd
      }).then(r=>r.json());
      if (!up.ok) { toast(up.msg || 'Image upload failed', 'error'); return; }
    }

    toast(id ? 'Product updated successfully' : 'Product created successfully', 'success');
    setTimeout(()=>{ window.location.href = '/admin/products'; }, 700);
  } catch (err) {
    console.error(err);
    toast('Unexpected error while saving. Check logs.', 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = oldText;
  }
}

function escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;'); }
function escAttr(s){return escH(s).replace(/'/g,'&#39;');}
function toast(msg, type='info') {
  const w=document.getElementById('tw'); const t=document.createElement('div');
  t.className='toast '+type; t.textContent=msg; w.appendChild(t);
  requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('show')));
  setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),300);},3000);
}

addTierRow();boot();
</script>
    </div></div></div>
</body></html>

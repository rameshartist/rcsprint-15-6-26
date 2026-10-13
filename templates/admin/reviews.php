<?php
$pageTitle = 'Reviews — RCS Admin';
$currentAdmPage = 'reviews';
include __DIR__ . '/layout.php';
?>
<div class="adm-pt">Customer Reviews</div>

<div class="admin-review-toolbar">
  <div>
    <strong id="reviewCount">Loading reviews…</strong>
    <p>Approve verified customer feedback and mark the best reviews as featured for product/home pages.</p>
  </div>
  <div class="admin-review-filters">
    <select id="reviewStatus" class="fi" onchange="loadReviews()">
      <option value="all">All Status</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
    </select>
    <input id="reviewSearch" class="fi" placeholder="Search product, customer or comment" oninput="scheduleReviewLoad()">
  </div>
</div>

<div id="reviewList" class="admin-review-list">
  <div style="text-align:center;padding:44px;color:var(--text2)"><div class="pay-spin" style="border-top-color:var(--blue);margin:0 auto 12px"></div>Loading reviews…</div>
</div>

<script>
let reviewTimer = null;
function escH(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
function statusLabel(status) {
  return {pending:'Pending', approved:'Approved', rejected:'Rejected'}[status] || status || 'Pending';
}
function scheduleReviewLoad() {
  clearTimeout(reviewTimer);
  reviewTimer = setTimeout(loadReviews, 250);
}
async function loadReviews() {
  const status = document.getElementById('reviewStatus').value;
  const search = document.getElementById('reviewSearch').value.trim();
  const params = new URLSearchParams({status, search});
  const list = document.getElementById('reviewList');
  try {
    const res = await fetch('/admin/api/reviews?' + params.toString(), {credentials:'same-origin'}).then(r=>r.json());
    const reviews = res.reviews || [];
    document.getElementById('reviewCount').textContent = `${reviews.length} review${reviews.length === 1 ? '' : 's'}`;
    if (!reviews.length) {
      list.innerHTML = `<div class="admin-review-empty"><strong>No reviews found</strong><span>Customer reviews will appear here after submission.</span></div>`;
      return;
    }
    list.innerHTML = reviews.map(r => `
      <article class="admin-review-card" data-review-id="${Number(r.id || 0)}">
        <div class="admin-review-top">
          <div class="admin-review-customer">
            <span class="admin-review-avatar">${r.customer_avatar?`<img src="${escH(r.customer_avatar)}" alt="" loading="lazy">`:escH((r.customer_initials||'RC').slice(0,2))}</span>
            <div>
            <strong>${escH(r.product_name || 'Product')}</strong>
            <span>${escH(r.customer_name || 'Customer')} · ${escH(r.customer_email || '')}</span>
            </div>
          </div>
          <div class="admin-review-badges">
            <span class="admin-review-stars" aria-label="${Number(r.rating || 0)} out of 5 stars">${escH(r.stars || '★★★★★')}</span>
            <span class="admin-review-status is-${escH(r.status || 'pending')}">${escH(statusLabel(r.status))}</span>
            ${r.is_featured ? '<span class="admin-review-featured">Featured</span>' : ''}
          </div>
        </div>
        <p>${escH(r.comment || '')}</p>
        <div class="admin-review-meta">
          <span>Order: ${escH(r.public_order_id || '—')}</span>
          <span>Date: ${escH(r.created_display || '—')}</span>
          ${r.product_slug ? `<a href="/product/${encodeURIComponent(r.product_slug)}" target="_blank" rel="noopener">View product</a>` : ''}
        </div>
        <div class="admin-review-actions">
          <button class="btn btn-blue btn-sm" onclick="moderateReview(${Number(r.id || 0)}, 'approve')">Approve</button>
          <button class="btn btn-outline btn-sm" onclick="moderateReview(${Number(r.id || 0)}, 'pending')">Move Pending</button>
          <button class="btn btn-outline btn-sm" onclick="moderateReview(${Number(r.id || 0)}, 'reject')">Reject</button>
          <button class="btn btn-outline btn-sm" onclick="featureReview(${Number(r.id || 0)}, ${r.is_featured ? 'false' : 'true'})">${r.is_featured ? 'Unfeature' : 'Feature'}</button>
          <button class="btn btn-danger btn-sm" onclick="deleteReview(${Number(r.id || 0)})">Delete</button>
        </div>
      </article>
    `).join('');
  } catch (err) {
    console.error(err);
    list.innerHTML = `<div class="admin-review-empty is-error"><strong>Could not load reviews</strong><span>Please ensure product_reviews table migration is installed.</span></div>`;
  }
}
async function moderateReview(id, action) {
  const note = action === 'reject' ? (prompt('Optional rejection note for customer/admin record:', '') || '') : '';
  const res = await fetch(`/admin/api/reviews/${id}/${action}`, {
    method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({note})
  }).then(r=>r.json());
  toast(res.msg || (res.ok ? 'Review updated' : 'Failed'), res.ok ? 'success' : 'error');
  if (res.ok) loadReviews();
}
async function featureReview(id, featured) {
  const res = await fetch(`/admin/api/reviews/${id}/feature`, {
    method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({featured})
  }).then(r=>r.json());
  toast(res.msg || (res.ok ? 'Review updated' : 'Failed'), res.ok ? 'success' : 'error');
  if (res.ok) loadReviews();
}
async function deleteReview(id) {
  if (!confirm('Delete this review permanently?')) return;
  const res = await fetch(`/admin/api/reviews/${id}`, {method:'DELETE', credentials:'same-origin'}).then(r=>r.json());
  toast(res.msg || (res.ok ? 'Review deleted' : 'Failed'), res.ok ? 'success' : 'error');
  if (res.ok) loadReviews();
}
function toast(msg, type='info') {
  const w=document.getElementById('tw'); const t=document.createElement('div');
  t.className='toast '+type; t.textContent=msg; w.appendChild(t);
  requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('show')));
  setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),300);},2800);
}
loadReviews();
</script>
</div></div></div>
</body></html>

<?php
$pageTitle = 'Add FAQ — RCS Admin';
$currentAdmPage = 'faqs';
include __DIR__ . '/layout.php';
?>
<div class="adm-faq-page">
  <section class="adm-faq-hero">
    <div>
      <span>Content Manager</span>
      <h1>Add FAQ</h1>
      <p>Create separate FAQs for the Contact page and common Product Detail pages.</p>
    </div>
    <button class="adm-faq-primary" type="button" onclick="openFaqForm()">+ Add FAQ</button>
  </section>

  <section class="adm-faq-panel">
    <div class="adm-faq-tabs" id="faqTabs">
      <button class="act" type="button" data-page="all" onclick="setFaqFilter('all')">All FAQs</button>
      <button type="button" data-page="contact" onclick="setFaqFilter('contact')">Contact Page</button>
      <button type="button" data-page="product_detail" onclick="setFaqFilter('product_detail')">Product Detail Pages</button>
    </div>
    <div id="faqList" class="adm-faq-list"><div class="adm-faq-empty">Loading FAQs…</div></div>
  </section>
</div>

<div id="faqModal" class="adm-faq-modal" hidden>
  <form class="adm-faq-dialog" onsubmit="saveFaq(event)">
    <div class="adm-faq-dialog-head">
      <div><strong id="faqModalTitle">Add FAQ</strong><small>Choose where this FAQ should appear.</small></div>
      <button type="button" onclick="closeFaqForm()">×</button>
    </div>
    <input type="hidden" id="faq-id" value="0">
    <label>FAQ For
      <select id="faq-page" class="fi fi-sel">
        <option value="contact">FAQs for Contact Page</option>
        <option value="product_detail">FAQs for All Product Detail Pages</option>
      </select>
    </label>
    <label>FAQ Category / Topic
      <input id="faq-topic" class="fi" maxlength="160" placeholder="Example: Artwork & Design">
    </label>
    <label id="faq-category-wrap" hidden>Product Category
      <select id="faq-category" class="fi fi-sel"><option value="">All Product Detail Pages</option></select>
    </label>
    <label>Question
      <input id="faq-question" class="fi" maxlength="255" required placeholder="Example: Do you offer design support?">
    </label>
    <label>Answer
      <textarea id="faq-answer" class="fi" rows="5" required placeholder="Write a clear answer for customers..."></textarea>
    </label>
    <div class="f2">
      <label>Sort Order
        <input id="faq-sort" class="fi" type="number" value="0">
      </label>
      <label>Status
        <select id="faq-active" class="fi fi-sel"><option value="1">Active</option><option value="0">Inactive</option></select>
      </label>
    </div>
    <div id="faqErr" class="adm-faq-error" hidden></div>
    <div class="adm-faq-dialog-actions">
      <button class="btn btn-blue btn-sm" type="submit" id="faqSaveBtn">Save FAQ</button>
      <button class="btn btn-outline btn-sm" type="button" onclick="closeFaqForm()">Cancel</button>
    </div>
  </form>
</div>

<script>
const CSRF = '<?= htmlspecialchars($csrf ?? '') ?>';
let FAQS = [];
let FAQ_LABELS = {};
let FAQ_CATEGORIES = [];
let faqFilter = 'all';

const escFaq = (s) => String(s || '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

async function loadFaqs() {
  const res = await fetch('/admin/api/faqs', {credentials:'same-origin'}).then(r => r.json());
  FAQS = res.faqs || [];
  FAQ_LABELS = res.page_labels || {}; FAQ_CATEGORIES=res.categories||[];document.getElementById('faq-category').innerHTML='<option value="">All Product Detail Pages</option>'+FAQ_CATEGORIES.map(c=>`<option value="${Number(c.id)}">${escFaq(c.name)}</option>`).join('');
  renderFaqs();
}

function setFaqFilter(page) {
  faqFilter = page;
  document.querySelectorAll('#faqTabs button').forEach(btn => btn.classList.toggle('act', btn.dataset.page === page));
  renderFaqs();
}

function renderFaqs() {
  const list = document.getElementById('faqList');
  const rows = faqFilter === 'all' ? FAQS : FAQS.filter(f => f.page_key === faqFilter);
  if (!rows.length) {
    list.innerHTML = '<div class="adm-faq-empty">No FAQs found. Add your first FAQ.</div>';
    return;
  }
  list.innerHTML = rows.map(f => `
    <article class="adm-faq-card">
      <div>
        <span>${escFaq(FAQ_LABELS[f.page_key] || f.page_key)}${f.topic?' · '+escFaq(f.topic):''}</span>
        <strong>${escFaq(f.question)}</strong>
        <p>${escFaq(f.answer)}</p>
        <small>Sort: ${Number(f.sort_order || 0)} · ${Number(f.is_active) ? 'Active' : 'Inactive'}</small>
      </div>
      <div class="adm-faq-card-actions">
        <button type="button" onclick="openFaqForm(${Number(f.id)})">Edit</button>
        <button type="button" onclick="toggleFaq(${Number(f.id)})">${Number(f.is_active) ? 'Disable' : 'Enable'}</button>
        <button type="button" class="danger" onclick="deleteFaq(${Number(f.id)})">Delete</button>
      </div>
    </article>
  `).join('');
}

function openFaqForm(id = 0) {
  const faq = FAQS.find(f => Number(f.id) === Number(id));
  document.getElementById('faqModalTitle').textContent = faq ? 'Edit FAQ' : 'Add FAQ';
  document.getElementById('faq-id').value = faq ? Number(faq.id) : 0;
  document.getElementById('faq-page').value = faq?.page_key || (faqFilter !== 'all' ? faqFilter : 'contact');
  document.getElementById('faq-topic').value = faq?.topic || '';
  document.getElementById('faq-category').value = faq?.category_id || '';
  toggleFaqCategory();
  document.getElementById('faq-question').value = faq?.question || '';
  document.getElementById('faq-answer').value = faq?.answer || '';
  document.getElementById('faq-sort').value = Number(faq?.sort_order || 0);
  document.getElementById('faq-active').value = Number(faq?.is_active ?? 1) ? '1' : '0';
  document.getElementById('faqErr').hidden = true;
  document.getElementById('faqModal').hidden = false;
}

function closeFaqForm() {
  document.getElementById('faqModal').hidden = true;
}

async function saveFaq(event) {
  event.preventDefault();
  const id = Number(document.getElementById('faq-id').value || 0);
  const btn = document.getElementById('faqSaveBtn');
  const err = document.getElementById('faqErr');
  const payload = {
    page_key: document.getElementById('faq-page').value,
    topic: document.getElementById('faq-topic').value.trim(),
    category_id: document.getElementById('faq-page').value==='product_detail'?(Number(document.getElementById('faq-category').value)||null):null,
    question: document.getElementById('faq-question').value.trim(),
    answer: document.getElementById('faq-answer').value.trim(),
    sort_order: Number(document.getElementById('faq-sort').value || 0),
    is_active: Number(document.getElementById('faq-active').value || 0),
  };
  btn.disabled = true;
  btn.textContent = 'Saving…';
  try {
    const res = await fetch(id ? `/admin/api/faqs/${id}` : '/admin/api/faqs', {
      method: id ? 'PUT' : 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
      credentials:'same-origin',
      body: JSON.stringify(payload)
    }).then(r => r.json());
    if (!res.ok) {
      err.textContent = res.msg || 'Could not save FAQ.';
      err.hidden = false;
      return;
    }
    closeFaqForm();
    await loadFaqs();
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save FAQ';
  }
}

function toggleFaqCategory() {
  document.getElementById('faq-category-wrap').hidden = document.getElementById('faq-page').value !== 'product_detail';
}

async function toggleFaq(id) {
  await fetch(`/admin/api/faqs/${id}/toggle`, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF}, credentials:'same-origin'});
  loadFaqs();
}

async function deleteFaq(id) {
  if (!confirm('Delete this FAQ?')) return;
  await fetch(`/admin/api/faqs/${id}`, {method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF}, credentials:'same-origin'});
  loadFaqs();
}

document.getElementById('faq-page').addEventListener('change', toggleFaqCategory);
loadFaqs();
</script>
    </div></div></div>
</body></html>

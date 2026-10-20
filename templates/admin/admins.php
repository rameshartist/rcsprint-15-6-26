<?php
$pageTitle = 'Admins — RCS Admin';
$currentAdmPage = 'admins';
include __DIR__ . '/layout.php';
$currentAdmin = \Auth\Auth::admin();
$canManageAdmins = \Auth\Auth::isSuperAdmin();
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px">
  <div>
    <div class="adm-pt" style="margin:0">Admins</div>
    <div style="font-size:12px;color:var(--text2);margin-top:4px">Create admin users and manage backend access.</div>
  </div>
  <div style="display:flex;align-items:center;gap:10px"><span id="admCount" style="font-size:14px;color:var(--text2)"></span><?php if($canManageAdmins): ?><button class="btn btn-blue" type="button" onclick="toggleAdminCreate(true)">+ Add Admin</button><?php endif; ?></div>
</div>

<?php if ($canManageAdmins): ?>
<div class="fsec admin-create-panel" id="adminCreatePanel" style="margin-bottom:14px" hidden>
  <div class="fsec-t">➕ Add New Admin</div>
  <div class="f2">
    <div class="fg"><label>Name *</label><input class="fi" id="adm-name" placeholder="Admin name"></div>
    <div class="fg"><label>Email *</label><input class="fi" id="adm-email" type="email" placeholder="name@example.com"></div>
  </div>
  <div class="f2">
    <div class="fg"><label>Mobile *</label><input class="fi" id="adm-mobile" placeholder="9876543210" maxlength="15"></div>
    <div class="fg"><label>Role *</label><select class="fi" id="adm-role"><option value="admin">Admin</option><option value="super">Super Admin</option></select></div>
  </div>
  <div class="f2">
    <div class="fg" style="position:relative">
      <label>Password *</label>
      <input class="fi" id="adm-password" type="password" placeholder="Minimum 6 characters" style="padding-right:42px">
      <button type="button" onclick="togglePass('adm-password', this)" aria-label="Show password" style="position:absolute;right:8px;top:34px;border:none;background:transparent;color:var(--text2);cursor:pointer;font-size:16px">👁️</button>
    </div>
    <div></div>
  </div>
  <div style="display:flex;gap:8px"><button class="btn btn-blue" onclick="createAdmin()" style="padding:10px 16px;border-radius:9px">Create Admin</button><button class="btn btn-outline" type="button" onclick="toggleAdminCreate(false)">Cancel</button></div>
</div>

<div class="modal-bg" id="m-adm-edit"><div class="modal-box"><div class="modal-pill"></div><div class="modal-hdr"><div class="modal-ttl">Edit Admin Profile</div><button class="modal-cls" onclick="closeAdminEdit()">✕</button></div><div class="modal-bdy"><input type="hidden" id="edit-adm-id"><div class="fg"><label>Name *</label><input class="fi" id="edit-adm-name"></div><div class="fg"><label>Email *</label><input class="fi" type="email" id="edit-adm-email"></div><div class="fg"><label>Mobile *</label><input class="fi" id="edit-adm-mobile"></div><div class="fg"><label>Role *</label><select class="fi" id="edit-adm-role"><option value="admin">Admin</option><option value="super">Super Admin</option></select></div><button class="btn btn-blue btn-full" onclick="saveAdminProfile()">Save Admin Details</button></div></div></div>
<?php else: ?>
<div class="fsec" style="margin-bottom:14px;border-left:4px solid #f59e0b"><strong>Read-only access</strong><br><span style="font-size:12px;color:var(--text2)">Only Super Admin can add, edit or remove admin users.</span></div>
<?php endif; ?>

<div class="fsec" style="padding:0;overflow:hidden">
  <div style="padding:14px 14px 10px;border-bottom:1px solid var(--border);font-weight:700">All Admin Users</div>
  <div id="adminsWrap"><div style="text-align:center;padding:30px;color:var(--text2)">Loading…</div></div>
</div>

<div class="modal-bg" id="m-adm-pass">
  <div class="modal-box">
    <div class="modal-pill"></div>
    <div class="modal-hdr"><div class="modal-ttl">Change Admin Password</div><button class="modal-cls" onclick="closePassModal()">✕</button></div>
    <div class="modal-bdy">
      <div id="passTarget" style="font-size:12px;color:var(--text2);margin-bottom:8px"></div>
      <div class="fg" style="position:relative">
        <label>New Password *</label>
        <input class="fi" id="adm-pass-new" type="password" placeholder="Minimum 6 characters" style="padding-right:42px">
        <button type="button" onclick="togglePass('adm-pass-new', this)" aria-label="Show password" style="position:absolute;right:8px;top:34px;border:none;background:transparent;color:var(--text2);cursor:pointer;font-size:16px">👁️</button>
      </div>
      <div class="fg" style="position:relative">
        <label>Confirm Password *</label>
        <input class="fi" id="adm-pass-confirm" type="password" placeholder="Retype password" style="padding-right:42px">
        <button type="button" onclick="togglePass('adm-pass-confirm', this)" aria-label="Show password" style="position:absolute;right:8px;top:34px;border:none;background:transparent;color:var(--text2);cursor:pointer;font-size:16px">👁️</button>
      </div>
      <button class="btn btn-blue btn-full" onclick="saveAdminPassword()" style="border-radius:10px">Update Password</button>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= htmlspecialchars($csrf ?? '') ?>';
const CURRENT_ADMIN_ID = Number('<?= (int)($currentAdmin['id'] ?? 0) ?>');
const CAN_MANAGE_ADMINS = <?= $canManageAdmins ? 'true' : 'false' ?>;
let passAdminId = 0;
let passAdminName = '';
let ADMIN_ROWS = [];
function toggleAdminCreate(show){document.getElementById('adminCreatePanel').hidden=!show;if(show)document.getElementById('adm-name')?.focus();}

function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fmtDate(s){ if(!s) return '—'; const d=new Date(s); return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}); }
function toast(msg,type='info'){const w=document.getElementById('tw');const t=document.createElement('div');t.className='toast '+type;t.textContent=msg;w.appendChild(t);requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('show')));setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),300);},2800);}
function togglePass(id, btn){ const inp=document.getElementById(id); if(!inp) return; const show=inp.type==='password'; inp.type=show?'text':'password'; btn.textContent=show?'🙈':'👁️'; }

async function loadAdmins() {
  const res = await fetch('/admin/api/admin-users', { credentials:'same-origin' }).then(r=>r.json());
  const admins = res.admins || [];
  ADMIN_ROWS = admins;
  document.getElementById('admCount').textContent = admins.length + ' admin users';
  if (!admins.length) {
    document.getElementById('adminsWrap').innerHTML = `<div style="text-align:center;padding:40px;color:var(--text2)"><div style="font-size:40px;margin-bottom:8px">🛡️</div><div>No admins found</div></div>`;
    return;
  }

  document.getElementById('adminsWrap').innerHTML = admins.map(a => `
    <div style="padding:12px 14px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div>
          <div style="font-size:14px;font-weight:700;color:var(--ink)">${escH(a.name || 'Admin')}</div>
          <div style="font-size:12px;color:var(--text2);margin-top:2px">${escH(a.email || '')}</div>
          <div style="font-size:12px;color:var(--text2)">${escH(a.mobile || '—')}</div>
        </div>
        <div style="text-align:right">
          <div style="font-size:11px;color:var(--text3)">${a.is_active == 1 ? 'Active' : 'Inactive'} · ${escH(a.role || 'admin')}</div>
          <div style="font-size:11px;color:var(--text3)">Created: ${fmtDate(a.created_at)}</div>
          <div style="font-size:11px;color:var(--text3)">Last login: ${fmtDate(a.last_login)}</div>
        </div>
      </div>
      ${CAN_MANAGE_ADMINS ? `<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px;flex-wrap:wrap"><button class="btn btn-outline btn-xs js-edit-btn" data-id="${Number(a.id)}">Edit Profile</button><button class="btn btn-outline btn-xs js-pass-btn" data-id="${Number(a.id)}" data-name="${escH(a.name || 'Admin')}">Change Password</button><button class="btn btn-red btn-xs js-remove-btn" data-id="${Number(a.id)}" data-name="${escH(a.name || 'Admin')}" ${Number(a.id)===CURRENT_ADMIN_ID ? 'disabled title="Cannot remove current login"' : ''}>Remove</button></div>` : ''}
    </div>
  `).join('');
}

function openAdminEdit(id){const a=ADMIN_ROWS.find(row=>Number(row.id)===Number(id));if(!a)return;document.getElementById('edit-adm-id').value=a.id;document.getElementById('edit-adm-name').value=a.name||'';document.getElementById('edit-adm-email').value=a.email||'';document.getElementById('edit-adm-mobile').value=a.mobile||'';document.getElementById('edit-adm-role').value=a.role==='super'?'super':'admin';document.getElementById('m-adm-edit').classList.add('show');}
function closeAdminEdit(){document.getElementById('m-adm-edit').classList.remove('show');}
async function saveAdminProfile(){const id=Number(document.getElementById('edit-adm-id').value);const payload={name:document.getElementById('edit-adm-name').value.trim(),email:document.getElementById('edit-adm-email').value.trim(),mobile:document.getElementById('edit-adm-mobile').value.trim(),role:document.getElementById('edit-adm-role').value};if(!payload.name||!payload.email||!payload.mobile){toast('Complete all admin details','error');return;}const res=await fetch(`/admin/api/admin-users/${id}`,{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},credentials:'same-origin',body:JSON.stringify(payload)}).then(r=>r.json());if(!res.ok){toast(res.msg||'Could not update admin','error');return;}toast('Admin profile updated','success');closeAdminEdit();loadAdmins();}

async function createAdmin() {
  if (!CAN_MANAGE_ADMINS) { toast('Only Super Admin can create admin users', 'error'); return; }
  const name = document.getElementById('adm-name').value.trim();
  const email = document.getElementById('adm-email').value.trim();
  const mobile = document.getElementById('adm-mobile').value.trim();
  const password = document.getElementById('adm-password').value;
  const role = document.getElementById('adm-role')?.value === 'super' ? 'super' : 'admin';

  if (!name || !email || !mobile || !password) { toast('Please fill all required fields', 'error'); return; }
  const resp = await fetch('/admin/api/admin-users', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    credentials:'same-origin',
    body: JSON.stringify({ name, email, mobile, password, role })
  }).then(r=>r.json());

  if (!resp.ok) { toast(resp.msg || 'Could not create admin', 'error'); return; }
  toast('Admin user created successfully', 'success');
  ['adm-name','adm-email','adm-mobile','adm-password'].forEach(id => { const el = document.getElementById(id); if (el) el.value=''; });
  const roleEl = document.getElementById('adm-role'); if (roleEl) roleEl.value = 'admin';
  document.getElementById('adm-password').type = 'password';
  loadAdmins();
}

function openPasswordModal(id, name) {
  passAdminId = Number(id || 0);
  passAdminName = String(name || 'Admin');
  document.getElementById('passTarget').textContent = `Updating password for: ${passAdminName}`;
  document.getElementById('adm-pass-new').value = '';
  document.getElementById('adm-pass-confirm').value = '';
  document.getElementById('m-adm-pass').classList.add('show');
}

function closePassModal() {
  document.getElementById('m-adm-pass').classList.remove('show');
}

async function saveAdminPassword() {
  if (!CAN_MANAGE_ADMINS) { toast('Only Super Admin can update passwords', 'error'); return; }
  const p1 = document.getElementById('adm-pass-new').value;
  const p2 = document.getElementById('adm-pass-confirm').value;
  if (!passAdminId) { toast('Select admin first', 'error'); return; }
  if (!p1 || !p2) { toast('Please fill password fields', 'error'); return; }
  if (p1 !== p2) { toast('Passwords do not match', 'error'); return; }
  if (p1.length < 6) { toast('Password must be at least 6 chars', 'error'); return; }

  let res;
  try {
    res = await fetch(`/admin/api/admin-users/${passAdminId}/password`, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
      credentials:'same-origin',
      body: JSON.stringify({ new_password: p1 })
    }).then(r=>r.json());
  } catch (e) {
    toast('Network error. Please try again.', 'error');
    return;
  }
  if (!res.ok) { toast(res.msg || 'Could not update password', 'error'); return; }
  toast(`Password updated for ${passAdminName}`, 'success');
  closePassModal();
}

async function removeAdmin(id, name) {
  if (!CAN_MANAGE_ADMINS) { toast('Only Super Admin can remove admins', 'error'); return; }
  const adminId = Number(id || 0);
  if (!adminId) return;
  if (adminId === CURRENT_ADMIN_ID) {
    toast('You cannot remove your own admin account', 'error');
    return;
  }
  if (!confirm(`Are you sure you want to remove admin \"${name}\"?`)) return;
  let res;
  try {
    res = await fetch(`/admin/api/admin-users/${adminId}`, {
      method:'DELETE',
      headers:{'X-CSRF-TOKEN':CSRF},
      credentials:'same-origin'
    }).then(r=>r.json());
  } catch (e) {
    toast('Network error. Please try again.', 'error');
    return;
  }
  if (!res.ok) { toast(res.msg || 'Could not remove admin', 'error'); return; }
  toast('Admin removed successfully', 'success');
  loadAdmins();
}

function setupAdminActions() {
  const wrap = document.getElementById('adminsWrap');
  if (!wrap || wrap.dataset.actionsBound === '1') return;
  wrap.dataset.actionsBound = '1';
  wrap.addEventListener('click', function (e) {
    const passBtn = e.target.closest('.js-pass-btn');
    if (passBtn) {
      openPasswordModal(Number(passBtn.dataset.id || 0), passBtn.dataset.name || 'Admin');
      return;
    }
    const editBtn=e.target.closest('.js-edit-btn');if(editBtn){openAdminEdit(Number(editBtn.dataset.id||0));return;}
    const remBtn = e.target.closest('.js-remove-btn');
    if (remBtn) {
      removeAdmin(Number(remBtn.dataset.id || 0), remBtn.dataset.name || 'Admin');
    }
  });
}

setupAdminActions();
loadAdmins();
</script>
    </div></div></div>
</body></html>

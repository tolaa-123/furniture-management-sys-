<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$managerName = $_SESSION['user_name'] ?? 'Manager User';
$managerId   = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages - FurnitureCraft</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
  <?php include_once __DIR__ . '/../../includes/msg_styles.php'; ?>
</head>
<body>
<button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay"></div>
<?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
<!-- Top Header -->
<?php 
$pageTitle = 'Messages';
include_once __DIR__ . '/../../includes/manager_header.php'; 
?>
  <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Workshop Manager</div></div>
  <div class="header-right">
    <div class="admin-profile">
      <div class="admin-avatar"><?php echo strtoupper(substr($managerName,0,1)); ?></div>
      <div>
        <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($managerName); ?></div>
        <div class="admin-role-badge">MANAGER</div>
      </div>
    </div>
  </div>
</div>
<div class="main-content">
  <div id="toast" class="msg-toast" style="display:none;"></div>

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
      <h1 style="margin:0 0 4px;font-size:22px;color:#2C3E50;"><i class="fas fa-envelope" style="color:#3498DB;margin-right:8px;"></i>Messages</h1>
      <p style="margin:0;font-size:13px;color:#95A5A6;">Communicate with your team and customers</p>
    </div>
    <button class="msg-btn-primary" onclick="openCompose()">
      <i class="fas fa-pen"></i> Compose Message
    </button>
  </div>

  <!-- Stats -->
  <div class="stats-grid" style="margin-bottom:24px;" id="statsRow">
    <div class="stat-card" style="border-left:4px solid #3498DB;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div><div class="stat-value" id="stat-inbox">—</div><div class="stat-label">Inbox</div></div>
        <i class="fas fa-inbox" style="font-size:28px;color:#3498DB;opacity:.5;"></i>
      </div>
    </div>
    <div class="stat-card" style="border-left:4px solid #E74C3C;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div><div class="stat-value" id="stat-unread">—</div><div class="stat-label">Unread</div></div>
        <i class="fas fa-envelope" style="font-size:28px;color:#E74C3C;opacity:.5;"></i>
      </div>
    </div>
    <div class="stat-card" style="border-left:4px solid #27AE60;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div><div class="stat-value" id="stat-sent">—</div><div class="stat-label">Sent</div></div>
        <i class="fas fa-paper-plane" style="font-size:28px;color:#27AE60;opacity:.5;"></i>
      </div>
    </div>
  </div>

  <!-- Tabs + Table -->
  <div class="section-card">
    <div class="msg-tabs">
      <button class="msg-tab active" id="tab-inbox" onclick="switchTab('inbox')">
        <i class="fas fa-inbox"></i> Inbox <span class="unread-dot" id="badge-inbox" style="display:none;">0</span>
      </button>
      <button class="msg-tab" id="tab-sent" onclick="switchTab('sent')">
        <i class="fas fa-paper-plane"></i> Sent
      </button>
    </div>
    <div id="pane-inbox" class="tab-pane active"></div>
    <div id="pane-sent"  class="tab-pane"></div>
  </div>
</div>

<!-- Compose Modal -->
<div class="msg-modal" id="composeModal">
  <div class="msg-modal-box">
    <div class="msg-modal-head">
      <h3><i class="fas fa-pen" style="margin-right:8px;"></i>Compose Message</h3>
      <button class="msg-modal-close" onclick="closeModal('composeModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="msg-modal-body">
      <div class="fg">
        <label>To <span style="color:#E74C3C;">*</span></label>
        <select id="c_receiver" class="fg-input">
          <option value="">— Select Recipient —</option>
        </select>
      </div>
      <div class="fg">
        <label>Subject <span style="color:#E74C3C;">*</span></label>
        <input type="text" id="c_subject" class="fg-input" placeholder="Enter subject…">
      </div>
      <div class="fg">
        <label>Message <span style="color:#E74C3C;">*</span></label>
        <textarea id="c_message" class="fg-input" rows="5" placeholder="Type your message…"></textarea>
      </div>
    </div>
    <div class="msg-modal-foot">
      <button class="msg-btn-cancel" onclick="closeModal('composeModal')">Cancel</button>
      <button class="msg-btn-primary" onclick="sendMessage()"><i class="fas fa-paper-plane"></i> Send</button>
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="msg-modal" id="viewModal">
  <div class="msg-modal-box" style="max-width:620px;">
    <div class="msg-modal-head">
      <h3><i class="fas fa-envelope-open" style="margin-right:8px;"></i>Message</h3>
      <button class="msg-modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="msg-modal-body">
      <div class="msg-meta" id="v_meta"></div>
      <div style="font-size:16px;font-weight:700;color:#2C3E50;margin-bottom:10px;" id="v_subject"></div>
      <div class="msg-body-box" id="v_body"></div>
    </div>
    <div class="msg-modal-foot">
      <button class="msg-btn-cancel" onclick="closeModal('viewModal')">Close</button>
      <button class="msg-btn-primary" id="v_reply_btn" style="display:none;" onclick="replyFromView()">
        <i class="fas fa-reply"></i> Reply
      </button>
    </div>
  </div>
</div>

<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
<script>
const API   = '<?php echo BASE_URL; ?>/public/api/messages.php';
const CSRF  = <?php echo json_encode($csrf_token); ?>;
let _inbox  = [], _sent = [], _recipients = [], _viewMsg = null, _activeTab = 'inbox';

async function api(method, params) {
  if (method === 'GET') {
    const q = new URLSearchParams(params);
    const r = await fetch(API + '?' + q);
    return r.json();
  }
  const r = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({...params, csrf_token: CSRF})
  });
  return r.json();
}

function toast(msg, type='success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'msg-toast msg-toast-' + type;
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 3500);
}

async function loadAll() {
  const [ib, st, rc] = await Promise.all([
    api('GET', {action:'inbox'}),
    api('GET', {action:'sent'}),
    api('GET', {action:'recipients'})
  ]);
  _inbox = ib.data || [];
  _sent  = st.data || [];
  _recipients = rc.data || [];
  renderRecipients();
  renderStats();
  renderTab('inbox');
  renderTab('sent');
}

function renderRecipients() {
  const sel = document.getElementById('c_receiver');
  sel.innerHTML = '<option value="">— Select Recipient —</option>';
  const groups = {};
  _recipients.forEach(r => { (groups[r.role] = groups[r.role]||[]).push(r); });
  const roleLabel = {manager:'Managers', admin:'Admins', employee:'Employees', customer:'Customers'};
  Object.keys(groups).forEach(role => {
    const og = document.createElement('optgroup');
    og.label = roleLabel[role] || role;
    groups[role].forEach(r => {
      const o = document.createElement('option');
      o.value = r.id; o.textContent = r.name;
      og.appendChild(o);
    });
    sel.appendChild(og);
  });
}

function renderStats() {
  const unread = _inbox.filter(m => !parseInt(m.is_read)).length;
  document.getElementById('stat-inbox').textContent  = _inbox.length;
  document.getElementById('stat-unread').textContent = unread;
  document.getElementById('stat-sent').textContent   = _sent.length;
  const badge = document.getElementById('badge-inbox');
  if (unread > 0) { badge.textContent = unread; badge.style.display = 'inline-flex'; }
  else badge.style.display = 'none';
}

function renderTab(tab) {
  const pane = document.getElementById('pane-' + tab);
  const msgs = tab === 'inbox' ? _inbox : _sent;
  if (!msgs.length) {
    pane.innerHTML = `<div class="msg-empty"><i class="fas fa-${tab==='inbox'?'inbox':'paper-plane'}"></i><div>${tab==='inbox'?'No messages yet':'No sent messages'}</div></div>`;
    return;
  }
  const rows = msgs.map(m => {
    const isUnread = tab === 'inbox' && !parseInt(m.is_read);
    const who = tab === 'inbox'
      ? `<div style="font-weight:${isUnread?700:500};font-size:13px;">${esc(m.sender_name||'Unknown')}</div><span class="role-badge role-${m.sender_role||''}">${cap(m.sender_role||'')}</span>`
      : `<div style="font-size:13px;">${esc(m.receiver_name||'Unknown')}</div><span class="role-badge role-${m.receiver_role||''}">${cap(m.receiver_role||'')}</span>`;
    const statusBadge = tab === 'inbox'
      ? (isUnread ? '<span class="s-new">NEW</span>' : '<span class="s-read">READ</span>')
      : '<span class="s-sent">SENT</span>';
    const actions = tab === 'inbox'
      ? `<button class="btn-view" onclick='viewMsg(${m.id},"inbox")'><i class="fas fa-eye"></i> View</button>
         <button class="btn-reply" onclick='quickReply(${m.id},${m.sender_id},"${esc2(m.sender_name)}","${esc2(m.subject)}")'><i class="fas fa-reply"></i> Reply</button>
         <button class="btn-del" onclick='delMsg(${m.id},"inbox")'><i class="fas fa-trash"></i></button>`
      : `<button class="btn-view" onclick='viewMsg(${m.id},"sent")'><i class="fas fa-eye"></i> View</button>`;
    return `<tr id="row-${m.id}" class="${isUnread?'unread-row':''}">
      <td><div style="display:flex;align-items:center;gap:8px;"><div class="msg-avatar">${(m.sender_name||m.receiver_name||'?')[0].toUpperCase()}</div><div>${who}</div></div></td>
      <td style="font-weight:${isUnread?700:400};">${esc(m.subject)}</td>
      <td style="color:#7F8C8D;font-size:12px;">${esc(m.message.substring(0,55))}…</td>
      <td style="white-space:nowrap;font-size:12px;">${fmtDate(m.created_at)}</td>
      <td>${statusBadge}</td>
      <td><div class="action-btns">${actions}</div></td>
    </tr>`;
  }).join('');
  pane.innerHTML = `<div class="table-responsive"><table class="data-table"><thead><tr>
    <th>${tab==='inbox'?'From':'To'}</th><th>Subject</th><th>Preview</th><th>Date</th><th>Status</th><th>Actions</th>
  </tr></thead><tbody>${rows}</tbody></table></div>`;
}

function switchTab(tab) {
  _activeTab = tab;
  document.querySelectorAll('.msg-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  document.getElementById('pane-' + tab).classList.add('active');
}

function openCompose(receiverId='', subject='') {
  document.getElementById('c_receiver').value = receiverId;
  document.getElementById('c_subject').value  = subject;
  document.getElementById('c_message').value  = '';
  openModal('composeModal');
}

async function sendMessage() {
  const to  = document.getElementById('c_receiver').value;
  const sub = document.getElementById('c_subject').value.trim();
  const msg = document.getElementById('c_message').value.trim();
  if (!to || !sub || !msg) { toast('All fields are required.','error'); return; }
  const res = await api('POST', {action:'send', receiver_id:to, subject:sub, message:msg});
  if (res.ok) { toast('Message sent.'); closeModal('composeModal'); loadAll(); }
  else toast(res.error || 'Send failed.', 'error');
}

function viewMsg(id, tab) {
  const msgs = tab === 'inbox' ? _inbox : _sent;
  const m = msgs.find(x => x.id == id);
  if (!m) return;
  _viewMsg = {...m, _tab: tab};
  const isInbox = tab === 'inbox';
  const who = isInbox
    ? `${esc(m.sender_name||'Unknown')} <span class="role-badge role-${m.sender_role||''}">${cap(m.sender_role||'')}</span>`
    : `${esc(m.receiver_name||'Unknown')} <span class="role-badge role-${m.receiver_role||''}">${cap(m.receiver_role||'')}</span>`;
  document.getElementById('v_meta').innerHTML =
    `<div><strong>${isInbox?'From':'To'}:</strong><br>${who}</div><div><strong>Date:</strong><br>${fmtDate(m.created_at)}</div>`;
  document.getElementById('v_subject').textContent = m.subject;
  document.getElementById('v_body').textContent    = m.message;
  document.getElementById('v_reply_btn').style.display = isInbox ? 'inline-flex' : 'none';
  openModal('viewModal');
  if (isInbox && !parseInt(m.is_read)) {
    api('POST', {action:'mark_read', id: m.id}).then(() => {
      m.is_read = 1;
      const row = document.getElementById('row-' + m.id);
      if (row) { row.classList.remove('unread-row'); const nb = row.querySelector('.s-new'); if(nb){nb.className='s-read';nb.textContent='READ';} }
      renderStats();
    });
  }
}

function replyFromView() {
  if (!_viewMsg) return;
  closeModal('viewModal');
  quickReply(_viewMsg.id, _viewMsg.sender_id, _viewMsg.sender_name, _viewMsg.subject);
}

function quickReply(origId, senderId, senderName, subject) {
  document.getElementById('c_receiver').value = senderId;
  document.getElementById('c_subject').value  = 'Re: ' + subject;
  document.getElementById('c_message').value  = '';
  openModal('composeModal');
}

async function delMsg(id, tab) {
  if (!confirm('Delete this message?')) return;
  const res = await api('POST', {action:'delete', id});
  if (res.ok) {
    if (tab === 'inbox') _inbox = _inbox.filter(m => m.id != id);
    else _sent = _sent.filter(m => m.id != id);
    renderStats(); renderTab(tab); toast('Message deleted.');
  } else toast('Delete failed.', 'error');
}

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function esc(s)  { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
function esc2(s) { return (s||'').replace(/\\/g,'\\\\').replace(/"/g,'\\"'); }
function cap(s)  { return s ? s[0].toUpperCase()+s.slice(1) : ''; }
function fmtDate(s) { return new Date(s).toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}); }

// Close on backdrop click
document.querySelectorAll('.msg-modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) closeModal(m.id); }));

loadAll();
</script>
</body>
</html>

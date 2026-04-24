<?php
// ============================================================
//  user/messages.php  — User Messages
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('user');

$db   = db();
$uid  = current_user_id();
$csrf = $_SESSION['csrf_token'] ?? '';

$success = get_flash('success') ?? '';
$error   = get_flash('error')   ?? '';

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: messages.php'); exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $to_id  = (int)($_POST['to_id']  ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body']    ?? '');

        if (!$to_id || !$subject || !$body) {
            set_flash('error', 'All fields are required.');
        } else {
            $stmt = $db->prepare(
                'INSERT INTO messages (from_id, to_id, subject, body, created_at) VALUES (?,?,?,?,NOW())'
            );
            $stmt->bind_param('iiss', $uid, $to_id, $subject, $body);
            if ($stmt->execute()) {
                set_flash('success', 'Message sent successfully.');
            } else {
                set_flash('error', 'Failed to send message. Does the messages table exist?');
            }
            $stmt->close();
        }
        header('Location: messages.php'); exit;
    }

    if ($action === 'mark_read') {
        $mid = (int)($_POST['mid'] ?? 0);
        $db->query("UPDATE messages SET is_read=1 WHERE id={$mid} AND to_id={$uid}");
        header('Location: messages.php'); exit;
    }

    if ($action === 'delete') {
        $mid = (int)($_POST['mid'] ?? 0);
        $db->query("DELETE FROM messages WHERE id={$mid} AND (to_id={$uid} OR from_id={$uid})");
        set_flash('success', 'Message deleted.');
        header('Location: messages.php'); exit;
    }
}

// ── Tab ──────────────────────────────────────────────────────
$tab = in_array($_GET['tab'] ?? '', ['sent']) ? 'sent' : 'inbox';

// ── Inbox ────────────────────────────────────────────────────
$inbox_res = $db->prepare(
    'SELECT m.id, m.subject, m.body, m.created_at, m.is_read,
            u.full_name AS sender_name, u.avatar_initials AS sender_av, u.role AS sender_role
     FROM messages m
     JOIN users u ON u.id = m.from_id
     WHERE m.to_id = ? ORDER BY m.created_at DESC LIMIT 50'
);
$inbox_res->bind_param('i', $uid);
$inbox_res->execute();
$inbox = $inbox_res->get_result();
$inbox_res->close();

$unread_stmt = $db->prepare('SELECT COUNT(*) AS c FROM messages WHERE to_id=? AND is_read=0');
$unread_stmt->bind_param('i', $uid);
$unread_stmt->execute();
$unread_count = (int)$unread_stmt->get_result()->fetch_assoc()['c'];
$unread_stmt->close();

// ── Sent ─────────────────────────────────────────────────────
$sent_res = $db->prepare(
    'SELECT m.id, m.subject, m.body, m.created_at, m.is_read,
            u.full_name AS to_name, u.avatar_initials AS to_av, u.role AS to_role
     FROM messages m
     JOIN users u ON u.id = m.to_id
     WHERE m.from_id = ? ORDER BY m.created_at DESC LIMIT 50'
);
$sent_res->bind_param('i', $uid);
$sent_res->execute();
$sent = $sent_res->get_result();
$sent_res->close();

// ── All admins/users to message ──────────────────────────────
$rec_res = $db->query("SELECT id, full_name, role FROM users WHERE id != {$uid} ORDER BY role,full_name");

$page_title = 'Messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — LoginSys</title>
  <link rel="shortcut icon" href="https://images.aimecol.com/uploads/large/logo_691c45e2513cc_large.jpg" type="image/x-icon">
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    .msg-tabs { display: flex; gap: .5rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border); padding-bottom: .5rem; }
    .msg-tab {
      padding: .5rem 1.2rem; border-radius: var(--radius-sm) var(--radius-sm) 0 0;
      font-size: .9rem; font-weight: 600; color: var(--text-muted);
      text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px;
      transition: color .15s;
    }
    .msg-tab:hover { color: var(--text); text-decoration: none; }
    .msg-tab.active { color: var(--user-hue); border-bottom-color: var(--user-hue); }
    .unread-dot {
      display: inline-flex; align-items: center; justify-content: center;
      width: 18px; height: 18px; border-radius: 50%;
      background: var(--danger); color: #fff;
      font-size: .65rem; font-weight: 800; margin-left: .35rem;
    }

    .msg-row {
      display: flex; align-items: flex-start; gap: 1rem;
      padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
      transition: background .15s; cursor: pointer;
    }
    .msg-row:last-child { border-bottom: none; }
    .msg-row:hover { background: var(--bg-hover); }
    .msg-row.unread { background: rgba(46,143,196,0.04); }
    .msg-avatar {
      width: 38px; height: 38px; border-radius: var(--radius-sm); flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      font-family: var(--font-display); font-size: .88rem; font-weight: 800;
    }
    .av-admin { background: rgba(212,74,74,0.12); color: var(--admin-hue); }
    .av-user  { background: rgba(46,143,196,0.12); color: var(--user-hue); }
    .msg-content { flex: 1; min-width: 0; }
    .msg-sender { font-size: .88rem; font-weight: 700; margin-bottom: .15rem; display: flex; align-items: center; gap: .5rem; }
    .msg-subject { font-size: .88rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .msg-subject.bold { font-weight: 600; color: var(--text); }
    .msg-preview { font-size: .78rem; color: var(--text-dim); margin-top: .1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 400px; }
    .msg-meta { font-size: .75rem; color: var(--text-dim); white-space: nowrap; margin-left: auto; padding-left: 1rem; }
    .unread-marker { width: 8px; height: 8px; border-radius: 50%; background: var(--user-hue); flex-shrink: 0; margin-top: 5px; }

    .compose-form { display: flex; flex-direction: column; gap: 1rem; padding: 1.5rem; }
    .form-group { display: flex; flex-direction: column; gap: .4rem; }
    .form-group label { font-size: .8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
    .form-group input, .form-group select, .form-group textarea {
      background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm);
      padding: .65rem .9rem; color: var(--text); font-family: var(--font-body); font-size: .9rem;
      transition: border-color .2s, box-shadow .2s; width: 100%;
    }
    .form-group textarea { resize: vertical; min-height: 100px; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
      outline: none; border-color: var(--user-hue); box-shadow: 0 0 0 3px rgba(46,143,196,0.12);
    }

    .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(4px); z-index: 500; align-items: center; justify-content: center; padding: 1rem; }
    .modal-backdrop.open { display: flex; }
    .modal { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2rem; width: 100%; max-width: 540px; box-shadow: 0 24px 60px rgba(0,0,0,.3); position: relative; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
    .modal-header h3 { font-family: var(--font-display); font-size: 1.15rem; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px; border-radius: 4px; }
    .modal-close:hover { color: var(--text); }
  </style>
</head>
<body>
<div class="page-bg-accent"></div>
<div class="dash-layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon" style="color:var(--accent);"><?= icon('logo', '', 30) ?></div>
      <span>LoginSys</span>
    </div>
    <div class="sidebar-user">
      <div class="avatar avatar-user"><?= e(current_initials()) ?></div>
      <div class="sidebar-user-info">
        <div class="name"><?= e(current_name()) ?></div>
        <span class="role-badge role-badge-user">User</span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="dashboard.php" class="nav-item"><?= icon('dashboard') ?>Dashboard</a>
      <div class="nav-section-label">Account</div>
      <a href="profile.php" class="nav-item"><?= icon('profile') ?>My Profile</a>
      <a href="login_history.php" class="nav-item"><?= icon('activity') ?>Login History</a>
      <a href="messages.php" class="nav-item active"><?= icon('mail') ?>Messages <?php if ($unread_count): ?><span class="unread-dot"><?= $unread_count ?></span><?php endif; ?></a>
      <a href="change_password.php" class="nav-item"><?= icon('key') ?>Change Password</a>
      <a href="notifications.php" class="nav-item"><?= icon('bell') ?>Notifications</a>
    </nav>
    <div class="sidebar-footer">
      <form method="POST" action="../logout.php">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
        <button type="submit" class="btn btn-danger" style="width:100%;"><?= icon('logout', '', 17) ?> Sign Out</button>
      </form>
    </div>
  </aside>

  <div class="dash-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <button class="mobile-menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')"><?= icon('menu', '', 20) ?></button>
        <h1 class="topbar-title"><?= icon('mail', '', 20) ?> Messages</h1>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary" onclick="document.getElementById('composeModal').classList.add('open')" style="font-size:.85rem;">
          <?= icon('mail', '', 16) ?> Compose
        </button>
        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
          <span class="icon-moon"><?= icon('moon', '', 16) ?></span>
          <span class="icon-sun"><?= icon('sun', '', 16) ?></span>
        </button>
      </div>
    </header>

    <main class="dash-content">

      <?php if ($success): ?>
        <div style="background:rgba(34,184,110,0.1);border:1px solid rgba(34,184,110,0.3);color:var(--success);padding:.75rem 1rem;border-radius:var(--radius-sm);margin-bottom:1rem;font-size:.9rem;"><?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div style="background:rgba(212,74,74,0.1);border:1px solid rgba(212,74,74,0.3);color:var(--danger);padding:.75rem 1rem;border-radius:var(--radius-sm);margin-bottom:1rem;font-size:.9rem;"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="msg-tabs">
        <a href="?tab=inbox" class="msg-tab <?= $tab==='inbox'?'active':'' ?>">
          Inbox <?php if ($unread_count): ?><span class="unread-dot"><?= $unread_count ?></span><?php endif; ?>
        </a>
        <a href="?tab=sent" class="msg-tab <?= $tab==='sent'?'active':'' ?>">Sent</a>
      </div>

      <div class="section-card">
        <?php if ($tab === 'inbox'): ?>
          <?php if ($inbox->num_rows === 0): ?>
            <div style="text-align:center;padding:3rem;color:var(--text-muted);">
              <?= icon('mail', '', 36) ?>
              <p style="margin-top:1rem;font-size:.95rem;">Your inbox is empty.</p>
            </div>
          <?php else: ?>
            <?php while ($m = $inbox->fetch_assoc()): ?>
              <div class="msg-row <?= !$m['is_read'] ? 'unread' : '' ?>" onclick="openMsg(<?= $m['id'] ?>, '<?= addslashes(e($m['subject'])) ?>', '<?= addslashes(e($m['sender_name'])) ?>', '<?= addslashes(e($m['body'])) ?>', '<?= date('M j, Y g:i A', strtotime($m['created_at'])) ?>')">
                <div class="msg-avatar av-<?= $m['sender_role'] ?>"><?= e($m['sender_av']) ?></div>
                <?php if (!$m['is_read']): ?>
                  <div class="unread-marker"></div>
                <?php endif; ?>
                <div class="msg-content">
                  <div class="msg-sender">
                    <?= e($m['sender_name']) ?>
                    <span class="badge badge-<?= $m['sender_role'] ?>"><?= $m['sender_role'] ?></span>
                  </div>
                  <div class="msg-subject <?= !$m['is_read'] ? 'bold' : '' ?>"><?= e($m['subject']) ?></div>
                  <div class="msg-preview"><?= e($m['body']) ?></div>
                </div>
                <div class="msg-meta"><?= date('M j', strtotime($m['created_at'])) ?></div>
              </div>
            <?php endwhile; ?>
          <?php endif; ?>

        <?php else: ?>
          <?php if ($sent->num_rows === 0): ?>
            <div style="text-align:center;padding:3rem;color:var(--text-muted);">
              <?= icon('mail', '', 36) ?>
              <p style="margin-top:1rem;font-size:.95rem;">No sent messages yet.</p>
            </div>
          <?php else: ?>
            <?php while ($m = $sent->fetch_assoc()): ?>
              <div class="msg-row" onclick="openMsg(<?= $m['id'] ?>, '<?= addslashes(e($m['subject'])) ?>', 'To: <?= addslashes(e($m['to_name'])) ?>', '<?= addslashes(e($m['body'])) ?>', '<?= date('M j, Y g:i A', strtotime($m['created_at'])) ?>')">
                <div class="msg-avatar av-<?= $m['to_role'] ?>"><?= e($m['to_av']) ?></div>
                <div class="msg-content">
                  <div class="msg-sender">To: <?= e($m['to_name']) ?> <span class="badge badge-<?= $m['to_role'] ?>"><?= $m['to_role'] ?></span></div>
                  <div class="msg-subject"><?= e($m['subject']) ?></div>
                  <div class="msg-preview"><?= e($m['body']) ?></div>
                </div>
                <div class="msg-meta">
                  <?= $m['is_read'] ? '<span style="font-size:.72rem;color:var(--success);">✓ Read</span>' : '' ?>
                  <br><?= date('M j', strtotime($m['created_at'])) ?>
                </div>
              </div>
            <?php endwhile; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>

    </main>
  </div>
</div>

<!-- Compose Modal -->
<div class="modal-backdrop" id="composeModal">
  <div class="modal">
    <div class="modal-header">
      <h3><?= icon('mail', '', 18) ?>&nbsp; New Message</h3>
      <button class="modal-close" onclick="document.getElementById('composeModal').classList.remove('open')"><?= icon('key', '', 18) ?>✕</button>
    </div>
    <form method="POST" action="messages.php">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
      <input type="hidden" name="action" value="send"/>
      <div class="compose-form">
        <div class="form-group">
          <label>To</label>
          <select name="to_id" required>
            <option value="">— Select recipient —</option>
            <?php while ($r = $rec_res->fetch_assoc()): ?>
              <option value="<?= $r['id'] ?>"><?= e($r['full_name']) ?> (<?= $r['role'] ?>)</option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Subject</label>
          <input type="text" name="subject" placeholder="Message subject…" required/>
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea name="body" placeholder="Write your message here…" required></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.75rem;">
          <button type="button" class="btn btn-outline" onclick="document.getElementById('composeModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn btn-primary"><?= icon('mail', '', 16) ?> Send</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Read Modal -->
<div class="modal-backdrop" id="readModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="readSubject"></h3>
      <button class="modal-close" onclick="document.getElementById('readModal').classList.remove('open')">✕</button>
    </div>
    <p id="readFrom" style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;"></p>
    <div id="readBody" style="font-size:.92rem;line-height:1.7;color:var(--text);white-space:pre-wrap;"></div>
    <div id="readDate" style="margin-top:1rem;font-size:.78rem;color:var(--text-dim);"></div>
  </div>
</div>

<script>
const html = document.documentElement;
if (localStorage.getItem('theme')==='dark') html.setAttribute('data-theme','dark');
document.getElementById('themeToggle').addEventListener('click', () => {
  const d = html.getAttribute('data-theme')==='dark';
  html.setAttribute('data-theme', d?'light':'dark');
  localStorage.setItem('theme', d?'light':'dark');
});
function openMsg(id, subject, from, body, date) {
  document.getElementById('readSubject').textContent = subject;
  document.getElementById('readFrom').textContent    = from;
  document.getElementById('readBody').textContent    = body;
  document.getElementById('readDate').textContent    = date;
  document.getElementById('readModal').classList.add('open');
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.remove('open'));
  }
});
</script>
</body>
</html>
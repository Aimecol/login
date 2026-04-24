<?php
// ============================================================
//  user/notifications.php  — User Notifications Page
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('user');

$db   = db();
$uid  = current_user_id();
$csrf = $_SESSION['csrf_token'] ?? '';

$success = get_flash('success') ?? '';

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: notifications.php'); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {
        $db->query("UPDATE notifications SET is_read=1 WHERE user_id={$uid}");
        set_flash('success', 'All notifications marked as read.');
        header('Location: notifications.php'); exit;
    }
    if ($action === 'mark_read') {
        $nid = (int)($_POST['nid'] ?? 0);
        $db->query("UPDATE notifications SET is_read=1 WHERE id={$nid} AND user_id={$uid}");
        header('Location: notifications.php'); exit;
    }
    if ($action === 'delete') {
        $nid = (int)($_POST['nid'] ?? 0);
        $db->query("DELETE FROM notifications WHERE id={$nid} AND user_id={$uid}");
        set_flash('success', 'Notification deleted.');
        header('Location: notifications.php'); exit;
    }
    if ($action === 'clear_all') {
        $db->query("DELETE FROM notifications WHERE user_id={$uid} AND is_read=1");
        set_flash('success', 'Read notifications cleared.');
        header('Location: notifications.php'); exit;
    }
    // Save preferences
    if ($action === 'save_prefs') {
        $login_notif  = isset($_POST['notify_login'])    ? 1 : 0;
        $msg_notif    = isset($_POST['notify_messages'])  ? 1 : 0;
        $sys_notif    = isset($_POST['notify_system'])    ? 1 : 0;
        // Store in session for demo (real app: persist to DB)
        $_SESSION['notif_prefs'] = compact('login_notif', 'msg_notif', 'sys_notif');
        set_flash('success', 'Notification preferences saved.');
        header('Location: notifications.php'); exit;
    }
}

// ── Fetch notifications ──────────────────────────────────────
// Graceful fallback if table doesn't exist yet
$notifs = [];
$unread_count = 0;
$total_count  = 0;
$result = $db->query("SHOW TABLES LIKE 'notifications'");
if ($result && $result->num_rows > 0) {
    $q = $db->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
    $q->bind_param('i', $uid);
    $q->execute();
    $res = $q->get_result();
    $q->close();
    while ($r = $res->fetch_assoc()) {
        $notifs[] = $r;
        $total_count++;
        if (!$r['is_read']) $unread_count++;
    }
}

// If no notifications table, build demo notifications from session log
if (empty($notifs)) {
    $q2 = $db->prepare('SELECT ip_address, login_at FROM session_log WHERE user_id=? ORDER BY login_at DESC LIMIT 5');
    $q2->bind_param('i', $uid);
    $q2->execute();
    $res2 = $q2->get_result();
    $q2->close();
    $i = 0;
    while ($r = $res2->fetch_assoc()) {
        $notifs[] = [
            'id'         => $i++,
            'type'       => 'login',
            'title'      => 'New login detected',
            'body'       => 'Your account was accessed from IP ' . $r['ip_address'],
            'created_at' => $r['login_at'],
            'is_read'    => ($i > 1) ? 1 : 0,
        ];
    }
    // Add a welcome notification
    array_push($notifs, [
        'id' => 99, 'type' => 'system',
        'title' => 'Welcome to LoginSys!',
        'body'  => 'Your account was created successfully. Explore your dashboard to get started.',
        'created_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
        'is_read' => 1,
    ]);
    $unread_count = count(array_filter($notifs, fn($n) => !$n['is_read']));
    $total_count  = count($notifs);
}

$prefs = $_SESSION['notif_prefs'] ?? ['login_notif' => 1, 'msg_notif' => 1, 'sys_notif' => 1];

// Filter
$filter = $_GET['filter'] ?? 'all';
$filtered = $notifs;
if ($filter === 'unread') {
    $filtered = array_filter($notifs, fn($n) => !$n['is_read']);
} elseif ($filter === 'read') {
    $filtered = array_filter($notifs, fn($n) => $n['is_read']);
}

$page_title = 'Notifications';

// Notification type styling
function notif_style(string $type): array {
    return match($type) {
        'login'   => ['color' => 'var(--user-hue)',   'bg' => 'rgba(46,143,196,0.12)',   'icon' => 'activity'],
        'message' => ['color' => 'var(--accent)',      'bg' => 'rgba(232,148,14,0.12)',   'icon' => 'mail'],
        'system'  => ['color' => 'var(--success)',     'bg' => 'rgba(34,184,110,0.12)',   'icon' => 'settings'],
        'alert'   => ['color' => 'var(--danger)',      'bg' => 'rgba(212,74,74,0.12)',    'icon' => 'bell'],
        default   => ['color' => 'var(--text-muted)',  'bg' => 'var(--bg-hover)',         'icon' => 'bell'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — LoginSys</title>
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    .notif-filter-bar {
      display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius); padding: .85rem 1.25rem; margin-bottom: 1.5rem;
    }
    .filter-btn {
      padding: .38rem .9rem; border-radius: var(--radius-sm);
      border: 1px solid var(--border); background: var(--bg);
      color: var(--text-muted); font-size: .82rem; text-decoration: none;
      cursor: pointer; transition: all .15s; font-family: var(--font-body);
    }
    .filter-btn:hover, .filter-btn.active {
      border-color: var(--user-hue); color: var(--user-hue);
      background: rgba(46,143,196,0.08); text-decoration: none;
    }

    .notif-item {
      display: flex; align-items: flex-start; gap: 1rem;
      padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
      transition: background .15s; position: relative;
    }
    .notif-item:last-child { border-bottom: none; }
    .notif-item.unread { background: rgba(46,143,196,0.03); }
    .notif-item:hover  { background: var(--bg-hover); }

    .notif-icon {
      width: 40px; height: 40px; border-radius: var(--radius);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .notif-content { flex: 1; min-width: 0; }
    .notif-title { font-size: .9rem; font-weight: 600; margin-bottom: .2rem; display: flex; align-items: center; gap: .5rem; }
    .notif-body  { font-size: .83rem; color: var(--text-muted); line-height: 1.5; }
    .notif-time  { font-size: .75rem; color: var(--text-dim); margin-top: .3rem; }
    .unread-dot  { width: 8px; height: 8px; border-radius: 50%; background: var(--user-hue); flex-shrink: 0; margin-top: 6px; }
    .notif-actions { display: flex; gap: .5rem; margin-left: auto; padding-left: .75rem; opacity: 0; transition: opacity .15s; }
    .notif-item:hover .notif-actions { opacity: 1; }
    .act-btn {
      background: none; border: 1px solid var(--border); border-radius: var(--radius-sm);
      color: var(--text-muted); font-size: .75rem; padding: 3px 8px;
      cursor: pointer; transition: all .15s; font-family: var(--font-body);
    }
    .act-btn:hover { border-color: var(--user-hue); color: var(--user-hue); }
    .act-btn.del:hover { border-color: var(--danger); color: var(--danger); }

    .prefs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .pref-toggle {
      display: flex; align-items: center; justify-content: space-between;
      background: var(--bg); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1rem 1.25rem;
    }
    .pref-label { font-size: .88rem; font-weight: 600; }
    .pref-sub   { font-size: .76rem; color: var(--text-muted); margin-top: .1rem; }
    .toggle-switch { position: relative; width: 40px; height: 22px; flex-shrink: 0; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider {
      position: absolute; inset: 0; border-radius: 22px;
      background: var(--border); transition: .2s;
      cursor: pointer;
    }
    .toggle-slider::before {
      content: ''; position: absolute; width: 16px; height: 16px;
      left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s;
    }
    .toggle-switch input:checked + .toggle-slider { background: var(--user-hue); }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(18px); }

    .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
    .empty-state svg { opacity: .3; margin: 0 auto 1rem; }
  </style>
</head>
<body>
<div class="page-bg-accent"></div>
<div class="dash-layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon" style="color:var(--accent);"><?= icon('logo','',30) ?></div>
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
      <a href="messages.php" class="nav-item"><?= icon('mail') ?>Messages</a>
      <a href="change_password.php" class="nav-item"><?= icon('key') ?>Change Password</a>
      <a href="notifications.php" class="nav-item active"><?= icon('bell') ?>Notifications <?php if ($unread_count): ?><span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:var(--danger);color:#fff;font-size:.62rem;font-weight:800;margin-left:.3rem;"><?= $unread_count ?></span><?php endif; ?></a>
    </nav>
    <div class="sidebar-footer">
      <form method="POST" action="../logout.php">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
        <button type="submit" class="btn btn-danger" style="width:100%;"><?= icon('logout','',17) ?> Sign Out</button>
      </form>
    </div>
  </aside>

  <div class="dash-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <button class="mobile-menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')"><?= icon('menu','',20) ?></button>
        <h1 class="topbar-title"><?= icon('bell','',20) ?> Notifications</h1>
      </div>
      <div class="topbar-actions">
        <?php if ($unread_count > 0): ?>
          <form method="POST" action="notifications.php" style="display:inline;">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
            <input type="hidden" name="action" value="mark_all_read"/>
            <button type="submit" class="btn btn-outline" style="font-size:.82rem;">✓ Mark all read</button>
          </form>
        <?php endif; ?>
        <button class="theme-toggle" id="themeToggle">
          <span class="icon-moon"><?= icon('moon','',16) ?></span>
          <span class="icon-sun"><?= icon('sun','',16) ?></span>
        </button>
      </div>
    </header>

    <main class="dash-content">

      <?php if ($success): ?>
        <div style="background:rgba(34,184,110,0.1);border:1px solid rgba(34,184,110,0.3);color:var(--success);padding:.75rem 1rem;border-radius:var(--radius-sm);margin-bottom:1rem;font-size:.9rem;"><?= e($success) ?></div>
      <?php endif; ?>

      <!-- Stats -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="stat-card c-blue">
          <div class="stat-label">Total</div>
          <div class="stat-value"><?= $total_count ?></div>
          <div class="stat-sub">notifications</div>
        </div>
        <div class="stat-card c-red">
          <div class="stat-label">Unread</div>
          <div class="stat-value"><?= $unread_count ?></div>
          <div class="stat-sub">new</div>
        </div>
        <div class="stat-card c-green">
          <div class="stat-label">Read</div>
          <div class="stat-value"><?= $total_count - $unread_count ?></div>
          <div class="stat-sub">seen</div>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="notif-filter-bar">
        <span style="font-size:.82rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Show:</span>
        <a href="?filter=all"    class="filter-btn <?= $filter==='all'    ?'active':'' ?>">All</a>
        <a href="?filter=unread" class="filter-btn <?= $filter==='unread' ?'active':'' ?>">Unread <?php if($unread_count): ?>(<?= $unread_count ?>)<?php endif;?></a>
        <a href="?filter=read"   class="filter-btn <?= $filter==='read'   ?'active':'' ?>">Read</a>
        <span style="margin-left:auto;">
          <form method="POST" action="notifications.php" style="display:inline;">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
            <input type="hidden" name="action" value="clear_all"/>
            <button type="submit" class="filter-btn" style="color:var(--danger);border-color:rgba(212,74,74,0.3);" onclick="return confirm('Clear all read notifications?')">Clear Read</button>
          </form>
        </span>
      </div>

      <!-- Notifications List -->
      <div class="section-card" style="margin-bottom:1.5rem;">
        <div class="section-card-header">
          <h2><?= icon('bell') ?>Activity Feed</h2>
        </div>

        <?php if (empty($filtered)): ?>
          <div class="empty-state">
            <?= icon('bell', '', 40) ?>
            <p style="font-size:.95rem;">No notifications here.</p>
          </div>
        <?php else: ?>
          <?php foreach ($filtered as $n): ?>
            <?php $st = notif_style($n['type'] ?? 'system'); ?>
            <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
              <div class="notif-icon" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
                <?= icon($st['icon'], '', 18) ?>
              </div>
              <?php if (!$n['is_read']): ?>
                <div class="unread-dot"></div>
              <?php endif; ?>
              <div class="notif-content">
                <div class="notif-title">
                  <?= e($n['title']) ?>
                  <span style="font-size:.72rem;font-weight:700;padding:1px 7px;border-radius:3px;background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;text-transform:uppercase;letter-spacing:.04em;"><?= ucfirst($n['type'] ?? 'system') ?></span>
                </div>
                <div class="notif-body"><?= e($n['body']) ?></div>
                <div class="notif-time"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></div>
              </div>
              <div class="notif-actions">
                <?php if (!$n['is_read']): ?>
                  <form method="POST" action="notifications.php">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
                    <input type="hidden" name="action" value="mark_read"/>
                    <input type="hidden" name="nid" value="<?= $n['id'] ?>"/>
                    <button type="submit" class="act-btn">✓ Read</button>
                  </form>
                <?php endif; ?>
                <form method="POST" action="notifications.php">
                  <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
                  <input type="hidden" name="action" value="delete"/>
                  <input type="hidden" name="nid" value="<?= $n['id'] ?>"/>
                  <button type="submit" class="act-btn del" onclick="return confirm('Delete this notification?')">✕</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Notification Preferences -->
      <div class="section-card">
        <div class="section-card-header">
          <h2><?= icon('settings') ?>Notification Preferences</h2>
        </div>
        <form method="POST" action="notifications.php" style="padding:1.5rem;display:flex;flex-direction:column;gap:1.25rem;">
          <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
          <input type="hidden" name="action" value="save_prefs"/>
          <div class="prefs-grid">
            <div class="pref-toggle">
              <div>
                <div class="pref-label"><?= icon('activity','',14) ?> Login Alerts</div>
                <div class="pref-sub">Notify on new login</div>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" name="notify_login" <?= $prefs['login_notif'] ? 'checked' : '' ?>/>
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="pref-toggle">
              <div>
                <div class="pref-label"><?= icon('mail','',14) ?> New Messages</div>
                <div class="pref-sub">Notify on incoming messages</div>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" name="notify_messages" <?= $prefs['msg_notif'] ? 'checked' : '' ?>/>
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="pref-toggle">
              <div>
                <div class="pref-label"><?= icon('bell','',14) ?> System Updates</div>
                <div class="pref-sub">Notify on system events</div>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" name="notify_system" <?= $prefs['sys_notif'] ? 'checked' : '' ?>/>
                <span class="toggle-slider"></span>
              </label>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary"><?= icon('settings','',16) ?> Save Preferences</button>
          </div>
        </form>
      </div>

    </main>
  </div>
</div>

<script>
const html=document.documentElement;
if(localStorage.getItem('theme')==='dark')html.setAttribute('data-theme','dark');
document.getElementById('themeToggle').addEventListener('click',()=>{
  const d=html.getAttribute('data-theme')==='dark';
  html.setAttribute('data-theme',d?'light':'dark');
  localStorage.setItem('theme',d?'light':'dark');
});
</script>
</body>
</html>
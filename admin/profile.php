<?php
// ============================================================
//  admin/profile.php  — Admin Profile & Account Management
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('admin');

$db   = db();
$uid  = current_user_id();
$csrf = $_SESSION['csrf_token'] ?? '';

$success = get_flash('success') ?? '';
$error   = get_flash('error')   ?? '';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: profile.php'); exit;
    }

    $action = $_POST['action'] ?? '';

    // Update profile info
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $username  = trim($_POST['username']  ?? '');

        if (!$full_name || !$email || !$username) {
            set_flash('error', 'All fields are required.');
        } else {
            $parts    = explode(' ', $full_name);
            $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : substr($parts[0], 1, 1)));
            $stmt = $db->prepare('UPDATE users SET full_name=?, email=?, username=?, avatar_initials=? WHERE id=?');
            $stmt->bind_param('ssssi', $full_name, $email, $username, $initials, $uid);
            if ($stmt->execute()) {
                $_SESSION['full_name']       = $full_name;
                $_SESSION['avatar_initials'] = $initials;
                set_flash('success', 'Profile updated successfully.');
            } else {
                set_flash('error', 'Update failed. Username or email may already be in use.');
            }
            $stmt->close();
        }
        header('Location: profile.php'); exit;
    }

    // Change password
    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password']      ?? '';
        $confirm  = $_POST['confirm_password']  ?? '';

        $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($current, $row['password'])) {
            set_flash('error', 'Current password is incorrect.');
        } elseif (strlen($new_pass) < 8) {
            set_flash('error', 'New password must be at least 8 characters.');
        } elseif ($new_pass !== $confirm) {
            set_flash('error', 'Passwords do not match.');
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET password=? WHERE id=?');
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Password changed successfully.');
        }
        header('Location: profile.php'); exit;
    }
}

$unread_q = $db->prepare('SELECT COUNT(*) AS c FROM messages WHERE to_id=? AND is_read=0');
$unread_q->bind_param('i', $uid);
$unread_q->execute();
$unread_count = (int)$unread_q->get_result()->fetch_assoc()['c'];
$unread_q->close();

// ── Fetch profile ────────────────────────────────────────────
$stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $uid);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Fetch recent sessions ────────────────────────────────────
$stmt2 = $db->prepare(
    'SELECT session_id, ip_address, user_agent, login_at, logout_at
     FROM session_log WHERE user_id = ? ORDER BY login_at DESC LIMIT 6'
);
$stmt2->bind_param('i', $uid);
$stmt2->execute();
$sessions = $stmt2->get_result();
$stmt2->close();

// ── Count stats ──────────────────────────────────────────────
$res_logins = $db->prepare('SELECT COUNT(*) AS cnt FROM session_log WHERE user_id = ?');
$res_logins->bind_param('i', $uid);
$res_logins->execute();
$total_logins = (int)($res_logins->get_result()->fetch_assoc()['cnt'] ?? 0);
$res_logins->close();

$res_users = $db->query('SELECT COUNT(*) AS cnt FROM users');
$total_users = (int)($res_users->fetch_assoc()['cnt'] ?? 0);

$joined   = new DateTime($profile['created_at']);
$days     = (int)(new DateTime())->diff($joined)->days;

$page_title = 'Admin Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — LoginSys</title>
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    .profile-hero {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 2.5rem 2rem;
      display: flex;
      align-items: center;
      gap: 2rem;
      margin-bottom: 1.5rem;
      position: relative;
      overflow: hidden;
    }
    .profile-hero::before {
      content: '';
      position: absolute;
      top: -60px; right: -60px;
      width: 220px; height: 220px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(212,74,74,0.12) 0%, transparent 70%);
      pointer-events: none;
    }
    .admin-avatar-lg {
      width: 88px; height: 88px;
      border-radius: var(--radius);
      background: rgba(212,74,74,0.12);
      color: var(--admin-hue);
      border: 2px solid rgba(212,74,74,0.25);
      display: flex; align-items: center; justify-content: center;
      font-family: var(--font-display);
      font-size: 2rem; font-weight: 800;
      flex-shrink: 0;
    }
    .hero-info h1 {
      font-family: var(--font-display);
      font-size: 1.6rem; font-weight: 800;
      margin-bottom: 0.3rem;
    }
    .unread-dot { display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:var(--danger);color:#fff;font-size:.65rem;font-weight:800;margin-left:.35rem; }
    .hero-info p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.6rem; }
    .hero-meta { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.6rem; }
    .hero-meta-item {
      display: flex; align-items: center; gap: 0.4rem;
      font-size: 0.82rem; color: var(--text-muted);
    }
    .hero-meta-item svg { color: var(--accent); }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media(max-width:600px) { .form-grid { grid-template-columns: 1fr; } }

    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-group label { font-size: 0.82rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .form-group input {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0.65rem 0.9rem;
      color: var(--text);
      font-family: var(--font-body);
      font-size: 0.92rem;
      transition: border-color 0.2s, box-shadow 0.2s;
      width: 100%;
    }
    .form-group input:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-glow);
    }

    .strength-bar { height: 4px; border-radius: 2px; background: var(--border); margin-top: 6px; overflow: hidden; }
    .strength-fill { height: 100%; border-radius: 2px; transition: width 0.3s, background 0.3s; width: 0%; }

    .session-row {
      display: flex; align-items: center; gap: 1rem;
      padding: 0.85rem 0;
      border-bottom: 1px solid var(--border);
    }
    .session-row:last-child { border-bottom: none; }
    .session-icon {
      width: 36px; height: 36px; border-radius: var(--radius-sm);
      background: var(--bg-hover);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      color: var(--text-muted);
    }
    .session-info { flex: 1; min-width: 0; }
    .session-info .ip { font-size: 0.88rem; font-weight: 600; }
    .session-info .time { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.1rem; }
    .pill-online { display: inline-flex; align-items: center; gap: 4px; font-size: 0.72rem; font-weight: 700; background: rgba(34,184,110,0.12); color: var(--success); padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.04em; }
    .pill-offline { display: inline-flex; align-items: center; gap: 4px; font-size: 0.72rem; font-weight: 700; background: rgba(0,0,0,0.06); color: var(--text-dim); padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.04em; }
    [data-theme="dark"] .pill-offline { background: rgba(255,255,255,0.06); }
    .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
  </style>
</head>
<body>
<div class="page-bg-accent"></div>
<div class="dash-layout">

  <!-- ── Sidebar ─────────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon" style="color:var(--accent);"><?= icon('logo', '', 30) ?></div>
      <span>LoginSys</span>
    </div>
    <div class="sidebar-user">
      <div class="avatar avatar-admin"><?= e(current_initials()) ?></div>
      <div class="sidebar-user-info">
        <div class="name"><?= e(current_name()) ?></div>
        <span class="role-badge role-badge-admin">Administrator</span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="dashboard.php" class="nav-item"><?= icon('dashboard') ?>Dashboard</a>
      <div class="nav-section-label">Management</div>
      <a href="users.php" class="nav-item"><?= icon('users') ?>Users</a>
      <a href="sessions.php" class="nav-item"><?= icon('activity') ?>Session Log</a>
      <a href="messages.php" class="nav-item"><?= icon('mail') ?>Messages <?php if($unread_count): ?><span class="unread-dot"><?= $unread_count ?></span><?php endif;?></a>
      <div class="nav-section-label">System</div>
      <a href="permissions.php" class="nav-item"><?= icon('shield') ?>Permissions</a>
      <a href="database.php" class="nav-item"><?= icon('database') ?>Database</a>
      <a href="settings.php" class="nav-item"><?= icon('settings') ?>Settings</a>
      <div class="nav-section-label">Account</div>
      <a href="profile.php" class="nav-item active"><?= icon('profile') ?>My Profile</a>
    </nav>
    <div class="sidebar-footer">
      <form method="POST" action="../logout.php">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
        <button type="submit" class="btn btn-danger" style="width:100%;">
          <?= icon('logout', '', 17) ?> Sign Out
        </button>
      </form>
    </div>
  </aside>

  <!-- ── Main ────────────────────────────────────────────── -->
  <div class="dash-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <button class="mobile-menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">
          <?= icon('menu', '', 20) ?>
        </button>
        <h1 class="topbar-title"><?= icon('profile', '', 20) ?> My Profile</h1>
      </div>
      <div class="topbar-actions">
        <a href="dashboard.php" class="btn btn-outline" style="font-size:.85rem;">← Dashboard</a>
        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
          <span class="icon-moon"><?= icon('moon', '', 16) ?></span>
          <span class="icon-sun"><?= icon('sun', '', 16) ?></span>
        </button>
      </div>
    </header>

    <main class="dash-content">

      <?php if ($success): ?>
        <div class="alert alert-success" style="background:rgba(34,184,110,0.1);border:1px solid rgba(34,184,110,0.3);color:var(--success);padding:.75rem 1rem;border-radius:var(--radius-sm);margin-bottom:1rem;font-size:.9rem;"><?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-error" style="background:rgba(212,74,74,0.1);border:1px solid rgba(212,74,74,0.3);color:var(--danger);padding:.75rem 1rem;border-radius:var(--radius-sm);margin-bottom:1rem;font-size:.9rem;"><?= e($error) ?></div>
      <?php endif; ?>

      <!-- Hero Card -->
      <div class="profile-hero">
        <div class="admin-avatar-lg"><?= e($profile['avatar_initials']) ?></div>
        <div class="hero-info">
          <h1><?= e($profile['full_name']) ?></h1>
          <p><?= e($profile['email']) ?> &nbsp;·&nbsp; @<?= e($profile['username']) ?></p>
          <div style="display:flex;align-items:center;gap:.5rem;">
            <span class="badge badge-admin">Administrator</span>
            <?php if ($profile['is_active']): ?>
              <span class="pill-online"><span class="dot"></span>Active</span>
            <?php else: ?>
              <span class="pill-offline"><span class="dot"></span>Inactive</span>
            <?php endif; ?>
          </div>
          <div class="hero-meta">
            <span class="hero-meta-item"><?= icon('calendar', '', 14) ?> Joined <?= date('M j, Y', strtotime($profile['created_at'])) ?> (<?= $days ?> days ago)</span>
            <span class="hero-meta-item"><?= icon('clock', '', 14) ?> Last login <?= $profile['last_login'] ? date('M j, Y g:i A', strtotime($profile['last_login'])) : 'Never' ?></span>
          </div>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="stats-row">
        <div class="stat-card c-amber">
          <div class="stat-label">Total Logins</div>
          <div class="stat-value"><?= number_format($total_logins) ?></div>
          <div class="stat-sub">all time</div>
        </div>
        <div class="stat-card c-red">
          <div class="stat-label">System Users</div>
          <div class="stat-value"><?= $total_users ?></div>
          <div class="stat-sub">managed</div>
        </div>
        <div class="stat-card c-blue">
          <div class="stat-label">Account Age</div>
          <div class="stat-value"><?= $days ?></div>
          <div class="stat-sub">days</div>
        </div>
        <div class="stat-card c-green">
          <div class="stat-label">Role</div>
          <div class="stat-value" style="font-size:1.2rem;">Admin</div>
          <div class="stat-sub">full access</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

        <!-- Edit Profile -->
        <div class="section-card" style="grid-column: span 1;">
          <div class="section-card-header">
            <h2><?= icon('profile') ?>Edit Profile</h2>
          </div>
          <form method="POST" action="profile.php" style="padding:1.5rem;display:flex;flex-direction:column;gap:1rem;">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
            <input type="hidden" name="action" value="update_profile"/>
            <div class="form-grid">
              <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= e($profile['full_name']) ?>" required/>
              </div>
              <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?= e($profile['username']) ?>" required/>
              </div>
              <div class="form-group" style="grid-column:1/-1;">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= e($profile['email']) ?>" required/>
              </div>
            </div>
            <div style="display:flex;justify-content:flex-end;">
              <button type="submit" class="btn btn-primary"><?= icon('profile', '', 16) ?> Save Changes</button>
            </div>
          </form>
        </div>

        <!-- Change Password -->
        <div class="section-card" style="grid-column: span 1;">
          <div class="section-card-header">
            <h2><?= icon('key') ?>Change Password</h2>
          </div>
          <form method="POST" action="profile.php" style="padding:1.5rem;display:flex;flex-direction:column;gap:1rem;" id="pwForm">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
            <input type="hidden" name="action" value="change_password"/>
            <div class="form-group">
              <label>Current Password</label>
              <input type="password" name="current_password" required autocomplete="current-password"/>
            </div>
            <div class="form-group">
              <label>New Password</label>
              <input type="password" name="new_password" id="newPass" required autocomplete="new-password" oninput="updateStrength(this.value)"/>
              <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
              <div id="strengthLabel" style="font-size:.75rem;color:var(--text-dim);margin-top:2px;"></div>
            </div>
            <div class="form-group">
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" required autocomplete="new-password"/>
            </div>
            <div style="display:flex;justify-content:flex-end;">
              <button type="submit" class="btn btn-primary"><?= icon('lock', '', 16) ?> Update Password</button>
            </div>
          </form>
        </div>

        <!-- Recent Sessions -->
        <div class="section-card" style="grid-column:1/-1;">
          <div class="section-card-header">
            <h2><?= icon('activity') ?>Recent Login Sessions</h2>
            <a href="sessions.php" class="btn btn-outline" style="font-size:.82rem;">View All</a>
          </div>
          <div style="padding:0 1.5rem;">
            <?php if ($sessions->num_rows === 0): ?>
              <p style="color:var(--text-muted);padding:1.5rem 0;text-align:center;font-size:.9rem;">No sessions recorded.</p>
            <?php else: ?>
              <?php while ($s = $sessions->fetch_assoc()): ?>
                <div class="session-row">
                  <div class="session-icon"><?= icon('activity', '', 16) ?></div>
                  <div class="session-info">
                    <div class="ip"><?= e($s['ip_address']) ?></div>
                    <div class="time"><?= date('M j, Y · g:i A', strtotime($s['login_at'])) ?></div>
                  </div>
                  <div>
                    <?php if (is_null($s['logout_at'])): ?>
                      <span class="pill-online"><span class="dot"></span>Active</span>
                    <?php else: ?>
                      <span class="pill-offline">Ended <?= date('g:i A', strtotime($s['logout_at'])) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endwhile; ?>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /grid -->
    </main>
  </div>
</div>

<script>
// Theme toggle
const html = document.documentElement;
const btn  = document.getElementById('themeToggle');
const saved = localStorage.getItem('theme') || 'light';
if (saved === 'dark') html.setAttribute('data-theme','dark');
btn.addEventListener('click', () => {
  const isDark = html.getAttribute('data-theme') === 'dark';
  html.setAttribute('data-theme', isDark ? 'light' : 'dark');
  localStorage.setItem('theme', isDark ? 'light' : 'dark');
});

// Password strength
function updateStrength(val) {
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  let score = 0;
  if (val.length >= 8)  score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    { pct:'0%',   color:'transparent', text:'' },
    { pct:'25%',  color:'var(--danger)',   text:'Weak' },
    { pct:'50%',  color:'var(--accent)',   text:'Fair' },
    { pct:'75%',  color:'var(--info)',     text:'Good' },
    { pct:'100%', color:'var(--success)',  text:'Strong' },
  ];
  fill.style.width     = levels[score].pct;
  fill.style.background= levels[score].color;
  label.textContent    = levels[score].text;
  label.style.color    = levels[score].color;
}
</script>
</body>
</html>
<?php
// ============================================================
//  user/dashboard.php  — Regular-user dashboard
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('user');

$db  = db();
$uid = current_user_id();

// ── Fetch current user's profile ─────────────────────────────
$stmt = $db->prepare(
    'SELECT id, username, email, full_name, avatar_initials, last_login, created_at
     FROM users WHERE id = ? LIMIT 1'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Fetch this user's own session history ────────────────────
$stmt2 = $db->prepare(
    'SELECT session_id, ip_address, login_at, logout_at
     FROM session_log
     WHERE user_id = ?
     ORDER BY login_at DESC
     LIMIT 8'
);
$stmt2->bind_param('i', $uid);
$stmt2->execute();
$my_sessions = $stmt2->get_result();
$stmt2->close();

// ── Count my total logins ─────────────────────────────────────
$stmt3 = $db->prepare('SELECT COUNT(*) AS cnt FROM session_log WHERE user_id = ?');
$stmt3->bind_param('i', $uid);
$stmt3->execute();
$total_logins = (int)($stmt3->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt3->close();

// Account age in days
$joined    = new DateTime($profile['created_at']);
$now       = new DateTime();
$days      = (int)$joined->diff($now)->days;

$csrf       = $_SESSION['csrf_token'] ?? '';
$page_title = 'My Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — Login System</title>
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    .profile-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }

    .profile-avatar {
      width: 60px;
      height: 60px;
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-display);
      font-size: 1.15rem;
      font-weight: 800;
      flex-shrink: 0;
      background: rgba(92,184,224,0.15);
      color: var(--user-hue);
      border: 1px solid rgba(92,184,224,0.25);
    }

    .profile-info h3 {
      font-family: var(--font-display);
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 0.2rem;
    }

    .profile-info p {
      color: var(--text-muted);
      font-size: 0.88rem;
    }

    .sid-code {
      font-family: monospace;
      font-size: 0.72rem;
      color: var(--text-dim);
      background: rgba(0,0,0,0.3);
      padding: 2px 6px;
      border-radius: 3px;
    }

    .mobile-menu-btn {
      display: none;
      background: none;
      border: 1px solid var(--border-light);
      border-radius: var(--radius-sm);
      padding: 6px;
      cursor: pointer;
      color: var(--text-muted);
    }

    @media(max-width:768px) {
      .mobile-menu-btn { display: flex; align-items: center; }
      .profile-card { flex-direction: column; text-align: center; }
    }
  </style>
</head>
<body>
<div class="dash-layout">

  <!-- ── Sidebar ─────────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
      <div class="brand-icon" style="color:var(--accent);">
        <?= icon('logo', '', 30) ?>
      </div>
      <span>LoginSys</span>
    </div>

    <div class="sidebar-user">
      <div class="avatar avatar-user"><?= e(current_initials()) ?></div>
      <div class="sidebar-user-info">
        <div class="name"><?= e(current_name()) ?></div>
        <span class="role-badge role-badge-user">Member</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="dashboard.php" class="nav-item"><?= icon('dashboard') ?>Dashboard</a>
      <div class="nav-section-label">Account</div>
      <a href="profile.php" class="nav-item active"><?= icon('profile') ?>My Profile</a>
      <a href="login_history.php" class="nav-item"><?= icon('activity') ?>Login History</a>
      <a href="messages.php" class="nav-item"><?= icon('mail') ?>Messages</a>
      <a href="change_password.php" class="nav-item"><?= icon('key') ?>Change Password</a>
      <a href="notifications.php" class="nav-item"><?= icon('bell') ?>Notifications</a>
    </nav>

    <div class="sidebar-footer">
      <form method="POST" action="../logout.php">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
        <button type="submit" class="btn btn-danger" style="width:100%;">
          <?= icon('logout', '', 17) ?>
          Sign Out
        </button>
      </form>
    </div>

  </aside><!-- /sidebar -->

  <!-- ── Main ────────────────────────────────────────────── -->
  <div class="dash-main">

    <!-- Top bar -->
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <button class="mobile-menu-btn" id="menuToggle" aria-label="Toggle menu">
          <?= icon('menu', '', 20) ?>
        </button>
        <span class="topbar-title"><?= e($page_title) ?></span>
      </div>
      <div class="topbar-actions">
        <span style="font-size:0.8rem;color:var(--text-muted);"><?= date('D, d M Y') ?></span>
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
          <span class="icon-moon"><?= icon('moon', '', 16) ?></span>
          <span class="icon-sun"><?= icon('sun', '', 16) ?></span>
        </button>
        <?= icon('bell', '', 20) ?>
        <div class="avatar avatar-user" style="font-size:0.65rem;"><?= e(current_initials()) ?></div>
      </div>
    </header>

    <div class="dash-content">

      <!-- Welcome banner -->
      <div class="welcome-banner">
        <h2>Hello, <span><?= e(current_name()) ?></span> 👋</h2>
        <p>Welcome to your personal dashboard. View your account details, track your login history, and manage your preferences below.</p>
      </div>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card c-blue">
          <div class="stat-icon blue"><?= icon('activity', '', 20) ?></div>
          <div class="stat-value"><?= $total_logins ?></div>
          <div class="stat-label">Total Logins</div>
        </div>

        <div class="stat-card c-amber">
          <div class="stat-icon amber"><?= icon('calendar', '', 20) ?></div>
          <div class="stat-value"><?= $days ?></div>
          <div class="stat-label">Days as Member</div>
        </div>

        <div class="stat-card c-green">
          <div class="stat-icon green"><?= icon('check-circle', '', 20) ?></div>
          <div class="stat-value">Active</div>
          <div class="stat-label">Account Status</div>
        </div>

        <div class="stat-card c-red">
          <div class="stat-icon red"><?= icon('clock', '', 20) ?></div>
          <div class="stat-value">
            <?= $profile['last_login'] ? e(date('H:i', strtotime($profile['last_login']))) : '—' ?>
          </div>
          <div class="stat-label">Last Login Time</div>
        </div>
      </div>

      <!-- Profile section -->
      <div id="profile-section" class="section-card">
        <div class="section-card-header">
          <h2><?= icon('profile') ?> My Profile</h2>
        </div>
        <div style="padding:1.5rem;">
          <div class="profile-card" style="margin-bottom:0;border:none;padding:0;background:transparent;">
            <div class="profile-avatar"><?= e(current_initials()) ?></div>
            <div class="profile-info">
              <h3><?= e($profile['full_name']) ?></h3>
              <p>@<?= e($profile['username']) ?> &nbsp;·&nbsp; <?= e($profile['email']) ?></p>
            </div>
          </div>

          <div style="height:1.5rem;"></div>

          <div class="info-grid">
            <div class="info-item">
              <label>Full Name</label>
              <span><?= e($profile['full_name']) ?></span>
            </div>
            <div class="info-item">
              <label>Username</label>
              <span><?= e($profile['username']) ?></span>
            </div>
            <div class="info-item">
              <label>Email Address</label>
              <span><?= e($profile['email']) ?></span>
            </div>
            <div class="info-item">
              <label>Role</label>
              <span style="color:var(--user-hue);">Member</span>
            </div>
            <div class="info-item">
              <label>Member Since</label>
              <span><?= e(date('d M Y', strtotime($profile['created_at']))) ?></span>
            </div>
            <div class="info-item">
              <label>Last Login</label>
              <span>
                <?= $profile['last_login']
                    ? e(date('d M Y \a\t H:i', strtotime($profile['last_login'])))
                    : 'Never' ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Session history -->
      <div class="section-card" id="sessions-section">
        <div class="section-card-header">
          <h2><?= icon('clock') ?> My Login History</h2>
          <span style="font-size:0.78rem;color:var(--text-muted);">Last 8 sessions</span>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Session ID</th>
                <th>IP Address</th>
                <th>Login Time</th>
                <th>Logout Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($s = $my_sessions->fetch_assoc()): ?>
              <tr>
                <td><code class="sid-code"><?= e(substr($s['session_id'], 0, 12)) ?>…</code></td>
                <td><?= e($s['ip_address'] ?: '—') ?></td>
                <td><?= e(date('d M Y H:i', strtotime($s['login_at']))) ?></td>
                <td>
                  <?= $s['logout_at']
                      ? e(date('d M Y H:i', strtotime($s['logout_at'])))
                      : '—' ?>
                </td>
                <td>
                  <?php if (!$s['logout_at']): ?>
                    <span class="status-dot active">Current</span>
                  <?php else: ?>
                    <span class="status-dot inactive">Ended</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php if ($total_logins === 0): ?>
              <tr><td colspan="5" style="text-align:center;color:var(--text-dim);padding:2rem;">No sessions found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div><!-- /session history -->

    </div><!-- /dash-content -->
  </div><!-- /dash-main -->

</div><!-- /dash-layout -->

<script>
  const toggle  = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  }

  // Theme toggle
  (function() {
    const html  = document.documentElement;
    const btn   = document.getElementById('themeToggle');
    const saved = localStorage.getItem('theme') || 'light';
    if (saved === 'dark') html.setAttribute('data-theme', 'dark');
    btn.addEventListener('click', function() {
      const isDark = html.getAttribute('data-theme') === 'dark';
      html.setAttribute('data-theme', isDark ? 'light' : 'dark');
      localStorage.setItem('theme', isDark ? 'light' : 'dark');
    });
  })();
</script>
</body>
</html>
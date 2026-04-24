<?php
// ============================================================
//  admin/dashboard.php  — Admin-only dashboard
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('admin');

$db = db();

// ── Fetch stats ──────────────────────────────────────────────

// Total users
$res         = $db->query('SELECT COUNT(*) AS cnt FROM users');
$total_users = (int)($res->fetch_assoc()['cnt'] ?? 0);

// Total admins
$stmt        = $db->prepare('SELECT COUNT(*) AS cnt FROM users WHERE role = ?');
$role_a      = 'admin';
$stmt->bind_param('s', $role_a);
$stmt->execute();
$total_admins = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();

// Total regular users
$stmt2       = $db->prepare('SELECT COUNT(*) AS cnt FROM users WHERE role = ?');
$role_u      = 'user';
$stmt2->bind_param('s', $role_u);
$stmt2->execute();
$total_regular = (int)($stmt2->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt2->close();

// Active sessions today
$res2        = $db->query('SELECT COUNT(*) AS cnt FROM session_log WHERE DATE(login_at) = CURDATE()');
$sessions_today = (int)($res2->fetch_assoc()['cnt'] ?? 0);

// ── Fetch all users list ─────────────────────────────────────
$users_result = $db->query(
    'SELECT id, username, email, role, full_name, is_active, last_login, created_at
     FROM users ORDER BY role ASC, id ASC'
);

// ── Fetch recent session log ─────────────────────────────────
$log_result = $db->query(
    'SELECT sl.session_id, u.username, u.role, sl.ip_address,
            sl.login_at, sl.logout_at
     FROM session_log sl
     JOIN users u ON u.id = sl.user_id
     ORDER BY sl.login_at DESC
     LIMIT 10'
);

$csrf = $_SESSION['csrf_token'] ?? '';
$page_title = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — Login System</title>
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    .badge {
      display: inline-block;
      padding: 2px 9px;
      border-radius: 3px;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }
    .badge-admin { background: rgba(224,92,92,0.15); color: var(--admin-hue); }
    .badge-user  { background: rgba(92,184,224,0.15); color: var(--user-hue); }

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

    @media(max-width:768px) { .mobile-menu-btn { display: flex; align-items: center; } }
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
      <div class="avatar avatar-admin"><?= e(current_initials()) ?></div>
      <div class="sidebar-user-info">
        <div class="name"><?= e(current_name()) ?></div>
        <span class="role-badge role-badge-admin">Administrator</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>

      <a href="dashboard.php" class="nav-item active">
        <?= icon('dashboard') ?>
        Dashboard
      </a>

      <div class="nav-section-label">Management</div>

      <a href="./users.php" class="nav-item">
        <?= icon('users') ?>
        Users
      </a>

      <a href="sessions.php" class="nav-item">
        <?= icon('activity') ?>
        Session Log
      </a>

      <div class="nav-section-label">System</div>

      <a href="permissions.php" class="nav-item">
        <?= icon('shield') ?>
        Permissions
      </a>

      <a href="database.php" class="nav-item">
        <?= icon('database') ?>
        Database
      </a>

      <a href="settings.php" class="nav-item">
        <?= icon('settings') ?>
        Settings
      </a>

      <div class="nav-section-label">Account</div>
      
      <a href="profile.php" class="nav-item"><?= icon('profile') ?>My Profile</a>
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
        <div class="avatar avatar-admin" style="font-size:0.65rem;"><?= e(current_initials()) ?></div>
      </div>
    </header>

    <div class="dash-content">

      <!-- Welcome banner -->
      <div class="welcome-banner">
        <h2>Welcome back, <span><?= e(current_name()) ?></span> 👋</h2>
        <p>You have administrator access. Manage users, review session activity, and control system settings from this dashboard.</p>
      </div>

      <!-- Stats grid -->
      <div class="stats-grid">
        <div class="stat-card c-amber">
          <div class="stat-icon amber"><?= icon('users', '', 20) ?></div>
          <div class="stat-value"><?= $total_users ?></div>
          <div class="stat-label">Total Users</div>
        </div>

        <div class="stat-card c-red">
          <div class="stat-icon red"><?= icon('shield', '', 20) ?></div>
          <div class="stat-value"><?= $total_admins ?></div>
          <div class="stat-label">Administrators</div>
        </div>

        <div class="stat-card c-blue">
          <div class="stat-icon blue"><?= icon('profile', '', 20) ?></div>
          <div class="stat-value"><?= $total_regular ?></div>
          <div class="stat-label">Regular Users</div>
        </div>

        <div class="stat-card c-green">
          <div class="stat-icon green"><?= icon('activity', '', 20) ?></div>
          <div class="stat-value"><?= $sessions_today ?></div>
          <div class="stat-label">Logins Today</div>
        </div>
      </div>

      <!-- Users table -->
      <div class="section-card" id="users-section">
        <div class="section-card-header">
          <h2><?= icon('users') ?> All Users</h2>
          <span style="font-size:0.78rem;color:var(--text-muted);"><?= $total_users ?> registered</span>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($u = $users_result->fetch_assoc()): ?>
              <tr>
                <td style="color:var(--text-dim);"><?= e($u['id']) ?></td>
                <td style="font-weight:600;color:var(--text);"><?= e($u['full_name']) ?></td>
                <td><?= e($u['username']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td>
                  <span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span>
                </td>
                <td>
                  <?php if ((int)$u['is_active']): ?>
                    <span class="status-dot active">Active</span>
                  <?php else: ?>
                    <span class="status-dot inactive">Inactive</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= $u['last_login']
                      ? e(date('d M Y H:i', strtotime($u['last_login'])))
                      : '<span style="color:var(--text-dim)">Never</span>' ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div><!-- /users table -->

      <!-- Session log table -->
      <div class="section-card" id="sessions-section">
        <div class="section-card-header">
          <h2><?= icon('activity') ?> Recent Session Log</h2>
          <span style="font-size:0.78rem;color:var(--text-muted);">Last 10 sessions</span>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Session ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>IP Address</th>
                <th>Login</th>
                <th>Logout</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($log = $log_result->fetch_assoc()): ?>
              <tr>
                <td><code class="sid-code"><?= e(substr($log['session_id'], 0, 12)) ?>…</code></td>
                <td style="font-weight:600;color:var(--text);"><?= e($log['username']) ?></td>
                <td><span class="badge badge-<?= e($log['role']) ?>"><?= e($log['role']) ?></span></td>
                <td><?= e($log['ip_address'] ?: '—') ?></td>
                <td><?= e(date('d M H:i', strtotime($log['login_at']))) ?></td>
                <td>
                  <?= $log['logout_at']
                      ? e(date('d M H:i', strtotime($log['logout_at'])))
                      : '<span style="color:var(--success);font-size:0.8rem;">● Active</span>' ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div><!-- /session log -->

    </div><!-- /dash-content -->
  </div><!-- /dash-main -->

</div><!-- /dash-layout -->

<script>
  // Mobile sidebar toggle
  const toggle = document.getElementById('menuToggle');
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
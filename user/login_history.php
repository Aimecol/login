<?php
// ============================================================
//  user/login_history.php  — User Login History
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('user');

$db   = db();
$uid  = current_user_id();
$csrf = $_SESSION['csrf_token'] ?? '';

// ── Filters & Pagination ─────────────────────────────────────
$filter_date = $_GET['date'] ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 15;
$offset      = ($page - 1) * $per_page;

$where  = ['user_id = ?'];
$params = [$uid];
$types  = 'i';

if ($filter_date === 'today') {
    $where[] = 'DATE(login_at) = CURDATE()';
} elseif ($filter_date === 'week') {
    $where[] = 'login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($filter_date === 'month') {
    $where[] = 'login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

$where_sql = implode(' AND ', $where);

// Count
$cnt_stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM session_log WHERE {$where_sql}");
$cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute();
$total = (int)$cnt_stmt->get_result()->fetch_assoc()['cnt'];
$cnt_stmt->close();
$total_pages = max(1, ceil($total / $per_page));

// Fetch
$list_stmt = $db->prepare(
    "SELECT id, session_id, ip_address, user_agent, login_at, logout_at
     FROM session_log WHERE {$where_sql}
     ORDER BY login_at DESC LIMIT ? OFFSET ?"
);
$list_params = array_merge($params, [$per_page, $offset]);
$list_stmt->bind_param($types . 'ii', ...$list_params);
$list_stmt->execute();
$sessions = $list_stmt->get_result();
$list_stmt->close();

// ── Summary stats ─────────────────────────────────────────────
$s_total = $db->prepare('SELECT COUNT(*) AS c FROM session_log WHERE user_id=?');
$s_total->bind_param('i', $uid);
$s_total->execute();
$total_all = (int)$s_total->get_result()->fetch_assoc()['c'];
$s_total->close();

$s_today = $db->prepare('SELECT COUNT(*) AS c FROM session_log WHERE user_id=? AND DATE(login_at)=CURDATE()');
$s_today->bind_param('i', $uid);
$s_today->execute();
$today_count = (int)$s_today->get_result()->fetch_assoc()['c'];
$s_today->close();

$s_active = $db->prepare('SELECT COUNT(*) AS c FROM session_log WHERE user_id=? AND logout_at IS NULL');
$s_active->bind_param('i', $uid);
$s_active->execute();
$active_count = (int)$s_active->get_result()->fetch_assoc()['c'];
$s_active->close();

$page_title = 'Login History';
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
    .filter-bar {
      display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius); padding: .9rem 1.25rem;
      margin-bottom: 1.5rem;
    }
    .filter-btn {
      padding: .4rem .9rem; border-radius: var(--radius-sm);
      border: 1px solid var(--border); background: var(--bg);
      color: var(--text-muted); font-family: var(--font-body); font-size: .82rem;
      cursor: pointer; text-decoration: none; transition: all .15s;
    }
    .filter-btn:hover, .filter-btn.active {
      border-color: var(--user-hue); color: var(--user-hue);
      background: rgba(46,143,196,0.08); text-decoration: none;
    }
    .filter-label { font-size: .82rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }

    .history-table { width: 100%; border-collapse: collapse; }
    .history-table th {
      text-align: left; padding: .75rem 1rem;
      font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
      color: var(--text-muted); background: var(--table-head-bg);
      border-bottom: 1px solid var(--border);
    }
    .history-table td {
      padding: .9rem 1rem; border-bottom: 1px solid var(--border);
      font-size: .875rem; vertical-align: middle;
    }
    .history-table tr:last-child td { border-bottom: none; }
    .history-table tr:hover td { background: var(--bg-hover); }

    .device-icon {
      width: 32px; height: 32px; border-radius: var(--radius-sm);
      background: var(--bg-hover); display: flex; align-items: center; justify-content: center;
      color: var(--text-muted); flex-shrink: 0;
    }
    .ua-text { font-size: .76rem; color: var(--text-dim); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pill-active { font-size: .7rem; font-weight: 700; background: rgba(34,184,110,0.12); color: var(--success); padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
    .pill-ended  { font-size: .7rem; font-weight: 700; background: rgba(0,0,0,0.06); color: var(--text-dim); padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
    [data-theme="dark"] .pill-ended { background: rgba(255,255,255,0.06); }

    .pagination { display: flex; align-items: center; gap: .5rem; justify-content: center; padding: 1.5rem 0 .5rem; }
    .pag-btn {
      padding: .4rem .8rem; border-radius: var(--radius-sm);
      border: 1px solid var(--border); background: var(--bg);
      color: var(--text-muted); font-size: .82rem; text-decoration: none; transition: all .15s;
    }
    .pag-btn:hover { border-color: var(--user-hue); color: var(--user-hue); text-decoration: none; }
    .pag-btn.active { background: var(--user-hue); color: #fff; border-color: var(--user-hue); }
    .pag-btn.disabled { opacity: .4; pointer-events: none; }
  </style>
</head>
<body>
<div class="page-bg-accent"></div>
<div class="dash-layout">

  <!-- Sidebar -->
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
      <a href="login_history.php" class="nav-item active"><?= icon('activity') ?>Login History</a>
      <a href="messages.php" class="nav-item"><?= icon('mail') ?>Messages</a>
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
        <h1 class="topbar-title"><?= icon('activity', '', 20) ?> Login History</h1>
      </div>
      <div class="topbar-actions">
        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
          <span class="icon-moon"><?= icon('moon', '', 16) ?></span>
          <span class="icon-sun"><?= icon('sun', '', 16) ?></span>
        </button>
      </div>
    </header>

    <main class="dash-content">

      <!-- Stats -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="stat-card c-blue">
          <div class="stat-label">Total Logins</div>
          <div class="stat-value"><?= $total_all ?></div>
          <div class="stat-sub">all time</div>
        </div>
        <div class="stat-card c-amber">
          <div class="stat-label">Today</div>
          <div class="stat-value"><?= $today_count ?></div>
          <div class="stat-sub">logins today</div>
        </div>
        <div class="stat-card c-green">
          <div class="stat-label">Active Sessions</div>
          <div class="stat-value"><?= $active_count ?></div>
          <div class="stat-sub">currently open</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="filter-bar">
        <span class="filter-label">Filter:</span>
        <a href="login_history.php" class="filter-btn <?= $filter_date==='' ? 'active' : '' ?>">All Time</a>
        <a href="?date=today" class="filter-btn <?= $filter_date==='today' ? 'active' : '' ?>">Today</a>
        <a href="?date=week"  class="filter-btn <?= $filter_date==='week'  ? 'active' : '' ?>">Past 7 Days</a>
        <a href="?date=month" class="filter-btn <?= $filter_date==='month' ? 'active' : '' ?>">Past 30 Days</a>
        <span style="margin-left:auto;font-size:.82rem;color:var(--text-muted);"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?></span>
      </div>

      <!-- Table -->
      <div class="section-card">
        <div class="section-card-header">
          <h2><?= icon('clock') ?>Session Log</h2>
        </div>
        <div style="overflow-x:auto;">
          <table class="history-table">
            <thead>
              <tr>
                <th>#</th>
                <th>IP Address</th>
                <th>Device / Browser</th>
                <th>Login Time</th>
                <th>Logout Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($sessions->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">No sessions found for this filter.</td></tr>
              <?php else: ?>
                <?php $row_num = $offset + 1; while ($s = $sessions->fetch_assoc()): ?>
                  <tr>
                    <td style="color:var(--text-dim);font-size:.8rem;"><?= $row_num++ ?></td>
                    <td>
                      <div style="display:flex;align-items:center;gap:.6rem;">
                        <div class="device-icon"><?= icon('home', '', 14) ?></div>
                        <span style="font-weight:600;"><?= e($s['ip_address']) ?></span>
                      </div>
                    </td>
                    <td>
                      <div class="ua-text" title="<?= e($s['user_agent']) ?>"><?= e($s['user_agent'] ?? 'Unknown') ?></div>
                    </td>
                    <td style="white-space:nowrap;">
                      <div style="font-size:.88rem;"><?= date('M j, Y', strtotime($s['login_at'])) ?></div>
                      <div style="font-size:.78rem;color:var(--text-muted);"><?= date('g:i A', strtotime($s['login_at'])) ?></div>
                    </td>
                    <td style="white-space:nowrap;">
                      <?php if ($s['logout_at']): ?>
                        <div style="font-size:.88rem;"><?= date('M j, Y', strtotime($s['logout_at'])) ?></div>
                        <div style="font-size:.78rem;color:var(--text-muted);"><?= date('g:i A', strtotime($s['logout_at'])) ?></div>
                      <?php else: ?>
                        <span style="font-size:.82rem;color:var(--text-dim);">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= is_null($s['logout_at']) ? '<span class="pill-active">● Active</span>' : '<span class="pill-ended">Ended</span>' ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="pagination">
            <a href="?date=<?= urlencode($filter_date) ?>&page=<?= max(1,$page-1) ?>" class="pag-btn <?= $page<=1 ? 'disabled' : '' ?>">‹ Prev</a>
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
              <a href="?date=<?= urlencode($filter_date) ?>&page=<?= $p ?>" class="pag-btn <?= $p===$page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="?date=<?= urlencode($filter_date) ?>&page=<?= min($total_pages,$page+1) ?>" class="pag-btn <?= $page>=$total_pages ? 'disabled' : '' ?>">Next ›</a>
          </div>
        <?php endif; ?>
      </div>

    </main>
  </div>
</div>
<script>
const html = document.documentElement;
if (localStorage.getItem('theme')==='dark') html.setAttribute('data-theme','dark');
document.getElementById('themeToggle').addEventListener('click', () => {
  const d = html.getAttribute('data-theme')==='dark';
  html.setAttribute('data-theme', d ? 'light' : 'dark');
  localStorage.setItem('theme', d ? 'light' : 'dark');
});
</script>
</body>
</html>
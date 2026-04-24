<?php
// ============================================================
//  admin/sessions.php  — Session Log Management
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('admin');

$db   = db();
$csrf = $_SESSION['csrf_token'] ?? '';

$success = get_flash('success') ?? '';
$error   = get_flash('error')   ?? '';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: sessions.php'); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_session') {
        $sid = (int)($_POST['sid'] ?? 0);
        $db->query("DELETE FROM session_log WHERE id = {$sid}");
        set_flash('success', 'Session record deleted.');
        header('Location: sessions.php'); exit;
    }
    if ($action === 'clear_all') {
        $db->query("DELETE FROM session_log");
        set_flash('success', 'All session logs cleared.');
        header('Location: sessions.php'); exit;
    }
    if ($action === 'clear_old') {
        $db->query("DELETE FROM session_log WHERE login_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        set_flash('success', 'Sessions older than 30 days cleared.');
        header('Location: sessions.php'); exit;
    }
}

// ── Filters & Pagination ─────────────────────────────────────
$search      = trim($_GET['q'] ?? '');
$filter_role = $_GET['role'] ?? '';
$filter_date = $_GET['date'] ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 20;
$offset      = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(u.username LIKE ? OR sl.ip_address LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s]);
    $types .= 'ss';
}
if ($filter_role !== '') {
    $where[] = 'u.role = ?';
    $params[] = $filter_role;
    $types .= 's';
}
if ($filter_date === 'today') {
    $where[] = 'DATE(sl.login_at) = CURDATE()';
} elseif ($filter_date === 'week') {
    $where[] = 'sl.login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($filter_date === 'month') {
    $where[] = 'sl.login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

$where_sql = implode(' AND ', $where);

// Count
$count_sql = "SELECT COUNT(*) AS cnt FROM session_log sl JOIN users u ON u.id = sl.user_id WHERE {$where_sql}";
$count_stmt = $db->prepare($count_sql);
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_sessions = (int)$count_stmt->get_result()->fetch_assoc()['cnt'];
$count_stmt->close();
$total_pages = max(1, ceil($total_sessions / $per_page));

// Fetch
$list_sql = "SELECT sl.id, sl.session_id, sl.ip_address, sl.user_agent, sl.login_at, sl.logout_at,
                    u.id AS uid, u.username, u.full_name, u.avatar_initials, u.role
             FROM session_log sl
             JOIN users u ON u.id = sl.user_id
             WHERE {$where_sql}
             ORDER BY sl.login_at DESC
             LIMIT ? OFFSET ?";
$list_params = array_merge($params, [$per_page, $offset]);
$list_types  = $types . 'ii';
$list_stmt = $db->prepare($list_sql);
$list_stmt->bind_param($list_types, ...$list_params);
$list_stmt->execute();
$sessions = $list_stmt->get_result();
$list_stmt->close();

// ── Stats ────────────────────────────────────────────────────
$stat_total   = (int)$db->query('SELECT COUNT(*) c FROM session_log')->fetch_assoc()['c'];
$stat_today   = (int)$db->query('SELECT COUNT(*) c FROM session_log WHERE DATE(login_at)=CURDATE()')->fetch_assoc()['c'];
$stat_active  = (int)$db->query('SELECT COUNT(*) c FROM session_log WHERE logout_at IS NULL')->fetch_assoc()['c'];
$stat_week    = (int)$db->query('SELECT COUNT(*) c FROM session_log WHERE login_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)')->fetch_assoc()['c'];

$page_title = 'Session Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — Login System</title>
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    .table-toolbar {
      display: flex; align-items: center; gap: 0.75rem;
      flex-wrap: wrap; padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--border);
    }
    .search-wrap { position: relative; flex: 1; min-width: 180px; }
    .search-wrap svg { position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-dim); pointer-events:none; }
    .search-input {
      width: 100%; padding: 0.6rem 0.85rem 0.6rem 2.4rem;
      background: var(--bg); border: 1px solid var(--border-light);
      border-radius: var(--radius-sm); color: var(--text);
      font-family: var(--font-body); font-size: 0.88rem; outline: none;
    }
    .search-input:focus { border-color: var(--accent); }
    .filter-select {
      padding: 0.58rem 0.85rem; background: var(--bg);
      border: 1px solid var(--border-light); border-radius: var(--radius-sm);
      color: var(--text-muted); font-family: var(--font-body); font-size: 0.85rem; cursor: pointer;
    }
    .filter-select:focus { outline:none; border-color:var(--accent); }

    .user-cell { display:flex; align-items:center; gap:0.6rem; }
    .u-avatar { width:30px; height:30px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:0.6rem; font-weight:700; flex-shrink:0; }
    .u-avatar.admin { background:rgba(212,74,74,0.1); color:var(--admin-hue); border:1px solid rgba(212,74,74,0.2); }
    .u-avatar.user  { background:rgba(46,143,196,0.1); color:var(--user-hue);  border:1px solid rgba(46,143,196,0.2); }
    .u-name  { font-weight:600; color:var(--text); font-size:0.86rem; }
    .u-email { font-size:0.73rem; color:var(--text-dim); }

    .sid-code { font-family:monospace; font-size:0.72rem; color:var(--text-dim); background:var(--bg-hover); padding:2px 6px; border-radius:3px; border:1px solid var(--border); }
    .ua-text  { font-size:0.73rem; color:var(--text-dim); max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    .duration-badge { font-size:0.72rem; padding:2px 8px; border-radius:3px; font-weight:600; background:rgba(34,184,110,0.1); color:var(--success); }
    .duration-badge.ended { background:var(--bg-hover); color:var(--text-dim); }

    .action-btn { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--bg); color:var(--text-muted); cursor:pointer; transition:all 0.15s; }
    .action-btn:hover { border-color:var(--danger); color:var(--danger); background:rgba(212,74,74,0.08); }

    .pagination { display:flex; align-items:center; gap:0.35rem; padding:1rem 1.5rem; border-top:1px solid var(--border); justify-content:space-between; }
    .page-info { font-size:0.8rem; color:var(--text-muted); }
    .page-btns { display:flex; gap:0.35rem; }
    .page-btn { padding:0.35rem 0.7rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--bg); color:var(--text-muted); font-size:0.8rem; cursor:pointer; transition:all 0.15s; text-decoration:none; }
    .page-btn:hover, .page-btn.active { border-color:var(--accent); color:var(--accent); background:var(--accent-glow); text-decoration:none; }

    .sess-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
    .s-stat { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); padding:1.25rem 1.5rem; display:flex; align-items:center; gap:1rem; }
    .s-stat-icon { width:42px; height:42px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .s-stat-val { font-family:var(--font-display); font-size:1.6rem; font-weight:800; line-height:1; }
    .s-stat-lbl { font-size:0.75rem; color:var(--text-muted); font-weight:500; text-transform:uppercase; letter-spacing:0.04em; margin-top:2px; }

    .danger-zone { background:rgba(212,74,74,0.04); border:1px solid rgba(212,74,74,0.15); border-radius:var(--radius); padding:1.5rem; margin-bottom:1.5rem; }
    .danger-zone h3 { font-family:var(--font-display); font-size:0.95rem; font-weight:700; color:var(--danger); margin-bottom:0.75rem; display:flex; align-items:center; gap:0.5rem; }
    .danger-zone p  { font-size:0.82rem; color:var(--text-muted); margin-bottom:1rem; }
    .danger-actions { display:flex; gap:0.75rem; flex-wrap:wrap; }

    @media(max-width:900px){ .sess-stats{grid-template-columns:1fr 1fr;} }
    @media(max-width:600px){ .sess-stats{grid-template-columns:1fr;} }
  </style>
</head>
<body>
<div class="dash-layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon" style="color:var(--accent);"><?= icon('logo','',30) ?></div>
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
      <a href="sessions.php" class="nav-item active"><?= icon('activity') ?>Session Log</a>
      <div class="nav-section-label">System</div>
      <a href="permissions.php" class="nav-item"><?= icon('shield') ?>Permissions</a>
      <a href="database.php" class="nav-item"><?= icon('database') ?>Database</a>
      <a href="settings.php" class="nav-item"><?= icon('settings') ?>Settings</a>
      <div class="nav-section-label">Account</div>
      <a href="profile.php" class="nav-item"><?= icon('profile') ?>My Profile</a>
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
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <button class="mobile-menu-btn" id="menuToggle"><?= icon('menu','',20) ?></button>
        <span class="topbar-title"><?= e($page_title) ?></span>
      </div>
      <div class="topbar-actions">
        <span style="font-size:0.8rem;color:var(--text-muted);"><?= date('D, d M Y') ?></span>
        <button class="theme-toggle" id="themeToggle">
          <span class="icon-moon"><?= icon('moon','',16) ?></span>
          <span class="icon-sun"><?= icon('sun','',16) ?></span>
        </button>
        <div class="avatar avatar-admin" style="font-size:0.65rem;"><?= e(current_initials()) ?></div>
      </div>
    </header>

    <div class="dash-content">

      <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:1.25rem;"><?= icon('check-circle','',16) ?> <span><?= e($success) ?></span></div>
      <?php endif; ?>
      <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:1.25rem;"><?= icon('alert-circle','',16) ?> <span><?= e($error) ?></span></div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="sess-stats">
        <div class="s-stat">
          <div class="s-stat-icon" style="background:var(--accent-glow);color:var(--accent);"><?= icon('activity','',20) ?></div>
          <div><div class="s-stat-val"><?= $stat_total ?></div><div class="s-stat-lbl">Total Sessions</div></div>
        </div>
        <div class="s-stat">
          <div class="s-stat-icon" style="background:rgba(34,184,110,0.1);color:var(--success);"><?= icon('check-circle','',20) ?></div>
          <div><div class="s-stat-val"><?= $stat_active ?></div><div class="s-stat-lbl">Currently Active</div></div>
        </div>
        <div class="s-stat">
          <div class="s-stat-icon" style="background:rgba(46,143,196,0.1);color:var(--user-hue);"><?= icon('clock','',20) ?></div>
          <div><div class="s-stat-val"><?= $stat_today ?></div><div class="s-stat-lbl">Today</div></div>
        </div>
        <div class="s-stat">
          <div class="s-stat-icon" style="background:rgba(212,74,74,0.1);color:var(--admin-hue);"><?= icon('calendar','',20) ?></div>
          <div><div class="s-stat-val"><?= $stat_week ?></div><div class="s-stat-lbl">This Week</div></div>
        </div>
      </div>

      <!-- Danger zone -->
      <div class="danger-zone">
        <h3><?= icon('alert-circle','',16) ?> Log Management</h3>
        <p>Permanently remove session records from the database. These actions cannot be undone.</p>
        <div class="danger-actions">
          <form method="POST" onsubmit="return confirm('Clear sessions older than 30 days?')">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
            <input type="hidden" name="action" value="clear_old"/>
            <button type="submit" class="btn btn-outline" style="font-size:0.82rem;padding:0.5rem 1rem;border-color:rgba(212,74,74,0.3);color:var(--danger);">
              <?= icon('clock','',14) ?> Clear &gt;30 Days Old
            </button>
          </form>
          <form method="POST" onsubmit="return confirm('PERMANENTLY delete ALL session logs? This cannot be undone.')">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
            <input type="hidden" name="action" value="clear_all"/>
            <button type="submit" class="btn btn-danger" style="font-size:0.82rem;padding:0.5rem 1rem;">
              <?= icon('logout','',14) ?> Clear All Logs
            </button>
          </form>
        </div>
      </div>

      <!-- Table -->
      <div class="section-card">
        <form method="GET" action="sessions.php">
          <div class="table-toolbar">
            <div class="search-wrap">
              <?= icon('profile','',16) ?>
              <input class="search-input" type="text" name="q" placeholder="Search username or IP…" value="<?= e($search) ?>"/>
            </div>
            <select class="filter-select" name="role">
              <option value="">All Roles</option>
              <option value="admin" <?= $filter_role==='admin'?'selected':'' ?>>Admin</option>
              <option value="user"  <?= $filter_role==='user' ?'selected':'' ?>>Member</option>
            </select>
            <select class="filter-select" name="date">
              <option value="">All Time</option>
              <option value="today" <?= $filter_date==='today'?'selected':'' ?>>Today</option>
              <option value="week"  <?= $filter_date==='week' ?'selected':'' ?>>Last 7 Days</option>
              <option value="month" <?= $filter_date==='month'?'selected':'' ?>>Last 30 Days</option>
            </select>
            <button type="submit" class="btn btn-outline" style="padding:0.55rem 1rem;font-size:0.85rem;"><?= icon('activity','',15) ?> Filter</button>
            <?php if ($search || $filter_role || $filter_date): ?>
            <a href="sessions.php" class="btn btn-outline" style="padding:0.55rem 1rem;font-size:0.85rem;">Clear</a>
            <?php endif; ?>
          </div>
        </form>

        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Session ID</th>
                <th>User</th>
                <th>IP Address</th>
                <th>User Agent</th>
                <th>Login</th>
                <th>Logout</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php while ($s = $sessions->fetch_assoc()): ?>
              <?php
                $duration = '';
                if ($s['logout_at']) {
                    $diff = strtotime($s['logout_at']) - strtotime($s['login_at']);
                    $mins = floor($diff/60);
                    $duration = $mins < 60 ? "{$mins}m" : floor($mins/60)."h ".($mins%60)."m";
                }
              ?>
              <tr>
                <td><code class="sid-code"><?= e(substr($s['session_id'],0,12)) ?>…</code></td>
                <td>
                  <div class="user-cell">
                    <div class="u-avatar <?= e($s['role']) ?>"><?= e($s['avatar_initials']) ?></div>
                    <div>
                      <div class="u-name"><?= e($s['full_name']) ?></div>
                      <div class="u-email">@<?= e($s['username']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-family:monospace;font-size:0.82rem;"><?= e($s['ip_address'] ?: '—') ?></td>
                <td><div class="ua-text" title="<?= e($s['user_agent']) ?>"><?= e($s['user_agent'] ?: '—') ?></div></td>
                <td style="font-size:0.82rem;"><?= e(date('d M H:i', strtotime($s['login_at']))) ?></td>
                <td style="font-size:0.82rem;">
                  <?= $s['logout_at'] ? e(date('d M H:i', strtotime($s['logout_at']))) : '—' ?>
                </td>
                <td>
                  <?php if (!$s['logout_at']): ?>
                    <span class="status-dot active">Active</span>
                  <?php else: ?>
                    <span class="duration-badge ended"><?= $duration ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="POST" onsubmit="return confirm('Delete this session record?')">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
                    <input type="hidden" name="action" value="delete_session"/>
                    <input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"/>
                    <button type="submit" class="action-btn" title="Delete record"><?= icon('logout','',13) ?></button>
                  </form>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php if ($total_sessions === 0): ?>
              <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--text-dim);">No session records found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="pagination">
          <span class="page-info">Showing <?= min($offset+1,$total_sessions) ?>–<?= min($offset+$per_page,$total_sessions) ?> of <?= $total_sessions ?> sessions</span>
          <div class="page-btns">
            <?php
            $qs = http_build_query(['q'=>$search,'role'=>$filter_role,'date'=>$filter_date]);
            for ($p=1; $p<=$total_pages; $p++):
              if ($total_pages > 10 && abs($p-$page) > 2 && $p !== 1 && $p !== $total_pages) continue;
            ?>
            <a href="sessions.php?<?= $qs ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar    = document.getElementById('sidebar');
if (menuToggle) menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));

(function(){
  const html = document.documentElement;
  const btn  = document.getElementById('themeToggle');
  const saved = localStorage.getItem('theme') || 'light';
  if (saved === 'dark') html.setAttribute('data-theme','dark');
  btn.addEventListener('click', function(){
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
  });
})();
</script>
</body>
</html>
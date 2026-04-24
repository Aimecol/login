<?php
// ============================================================
//  admin/users.php  — Full User Management
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
        header('Location: users.php'); exit;
    }

    $action = $_POST['action'] ?? '';

    // Add user
    if ($action === 'add') {
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin','user']) ? $_POST['role'] : 'user';
        $password  = $_POST['password'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!$username || !$email || !$full_name || !$password) {
            set_flash('error', 'All fields are required.');
        } else {
            $parts    = explode(' ', $full_name);
            $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : substr($parts[0], 1, 1)));
            $hash     = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username,email,password,role,full_name,avatar_initials,is_active) VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param('ssssssi', $username, $email, $hash, $role, $full_name, $initials, $is_active);
            if ($stmt->execute()) {
                set_flash('success', "User \"" . $full_name . "\" created successfully.");
            } else {
                set_flash('error', 'Username or email already exists.');
            }
            $stmt->close();
        }
        header('Location: users.php'); exit;
    }

    // Edit user
    if ($action === 'edit') {
        $uid       = (int)($_POST['uid'] ?? 0);
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin','user']) ? $_POST['role'] : 'user';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $new_pass  = $_POST['new_password'] ?? '';

        if (!$uid || !$username || !$email || !$full_name) {
            set_flash('error', 'Required fields missing.');
        } else {
            $parts    = explode(' ', $full_name);
            $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : substr($parts[0], 1, 1)));

            if ($new_pass !== '') {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET username=?,email=?,full_name=?,avatar_initials=?,role=?,is_active=?,password=? WHERE id=?');
                $stmt->bind_param('sssssisi', $username, $email, $full_name, $initials, $role, $is_active, $hash, $uid);
            } else {
                $stmt = $db->prepare('UPDATE users SET username=?,email=?,full_name=?,avatar_initials=?,role=?,is_active=? WHERE id=?');
                $stmt->bind_param('ssssisi', $username, $email, $full_name, $initials, $role, $is_active, $uid);
            }
            if ($stmt->execute()) {
                set_flash('success', "User updated successfully.");
            } else {
                set_flash('error', 'Update failed. Username or email may be taken.');
            }
            $stmt->close();
        }
        header('Location: users.php'); exit;
    }

    // Toggle active
    if ($action === 'toggle_active') {
        $uid = (int)($_POST['uid'] ?? 0);
        $db->query("UPDATE users SET is_active = 1 - is_active WHERE id = {$uid}");
        set_flash('success', 'User status updated.');
        header('Location: users.php'); exit;
    }

    // Delete
    if ($action === 'delete') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid === current_user_id()) {
            set_flash('error', 'You cannot delete your own account.');
        } else {
            $db->query("DELETE FROM users WHERE id = {$uid}");
            set_flash('success', 'User deleted.');
        }
        header('Location: users.php'); exit;
    }
}

// ── Fetch data ───────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filter_role   = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(username LIKE ? OR email LIKE ? OR full_name LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s]);
    $types .= 'sss';
}
if ($filter_role !== '') {
    $where[] = 'role = ?';
    $params[] = $filter_role;
    $types .= 's';
}
if ($filter_status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($filter_status === 'inactive') {
    $where[] = 'is_active = 0';
}

$where_sql = implode(' AND ', $where);

// Count
$count_stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM users WHERE {$where_sql}");
if ($params) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_users = (int)$count_stmt->get_result()->fetch_assoc()['cnt'];
$count_stmt->close();
$total_pages = max(1, ceil($total_users / $per_page));

// Fetch page
$list_stmt = $db->prepare("SELECT id,username,email,role,full_name,avatar_initials,is_active,last_login,created_at FROM users WHERE {$where_sql} ORDER BY id ASC LIMIT ? OFFSET ?");
$list_params = array_merge($params, [$per_page, $offset]);
$list_types  = $types . 'ii';
$list_stmt->bind_param($list_types, ...$list_params);
$list_stmt->execute();
$users = $list_stmt->get_result();
$list_stmt->close();

// Stats
$res_stats = $db->query('SELECT role, COUNT(*) as c, SUM(is_active) as active FROM users GROUP BY role');
$stats = ['admin' => ['total'=>0,'active'=>0], 'user' => ['total'=>0,'active'=>0]];
while ($r = $res_stats->fetch_assoc()) {
    $stats[$r['role']] = ['total' => (int)$r['c'], 'active' => (int)$r['active']];
}
$total_all    = $stats['admin']['total'] + $stats['user']['total'];
$total_active = $stats['admin']['active'] + $stats['user']['active'];

$page_title = 'User Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — Login System</title>
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    /* ── Modal ───────────────────────────────────────────── */
    .modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.55);
      backdrop-filter: blur(4px);
      z-index: 500;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .modal-backdrop.open { display: flex; }
    .modal {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 2rem;
      width: 100%;
      max-width: 520px;
      box-shadow: 0 24px 60px rgba(0,0,0,0.3);
      position: relative;
      max-height: 90vh;
      overflow-y: auto;
    }
    .modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }
    .modal-title {
      font-family: var(--font-display);
      font-size: 1.15rem;
      font-weight: 700;
    }
    .modal-close {
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-dim);
      padding: 4px;
      border-radius: var(--radius-sm);
      transition: color 0.2s;
    }
    .modal-close:hover { color: var(--text); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label {
      display: block;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--text-muted);
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 0.4rem;
    }
    .form-control {
      width: 100%;
      padding: 0.65rem 0.85rem;
      background: var(--bg);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-sm);
      color: var(--text);
      font-family: var(--font-body);
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-control:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-glow);
    }
    .form-control option { background: var(--bg-card); }
    .toggle-row {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.65rem 0;
    }
    .toggle-switch {
      position: relative;
      width: 38px;
      height: 22px;
      flex-shrink: 0;
    }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider {
      position: absolute;
      inset: 0;
      background: var(--border);
      border-radius: 22px;
      cursor: pointer;
      transition: background 0.2s;
    }
    .toggle-slider::before {
      content: '';
      position: absolute;
      width: 16px; height: 16px;
      left: 3px; top: 3px;
      background: #fff;
      border-radius: 50%;
      transition: transform 0.2s;
    }
    .toggle-switch input:checked + .toggle-slider { background: var(--success); }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(16px); }
    .toggle-label { font-size: 0.88rem; color: var(--text-muted); }

    /* ── Table extras ────────────────────────────────────── */
    .table-toolbar {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-wrap: wrap;
      padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--border);
      background: var(--bg-card);
    }
    .search-wrap {
      position: relative;
      flex: 1;
      min-width: 200px;
    }
    .search-wrap svg {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-dim);
      pointer-events: none;
    }
    .search-input {
      width: 100%;
      padding: 0.6rem 0.85rem 0.6rem 2.4rem;
      background: var(--bg);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-sm);
      color: var(--text);
      font-family: var(--font-body);
      font-size: 0.88rem;
      outline: none;
    }
    .search-input:focus { border-color: var(--accent); }
    .filter-select {
      padding: 0.58rem 0.85rem;
      background: var(--bg);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-sm);
      color: var(--text-muted);
      font-family: var(--font-body);
      font-size: 0.85rem;
      cursor: pointer;
    }
    .filter-select:focus { outline: none; border-color: var(--accent); }

    /* Action buttons in table */
    .action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 30px; height: 30px;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
      background: var(--bg);
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.15s;
      font-size: 0;
    }
    .action-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-glow); }
    .action-btn.danger:hover { border-color: var(--danger); color: var(--danger); background: rgba(212,74,74,0.08); }

    /* Pagination */
    .pagination {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      padding: 1rem 1.5rem;
      border-top: 1px solid var(--border);
      justify-content: space-between;
    }
    .page-info { font-size: 0.8rem; color: var(--text-muted); }
    .page-btns { display: flex; gap: 0.35rem; }
    .page-btn {
      padding: 0.35rem 0.7rem;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
      background: var(--bg);
      color: var(--text-muted);
      font-size: 0.8rem;
      cursor: pointer;
      transition: all 0.15s;
    }
    .page-btn:hover, .page-btn.active {
      border-color: var(--accent);
      color: var(--accent);
      background: var(--accent-glow);
    }
    .page-btn:disabled { opacity: 0.4; cursor: default; }

    /* User avatar in table */
    .user-cell { display: flex; align-items: center; gap: 0.65rem; }
    .u-avatar {
      width: 32px; height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-display);
      font-size: 0.65rem;
      font-weight: 700;
      flex-shrink: 0;
    }
    .u-avatar.admin { background: rgba(212,74,74,0.1); color: var(--admin-hue); border: 1px solid rgba(212,74,74,0.2); }
    .u-avatar.user  { background: rgba(46,143,196,0.1); color: var(--user-hue);  border: 1px solid rgba(46,143,196,0.2); }
    .u-name { font-weight: 600; color: var(--text); font-size: 0.88rem; }
    .u-email { font-size: 0.75rem; color: var(--text-dim); }

    /* Stats strip */
    .user-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .u-stat {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .u-stat-icon {
      width: 42px; height: 42px;
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .u-stat-val { font-family: var(--font-display); font-size: 1.6rem; font-weight: 800; line-height: 1; }
    .u-stat-lbl { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 2px; }

    @media(max-width:900px) { .user-stats { grid-template-columns: 1fr 1fr; } .form-row { grid-template-columns: 1fr; } }
    @media(max-width:600px) { .user-stats { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="dash-layout">

  <!-- Sidebar -->
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
      <a href="users.php" class="nav-item active"><?= icon('users') ?>Users</a>
      <a href="sessions.php" class="nav-item"><?= icon('activity') ?>Session Log</a>
      <a href="messages.php" class="nav-item"><?= icon('mail') ?>Messages</a>
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

  <!-- Main -->
  <div class="dash-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <button class="mobile-menu-btn" id="menuToggle" aria-label="Toggle menu"><?= icon('menu','',20) ?></button>
        <span class="topbar-title"><?= e($page_title) ?></span>
      </div>
      <div class="topbar-actions">
        <span style="font-size:0.8rem;color:var(--text-muted);"><?= date('D, d M Y') ?></span>
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
          <span class="icon-moon"><?= icon('moon','',16) ?></span>
          <span class="icon-sun"><?= icon('sun','',16) ?></span>
        </button>
        <button class="btn btn-primary" style="font-size:0.85rem;padding:0.55rem 1rem;" onclick="openModal('addModal')">
          <?= icon('profile','',16) ?> Add User
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
      <div class="user-stats">
        <div class="u-stat">
          <div class="u-stat-icon" style="background:var(--accent-glow);color:var(--accent);"><?= icon('users','',20) ?></div>
          <div><div class="u-stat-val"><?= $total_all ?></div><div class="u-stat-lbl">Total Users</div></div>
        </div>
        <div class="u-stat">
          <div class="u-stat-icon" style="background:rgba(34,184,110,0.1);color:var(--success);"><?= icon('check-circle','',20) ?></div>
          <div><div class="u-stat-val"><?= $total_active ?></div><div class="u-stat-lbl">Active</div></div>
        </div>
        <div class="u-stat">
          <div class="u-stat-icon" style="background:rgba(212,74,74,0.1);color:var(--admin-hue);"><?= icon('shield','',20) ?></div>
          <div><div class="u-stat-val"><?= $stats['admin']['total'] ?></div><div class="u-stat-lbl">Admins</div></div>
        </div>
        <div class="u-stat">
          <div class="u-stat-icon" style="background:rgba(46,143,196,0.1);color:var(--user-hue);"><?= icon('profile','',20) ?></div>
          <div><div class="u-stat-val"><?= $stats['user']['total'] ?></div><div class="u-stat-lbl">Members</div></div>
        </div>
      </div>

      <!-- Table card -->
      <div class="section-card">
        <!-- Toolbar -->
        <form method="GET" action="users.php">
          <div class="table-toolbar">
            <div class="search-wrap">
              <?= icon('profile','',16) ?>
              <input class="search-input" type="text" name="q" placeholder="Search by name, username or email…" value="<?= e($search) ?>"/>
            </div>
            <select class="filter-select" name="role">
              <option value="">All Roles</option>
              <option value="admin" <?= $filter_role==='admin'?'selected':'' ?>>Admin</option>
              <option value="user"  <?= $filter_role==='user' ?'selected':'' ?>>Member</option>
            </select>
            <select class="filter-select" name="status">
              <option value="">All Status</option>
              <option value="active"   <?= $filter_status==='active'  ?'selected':'' ?>>Active</option>
              <option value="inactive" <?= $filter_status==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn btn-outline" style="padding:0.55rem 1rem;font-size:0.85rem;"><?= icon('activity','',15) ?> Filter</button>
            <?php if ($search || $filter_role || $filter_status): ?>
            <a href="users.php" class="btn btn-outline" style="padding:0.55rem 1rem;font-size:0.85rem;">Clear</a>
            <?php endif; ?>
          </div>
        </form>

        <!-- Table -->
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Joined</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $users->data_seek(0); while ($u = $users->fetch_assoc()): ?>
              <tr>
                <td style="color:var(--text-dim);font-size:0.8rem;"><?= e($u['id']) ?></td>
                <td>
                  <div class="user-cell">
                    <div class="u-avatar <?= e($u['role']) ?>"><?= e($u['avatar_initials']) ?></div>
                    <div>
                      <div class="u-name"><?= e($u['full_name']) ?></div>
                      <div class="u-email">@<?= e($u['username']) ?> · <?= e($u['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                <td>
                  <?php if ((int)$u['is_active']): ?>
                    <span class="status-dot active">Active</span>
                  <?php else: ?>
                    <span class="status-dot inactive">Inactive</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:0.82rem;">
                  <?= $u['last_login'] ? e(date('d M Y H:i', strtotime($u['last_login']))) : '<span style="color:var(--text-dim)">Never</span>' ?>
                </td>
                <td style="font-size:0.82rem;color:var(--text-muted);"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                <td>
                  <div style="display:flex;gap:0.35rem;justify-content:flex-end;">
                    <button class="action-btn" title="Edit user" onclick='openEdit(<?= json_encode($u) ?>)'><?= icon('settings','',14) ?></button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle active status?')">
                      <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
                      <input type="hidden" name="action" value="toggle_active"/>
                      <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>"/>
                      <button type="submit" class="action-btn" title="Toggle status"><?= icon('activity','',14) ?></button>
                    </form>
                    <?php if ((int)$u['id'] !== current_user_id()): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user permanently?')">
                      <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
                      <input type="hidden" name="action" value="delete"/>
                      <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>"/>
                      <button type="submit" class="action-btn danger" title="Delete user"><?= icon('logout','',14) ?></button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php if ($total_users === 0): ?>
              <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--text-dim);">No users found matching your filters.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
          <span class="page-info">Showing <?= min($offset+1,$total_users) ?>–<?= min($offset+$per_page,$total_users) ?> of <?= $total_users ?> users</span>
          <div class="page-btns">
            <?php
            $qs = http_build_query(['q'=>$search,'role'=>$filter_role,'status'=>$filter_status]);
            for ($p = 1; $p <= $total_pages; $p++):
            ?>
            <a href="users.php?<?= $qs ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        </div>
      </div><!-- /section-card -->

    </div><!-- /dash-content -->
  </div><!-- /dash-main -->
</div><!-- /dash-layout -->

<!-- ADD USER MODAL -->
<div class="modal-backdrop" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add New User</span>
      <button class="modal-close" onclick="closeModal('addModal')"><?= icon('alert-circle','',20) ?></button>
    </div>
    <form method="POST" action="users.php">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
      <input type="hidden" name="action" value="add"/>
      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <input class="form-control" type="text" name="full_name" required placeholder="Aimecol Mazimpaka"/>
        </div>
        <div class="form-group">
          <label>Username</label>
          <input class="form-control" type="text" name="username" required placeholder="Aimecol"/>
        </div>
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input class="form-control" type="email" name="email" required placeholder="aimecol@example.com"/>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Role</label>
          <select class="form-control" name="role">
            <option value="user">Member</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input class="form-control" type="password" name="password" required placeholder="Min 3 chars"/>
        </div>
      </div>
      <div class="form-group">
        <div class="toggle-row">
          <label class="toggle-switch">
            <input type="checkbox" name="is_active" checked/>
            <span class="toggle-slider"></span>
          </label>
          <span class="toggle-label">Account active</span>
        </div>
      </div>
      <div style="display:flex;gap:0.75rem;margin-top:0.5rem;">
        <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1;"><?= icon('profile','',16) ?> Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal-backdrop" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit User</span>
      <button class="modal-close" onclick="closeModal('editModal')"><?= icon('alert-circle','',20) ?></button>
    </div>
    <form method="POST" action="users.php">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
      <input type="hidden" name="action" value="edit"/>
      <input type="hidden" name="uid" id="edit_uid"/>
      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <input class="form-control" type="text" name="full_name" id="edit_full_name" required/>
        </div>
        <div class="form-group">
          <label>Username</label>
          <input class="form-control" type="text" name="username" id="edit_username" required/>
        </div>
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input class="form-control" type="email" name="email" id="edit_email" required/>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Role</label>
          <select class="form-control" name="role" id="edit_role">
            <option value="user">Member</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
        <div class="form-group">
          <label>New Password <span style="font-size:0.7rem;opacity:0.6;">(leave blank to keep)</span></label>
          <input class="form-control" type="password" name="new_password" placeholder="Leave blank to keep"/>
        </div>
      </div>
      <div class="form-group">
        <div class="toggle-row">
          <label class="toggle-switch">
            <input type="checkbox" name="is_active" id="edit_is_active"/>
            <span class="toggle-slider"></span>
          </label>
          <span class="toggle-label">Account active</span>
        </div>
      </div>
      <div style="display:flex;gap:0.75rem;margin-top:0.5rem;">
        <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1;"><?= icon('settings','',16) ?> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openEdit(u) {
  document.getElementById('edit_uid').value       = u.id;
  document.getElementById('edit_full_name').value = u.full_name;
  document.getElementById('edit_username').value  = u.username;
  document.getElementById('edit_email').value     = u.email;
  document.getElementById('edit_role').value      = u.role;
  document.getElementById('edit_is_active').checked = u.is_active == 1;
  openModal('editModal');
}

// Close modal on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(b => {
  b.addEventListener('click', e => { if (e.target === b) b.classList.remove('open'); });
});

// Sidebar toggle
const menuToggle = document.getElementById('menuToggle');
const sidebar    = document.getElementById('sidebar');
if (menuToggle) menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));

// Theme toggle
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
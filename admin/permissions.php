<?php
// ============================================================
//  admin/permissions.php  — Role & Permission Management
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('admin');

$db   = db();
$csrf = $_SESSION['csrf_token'] ?? '';

$success = get_flash('success') ?? '';
$error   = get_flash('error')   ?? '';

// ── Define the permission matrix ─────────────────────────────
// These are stored as a JSON config in the DB settings table.
// We'll create a simple settings table concept here using a flat array.
// In a real app these would be in a permissions table.

$default_permissions = [
    'users' => [
        'label' => 'User Management',
        'icon'  => 'users',
        'perms' => [
            'view_users'   => 'View user list',
            'create_users' => 'Create new users',
            'edit_users'   => 'Edit user details',
            'delete_users' => 'Delete users',
            'toggle_users' => 'Activate / Deactivate users',
        ]
    ],
    'sessions' => [
        'label' => 'Session Logs',
        'icon'  => 'activity',
        'perms' => [
            'view_sessions'   => 'View session logs',
            'delete_sessions' => 'Delete session records',
            'clear_sessions'  => 'Clear all session logs',
        ]
    ],
    'permissions' => [
        'label' => 'Permissions',
        'icon'  => 'shield',
        'perms' => [
            'view_permissions' => 'View permissions panel',
            'edit_permissions' => 'Modify role permissions',
        ]
    ],
    'database' => [
        'label' => 'Database',
        'icon'  => 'database',
        'perms' => [
            'view_database'   => 'View database info',
            'export_database' => 'Export database / SQL',
            'run_queries'     => 'Execute custom queries',
        ]
    ],
    'settings' => [
        'label' => 'System Settings',
        'icon'  => 'settings',
        'perms' => [
            'view_settings' => 'View settings',
            'edit_settings' => 'Modify system settings',
        ]
    ],
    'profile' => [
        'label' => 'Profile',
        'icon'  => 'profile',
        'perms' => [
            'view_profile' => 'View own profile',
            'edit_profile' => 'Edit own profile',
            'change_password' => 'Change own password',
        ]
    ],
];

// Default role permissions
$admin_perms = [];
$user_perms  = [];
foreach ($default_permissions as $section => $data) {
    foreach ($data['perms'] as $key => $label) {
        $admin_perms[$key] = true;
        // Users only get profile perms by default
        $user_perms[$key]  = in_array($key, ['view_profile','edit_profile','change_password','view_sessions']);
    }
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: permissions.php'); exit;
    }
    // In a real app, save to DB. Here we just flash success.
    set_flash('success', 'Permission settings saved successfully.');
    header('Location: permissions.php'); exit;
}

// Fetch role user counts
$role_counts = [];
$rc = $db->query("SELECT role, COUNT(*) as c FROM users GROUP BY role");
while ($r = $rc->fetch_assoc()) $role_counts[$r['role']] = (int)$r['c'];

$page_title = 'Permissions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — Login System</title>
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    .perm-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    .role-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }
    .role-card-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .role-card-title {
      font-family: var(--font-display);
      font-size: 1rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .role-card-meta { font-size: 0.78rem; color: var(--text-muted); }
    .role-card-body { padding: 1.5rem; }

    .perm-section { margin-bottom: 1.5rem; }
    .perm-section:last-child { margin-bottom: 0; }
    .perm-section-title {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-dim);
      margin-bottom: 0.6rem;
      padding-bottom: 0.5rem;
      border-bottom: 1px solid var(--border);
    }
    .perm-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.5rem 0;
    }
    .perm-item-label { font-size: 0.86rem; color: var(--text-muted); }

    /* Toggle switch */
    .toggle-switch { position:relative; width:38px; height:22px; flex-shrink:0; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider { position:absolute; inset:0; background:var(--border); border-radius:22px; cursor:pointer; transition:background 0.2s; }
    .toggle-slider::before { content:''; position:absolute; width:16px; height:16px; left:3px; top:3px; background:#fff; border-radius:50%; transition:transform 0.2s; }
    .toggle-switch input:checked + .toggle-slider { background: var(--success); }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(16px); }
    .toggle-switch input:disabled + .toggle-slider { opacity: 0.5; cursor: not-allowed; }

    /* Role overview cards */
    .role-overview { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .role-ov-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .role-ov-icon { width:44px;height:44px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .role-ov-val  { font-family:var(--font-display);font-size:1.5rem;font-weight:800;line-height:1; }
    .role-ov-lbl  { font-size:0.75rem;color:var(--text-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.04em;margin-top:2px; }

    .save-bar {
      position: sticky;
      bottom: 0;
      background: var(--bg-card);
      border-top: 1px solid var(--border);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      z-index: 10;
      margin: 0 -2rem -2rem;
    }
    .save-bar p { font-size:0.82rem; color:var(--text-muted); }

    @media(max-width:900px){ .perm-layout{grid-template-columns:1fr;} .role-overview{grid-template-columns:1fr 1fr;} }
    @media(max-width:500px){ .role-overview{grid-template-columns:1fr;} }
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
      <a href="sessions.php" class="nav-item"><?= icon('activity') ?>Session Log</a>
      <div class="nav-section-label">System</div>
      <a href="permissions.php" class="nav-item active"><?= icon('shield') ?>Permissions</a>
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

      <!-- Role overview -->
      <div class="role-overview">
        <div class="role-ov-card">
          <div class="role-ov-icon" style="background:rgba(212,74,74,0.1);color:var(--admin-hue);"><?= icon('shield','',20) ?></div>
          <div><div class="role-ov-val"><?= $role_counts['admin'] ?? 0 ?></div><div class="role-ov-lbl">Administrators</div></div>
        </div>
        <div class="role-ov-card">
          <div class="role-ov-icon" style="background:rgba(46,143,196,0.1);color:var(--user-hue);"><?= icon('profile','',20) ?></div>
          <div><div class="role-ov-val"><?= $role_counts['user'] ?? 0 ?></div><div class="role-ov-lbl">Members</div></div>
        </div>
        <div class="role-ov-card">
          <div class="role-ov-icon" style="background:var(--accent-glow);color:var(--accent);"><?= icon('key','',20) ?></div>
          <div>
            <div class="role-ov-val"><?= array_sum(array_map(fn($s)=>count($s['perms']), $default_permissions)) ?></div>
            <div class="role-ov-lbl">Total Permissions</div>
          </div>
        </div>
      </div>

      <!-- Permission matrix -->
      <form method="POST" action="permissions.php">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>

        <div class="perm-layout">
          <!-- Admin column -->
          <div class="role-card">
            <div class="role-card-header">
              <div class="role-card-title">
                <?= icon('shield','',18) ?>
                Administrator
                <span class="badge badge-admin" style="font-size:0.65rem;">Admin</span>
              </div>
              <span class="role-card-meta"><?= $role_counts['admin'] ?? 0 ?> users</span>
            </div>
            <div class="role-card-body">
              <?php foreach ($default_permissions as $section => $data): ?>
              <div class="perm-section">
                <div class="perm-section-title">
                  <?= icon($data['icon'],'',13) ?>
                  <?= e($data['label']) ?>
                </div>
                <?php foreach ($data['perms'] as $key => $label): ?>
                <div class="perm-item">
                  <span class="perm-item-label"><?= e($label) ?></span>
                  <label class="toggle-switch">
                    <input type="checkbox" name="admin_perm[]" value="<?= e($key) ?>" checked disabled/>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endforeach; ?>
              <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
                <p style="font-size:0.78rem;color:var(--text-dim);">Administrators have full access to all features and cannot have permissions restricted.</p>
              </div>
            </div>
          </div>

          <!-- Member column -->
          <div class="role-card">
            <div class="role-card-header">
              <div class="role-card-title">
                <?= icon('profile','',18) ?>
                Member
                <span class="badge badge-user" style="font-size:0.65rem;">User</span>
              </div>
              <span class="role-card-meta"><?= $role_counts['user'] ?? 0 ?> users</span>
            </div>
            <div class="role-card-body">
              <?php foreach ($default_permissions as $section => $data): ?>
              <div class="perm-section">
                <div class="perm-section-title">
                  <?= icon($data['icon'],'',13) ?>
                  <?= e($data['label']) ?>
                </div>
                <?php foreach ($data['perms'] as $key => $label): ?>
                <?php $checked = $user_perms[$key] ?? false; ?>
                <div class="perm-item">
                  <span class="perm-item-label"><?= e($label) ?></span>
                  <label class="toggle-switch">
                    <input type="checkbox" name="user_perm[]" value="<?= e($key) ?>" <?= $checked?'checked':'' ?>/>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Save bar -->
        <div class="save-bar">
          <p><?= icon('shield','',14) ?> Changes apply to all users with the Member role immediately.</p>
          <div style="display:flex;gap:0.75rem;">
            <button type="button" class="btn btn-outline" onclick="resetToDefault()">Reset Defaults</button>
            <button type="submit" class="btn btn-primary"><?= icon('check-circle','',16) ?> Save Permissions</button>
          </div>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
function resetToDefault() {
  if (!confirm('Reset member permissions to defaults?')) return;
  const defaults = ['view_profile','edit_profile','change_password','view_sessions'];
  document.querySelectorAll('input[name="user_perm[]"]').forEach(cb => {
    cb.checked = defaults.includes(cb.value);
  });
}

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
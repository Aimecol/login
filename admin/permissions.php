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

// ── Permission matrix definition (canonical source of truth) ─
$matrix = [
    'users' => [
        'label' => 'User Management',
        'icon'  => 'users',
        'perms' => [
            'view_users'   => 'View user list',
            'create_users' => 'Create new users',
            'edit_users'   => 'Edit user details',
            'delete_users' => 'Delete users',
            'toggle_users' => 'Activate / Deactivate users',
        ],
    ],
    'sessions' => [
        'label' => 'Session Logs',
        'icon'  => 'activity',
        'perms' => [
            'view_sessions'   => 'View session logs',
            'delete_sessions' => 'Delete session records',
            'clear_sessions'  => 'Clear all session logs',
        ],
    ],
    'permissions' => [
        'label' => 'Permissions',
        'icon'  => 'shield',
        'perms' => [
            'view_permissions' => 'View permissions panel',
            'edit_permissions' => 'Modify role permissions',
        ],
    ],
    'database' => [
        'label' => 'Database',
        'icon'  => 'database',
        'perms' => [
            'view_database'   => 'View database info & table stats',
            'export_database' => 'Export database / SQL schema',
            'run_queries'     => 'Execute custom SQL queries',
        ],
    ],
    'settings' => [
        'label' => 'System Settings',
        'icon'  => 'settings',
        'perms' => [
            'view_settings' => 'View settings page',
            'edit_settings' => 'Modify system settings',
        ],
    ],
    'profile' => [
        'label' => 'Profile',
        'icon'  => 'profile',
        'perms' => [
            'view_profile'    => 'View own profile',
            'edit_profile'    => 'Edit own profile',
            'change_password' => 'Change own password',
        ],
    ],
    'messages' => [
        'label' => 'Messages',
        'icon'  => 'mail',
        'perms' => [
            'view_messages'   => 'View inbox and sent messages',
            'send_messages'   => 'Send messages to other users',
            'delete_messages' => 'Delete own messages',
        ],
    ],
    'notifications' => [
        'label' => 'Notifications',
        'icon'  => 'bell',
        'perms' => [
            'view_notifications'     => 'View own notifications',
            'manage_notifications'   => 'Mark read / delete notifications',
            'broadcast_notification' => 'Send system-wide notifications',
        ],
    ],
];

// ── Default user grants ───────────────────────────────────────
$default_user_grants = [
    'view_profile','edit_profile','change_password',
    'view_sessions','view_messages','send_messages','delete_messages',
    'view_notifications','manage_notifications',
];

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: permissions.php'); exit;
    }

    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $granted = $_POST['user_perm'] ?? [];

        // Check if permissions table exists
        $tbl_check = $db->query("SHOW TABLES LIKE 'permissions'");
        if ($tbl_check && $tbl_check->num_rows > 0) {
            // Update each user permission row
            foreach ($matrix as $section => $data) {
                foreach ($data['perms'] as $key => $label) {
                    $is_granted = in_array($key, $granted) ? 1 : 0;
                    $stmt = $db->prepare(
                        "INSERT INTO permissions (role, permission_key, section, section_label, perm_label, is_granted)
                         VALUES ('user', ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE is_granted = VALUES(is_granted), updated_at = NOW()"
                    );
                    $stmt->bind_param('ssssi', $key, $section, $data['label'], $label, $is_granted);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            set_flash('success', 'Member permissions updated successfully.');
        } else {
            set_flash('success', 'Permissions saved (run database.sql to persist across sessions).');
        }
        header('Location: permissions.php'); exit;
    }

    if ($action === 'reset') {
        $tbl_check = $db->query("SHOW TABLES LIKE 'permissions'");
        if ($tbl_check && $tbl_check->num_rows > 0) {
            foreach ($matrix as $section => $data) {
                foreach ($data['perms'] as $key => $label) {
                    $is_granted = in_array($key, $default_user_grants) ? 1 : 0;
                    $stmt = $db->prepare(
                        "INSERT INTO permissions (role, permission_key, section, section_label, perm_label, is_granted)
                         VALUES ('user', ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE is_granted = VALUES(is_granted), updated_at = NOW()"
                    );
                    $stmt->bind_param('ssssi', $key, $section, $data['label'], $label, $is_granted);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        set_flash('success', 'Member permissions reset to defaults.');
        header('Location: permissions.php'); exit;
    }
}

// ── Load current user grants from DB ─────────────────────────
$user_grants = [];
$tbl_check = $db->query("SHOW TABLES LIKE 'permissions'");
if ($tbl_check && $tbl_check->num_rows > 0) {
    $res = $db->query("SELECT permission_key, is_granted FROM permissions WHERE role = 'user'");
    while ($r = $res->fetch_assoc()) {
        $user_grants[$r['permission_key']] = (bool)$r['is_granted'];
    }
} else {
    // Fall back to defaults if table doesn't exist yet
    foreach ($default_user_grants as $k) $user_grants[$k] = true;
}

// ── Role counts ───────────────────────────────────────────────
$role_counts = [];
$rc = $db->query("SELECT role, COUNT(*) as c FROM users GROUP BY role");
while ($r = $rc->fetch_assoc()) $role_counts[$r['role']] = (int)$r['c'];

$total_perms     = array_sum(array_map(fn($s) => count($s['perms']), $matrix));
$user_granted_ct = count(array_filter($user_grants));

// ── Last updated ──────────────────────────────────────────────
$last_updated = null;
$tbl2 = $db->query("SHOW TABLES LIKE 'permissions'");
if ($tbl2 && $tbl2->num_rows > 0) {
    $lu = $db->query("SELECT MAX(updated_at) AS t FROM permissions WHERE role='user'");
    $last_updated = $lu ? $lu->fetch_assoc()['t'] : null;
}

$page_title = 'Permissions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — Login System</title>
  <link rel="shortcut icon" href="https://images.aimecol.com/uploads/large/logo_691c45e2513cc_large.jpg" type="image/x-icon">
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    /* ── Overview cards ────────────────────────────────────── */
    .perm-overview {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 1.75rem;
    }
    .pov-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      transition: border-color 0.2s, transform 0.2s;
    }
    .pov-card:hover { border-color: var(--border-light); transform: translateY(-1px); }
    .pov-icon {
      width: 44px; height: 44px;
      border-radius: var(--radius-sm);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .pov-icon svg { width: 20px; height: 20px; }
    .pov-value { font-family: var(--font-display); font-size: 1.6rem; font-weight: 800; line-height: 1; }
    .pov-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 3px; }
    .pov-sub   { font-size: 0.75rem; color: var(--text-dim); margin-top: 2px; }

    /* ── Matrix layout ─────────────────────────────────────── */
    .perm-layout {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      align-items: start;
    }

    /* ── Role cards ────────────────────────────────────────── */
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
      gap: 0.75rem;
    }
    .role-card-title {
      font-family: var(--font-display);
      font-size: 1rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .role-card-title svg { width: 18px; height: 18px; }
    .role-card-meta { font-size: 0.78rem; color: var(--text-muted); text-align: right; line-height: 1.4; }
    .role-card-body { padding: 1.25rem 1.5rem; }
    .role-card-note {
      margin-top: 1.25rem;
      padding: 0.85rem 1rem;
      background: var(--accent-glow);
      border: 1px solid rgba(232,148,14,0.18);
      border-radius: var(--radius-sm);
      font-size: 0.78rem;
      color: var(--text-muted);
      line-height: 1.6;
    }

    /* ── Permission sections ───────────────────────────────── */
    .perm-section { margin-bottom: 1.25rem; }
    .perm-section:last-of-type { margin-bottom: 0; }
    .perm-section-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-dim);
      margin-bottom: 0.5rem;
      padding-bottom: 0.5rem;
      border-bottom: 1px solid var(--border);
    }
    .perm-section-header svg { width: 13px; height: 13px; }

    .perm-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.55rem 0;
      gap: 1rem;
    }
    .perm-item-label { font-size: 0.86rem; color: var(--text-muted); line-height: 1.4; }

    /* ── Toggle switch ─────────────────────────────────────── */
    .toggle-switch { position: relative; width: 42px; height: 24px; flex-shrink: 0; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
    .toggle-slider {
      position: absolute; inset: 0;
      background: var(--border-light);
      border-radius: 24px;
      cursor: pointer;
      transition: background 0.2s;
    }
    .toggle-slider::before {
      content: '';
      position: absolute;
      width: 18px; height: 18px;
      left: 3px; top: 3px;
      background: #fff;
      border-radius: 50%;
      transition: transform 0.2s;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .toggle-switch input:checked + .toggle-slider { background: var(--success); }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(18px); }
    .toggle-switch input:disabled + .toggle-slider { opacity: 0.55; cursor: not-allowed; }
    .toggle-switch input:focus-visible + .toggle-slider { outline: 2px solid var(--accent); outline-offset: 2px; }

    /* ── Progress bar ──────────────────────────────────────── */
    .perm-progress {
      height: 4px;
      background: var(--border);
      border-radius: 4px;
      overflow: hidden;
      margin-top: 0.5rem;
    }
    .perm-progress-fill {
      height: 100%;
      background: var(--success);
      border-radius: 4px;
      transition: width 0.4s ease;
    }

    /* ── Grant all / deny all quick actions ────────────────── */
    .quick-actions {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--border);
    }
    .quick-btn {
      font-size: 0.75rem;
      padding: 0.3rem 0.75rem;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border-light);
      background: transparent;
      color: var(--text-muted);
      cursor: pointer;
      font-family: var(--font-body);
      font-weight: 500;
      transition: all 0.15s;
    }
    .quick-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-glow); }
    .quick-btn.deny:hover { border-color: var(--danger); color: var(--danger); background: rgba(212,74,74,0.06); }

    /* ── Sticky save bar ───────────────────────────────────── */
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
      z-index: 20;
      margin: 1.5rem -2rem -2rem;
      box-shadow: 0 -4px 20px rgba(0,0,0,0.06);
    }
    .save-bar-info { font-size: 0.82rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; }
    .save-bar-info svg { width: 14px; height: 14px; color: var(--accent); flex-shrink: 0; }
    .save-bar-actions { display: flex; gap: 0.75rem; align-items: center; }

    /* ── Audit trail ───────────────────────────────────────── */
    .audit-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.72rem;
      color: var(--text-dim);
      background: var(--bg-hover);
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: 2px 8px;
    }
    .audit-chip svg { width: 11px; height: 11px; }

    /* ── Responsive ────────────────────────────────────────── */
    @media (max-width: 960px) {
      .perm-overview { grid-template-columns: 1fr 1fr; }
      .perm-layout   { grid-template-columns: 1fr; }
    }
    @media (max-width: 500px) {
      .perm-overview { grid-template-columns: 1fr 1fr; }
      .save-bar { padding: 0.85rem 1.25rem; }
    }
  </style>
</head>
<body>
<div class="dash-layout">

  <!-- ── Sidebar ──────────────────────────────────────────── -->
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
      <a href="users.php"    class="nav-item"><?= icon('users') ?>Users</a>
      <a href="sessions.php" class="nav-item"><?= icon('activity') ?>Session Log</a>
      <a href="messages.php" class="nav-item"><?= icon('mail') ?>Messages</a>
      <div class="nav-section-label">System</div>
      <a href="permissions.php" class="nav-item active"><?= icon('shield') ?>Permissions</a>
      <a href="database.php"    class="nav-item"><?= icon('database') ?>Database</a>
      <a href="settings.php"    class="nav-item"><?= icon('settings') ?>Settings</a>
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

  <!-- ── Main ─────────────────────────────────────────────── -->
  <div class="dash-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <button class="mobile-menu-btn" id="menuToggle"><?= icon('menu','',20) ?></button>
        <span class="topbar-title"><?= e($page_title) ?></span>
      </div>
      <div class="topbar-actions">
        <?php if ($last_updated): ?>
        <span class="audit-chip"><?= icon('clock','',11) ?> Updated <?= date('d M, H:i', strtotime($last_updated)) ?></span>
        <?php endif; ?>
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
      <div class="alert alert-success" style="margin-bottom:1.25rem;">
        <?= icon('check-circle','',16) ?> <span><?= e($success) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:1.25rem;">
        <?= icon('alert-circle','',16) ?> <span><?= e($error) ?></span>
      </div>
      <?php endif; ?>

      <!-- Overview cards -->
      <div class="perm-overview">
        <div class="pov-card">
          <div class="pov-icon" style="background:rgba(212,74,74,0.1);color:var(--admin-hue);">
            <?= icon('shield','',20) ?>
          </div>
          <div>
            <div class="pov-value"><?= $role_counts['admin'] ?? 0 ?></div>
            <div class="pov-label">Administrators</div>
            <div class="pov-sub">Full access</div>
          </div>
        </div>
        <div class="pov-card">
          <div class="pov-icon" style="background:rgba(46,143,196,0.1);color:var(--user-hue);">
            <?= icon('profile','',20) ?>
          </div>
          <div>
            <div class="pov-value"><?= $role_counts['user'] ?? 0 ?></div>
            <div class="pov-label">Members</div>
            <div class="pov-sub">Restricted access</div>
          </div>
        </div>
        <div class="pov-card">
          <div class="pov-icon" style="background:var(--accent-glow);color:var(--accent);">
            <?= icon('key','',20) ?>
          </div>
          <div>
            <div class="pov-value"><?= $total_perms ?></div>
            <div class="pov-label">Total Permissions</div>
            <div class="pov-sub"><?= count($matrix) ?> sections</div>
          </div>
        </div>
        <div class="pov-card">
          <div class="pov-icon" style="background:rgba(34,184,110,0.1);color:var(--success);">
            <?= icon('check-circle','',20) ?>
          </div>
          <div>
            <div class="pov-value"><?= $user_granted_ct ?></div>
            <div class="pov-label">Member Grants</div>
            <div class="pov-sub">of <?= $total_perms ?> total</div>
          </div>
        </div>
      </div>

      <!-- Permission matrix form -->
      <form method="POST" action="permissions.php" id="permForm">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
        <input type="hidden" name="action" value="save" id="formAction"/>

        <div class="perm-layout">

          <!-- ── Administrator column ───────────────────────── -->
          <div class="role-card">
            <div class="role-card-header">
              <div class="role-card-title">
                <?= icon('shield','',18) ?>
                Administrator
                <span class="badge badge-admin" style="font-size:0.65rem;margin-left:2px;">Admin</span>
              </div>
              <div class="role-card-meta">
                <strong><?= $role_counts['admin'] ?? 0 ?></strong> user<?= ($role_counts['admin'] ?? 0) !== 1 ? 's' : '' ?><br>
                <span style="color:var(--success);font-size:0.72rem;"><?= $total_perms ?> / <?= $total_perms ?> granted</span>
              </div>
            </div>
            <div class="role-card-body">
              <?php foreach ($matrix as $section => $data): ?>
              <div class="perm-section">
                <div class="perm-section-header">
                  <?= icon($data['icon'],'',13) ?>
                  <?= e($data['label']) ?>
                </div>
                <?php foreach ($data['perms'] as $key => $label): ?>
                <div class="perm-item">
                  <span class="perm-item-label"><?= e($label) ?></span>
                  <label class="toggle-switch" title="Admins always have this permission">
                    <input type="checkbox" checked disabled/>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endforeach; ?>
              <div class="role-card-note">
                <?= icon('shield','',14) ?>
                <strong>Full access.</strong> Administrator permissions are fixed and cannot be restricted. Admins always have access to all system features.
              </div>
            </div>
          </div>

          <!-- ── Member column ─────────────────────────────── -->
          <div class="role-card">
            <div class="role-card-header">
              <div class="role-card-title">
                <?= icon('profile','',18) ?>
                Member
                <span class="badge badge-user" style="font-size:0.65rem;margin-left:2px;">User</span>
              </div>
              <div class="role-card-meta">
                <strong><?= $role_counts['user'] ?? 0 ?></strong> user<?= ($role_counts['user'] ?? 0) !== 1 ? 's' : '' ?><br>
                <span id="grantCountLabel" style="color:var(--text-dim);font-size:0.72rem;"><?= $user_granted_ct ?> / <?= $total_perms ?> granted</span>
              </div>
            </div>
            <div class="role-card-body">

              <div class="quick-actions">
                <button type="button" class="quick-btn" onclick="toggleAll(true)">✓ Grant all</button>
                <button type="button" class="quick-btn deny" onclick="toggleAll(false)">✕ Deny all</button>
                <div style="flex:1;"></div>
                <div class="perm-progress" style="width:120px;align-self:center;">
                  <div class="perm-progress-fill" id="progressBar" style="width:<?= $total_perms > 0 ? round($user_granted_ct/$total_perms*100) : 0 ?>%"></div>
                </div>
              </div>

              <?php foreach ($matrix as $section => $data): ?>
              <div class="perm-section">
                <div class="perm-section-header">
                  <?= icon($data['icon'],'',13) ?>
                  <?= e($data['label']) ?>
                </div>
                <?php foreach ($data['perms'] as $key => $label): ?>
                <?php $checked = $user_grants[$key] ?? false; ?>
                <div class="perm-item">
                  <span class="perm-item-label"><?= e($label) ?></span>
                  <label class="toggle-switch">
                    <input type="checkbox" name="user_perm[]" value="<?= e($key) ?>"
                           class="user-perm-cb" <?= $checked ? 'checked' : '' ?>/>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endforeach; ?>

            </div>
          </div>

        </div><!-- /.perm-layout -->

        <!-- Save bar -->
        <div class="save-bar">
          <div class="save-bar-info">
            <?= icon('shield','',14) ?>
            Changes apply immediately to all users with the Member role.
          </div>
          <div class="save-bar-actions">
            <button type="button" class="btn btn-outline"
                    onclick="document.getElementById('formAction').value='reset';
                             if(confirm('Reset member permissions to defaults?')) document.getElementById('permForm').submit();">
              Reset Defaults
            </button>
            <button type="submit" class="btn btn-primary">
              <?= icon('check-circle','',16) ?> Save Permissions
            </button>
          </div>
        </div>

      </form>
    </div><!-- /.dash-content -->
  </div><!-- /.dash-main -->
</div><!-- /.dash-layout -->

<script>
// ── Live counter + progress bar ────────────────────────────
const cbs    = document.querySelectorAll('.user-perm-cb');
const label  = document.getElementById('grantCountLabel');
const bar    = document.getElementById('progressBar');
const total  = cbs.length;

function updateCounter() {
  const granted = [...cbs].filter(cb => cb.checked).length;
  if (label) label.textContent = granted + ' / ' + total + ' granted';
  if (bar)   bar.style.width   = (total > 0 ? Math.round(granted / total * 100) : 0) + '%';
}

cbs.forEach(cb => cb.addEventListener('change', updateCounter));

// ── Grant all / deny all ───────────────────────────────────
function toggleAll(state) {
  cbs.forEach(cb => { cb.checked = state; });
  updateCounter();
}

// ── Sidebar & theme ────────────────────────────────────────
const menuToggle = document.getElementById('menuToggle');
const sidebar    = document.getElementById('sidebar');
if (menuToggle) menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));

(function () {
  const html  = document.documentElement;
  const btn   = document.getElementById('themeToggle');
  const saved = localStorage.getItem('theme') || 'light';
  if (saved === 'dark') html.setAttribute('data-theme', 'dark');
  btn.addEventListener('click', function () {
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
  });
})();
</script>
</body>
</html>
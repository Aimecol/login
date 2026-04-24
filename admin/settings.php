<?php
// ============================================================
//  admin/settings.php  — System Settings
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('admin');

$db   = db();
$csrf = $_SESSION['csrf_token'] ?? '';

$success = get_flash('success') ?? '';
$error   = get_flash('error')   ?? '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: settings.php'); exit;
    }
    // In a real app these would be saved to a settings table.
    // Here we just flash success to demonstrate the UI.
    set_flash('success', 'Settings saved successfully.');
    header('Location: settings.php'); exit;
}

// PHP / server info
$php_version    = PHP_VERSION;
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$upload_max     = ini_get('upload_max_filesize');
$post_max       = ini_get('post_max_size');
$memory_limit   = ini_get('memory_limit');
$max_exec       = ini_get('max_execution_time') . 's';
$session_name   = session_name();
$session_life   = ini_get('session.gc_maxlifetime') . 's';

$page_title = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?> — Login System</title>
  <link rel="stylesheet" href="../css/main.css"/>
  <style>
    .settings-layout { display:grid; grid-template-columns:220px 1fr; gap:1.5rem; align-items:start; }
    .settings-nav {
      background:var(--bg-card);
      border:1px solid var(--border);
      border-radius:var(--radius);
      overflow:hidden;
      position:sticky;
      top:1rem;
    }
    .settings-nav-title { padding:1rem 1.25rem; font-size:0.72rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--text-dim); border-bottom:1px solid var(--border); }
    .settings-nav-link { display:flex; align-items:center; gap:0.6rem; padding:0.65rem 1.25rem; color:var(--text-muted); font-size:0.86rem; font-weight:500; text-decoration:none; border-left:2px solid transparent; transition:all 0.15s; }
    .settings-nav-link:hover { color:var(--text); background:var(--bg-hover); text-decoration:none; }
    .settings-nav-link.active { color:var(--accent); background:var(--accent-glow); border-left-color:var(--accent); }
    .settings-nav-link svg { width:16px;height:16px;flex-shrink:0; }

    .settings-body { display:flex; flex-direction:column; gap:1.5rem; }
    .settings-section {
      background:var(--bg-card);
      border:1px solid var(--border);
      border-radius:var(--radius);
      overflow:hidden;
    }
    .settings-section-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--border); }
    .settings-section-title  { font-family:var(--font-display); font-size:1rem; font-weight:700; display:flex; align-items:center; gap:0.5rem; }
    .settings-section-title svg { color:var(--accent); }
    .settings-section-desc   { font-size:0.8rem; color:var(--text-muted); margin-top:0.3rem; }
    .settings-section-body   { padding:1.5rem; }

    .setting-row { display:flex; align-items:center; justify-content:space-between; padding:0.85rem 0; border-bottom:1px solid var(--border); gap:1.5rem; }
    .setting-row:last-child { border-bottom:none; }
    .setting-info { flex:1; }
    .setting-label { font-size:0.9rem; font-weight:600; color:var(--text); }
    .setting-desc  { font-size:0.78rem; color:var(--text-muted); margin-top:2px; }
    .setting-control { flex-shrink:0; }

    .form-control {
      padding:0.6rem 0.85rem;
      background:var(--bg);
      border:1px solid var(--border-light);
      border-radius:var(--radius-sm);
      color:var(--text);
      font-family:var(--font-body);
      font-size:0.88rem;
      outline:none;
      transition:border-color 0.2s;
    }
    .form-control:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-glow); }
    .form-control option { background:var(--bg-card); }
    select.form-control { cursor:pointer; }

    .toggle-switch { position:relative; width:44px; height:24px; flex-shrink:0; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider { position:absolute; inset:0; background:var(--border); border-radius:24px; cursor:pointer; transition:background 0.2s; }
    .toggle-slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:transform 0.2s; }
    .toggle-switch input:checked + .toggle-slider { background:var(--success); }
    .toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }

    .server-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.75rem; }
    .server-item { background:var(--bg); border:1px solid var(--border); border-radius:var(--radius-sm); padding:0.85rem 1rem; }
    .server-item label { font-size:0.7rem; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--text-dim); display:block; margin-bottom:0.3rem; }
    .server-item span  { font-size:0.88rem; font-weight:500; color:var(--text); font-family:monospace; }

    .danger-zone { background:rgba(212,74,74,0.04); border:1px solid rgba(212,74,74,0.18); border-radius:var(--radius); padding:1.5rem; }
    .danger-zone-title { font-family:var(--font-display); font-size:1rem; font-weight:700; color:var(--danger); display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem; }
    .danger-zone-desc  { font-size:0.82rem; color:var(--text-muted); margin-bottom:1.25rem; }
    .danger-actions    { display:flex; gap:0.75rem; flex-wrap:wrap; }

    .save-bar { background:var(--bg-card); border-top:1px solid var(--border); padding:1rem 1.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; border-radius:0 0 var(--radius) var(--radius); }
    .save-bar p { font-size:0.82rem; color:var(--text-muted); }

    @media(max-width:900px){ .settings-layout{grid-template-columns:1fr;} .settings-nav{position:static;} .server-grid{grid-template-columns:1fr 1fr;} }
    @media(max-width:500px){ .server-grid{grid-template-columns:1fr;} }
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
      <a href="permissions.php" class="nav-item"><?= icon('shield') ?>Permissions</a>
      <a href="database.php" class="nav-item"><?= icon('database') ?>Database</a>
      <a href="settings.php" class="nav-item active"><?= icon('settings') ?>Settings</a>
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

      <form method="POST" action="settings.php">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>

        <div class="settings-layout">

          <!-- Nav -->
          <div class="settings-nav">
            <div class="settings-nav-title">Sections</div>
            <a href="#general"   class="settings-nav-link active"><?= icon('settings','',16) ?> General</a>
            <a href="#security"  class="settings-nav-link"><?= icon('shield','',16) ?> Security</a>
            <a href="#sessions"  class="settings-nav-link"><?= icon('clock','',16) ?> Sessions</a>
            <a href="#email"     class="settings-nav-link"><?= icon('mail','',16) ?> Email / SMTP</a>
            <a href="#appearance"class="settings-nav-link"><?= icon('bell','',16) ?> Appearance</a>
            <a href="#server"    class="settings-nav-link"><?= icon('database','',16) ?> Server Info</a>
            <a href="#danger"    class="settings-nav-link"><?= icon('alert-circle','',16) ?> Danger Zone</a>
          </div>

          <!-- Body -->
          <div class="settings-body">

            <!-- General -->
            <div class="settings-section" id="general">
              <div class="settings-section-header">
                <div class="settings-section-title"><?= icon('settings') ?> General</div>
                <div class="settings-section-desc">Basic application identity and behaviour settings.</div>
              </div>
              <div class="settings-section-body">
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Application Name</div>
                    <div class="setting-desc">Shown in the browser tab and email subjects.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="text" name="app_name" value="Login System" style="width:220px;"/>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">App URL</div>
                    <div class="setting-desc">The base URL of this application.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="url" name="app_url" value="<?= e((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.$_SERVER['HTTP_HOST']) ?>" style="width:220px;"/>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Timezone</div>
                    <div class="setting-desc">Used for date/time display across the admin panel.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="timezone" style="width:220px;">
                      <?php
                      $zones = ['UTC','Africa/Kigali','Africa/Nairobi','Europe/London','Europe/Paris','America/New_York','America/Los_Angeles','Asia/Tokyo','Australia/Sydney'];
                      foreach ($zones as $z): ?>
                      <option value="<?= e($z) ?>" <?= $z==='Africa/Kigali'?'selected':'' ?>><?= e($z) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Date Format</div>
                    <div class="setting-desc">Controls how dates are displayed throughout the application.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="date_format" style="width:220px;">
                      <option value="d M Y">23 Apr 2025</option>
                      <option value="Y-m-d">2025-04-23</option>
                      <option value="m/d/Y">04/23/2025</option>
                      <option value="d/m/Y">23/04/2025</option>
                    </select>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Maintenance Mode</div>
                    <div class="setting-desc">Prevent regular users from logging in while maintenance is performed.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="maintenance_mode"/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">User Registration</div>
                    <div class="setting-desc">Allow new users to self-register via the login page.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="allow_registration"/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Security -->
            <div class="settings-section" id="security">
              <div class="settings-section-header">
                <div class="settings-section-title"><?= icon('shield') ?> Security</div>
                <div class="settings-section-desc">Authentication, password policy, and brute-force protection.</div>
              </div>
              <div class="settings-section-body">
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Minimum Password Length</div>
                    <div class="setting-desc">Minimum number of characters for user passwords.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="number" name="min_password_len" value="8" min="4" max="64" style="width:100px;"/>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Require Uppercase + Numbers</div>
                    <div class="setting-desc">Enforce strong passwords for all accounts.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="strong_passwords" checked/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Max Login Attempts</div>
                    <div class="setting-desc">Lock account temporarily after this many failed logins.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="number" name="max_login_attempts" value="5" min="1" max="20" style="width:100px;"/>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Lockout Duration</div>
                    <div class="setting-desc">How many minutes to lock an account after max failed attempts.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="lockout_duration" style="width:160px;">
                      <option value="5">5 minutes</option>
                      <option value="15" selected>15 minutes</option>
                      <option value="30">30 minutes</option>
                      <option value="60">1 hour</option>
                      <option value="1440">24 hours</option>
                    </select>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Enforce HTTPS</div>
                    <div class="setting-desc">Redirect all HTTP traffic to HTTPS automatically.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="force_https" checked/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sessions -->
            <div class="settings-section" id="sessions">
              <div class="settings-section-header">
                <div class="settings-section-title"><?= icon('clock') ?> Sessions</div>
                <div class="settings-section-desc">Control how user sessions are managed and expired.</div>
              </div>
              <div class="settings-section-body">
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Session Lifetime</div>
                    <div class="setting-desc">How long until an idle session expires.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="session_lifetime" style="width:160px;">
                      <option value="900">15 minutes</option>
                      <option value="1800" selected>30 minutes</option>
                      <option value="3600">1 hour</option>
                      <option value="7200">2 hours</option>
                      <option value="86400">24 hours</option>
                    </select>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Remember Me Duration</div>
                    <div class="setting-desc">How long "Remember Me" tokens remain valid.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="remember_me_days" style="width:160px;">
                      <option value="7">7 days</option>
                      <option value="14" selected>14 days</option>
                      <option value="30">30 days</option>
                      <option value="90">90 days</option>
                    </select>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Log Session Activity</div>
                    <div class="setting-desc">Record login/logout events to the session_log table.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="log_sessions" checked/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Auto-Purge Old Logs</div>
                    <div class="setting-desc">Automatically delete session logs older than 90 days.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="auto_purge_logs" checked/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Email / SMTP -->
            <div class="settings-section" id="email">
              <div class="settings-section-header">
                <div class="settings-section-title"><?= icon('mail') ?> Email / SMTP</div>
                <div class="settings-section-desc">Configure outgoing email for notifications and password resets.</div>
              </div>
              <div class="settings-section-body">
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">From Address</div>
                    <div class="setting-desc">The "from" email for all system-sent messages.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="email" name="smtp_from" value="noreply@loginsystem.dev" style="width:240px;"/>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">SMTP Host</div>
                    <div class="setting-desc">Hostname of your SMTP server.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="text" name="smtp_host" value="smtp.mailgun.org" style="width:240px;"/>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">SMTP Port</div>
                    <div class="setting-desc">Common values: 25 (plain), 465 (SSL), 587 (TLS).</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="number" name="smtp_port" value="587" style="width:100px;"/>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">SMTP Encryption</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="smtp_enc" style="width:140px;">
                      <option value="tls" selected>TLS (STARTTLS)</option>
                      <option value="ssl">SSL</option>
                      <option value="none">None</option>
                    </select>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">SMTP Username</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="text" name="smtp_user" placeholder="user@domain.com" style="width:240px;"/>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">SMTP Password</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="password" name="smtp_pass" placeholder="••••••••••" style="width:240px;"/>
                  </div>
                </div>
              </div>
              <div class="save-bar">
                <p><?= icon('mail','',14) ?> Test your SMTP by sending a test email after saving.</p>
                <button type="button" class="btn btn-outline" style="font-size:0.85rem;">Send Test Email</button>
              </div>
            </div>

            <!-- Appearance -->
            <div class="settings-section" id="appearance">
              <div class="settings-section-header">
                <div class="settings-section-title"><?= icon('bell') ?> Appearance</div>
                <div class="settings-section-desc">Control the default visual theme and interface preferences.</div>
              </div>
              <div class="settings-section-body">
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Default Theme</div>
                    <div class="setting-desc">The initial theme shown to users who haven't set a preference.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="default_theme" style="width:160px;">
                      <option value="light" selected>Light</option>
                      <option value="dark">Dark</option>
                      <option value="system">System Preference</option>
                    </select>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Allow Theme Toggle</div>
                    <div class="setting-desc">Show the dark/light mode toggle button to all users.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="allow_theme_toggle" checked/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>
                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Items Per Page</div>
                    <div class="setting-desc">Default number of rows displayed in data tables.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="per_page" style="width:120px;">
                      <option value="10">10</option>
                      <option value="15" selected>15</option>
                      <option value="25">25</option>
                      <option value="50">50</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- Server Info -->
            <div class="settings-section" id="server">
              <div class="settings-section-header">
                <div class="settings-section-title"><?= icon('database') ?> Server Environment</div>
                <div class="settings-section-desc">Read-only runtime information about the server.</div>
              </div>
              <div class="settings-section-body">
                <div class="server-grid">
                  <div class="server-item"><label>PHP Version</label><span><?= e($php_version) ?></span></div>
                  <div class="server-item"><label>Server</label><span><?= e(substr($server_software,0,30)) ?></span></div>
                  <div class="server-item"><label>Memory Limit</label><span><?= e($memory_limit) ?></span></div>
                  <div class="server-item"><label>Upload Max</label><span><?= e($upload_max) ?></span></div>
                  <div class="server-item"><label>POST Max</label><span><?= e($post_max) ?></span></div>
                  <div class="server-item"><label>Max Exec Time</label><span><?= e($max_exec) ?></span></div>
                  <div class="server-item"><label>Session Name</label><span><?= e($session_name) ?></span></div>
                  <div class="server-item"><label>Session Lifetime</label><span><?= e($session_life) ?></span></div>
                  <div class="server-item"><label>OS</label><span><?= e(PHP_OS_FAMILY) ?></span></div>
                </div>
              </div>
            </div>

            <!-- Danger zone -->
            <div class="danger-zone" id="danger">
              <div class="danger-zone-title"><?= icon('alert-circle','',18) ?> Danger Zone</div>
              <div class="danger-zone-desc">These actions are irreversible. Proceed only if you know exactly what you are doing.</div>
              <div class="danger-actions">
                <button type="button" class="btn btn-danger" onclick="alert('Feature not enabled in demo.')">
                  <?= icon('logout','',15) ?> Reset All Settings
                </button>
                <button type="button" class="btn btn-danger" onclick="alert('This would wipe the database in production.')">
                  <?= icon('database','',15) ?> Factory Reset
                </button>
              </div>
            </div>

            <!-- Bottom save -->
            <div style="display:flex;justify-content:flex-end;gap:0.75rem;padding-bottom:1rem;">
              <button type="reset" class="btn btn-outline">Discard Changes</button>
              <button type="submit" class="btn btn-primary"><?= icon('check-circle','',16) ?> Save All Settings</button>
            </div>

          </div><!-- /settings-body -->
        </div><!-- /settings-layout -->

      </form>
    </div>
  </div>
</div>

<script>
// Smooth scroll for settings nav
document.querySelectorAll('.settings-nav-link').forEach(link => {
  link.addEventListener('click', e => {
    document.querySelectorAll('.settings-nav-link').forEach(l => l.classList.remove('active'));
    link.classList.add('active');
  });
});

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
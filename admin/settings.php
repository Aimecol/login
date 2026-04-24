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

// ── Helpers ───────────────────────────────────────────────────
function get_setting(mysqli $db, string $key, $fallback = ''): string {
    $tbl = $db->query("SHOW TABLES LIKE 'system_settings'");
    if (!$tbl || $tbl->num_rows === 0) return (string)$fallback;
    $s = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $s->bind_param('s', $key);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r ? $r['setting_value'] : (string)$fallback;
}

function save_setting(mysqli $db, string $key, string $value): void {
    $s = $db->prepare(
        'INSERT INTO system_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );
    $s->bind_param('ss', $key, $value);
    $s->execute();
    $s->close();
}

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: settings.php'); exit;
    }

    $tbl_check = $db->query("SHOW TABLES LIKE 'system_settings'");
    $has_table = $tbl_check && $tbl_check->num_rows > 0;

    if ($has_table) {
        // General
        save_setting($db, 'app_name',           trim($_POST['app_name']   ?? 'Login System'));
        save_setting($db, 'app_url',            trim($_POST['app_url']    ?? ''));
        save_setting($db, 'timezone',           trim($_POST['timezone']   ?? 'Africa/Kigali'));
        save_setting($db, 'date_format',        trim($_POST['date_format']?? 'd M Y'));
        save_setting($db, 'maintenance_mode',   isset($_POST['maintenance_mode'])  ? '1' : '0');
        save_setting($db, 'allow_registration', isset($_POST['allow_registration'])? '1' : '0');

        // Security
        save_setting($db, 'min_password_len',   (string)(int)($_POST['min_password_len']  ?? 8));
        save_setting($db, 'strong_passwords',   isset($_POST['strong_passwords'])  ? '1' : '0');
        save_setting($db, 'max_login_attempts', (string)(int)($_POST['max_login_attempts'] ?? 5));
        save_setting($db, 'lockout_duration',   (string)(int)($_POST['lockout_duration']   ?? 15));
        save_setting($db, 'force_https',        isset($_POST['force_https']) ? '1' : '0');

        // Sessions
        save_setting($db, 'session_lifetime',   (string)(int)($_POST['session_lifetime']   ?? 1800));
        save_setting($db, 'remember_me_days',   (string)(int)($_POST['remember_me_days']   ?? 14));
        save_setting($db, 'log_sessions',       isset($_POST['log_sessions'])    ? '1' : '0');
        save_setting($db, 'auto_purge_logs',    isset($_POST['auto_purge_logs']) ? '1' : '0');

        // Email
        save_setting($db, 'smtp_from', trim($_POST['smtp_from'] ?? ''));
        save_setting($db, 'smtp_host', trim($_POST['smtp_host'] ?? ''));
        save_setting($db, 'smtp_port', (string)(int)($_POST['smtp_port'] ?? 587));
        save_setting($db, 'smtp_enc',  in_array($_POST['smtp_enc'] ?? '', ['tls','ssl','none'])
                                        ? $_POST['smtp_enc'] : 'tls');
        save_setting($db, 'smtp_user', trim($_POST['smtp_user'] ?? ''));
        if (!empty($_POST['smtp_pass'])) {
            save_setting($db, 'smtp_pass', trim($_POST['smtp_pass']));
        }

        // Appearance
        save_setting($db, 'default_theme',     in_array($_POST['default_theme'] ?? '', ['light','dark','system'])
                                                ? $_POST['default_theme'] : 'light');
        save_setting($db, 'allow_theme_toggle', isset($_POST['allow_theme_toggle']) ? '1' : '0');
        save_setting($db, 'per_page',          (string)(int)($_POST['per_page'] ?? 15));

        set_flash('success', 'Settings saved successfully.');
    } else {
        set_flash('success', 'Settings saved (run database.sql to persist across sessions).');
    }
    header('Location: settings.php'); exit;
}

// ── Load all settings ─────────────────────────────────────────
$settings = [];
$tbl_check = $db->query("SHOW TABLES LIKE 'system_settings'");
if ($tbl_check && $tbl_check->num_rows > 0) {
    $res = $db->query('SELECT setting_key, setting_value FROM system_settings');
    while ($r = $res->fetch_assoc()) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
}

// Helper to get a setting with fallback
$s = fn(string $key, $fb = '') => $settings[$key] ?? (string)$fb;
$sb = fn(string $key, bool $fb = false): bool => isset($settings[$key]) ? (bool)$settings[$key] : $fb;

// ── PHP / server info ─────────────────────────────────────────
$php_version     = PHP_VERSION;
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$upload_max      = ini_get('upload_max_filesize');
$post_max        = ini_get('post_max_size');
$memory_limit    = ini_get('memory_limit');
$max_exec        = ini_get('max_execution_time') . 's';
$session_handler = ini_get('session.save_handler');
$session_name    = session_name();

// MySQL version
$mv = $db->query('SELECT VERSION() AS v');
$mysql_version = $mv ? $mv->fetch_assoc()['v'] : 'Unknown';

// Timezones for select
$timezones = DateTimeZone::listIdentifiers();

$page_title = 'Settings';
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
    /* ── Settings layout ───────────────────────────────────── */
    .settings-layout {
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 1.5rem;
      align-items: start;
    }

    /* ── Settings sidebar nav ──────────────────────────────── */
    .settings-nav {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      position: sticky;
      top: 1rem;
    }
    .settings-nav-title {
      padding: 1rem 1.25rem;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--text-dim);
      border-bottom: 1px solid var(--border);
    }
    .settings-nav-link {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.7rem 1.25rem;
      color: var(--text-muted);
      font-size: 0.86rem;
      font-weight: 500;
      text-decoration: none;
      border-left: 2px solid transparent;
      transition: all 0.15s;
    }
    .settings-nav-link svg { width: 15px; height: 15px; flex-shrink: 0; }
    .settings-nav-link:hover { color: var(--text); background: var(--bg-hover); text-decoration: none; }
    .settings-nav-link.active { color: var(--accent); background: var(--accent-glow); border-left-color: var(--accent); }

    /* ── Settings body ─────────────────────────────────────── */
    .settings-body { display: flex; flex-direction: column; gap: 1.5rem; }

    /* ── Settings section card ─────────────────────────────── */
    .settings-section {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      scroll-margin-top: 5rem;
    }
    .settings-section-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .settings-section-icon {
      width: 36px; height: 36px;
      border-radius: var(--radius-sm);
      background: var(--accent-glow);
      color: var(--accent);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .settings-section-icon svg { width: 17px; height: 17px; }
    .settings-section-title {
      font-family: var(--font-display);
      font-size: 0.95rem;
      font-weight: 700;
    }
    .settings-section-desc { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }
    .settings-section-body { padding: 0 1.5rem; }

    /* ── Setting rows ──────────────────────────────────────── */
    .setting-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 2rem;
      padding: 1rem 0;
      border-bottom: 1px solid var(--border);
    }
    .setting-row:last-child { border-bottom: none; }
    .setting-info { flex: 1; min-width: 0; }
    .setting-label { font-size: 0.9rem; font-weight: 600; color: var(--text); }
    .setting-desc  { font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; line-height: 1.5; }
    .setting-control { flex-shrink: 0; }

    /* ── Form controls ─────────────────────────────────────── */
    .form-control {
      padding: 0.55rem 0.85rem;
      background: var(--bg);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-sm);
      color: var(--text);
      font-family: var(--font-body);
      font-size: 0.88rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    .form-control option { background: var(--bg-card); }
    select.form-control { cursor: pointer; }

    /* ── Toggle switch ─────────────────────────────────────── */
    .toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
    .toggle-slider {
      position: absolute; inset: 0;
      background: var(--border-light);
      border-radius: 24px; cursor: pointer;
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
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
    .toggle-switch input:focus-visible + .toggle-slider { outline: 2px solid var(--accent); outline-offset: 2px; }

    /* ── Server info grid ──────────────────────────────────── */
    .server-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.75rem;
    }
    .server-item {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0.85rem 1rem;
    }
    .server-item label {
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-dim);
      display: block;
      margin-bottom: 0.35rem;
    }
    .server-item span {
      font-size: 0.86rem;
      font-weight: 500;
      color: var(--text);
      font-family: monospace;
      word-break: break-all;
    }

    /* ── Danger zone ───────────────────────────────────────── */
    .danger-section {
      background: rgba(212,74,74,0.03);
      border: 1px solid rgba(212,74,74,0.20);
      border-radius: var(--radius);
      overflow: hidden;
      scroll-margin-top: 5rem;
    }
    .danger-section-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(212,74,74,0.15);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .danger-section-icon {
      width: 36px; height: 36px;
      border-radius: var(--radius-sm);
      background: rgba(212,74,74,0.1);
      color: var(--danger);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .danger-section-icon svg { width: 17px; height: 17px; }
    .danger-section-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .danger-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.5rem;
      padding: 1rem 1.25rem;
      background: var(--bg-card);
      border: 1px solid rgba(212,74,74,0.12);
      border-radius: var(--radius-sm);
    }
    .danger-item-label { font-size: 0.9rem; font-weight: 600; color: var(--text); }
    .danger-item-desc  { font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; }

    /* ── Save bar (inside section) ─────────────────────────── */
    .settings-save-bar {
      padding: 1rem 1.5rem;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.75rem;
      background: var(--bg-card);
    }

    /* ── Floating save (sticky) ────────────────────────────── */
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

    /* ── Success flash dot ─────────────────────────────────── */
    .status-indicator {
      display: inline-block;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--success);
      box-shadow: 0 0 8px var(--success);
      flex-shrink: 0;
    }

    /* ── Responsive ────────────────────────────────────────── */
    @media (max-width: 960px) {
      .settings-layout { grid-template-columns: 1fr; }
      .settings-nav { position: static; }
      .server-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 600px) {
      .server-grid { grid-template-columns: 1fr; }
      .setting-row { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
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
      <a href="permissions.php" class="nav-item"><?= icon('shield') ?>Permissions</a>
      <a href="database.php"    class="nav-item"><?= icon('database') ?>Database</a>
      <a href="settings.php"    class="nav-item active"><?= icon('settings') ?>Settings</a>
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

      <form method="POST" action="settings.php" id="settingsForm">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>

        <div class="settings-layout">

          <!-- ── Settings nav ────────────────────────────── -->
          <div class="settings-nav">
            <div class="settings-nav-title">Sections</div>
            <a href="#general"    class="settings-nav-link active" data-section="general">
              <?= icon('settings','',15) ?> General
            </a>
            <a href="#security"   class="settings-nav-link" data-section="security">
              <?= icon('shield','',15) ?> Security
            </a>
            <a href="#sessions"   class="settings-nav-link" data-section="sessions">
              <?= icon('clock','',15) ?> Sessions
            </a>
            <a href="#email"      class="settings-nav-link" data-section="email">
              <?= icon('mail','',15) ?> Email / SMTP
            </a>
            <a href="#appearance" class="settings-nav-link" data-section="appearance">
              <?= icon('bell','',15) ?> Appearance
            </a>
            <a href="#server"     class="settings-nav-link" data-section="server">
              <?= icon('database','',15) ?> Server Info
            </a>
            <a href="#danger"     class="settings-nav-link" data-section="danger">
              <?= icon('alert-circle','',15) ?> Danger Zone
            </a>
          </div>

          <!-- ── Settings body ───────────────────────────── -->
          <div class="settings-body">

            <!-- ══ General ════════════════════════════════ -->
            <div class="settings-section" id="general">
              <div class="settings-section-header">
                <div class="settings-section-icon"><?= icon('settings','',17) ?></div>
                <div>
                  <div class="settings-section-title">General</div>
                  <div class="settings-section-desc">Basic application identity and behaviour.</div>
                </div>
              </div>
              <div class="settings-section-body">

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Application Name</div>
                    <div class="setting-desc">Shown in the browser tab and email headers.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="text" name="app_name"
                           value="<?= e($s('app_name','Login System')) ?>" style="width:220px;"/>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Application URL</div>
                    <div class="setting-desc">Base URL used for email links and redirects.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="url" name="app_url"
                           value="<?= e($s('app_url', (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.$_SERVER['HTTP_HOST'])) ?>"
                           style="width:220px;"/>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Timezone</div>
                    <div class="setting-desc">PHP timezone identifier used for all date/time display.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="timezone" style="width:220px;">
                      <?php foreach ($timezones as $z): ?>
                      <option value="<?= e($z) ?>" <?= $s('timezone','Africa/Kigali') === $z ? 'selected' : '' ?>>
                        <?= e($z) ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Date Format</div>
                    <div class="setting-desc">PHP date() format string for all date displays.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="date_format" style="width:220px;">
                      <?php
                      $fmts = ['d M Y' => '24 Apr 2025','Y-m-d' => '2025-04-24','m/d/Y' => '04/24/2025','d/m/Y' => '24/04/2025'];
                      $cur_fmt = $s('date_format','d M Y');
                      foreach ($fmts as $fmt => $ex): ?>
                      <option value="<?= e($fmt) ?>" <?= $cur_fmt === $fmt ? 'selected' : '' ?>>
                        <?= e($ex) ?> (<?= e($fmt) ?>)
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Maintenance Mode</div>
                    <div class="setting-desc">Non-admin users see a maintenance page when enabled.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="maintenance_mode" <?= $sb('maintenance_mode') ? 'checked' : '' ?>/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Allow Public Registration</div>
                    <div class="setting-desc">When disabled, only admins can create new accounts.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="allow_registration" <?= $sb('allow_registration', true) ? 'checked' : '' ?>/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

              </div><!-- /.settings-section-body -->
            </div>

            <!-- ══ Security ═══════════════════════════════ -->
            <div class="settings-section" id="security">
              <div class="settings-section-header">
                <div class="settings-section-icon" style="background:rgba(212,74,74,0.1);color:var(--danger);">
                  <?= icon('shield','',17) ?>
                </div>
                <div>
                  <div class="settings-section-title">Security</div>
                  <div class="settings-section-desc">Authentication, password policy, and brute-force protection.</div>
                </div>
              </div>
              <div class="settings-section-body">

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Minimum Password Length</div>
                    <div class="setting-desc">Passwords shorter than this value are rejected at registration and reset.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="number" name="min_password_len"
                           value="<?= e($s('min_password_len','8')) ?>"
                           min="4" max="64" style="width:90px;"/>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Require Strong Passwords</div>
                    <div class="setting-desc">Enforces at least one uppercase letter, one number, and one symbol.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="strong_passwords" <?= $sb('strong_passwords', true) ? 'checked' : '' ?>/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Max Login Attempts</div>
                    <div class="setting-desc">Failed attempts before the account is temporarily locked out.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="number" name="max_login_attempts"
                           value="<?= e($s('max_login_attempts','5')) ?>"
                           min="1" max="20" style="width:90px;"/>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Lockout Duration</div>
                    <div class="setting-desc">How long (minutes) a locked account must wait before retrying.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="lockout_duration" style="width:160px;">
                      <?php foreach ([5=>'5 minutes',15=>'15 minutes',30=>'30 minutes',60=>'1 hour',1440=>'24 hours'] as $v=>$l): ?>
                      <option value="<?= $v ?>" <?= $s('lockout_duration','15') == $v ? 'selected' : '' ?>>
                        <?= e($l) ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Force HTTPS</div>
                    <div class="setting-desc">Redirect all HTTP requests to HTTPS. Disable only on local dev.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="force_https" <?= $sb('force_https', true) ? 'checked' : '' ?>/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

              </div>
            </div>

            <!-- ══ Sessions ═══════════════════════════════ -->
            <div class="settings-section" id="sessions">
              <div class="settings-section-header">
                <div class="settings-section-icon" style="background:rgba(46,143,196,0.1);color:var(--user-hue);">
                  <?= icon('clock','',17) ?>
                </div>
                <div>
                  <div class="settings-section-title">Sessions</div>
                  <div class="settings-section-desc">Control how user sessions are managed and expired.</div>
                </div>
              </div>
              <div class="settings-section-body">

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Session Lifetime</div>
                    <div class="setting-desc">Idle timeout after which a logged-in session expires.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="session_lifetime" style="width:160px;">
                      <?php foreach ([900=>'15 minutes',1800=>'30 minutes',3600=>'1 hour',7200=>'2 hours',86400=>'24 hours'] as $v=>$l): ?>
                      <option value="<?= $v ?>" <?= $s('session_lifetime','1800') == $v ? 'selected' : '' ?>>
                        <?= e($l) ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Remember Me Duration</div>
                    <div class="setting-desc">How many days a "remember me" cookie persists.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="remember_me_days" style="width:160px;">
                      <?php foreach ([7=>'7 days',14=>'14 days',30=>'30 days',90=>'90 days'] as $v=>$l): ?>
                      <option value="<?= $v ?>" <?= $s('remember_me_days','14') == $v ? 'selected' : '' ?>>
                        <?= e($l) ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Log Sessions</div>
                    <div class="setting-desc">Record every login and logout in the session_log table.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="log_sessions" <?= $sb('log_sessions', true) ? 'checked' : '' ?>/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Auto-Purge Old Logs</div>
                    <div class="setting-desc">Automatically delete session_log entries older than 90 days.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="auto_purge_logs" <?= $sb('auto_purge_logs', true) ? 'checked' : '' ?>/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

              </div>
            </div>

            <!-- ══ Email / SMTP ════════════════════════════ -->
            <div class="settings-section" id="email">
              <div class="settings-section-header">
                <div class="settings-section-icon" style="background:rgba(34,184,110,0.1);color:var(--success);">
                  <?= icon('mail','',17) ?>
                </div>
                <div>
                  <div class="settings-section-title">Email / SMTP</div>
                  <div class="settings-section-desc">Configure outgoing mail for password resets and notifications.</div>
                </div>
              </div>
              <div class="settings-section-body">

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">From Address</div>
                    <div class="setting-desc">The email address used as the sender for all outgoing mail.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="email" name="smtp_from"
                           value="<?= e($s('smtp_from','noreply@loginsystem.dev')) ?>"
                           style="width:240px;"/>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">SMTP Host</div>
                    <div class="setting-desc">Hostname or IP of your outgoing mail server.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="text" name="smtp_host"
                           value="<?= e($s('smtp_host','smtp.mailgun.org')) ?>"
                           style="width:240px;"/>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">SMTP Port</div>
                    <div class="setting-desc">25 (plain), 465 (SSL), or 587 (TLS/STARTTLS) are common.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="number" name="smtp_port"
                           value="<?= e($s('smtp_port','587')) ?>"
                           min="1" max="65535" style="width:110px;"/>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Encryption</div>
                    <div class="setting-desc">Transport security for the SMTP connection.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="smtp_enc" style="width:180px;">
                      <?php
                      $cur_enc = $s('smtp_enc','tls');
                      foreach (['tls'=>'TLS (STARTTLS)','ssl'=>'SSL','none'=>'None (plain)'] as $v=>$l): ?>
                      <option value="<?= e($v) ?>" <?= $cur_enc === $v ? 'selected' : '' ?>>
                        <?= e($l) ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">SMTP Username</div>
                    <div class="setting-desc">Authentication username. Leave blank if not required.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="text" name="smtp_user"
                           value="<?= e($s('smtp_user')) ?>"
                           placeholder="user@domain.com" style="width:240px;"/>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">SMTP Password</div>
                    <div class="setting-desc">Leave blank to keep the existing password unchanged.</div>
                  </div>
                  <div class="setting-control">
                    <input class="form-control" type="password" name="smtp_pass"
                           placeholder="••••••••••" style="width:240px;"
                           autocomplete="new-password"/>
                  </div>
                </div>

              </div>
            </div>

            <!-- ══ Appearance ══════════════════════════════ -->
            <div class="settings-section" id="appearance">
              <div class="settings-section-header">
                <div class="settings-section-icon"><?= icon('bell','',17) ?></div>
                <div>
                  <div class="settings-section-title">Appearance</div>
                  <div class="settings-section-desc">Theme, pagination, and UI preferences.</div>
                </div>
              </div>
              <div class="settings-section-body">

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Default Theme</div>
                    <div class="setting-desc">Initial colour scheme applied before user preference is loaded.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="default_theme" style="width:180px;">
                      <?php
                      $cur_theme = $s('default_theme','light');
                      foreach (['light'=>'Light','dark'=>'Dark','system'=>'System Preference'] as $v=>$l): ?>
                      <option value="<?= e($v) ?>" <?= $cur_theme === $v ? 'selected' : '' ?>>
                        <?= e($l) ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Allow Theme Toggle</div>
                    <div class="setting-desc">Show the light/dark mode toggle button to all users.</div>
                  </div>
                  <div class="setting-control">
                    <label class="toggle-switch">
                      <input type="checkbox" name="allow_theme_toggle" <?= $sb('allow_theme_toggle', true) ? 'checked' : '' ?>/>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="setting-row">
                  <div class="setting-info">
                    <div class="setting-label">Items Per Page</div>
                    <div class="setting-desc">Default pagination size for all list views.</div>
                  </div>
                  <div class="setting-control">
                    <select class="form-control" name="per_page" style="width:120px;">
                      <?php foreach ([10,15,25,50] as $v): ?>
                      <option value="<?= $v ?>" <?= $s('per_page','15') == $v ? 'selected' : '' ?>>
                        <?= $v ?> rows
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

              </div>
            </div>

            <!-- ══ Server Info ═════════════════════════════ -->
            <div class="settings-section" id="server">
              <div class="settings-section-header">
                <div class="settings-section-icon" style="background:rgba(46,143,196,0.1);color:var(--user-hue);">
                  <?= icon('database','',17) ?>
                </div>
                <div>
                  <div class="settings-section-title">Server Information</div>
                  <div class="settings-section-desc">Read-only runtime environment details.</div>
                </div>
              </div>
              <div class="settings-section-body" style="padding:1.5rem;">
                <div class="server-grid">
                  <div class="server-item">
                    <label>PHP Version</label>
                    <span><?= e($php_version) ?></span>
                  </div>
                  <div class="server-item">
                    <label>MySQL Version</label>
                    <span><?= e($mysql_version) ?></span>
                  </div>
                  <div class="server-item">
                    <label>Web Server</label>
                    <span><?= e($server_software) ?></span>
                  </div>
                  <div class="server-item">
                    <label>Upload Max Size</label>
                    <span><?= e($upload_max) ?></span>
                  </div>
                  <div class="server-item">
                    <label>POST Max Size</label>
                    <span><?= e($post_max) ?></span>
                  </div>
                  <div class="server-item">
                    <label>Memory Limit</label>
                    <span><?= e($memory_limit) ?></span>
                  </div>
                  <div class="server-item">
                    <label>Max Exec Time</label>
                    <span><?= e($max_exec) ?></span>
                  </div>
                  <div class="server-item">
                    <label>Session Handler</label>
                    <span><?= e($session_handler) ?></span>
                  </div>
                  <div class="server-item">
                    <label>Session Name</label>
                    <span><?= e($session_name) ?></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══ Danger Zone ════════════════════════════ -->
            <div class="danger-section" id="danger">
              <div class="danger-section-header">
                <div class="danger-section-icon"><?= icon('alert-circle','',17) ?></div>
                <div>
                  <div class="settings-section-title" style="color:var(--danger);">Danger Zone</div>
                  <div class="settings-section-desc">Irreversible actions. Proceed with extreme caution.</div>
                </div>
              </div>
              <div class="danger-section-body">

                <div class="danger-item">
                  <div>
                    <div class="danger-item-label">Clear All Session Logs</div>
                    <div class="danger-item-desc">Permanently deletes all records in the session_log table. Login history will be lost.</div>
                  </div>
                  <button type="button" class="btn btn-danger" style="white-space:nowrap;"
                          onclick="confirmDanger('clear_sessions','Clear all session logs? This cannot be undone.')">
                    <?= icon('alert-circle','',15) ?> Clear Logs
                  </button>
                </div>

                <div class="danger-item">
                  <div>
                    <div class="danger-item-label">Reset Member Permissions</div>
                    <div class="danger-item-desc">Resets all Member-role permissions to their factory defaults.</div>
                  </div>
                  <a href="permissions.php" class="btn btn-danger" style="white-space:nowrap;">
                    <?= icon('shield','',15) ?> Reset Permissions
                  </a>
                </div>

                <div class="danger-item">
                  <div>
                    <div class="danger-item-label">Reset All Settings</div>
                    <div class="danger-item-desc">Restores every setting in the system_settings table to its default value.</div>
                  </div>
                  <button type="button" class="btn btn-danger" style="white-space:nowrap;"
                          onclick="confirmDanger('reset_settings','Reset ALL settings to defaults? This cannot be undone.')">
                    <?= icon('settings','',15) ?> Reset Settings
                  </button>
                </div>

              </div>
            </div>
            <!-- /.danger-section -->

          </div><!-- /.settings-body -->
        </div><!-- /.settings-layout -->

        <!-- Sticky save bar -->
        <div class="save-bar">
          <div class="save-bar-info">
            <?= icon('check-circle','',14) ?>
            All sections are saved together. Changes take effect immediately.
          </div>
          <div style="display:flex;gap:0.75rem;">
            <button type="reset" class="btn btn-outline">Discard</button>
            <button type="submit" class="btn btn-primary">
              <?= icon('check-circle','',16) ?> Save All Settings
            </button>
          </div>
        </div>

      </form>
    </div><!-- /.dash-content -->
  </div><!-- /.dash-main -->
</div><!-- /.dash-layout -->

<script>
// ── Settings nav active link on scroll ────────────────────
const sections = document.querySelectorAll('.settings-section[id], .danger-section[id]');
const navLinks  = document.querySelectorAll('.settings-nav-link[data-section]');

function updateActiveNav() {
  let active = null;
  sections.forEach(sec => {
    const rect = sec.getBoundingClientRect();
    if (rect.top <= 120) active = sec.id;
  });
  navLinks.forEach(link => {
    const matches = link.dataset.section === active;
    link.classList.toggle('active', matches);
  });
}
window.addEventListener('scroll', updateActiveNav, { passive: true });
updateActiveNav();

// ── Smooth scroll on nav click ─────────────────────────────
navLinks.forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    const target = document.getElementById(link.dataset.section);
    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});

// ── Danger zone confirmation ───────────────────────────────
function confirmDanger(action, msg) {
  if (!confirm(msg)) return;
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'settings.php';
  const fields = { _token: '<?= e($csrf) ?>', action: action };
  Object.entries(fields).forEach(([k, v]) => {
    const i = document.createElement('input');
    i.type = 'hidden'; i.name = k; i.value = v;
    form.appendChild(i);
  });
  document.body.appendChild(form);
  form.submit();
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
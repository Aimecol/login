<?php
// ============================================================
//  user/change_password.php  — Dedicated Change Password Page
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('user');

$db   = db();
$uid  = current_user_id();
$csrf = $_SESSION['csrf_token'] ?? '';

$success = get_flash('success') ?? '';
$error   = get_flash('error')   ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: change_password.php'); exit;
    }

    $current = $_POST['current_password'] ?? '';
    $new_pw  = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!password_verify($current, $row['password'])) {
        set_flash('error', 'Your current password is incorrect.');
    } elseif (strlen($new_pw) < 8) {
        set_flash('error', 'New password must be at least 8 characters long.');
    } elseif ($new_pw === $current) {
        set_flash('error', 'New password must be different from your current password.');
    } elseif ($new_pw !== $confirm) {
        set_flash('error', 'New password and confirmation do not match.');
    } else {
        $hash = password_hash($new_pw, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password=? WHERE id=?');
        $stmt->bind_param('si', $hash, $uid);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Password updated successfully. Please use your new password next time you log in.');
    }
    header('Location: change_password.php'); exit;
}

// Fetch minimal profile info
$stmt = $db->prepare('SELECT full_name, email, last_login FROM users WHERE id=? LIMIT 1');
$stmt->bind_param('i', $uid);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = 'Change Password';
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
    .pw-wrapper {
      max-width: 520px;
      margin: 0 auto;
    }

    .pw-hero {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 2rem;
      margin-bottom: 1.5rem;
      display: flex; align-items: center; gap: 1.25rem;
      position: relative; overflow: hidden;
    }
    .pw-hero::before {
      content: '';
      position: absolute; top: -50px; right: -50px;
      width: 160px; height: 160px; border-radius: 50%;
      background: radial-gradient(circle, rgba(46,143,196,0.1) 0%, transparent 70%);
    }
    .pw-icon-wrap {
      width: 56px; height: 56px; border-radius: var(--radius);
      background: rgba(46,143,196,0.12); color: var(--user-hue);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .pw-hero-text h2 { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; margin-bottom: .3rem; }
    .pw-hero-text p  { font-size: .87rem; color: var(--text-muted); }

    .form-card {
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 1.25rem;
    }
    .form-card-header {
      padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: .6rem;
      font-family: var(--font-display); font-weight: 700; font-size: 1rem;
    }
    .form-card-header svg { color: var(--accent); }
    .form-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.1rem; }

    .form-group { display: flex; flex-direction: column; gap: .4rem; }
    .form-group label { font-size: .8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
    .input-pw-wrap { position: relative; }
    .input-pw-wrap input {
      background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm);
      padding: .7rem 2.8rem .7rem .9rem; color: var(--text);
      font-family: var(--font-body); font-size: .92rem; width: 100%;
      transition: border-color .2s, box-shadow .2s;
    }
    .input-pw-wrap input:focus { outline: none; border-color: var(--user-hue); box-shadow: 0 0 0 3px rgba(46,143,196,0.12); }
    .input-pw-wrap input.valid   { border-color: var(--success); }
    .input-pw-wrap input.invalid { border-color: var(--danger); }
    .toggle-pw {
      position: absolute; right: .7rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--text-dim); cursor: pointer; padding: 2px;
    }
    .toggle-pw:hover { color: var(--text-muted); }

    .hint-list { margin-top: .4rem; display: flex; flex-direction: column; gap: .2rem; }
    .hint-item { display: flex; align-items: center; gap: .45rem; font-size: .78rem; color: var(--text-dim); transition: color .2s; }
    .hint-item.pass { color: var(--success); }
    .hint-item.fail { color: var(--text-dim); }
    .hint-item svg { flex-shrink: 0; }

    .strength-section { margin-top: .25rem; }
    .strength-label { font-size: .75rem; color: var(--text-dim); margin-bottom: .3rem; display: flex; justify-content: space-between; }
    .strength-bar { height: 5px; border-radius: 3px; background: var(--border); overflow: hidden; }
    .strength-fill { height: 100%; border-radius: 3px; transition: width .35s, background .35s; width: 0%; }

    .security-tips {
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1.25rem 1.5rem;
    }
    .security-tips h4 { font-size: .85rem; font-weight: 700; margin-bottom: .75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; }
    .tip-item { display: flex; align-items: flex-start; gap: .6rem; font-size: .83rem; color: var(--text-muted); margin-bottom: .5rem; }
    .tip-item:last-child { margin-bottom: 0; }
    .tip-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); margin-top: 7px; flex-shrink: 0; }
  </style>
</head>
<body>
<div class="page-bg-accent"></div>
<div class="dash-layout">

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
      <a href="login_history.php" class="nav-item"><?= icon('activity') ?>Login History</a>
      <a href="messages.php" class="nav-item"><?= icon('mail') ?>Messages</a>
      <a href="change_password.php" class="nav-item active"><?= icon('key') ?>Change Password</a>
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
        <h1 class="topbar-title"><?= icon('key', '', 20) ?> Change Password</h1>
      </div>
      <div class="topbar-actions">
        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
          <span class="icon-moon"><?= icon('moon', '', 16) ?></span>
          <span class="icon-sun"><?= icon('sun', '', 16) ?></span>
        </button>
      </div>
    </header>

    <main class="dash-content">
      <div class="pw-wrapper">

        <?php if ($success): ?>
          <div style="background:rgba(34,184,110,0.1);border:1px solid rgba(34,184,110,0.3);color:var(--success);padding:.9rem 1.1rem;border-radius:var(--radius-sm);margin-bottom:1.25rem;font-size:.9rem;display:flex;gap:.6rem;align-items:center;"><?= icon('shield', '', 18) ?><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div style="background:rgba(212,74,74,0.1);border:1px solid rgba(212,74,74,0.3);color:var(--danger);padding:.9rem 1.1rem;border-radius:var(--radius-sm);margin-bottom:1.25rem;font-size:.9rem;display:flex;gap:.6rem;align-items:center;"><?= icon('lock', '', 18) ?><?= e($error) ?></div>
        <?php endif; ?>

        <!-- Hero -->
        <div class="pw-hero">
          <div class="pw-icon-wrap"><?= icon('lock', '', 26) ?></div>
          <div class="pw-hero-text">
            <h2>Update Your Password</h2>
            <p>Account: <strong><?= e($profile['full_name']) ?></strong> &nbsp;·&nbsp; <?= e($profile['email']) ?></p>
            <?php if ($profile['last_login']): ?>
              <p style="margin-top:.2rem;font-size:.8rem;">Last login: <?= date('M j, Y g:i A', strtotime($profile['last_login'])) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Form -->
        <div class="form-card">
          <div class="form-card-header"><?= icon('key', '', 17) ?> Password Change</div>
          <form method="POST" action="change_password.php" id="pwForm">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
            <div class="form-body">

              <div class="form-group">
                <label>Current Password</label>
                <div class="input-pw-wrap">
                  <input type="password" name="current_password" id="currentPw" required autocomplete="current-password" placeholder="Enter your current password"/>
                  <button type="button" class="toggle-pw" onclick="togglePw('currentPw',this)"><?= icon('lock', '', 16) ?></button>
                </div>
              </div>

              <div class="form-group">
                <label>New Password</label>
                <div class="input-pw-wrap">
                  <input type="password" name="new_password" id="newPw" required autocomplete="new-password" placeholder="Choose a strong password" oninput="evaluate(this.value)"/>
                  <button type="button" class="toggle-pw" onclick="togglePw('newPw',this)"><?= icon('lock', '', 16) ?></button>
                </div>
                <!-- Strength -->
                <div class="strength-section">
                  <div class="strength-label">
                    <span>Password strength</span>
                    <span id="strengthText"></span>
                  </div>
                  <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                </div>
                <!-- Requirements -->
                <div class="hint-list">
                  <div class="hint-item" id="r-length"><?= icon('shield', '', 13) ?> At least 8 characters</div>
                  <div class="hint-item" id="r-upper"><?= icon('shield', '', 13) ?> At least one uppercase letter</div>
                  <div class="hint-item" id="r-num"><?= icon('shield', '', 13) ?> At least one number</div>
                  <div class="hint-item" id="r-special"><?= icon('shield', '', 13) ?> At least one special character</div>
                </div>
              </div>

              <div class="form-group">
                <label>Confirm New Password</label>
                <div class="input-pw-wrap">
                  <input type="password" name="confirm_password" id="confirmPw" required autocomplete="new-password" placeholder="Repeat new password" oninput="checkMatch()"/>
                  <button type="button" class="toggle-pw" onclick="togglePw('confirmPw',this)"><?= icon('lock', '', 16) ?></button>
                </div>
                <div id="matchHint" style="font-size:.78rem;margin-top:.25rem;"></div>
              </div>

              <div style="display:flex;justify-content:flex-end;gap:.75rem;padding-top:.5rem;">
                <a href="profile.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary" id="submitBtn"><?= icon('key', '', 16) ?> Update Password</button>
              </div>
            </div>
          </form>
        </div>

        <!-- Tips -->
        <div class="security-tips">
          <h4><?= icon('shield', '', 14) ?> Security Tips</h4>
          <div class="tip-item"><div class="tip-dot"></div>Use a mix of uppercase, lowercase, numbers, and symbols.</div>
          <div class="tip-item"><div class="tip-dot"></div>Avoid using personal info like birthdays or names.</div>
          <div class="tip-item"><div class="tip-dot"></div>Don't reuse passwords across different sites.</div>
          <div class="tip-item"><div class="tip-dot"></div>Consider using a password manager to store credentials securely.</div>
        </div>

      </div>
    </main>
  </div>
</div>

<script>
const html = document.documentElement;
if (localStorage.getItem('theme') === 'dark') html.setAttribute('data-theme','dark');
document.getElementById('themeToggle').addEventListener('click', () => {
  const d = html.getAttribute('data-theme') === 'dark';
  html.setAttribute('data-theme', d ? 'light' : 'dark');
  localStorage.setItem('theme', d ? 'light' : 'dark');
});

function togglePw(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

function rule(id, passes) {
  const el = document.getElementById(id);
  el.classList.toggle('pass', passes);
  el.classList.toggle('fail', !passes);
}

function evaluate(val) {
  const hasLen  = val.length >= 8;
  const hasUp   = /[A-Z]/.test(val);
  const hasNum  = /[0-9]/.test(val);
  const hasSpc  = /[^A-Za-z0-9]/.test(val);
  rule('r-length',  hasLen);
  rule('r-upper',   hasUp);
  rule('r-num',     hasNum);
  rule('r-special', hasSpc);

  let score = [hasLen, hasUp, hasNum, hasSpc].filter(Boolean).length;
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthText');
  const levels = [
    ['', 'transparent'],
    ['Weak',   'var(--danger)'],
    ['Fair',   'var(--accent)'],
    ['Good',   'var(--info)'],
    ['Strong', 'var(--success)'],
  ];
  fill.style.width      = (score * 25) + '%';
  fill.style.background = levels[score][1];
  label.textContent     = levels[score][0];
  label.style.color     = levels[score][1];
}

function checkMatch() {
  const nv  = document.getElementById('newPw').value;
  const cv  = document.getElementById('confirmPw').value;
  const hint = document.getElementById('matchHint');
  if (!cv) { hint.textContent = ''; return; }
  if (nv === cv) {
    hint.textContent = '✓ Passwords match';
    hint.style.color = 'var(--success)';
    document.getElementById('confirmPw').classList.add('valid');
    document.getElementById('confirmPw').classList.remove('invalid');
  } else {
    hint.textContent = '✗ Passwords do not match';
    hint.style.color = 'var(--danger)';
    document.getElementById('confirmPw').classList.remove('valid');
    document.getElementById('confirmPw').classList.add('invalid');
  }
}
</script>
</body>
</html>
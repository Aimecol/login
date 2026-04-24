<?php
// ============================================================
//  login.php  — Login page
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';

// Already logged in? Send to dashboard
redirect_if_logged_in();

$error   = '';
$success = get_flash('success') ?? '';

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF token check (simple double-submit pattern)
    if (!isset($_POST['_token']) || $_POST['_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = attempt_login($username, $password);

        if ($result === null) {
            // Success → redirect based on role
            $dest = current_role() === 'admin'
                ? base_url('admin/dashboard.php')
                : base_url('user/dashboard.php');
            header('Location: ' . $dest);
            exit;
        } else {
            $error = $result;
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In — Login System</title>
  <link rel="stylesheet" href="css/main.css"/>
  <style>
    .login-footer {
      text-align: center;
      margin-top: 2rem;
      font-size: 0.78rem;
      color: var(--text-dim);
    }

    .hint-box {
      background: rgba(245,166,35,0.06);
      border: 1px dashed rgba(245,166,35,0.2);
      border-radius: var(--radius-sm);
      padding: 0.85rem 1rem;
      margin-top: 1.5rem;
      font-size: 0.78rem;
      color: var(--text-muted);
      line-height: 1.7;
    }

    .hint-box strong { color: var(--accent); }

    .divider {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin: 1.5rem 0;
      color: var(--text-dim);
      font-size: 0.78rem;
    }

    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    /* Floating label animation on focus */
    .input-wrap input:focus + .focus-bar { width: 100%; }

    /* Spinner for submit button */
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner {
      display: none;
      width: 16px; height: 16px;
      border: 2px solid rgba(0,0,0,0.2);
      border-top-color: #0c0f17;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }

    .btn-primary:active .spinner,
    .btn-primary.loading .spinner { display: block; }
  </style>
</head>
<body>
  <!-- Theme toggle (fixed top-right) -->
  <button class="theme-toggle login-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
    <span class="icon-moon"><?= icon('moon', '', 18) ?></span>
    <span class="icon-sun"><?= icon('sun', '', 18) ?></span>
  </button>

  <div class="page-bg-accent"></div>

  <main class="page-center">
    <div class="login-wrap">

      <!-- Brand -->
      <div class="login-brand">
        <div class="brand-icon" style="color: var(--accent);">
          <?= icon('logo', '', 56) ?>
        </div>
        <h1>Login System</h1>
        <p>Enter your credentials to access your dashboard</p>
      </div>

      <!-- Card -->
      <div class="card">

        <!-- Flash error -->
        <?php if ($error): ?>
        <div class="alert alert-error" role="alert">
          <?= icon('alert-circle', '', 16) ?>
          <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Flash success (e.g. after logout) -->
        <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
          <?= icon('check-circle', '', 16) ?>
          <span><?= e($success) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm" novalidate>
          <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>

          <!-- Username -->
          <div class="form-group">
            <label for="username">Username</label>
            <div class="input-wrap">
              <?= icon('profile', 'input-icon', 18) ?>
              <input
                type="text"
                id="username"
                name="username"
                placeholder="Enter your username"
                value="<?= e($_POST['username'] ?? '') ?>"
                autocomplete="username"
                required
                spellcheck="false"
              />
            </div>
          </div>

          <!-- Password -->
          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <?= icon('lock', 'input-icon', 18) ?>
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
              />
            </div>
          </div>

          <div style="height:0.25rem;"></div>

          <button type="submit" class="btn btn-primary" id="submitBtn">
            <?= icon('key', '', 17) ?>
            <span>Sign In</span>
            <div class="spinner"></div>
          </button>
        </form>

        <!-- Demo credentials hint -->
        <div class="hint-box">
          <strong>Demo Accounts</strong><br/>
          Admin &nbsp;→ username: <strong>Admin</strong> &nbsp;| password: <strong>123</strong><br/>
          User &nbsp;&nbsp;→ username: <strong>Aimecol</strong> &nbsp;&nbsp;| password: <strong>123</strong>
        </div>

      </div><!-- /card -->

      <p class="login-footer">
        &copy; <?= date('Y') ?> Login System.
      </p>

    </div><!-- /login-wrap -->
  </main>

  <script>
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('submitBtn');
      btn.classList.add('loading');
      btn.disabled = true;
    });

    // Theme toggle
    (function() {
      const html = document.documentElement;
      const btn  = document.getElementById('themeToggle');
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
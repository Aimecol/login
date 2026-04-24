<?php
// ============================================================
//  includes/auth.php  — session & authentication helpers
// ============================================================

require_once __DIR__ . '/db.php';

// Start the session once, safely
if (session_status() === PHP_SESSION_NONE) {
    session_name('LSESSID');
    session_start();
}

// ── Core helpers ────────────────────────────────────────────

/** Returns true when a user is logged in. */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/** Returns the current user's role string, or null. */
function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/** Returns the current user's full name, or empty string. */
function current_name(): string {
    return $_SESSION['full_name'] ?? '';
}

/** Returns the current user's initials for the avatar. */
function current_initials(): string {
    return $_SESSION['avatar_initials'] ?? '??';
}

/** Returns the current user's id. */
function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

// ── Redirect guards ─────────────────────────────────────────

/**
 * Redirect to login if not authenticated.
 * Call at the top of every protected page.
 */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

/**
 * Redirect away if the logged-in user doesn't have $role.
 * Call after require_login().
 */
function require_role(string $role): void {
    if (current_role() !== $role) {
        // Wrong dashboard — send them to theirs
        $dest = current_role() === 'admin'
            ? base_url('admin/dashboard.php')
            : base_url('user/dashboard.php');
        header('Location: ' . $dest);
        exit;
    }
}

/**
 * Redirect authenticated users away from the login page.
 */
function redirect_if_logged_in(): void {
    if (is_logged_in()) {
        $dest = current_role() === 'admin'
            ? base_url('admin/dashboard.php')
            : base_url('user/dashboard.php');
        header('Location: ' . $dest);
        exit;
    }
}

// ── Login / Logout ──────────────────────────────────────────

/**
 * Attempt login with username + plain-text password.
 * Returns an error message string on failure, or null on success.
 */
function attempt_login(string $username, string $password): ?string {
    if (trim($username) === '' || trim($password) === '') {
        return 'Please fill in all fields.';
    }

    $db   = db();
    $stmt = $db->prepare(
        'SELECT id, username, password, role, full_name, avatar_initials, is_active
         FROM users WHERE username = ? LIMIT 1'
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return 'Invalid username or password.';
    }

    if (!(int)$user['is_active']) {
        return 'Your account has been disabled. Contact support.';
    }

    if (!password_verify($password, $user['password'])) {
        return 'Invalid username or password.';
    }

    // Regenerate session ID to prevent fixation attacks
    session_regenerate_id(true);

    // Populate session
    $_SESSION['user_id']         = (int)$user['id'];
    $_SESSION['username']        = $user['username'];
    $_SESSION['role']            = $user['role'];
    $_SESSION['full_name']       = $user['full_name'];
    $_SESSION['avatar_initials'] = $user['avatar_initials'];

    // Update last_login
    $upd = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $upd->bind_param('i', $user['id']);
    $upd->execute();
    $upd->close();

    // Log session
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $sid     = session_id();
    $uid     = (int)$user['id'];
    $log     = $db->prepare(
        'INSERT INTO session_log (user_id, session_id, ip_address, user_agent) VALUES (?,?,?,?)'
    );
    $log->bind_param('isss', $uid, $sid, $ip, $ua);
    $log->execute();
    $log->close();

    return null; // success
}

/**
 * Destroy the current session and redirect to login.
 */
function logout(): void {
    if (is_logged_in()) {
        // Mark logout time in session log
        $db  = db();
        $sid = session_id();
        $upd = $db->prepare(
            'UPDATE session_log SET logout_at = NOW() WHERE session_id = ? AND logout_at IS NULL'
        );
        $upd->bind_param('s', $sid);
        $upd->execute();
        $upd->close();
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . base_url('login.php'));
    exit;
}

// ── Utility ─────────────────────────────────────────────────

/**
 * Returns an absolute URL for a path relative to the project root.
 *
 * Strategy: compare the project root directory on disk against the
 * server's document root to derive the URL subfolder reliably.
 * This works whether the project lives at htdocs/ or htdocs/login-system/.
 */
function base_url(string $path = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Project root on disk = the folder that contains includes/auth.php
    // __FILE__ = …/login-system/includes/auth.php  → dirname twice = …/login-system
    $project_root = str_replace('\\', '/', dirname(__DIR__));

    // Document root on disk (normalise slashes)
    $doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    // Derive URL prefix: strip doc_root from project_root
    if ($doc_root !== '' && strpos($project_root, $doc_root) === 0) {
        $prefix = substr($project_root, strlen($doc_root));
    } else {
        // Fallback: use SCRIPT_NAME of the *currently executing* script
        // and walk up to the project root based on directory depth difference.
        $script_dir   = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $script_disk  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $depth        = substr_count(
            str_replace('\\', '/', realpath($script_disk) ?: $script_disk),
            $project_root
        ) ? 0 : (
            // How many levels is SCRIPT_FILENAME below project root?
            substr_count(
                ltrim(str_replace($project_root, '', str_replace('\\', '/', realpath($script_disk) ?: $script_disk)), '/'),
                '/'
            )
        );
        $prefix = rtrim($script_dir, '/');
        for ($i = 0; $i < $depth; $i++) {
            $prefix = dirname($prefix);
        }
        if ($prefix === '.') $prefix = '';
    }

    $prefix = rtrim($prefix, '/');

    return $scheme . '://' . $host . $prefix . '/' . ltrim($path, '/');
}

/**
 * Simple HTML-escape helper.
 */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Flash message helpers (stored in session between redirects).
 */
function set_flash(string $key, string $msg): void {
    $_SESSION['_flash'][$key] = $msg;
}

function get_flash(string $key): ?string {
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}
<?php
// ============================================================
//  admin/database.php  — Database Management
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
require_role('admin');

$db   = db();
$csrf = $_SESSION['csrf_token'] ?? '';

$success = get_flash('success') ?? '';
$error   = get_flash('error')   ?? '';

// ── Handle POST ──────────────────────────────────────────────
$query_result = null;
$query_error  = null;
$query_ran    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || $_POST['_token'] !== $csrf) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: database.php'); exit;
    }
    $action = $_POST['action'] ?? '';

    // Run custom query (SELECT only for safety)
    if ($action === 'run_query') {
        $sql = trim($_POST['sql'] ?? '');
        $query_ran = $sql;
        $sql_upper = strtoupper(ltrim($sql));
        if (str_starts_with($sql_upper, 'SELECT') || str_starts_with($sql_upper, 'SHOW') || str_starts_with($sql_upper, 'DESCRIBE')) {
            $res = $db->query($sql);
            if ($res) {
                $query_result = [];
                while ($row = $res->fetch_assoc()) $query_result[] = $row;
            } else {
                $query_error = $db->error;
            }
        } else {
            $query_error = 'Only SELECT, SHOW, and DESCRIBE queries are allowed for safety.';
        }
    }

    // Optimize tables
    if ($action === 'optimize') {
        $db->query('OPTIMIZE TABLE users');
        $db->query('OPTIMIZE TABLE session_log');
        set_flash('success', 'Tables optimized successfully.');
        header('Location: database.php'); exit;
    }
}

$unread_q = $db->prepare('SELECT COUNT(*) AS c FROM messages WHERE to_id=? AND is_read=0');
$unread_q->bind_param('i', $uid);
$unread_q->execute();
$unread_count = (int)$unread_q->get_result()->fetch_assoc()['c'];
$unread_q->close();

// ── Gather DB info ───────────────────────────────────────────
// Tables info
$tables_info = [];
$tables_res  = $db->query("SHOW TABLE STATUS");
while ($t = $tables_res->fetch_assoc()) {
    $tables_info[$t['Name']] = $t;
}

// DB version
$ver_res = $db->query("SELECT VERSION() AS v");
$db_version = $ver_res->fetch_assoc()['v'] ?? 'Unknown';

// DB name
$db_name_res = $db->query("SELECT DATABASE() AS d");
$db_name = $db_name_res->fetch_assoc()['d'] ?? 'Unknown';

// DB size
$size_res = $db->query("
  SELECT SUM(data_length + index_length) AS size
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
");
$db_size_bytes = (int)($size_res->fetch_assoc()['size'] ?? 0);
$db_size = $db_size_bytes > 1048576
    ? round($db_size_bytes/1048576, 2) . ' MB'
    : round($db_size_bytes/1024, 2) . ' KB';

// Charset / collation
$char_res = $db->query("SELECT @@character_set_database AS charset, @@collation_database AS collation");
$char_row = $char_res->fetch_assoc();

// Table row counts
$total_users_count   = (int)$db->query('SELECT COUNT(*) c FROM users')->fetch_assoc()['c'];
$total_session_count = (int)$db->query('SELECT COUNT(*) c FROM session_log')->fetch_assoc()['c'];

// Columns for each table
function get_columns($db, $table) {
    $res = $db->query("DESCRIBE `{$table}`");
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[] = $r;
    return $cols;
}
$users_cols   = get_columns($db, 'users');
$session_cols = get_columns($db, 'session_log');

// Generate export SQL for users table (structure only for demo)
$export_sql = "-- Login System Database Export\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- Server: MySQL {$db_version}\n\n";
$export_sql .= "-- Table: users\n";
$create_res = $db->query("SHOW CREATE TABLE users");
$create_row = $create_res->fetch_assoc();
$export_sql .= ($create_row['Create Table'] ?? '') . ";\n\n";
$create_res2 = $db->query("SHOW CREATE TABLE session_log");
$create_row2 = $create_res2->fetch_assoc();
$export_sql .= "-- Table: session_log\n";
$export_sql .= ($create_row2['Create Table'] ?? '') . ";\n\n";
$create_res3 = $db->query("SHOW CREATE TABLE notifications");
$create_row3 = $create_res3->fetch_assoc();
$export_sql .= "-- Table: notifications\n";
$export_sql .= ($create_row3['Create Table'] ?? '') . ";\n\n";
$create_res4 = $db->query("SHOW CREATE TABLE messages");
$create_row4 = $create_res4->fetch_assoc();
$export_sql .= "-- Table: messages\n";
$export_sql .= ($create_row4['Create Table'] ?? '') . ";\n\n";
$create_res5 = $db->query("SHOW CREATE TABLE system_settings");
$create_row5 = $create_res5->fetch_assoc();
$export_sql .= "-- Table: system_settings\n";
$export_sql .= ($create_row5['Create Table'] ?? '') . ";\n\n";
$create_res6 = $db->query("SHOW CREATE TABLE permissions");
$create_row6 = $create_res6->fetch_assoc();
$export_sql .= "-- Table: permissions\n";
$export_sql .= ($create_row6['Create Table'] ?? '') . ";\n\n";

$page_title = 'Database';
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
    .db-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
    .unread-dot { display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:var(--danger);color:#fff;font-size:.65rem;font-weight:800;margin-left:.35rem; }
    .db-stat { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); padding:1.25rem 1.5rem; display:flex; align-items:center; gap:1rem; }
    .db-stat-icon { width:42px;height:42px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .db-stat-val  { font-family:var(--font-display);font-size:1.5rem;font-weight:800;line-height:1; }
    .db-stat-lbl  { font-size:0.75rem;color:var(--text-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.04em;margin-top:2px; }

    .db-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem; }
    .db-mono { font-family:monospace; font-size:0.82rem; color:var(--text-muted); }

    .col-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
    .col-table th { text-align:left; padding:0.55rem 1rem; font-size:0.7rem; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--text-dim); border-bottom:1px solid var(--border); background:var(--table-head-bg); }
    .col-table td { padding:0.65rem 1rem; border-bottom:1px solid var(--border); color:var(--text-muted); vertical-align:middle; }
    .col-table tr:last-child td { border-bottom:none; }
    .col-table tbody tr:hover td { background:var(--bg-hover); }

    .key-badge { display:inline-block; padding:1px 7px; border-radius:3px; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
    .key-pri { background:rgba(245,166,35,0.15); color:var(--accent); }
    .key-uni { background:rgba(46,143,196,0.15); color:var(--user-hue); }
    .key-mul { background:rgba(34,184,110,0.1);  color:var(--success); }

    .query-wrap { position:relative; }
    .query-textarea {
      width:100%; min-height:120px;
      background:var(--bg); border:1px solid var(--border-light);
      border-radius:var(--radius-sm); color:var(--text);
      font-family:monospace; font-size:0.88rem;
      padding:0.85rem 1rem; outline:none; resize:vertical;
      transition:border-color 0.2s;
    }
    .query-textarea:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-glow); }
    .query-result-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
    .query-result-table th { text-align:left; padding:0.5rem 0.85rem; font-size:0.7rem; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--text-dim); border-bottom:1px solid var(--border); background:var(--table-head-bg); }
    .query-result-table td { padding:0.6rem 0.85rem; border-bottom:1px solid var(--border); color:var(--text-muted); font-family:monospace; font-size:0.8rem; }

    .export-pre {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 1rem 1.25rem;
      font-family: monospace;
      font-size: 0.75rem;
      color: var(--text-muted);
      overflow-x: auto;
      max-height: 300px;
      overflow-y: auto;
      white-space: pre;
    }
    .info-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
    .info-item2 { background:var(--bg); border:1px solid var(--border); border-radius:var(--radius-sm); padding:0.85rem 1rem; }
    .info-item2 label { font-size:0.7rem; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--text-dim); display:block; margin-bottom:0.3rem; }
    .info-item2 span  { font-size:0.88rem; font-weight:500; color:var(--text); font-family:monospace; }

    @media(max-width:900px){ .db-stats{grid-template-columns:1fr 1fr;} .db-grid{grid-template-columns:1fr;} .info-grid2{grid-template-columns:1fr;} }
    @media(max-width:500px){ .db-stats{grid-template-columns:1fr;} }
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
      <a href="messages.php" class="nav-item"><?= icon('mail') ?>Messages <?php if($unread_count): ?><span class="unread-dot"><?= $unread_count ?></span><?php endif;?></a>
      <div class="nav-section-label">System</div>
      <a href="permissions.php" class="nav-item"><?= icon('shield') ?>Permissions</a>
      <a href="database.php" class="nav-item active"><?= icon('database') ?>Database</a>
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

      <!-- DB Stats -->
      <div class="db-stats">
        <div class="db-stat">
          <div class="db-stat-icon" style="background:var(--accent-glow);color:var(--accent);"><?= icon('database','',20) ?></div>
          <div><div class="db-stat-val"><?= count($tables_info) ?></div><div class="db-stat-lbl">Tables</div></div>
        </div>
        <div class="db-stat">
          <div class="db-stat-icon" style="background:rgba(46,143,196,0.1);color:var(--user-hue);"><?= icon('users','',20) ?></div>
          <div><div class="db-stat-val"><?= $total_users_count ?></div><div class="db-stat-lbl">User Records</div></div>
        </div>
        <div class="db-stat">
          <div class="db-stat-icon" style="background:rgba(34,184,110,0.1);color:var(--success);"><?= icon('activity','',20) ?></div>
          <div><div class="db-stat-val"><?= $total_session_count ?></div><div class="db-stat-lbl">Session Records</div></div>
        </div>
        <div class="db-stat">
          <div class="db-stat-icon" style="background:rgba(212,74,74,0.1);color:var(--admin-hue);"><?= icon('reports','',20) ?></div>
          <div><div class="db-stat-val"><?= $db_size ?></div><div class="db-stat-lbl">DB Size</div></div>
        </div>
      </div>

      <!-- DB Info + Actions -->
      <div class="db-grid">
        <!-- Server info -->
        <div class="section-card">
          <div class="section-card-header">
            <h2><?= icon('database') ?> Server Information</h2>
            <form method="POST">
              <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
              <input type="hidden" name="action" value="optimize"/>
              <button type="submit" class="btn btn-outline" style="font-size:0.8rem;padding:0.45rem 0.9rem;"><?= icon('settings','',14) ?> Optimize Tables</button>
            </form>
          </div>
          <div style="padding:1.5rem;">
            <div class="info-grid2">
              <div class="info-item2"><label>Database</label><span><?= e($db_name) ?></span></div>
              <div class="info-item2"><label>MySQL Version</label><span><?= e($db_version) ?></span></div>
              <div class="info-item2"><label>Character Set</label><span><?= e($char_row['charset'] ?? '—') ?></span></div>
              <div class="info-item2"><label>Collation</label><span><?= e($char_row['collation'] ?? '—') ?></span></div>
              <div class="info-item2"><label>Total Size</label><span><?= e($db_size) ?></span></div>
              <div class="info-item2"><label>Tables</label><span><?= count($tables_info) ?></span></div>
            </div>
          </div>
        </div>

        <!-- Table status -->
        <div class="section-card">
          <div class="section-card-header">
            <h2><?= icon('reports') ?> Table Status</h2>
          </div>
          <div style="overflow-x:auto;">
            <table class="col-table">
              <thead>
                <tr><th>Table</th><th>Rows</th><th>Engine</th><th>Data Size</th><th>Collation</th></tr>
              </thead>
              <tbody>
                <?php foreach ($tables_info as $name => $info): ?>
                <tr>
                  <td style="font-weight:600;color:var(--text);font-family:monospace;"><?= e($name) ?></td>
                  <td><?= number_format((int)($info['Rows']??0)) ?></td>
                  <td><span style="font-family:monospace;font-size:0.78rem;"><?= e($info['Engine']??'—') ?></span></td>
                  <td style="font-family:monospace;font-size:0.78rem;">
                    <?php
                    $bytes = (int)($info['Data_length']??0) + (int)($info['Index_length']??0);
                    echo $bytes > 1024 ? round($bytes/1024,1).' KB' : $bytes.' B';
                    ?>
                  </td>
                  <td style="font-size:0.75rem;color:var(--text-dim);"><?= e(substr($info['Collation']??'',0,20)) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Query runner -->
      <div class="section-card" style="margin-bottom:1.5rem;">
        <div class="section-card-header">
          <h2><?= icon('reports') ?> Query Runner</h2>
          <span style="font-size:0.75rem;color:var(--text-dim);background:rgba(212,74,74,0.08);border:1px solid rgba(212,74,74,0.2);padding:2px 8px;border-radius:3px;">SELECT / SHOW / DESCRIBE only</span>
        </div>
        <div style="padding:1.5rem;">
          <form method="POST">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>"/>
            <input type="hidden" name="action" value="run_query"/>
            <div style="margin-bottom:0.85rem;">
              <textarea class="query-textarea" name="sql" placeholder="SELECT * FROM users LIMIT 10;"><?= e($query_ran) ?></textarea>
            </div>
            <div style="display:flex;gap:0.75rem;align-items:center;margin-bottom:1rem;">
              <button type="submit" class="btn btn-primary" style="font-size:0.9rem;"><?= icon('activity','',16) ?> Run Query</button>
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <?php
                $shortcuts = [
                    'SELECT * FROM users LIMIT 20',
                    'SELECT * FROM session_log ORDER BY login_at DESC LIMIT 20',
                    'SHOW TABLES',
                    'SHOW PROCESSLIST',
                ];
                foreach ($shortcuts as $sc): ?>
                <button type="button" class="btn btn-outline" style="font-size:0.75rem;padding:0.35rem 0.7rem;"
                  onclick="document.querySelector('.query-textarea').value = <?= json_encode($sc) ?>"><?= e($sc) ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          </form>

          <?php if ($query_error): ?>
          <div class="alert alert-error"><?= icon('alert-circle','',16) ?> <span><?= e($query_error) ?></span></div>
          <?php elseif ($query_result !== null): ?>
          <div style="margin-top:0.5rem;">
            <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.5rem;"><?= count($query_result) ?> row(s) returned</div>
            <?php if (count($query_result) > 0): ?>
            <div style="overflow-x:auto;border:1px solid var(--border);border-radius:var(--radius-sm);">
              <table class="query-result-table">
                <thead>
                  <tr><?php foreach (array_keys($query_result[0]) as $col): ?><th><?= e($col) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                  <?php foreach ($query_result as $row): ?>
                  <tr><?php foreach ($row as $val): ?><td><?= e((string)($val ?? 'NULL')) ?></td><?php endforeach; ?></tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <p style="font-size:0.85rem;color:var(--text-dim);">Query returned no rows.</p>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- SQL Export -->
      <div class="section-card">
        <div class="section-card-header">
          <h2><?= icon('reports') ?> SQL Schema Export</h2>
          <button onclick="copySQL(event)" class="btn btn-outline" style="font-size:0.8rem;padding:0.45rem 0.9rem;"><?= icon('reports','',14) ?> Copy SQL</button>
        </div>
        <div style="padding:1.5rem;">
          <pre class="export-pre" id="exportPre"><?= e($export_sql) ?></pre>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function copySQL(event) {
  const text = document.getElementById('exportPre').textContent;
  const btn  = event && event.target ? event.target.closest('button') : null;
  if (!navigator.clipboard) {
    // Fallback for non-HTTPS or older browsers
    const ta = document.createElement('textarea');
    ta.value = text; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    if (btn) { const o = btn.innerHTML; btn.textContent = '✓ Copied!'; setTimeout(() => btn.innerHTML = o, 2000); }
    return;
  }
  navigator.clipboard.writeText(text).then(() => {
    if (btn) { const orig = btn.innerHTML; btn.textContent = '✓ Copied!'; setTimeout(() => btn.innerHTML = orig, 2000); }
  }).catch(() => alert('Copy failed — please select and copy the text manually.'));
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
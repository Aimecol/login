<?php

require_once __DIR__ . '/includes/db.php';

$accounts = [
    ['username' => 'Admin', 'password' => '123'],
    ['username' => 'Aimecol',  'password' => '123'],
];

$db   = db();
$msgs = [];

foreach ($accounts as $acc) {
    $hash = password_hash($acc['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare('UPDATE users SET password = ? WHERE username = ?');
    $stmt->bind_param('ss', $hash, $acc['username']);
    $ok   = $stmt->execute();
    $stmt->close();
    $msgs[] = ($ok ? '✅' : '❌') . ' ' . htmlspecialchars($acc['username']) . ' → ' . htmlspecialchars($hash);
}

echo '<!DOCTYPE html><html><head><title>Seed Passwords</title>
<style>body{font-family:monospace;background:#0c0f17;color:#e8edf8;padding:2rem;}
pre{background:#131824;border:1px solid #1e2840;padding:1.5rem;border-radius:8px;line-height:2;}
h2{color:#f5a623;}p{color:#e05c5c;margin-top:1rem;font-weight:bold;}</style></head><body>';
echo '<h2>Password Re-seeder</h2><pre>' . implode("\n", $msgs) . '</pre>';
echo '<p>⚠️  Delete or restrict seed_passwords.php immediately after use!</p>';
echo '</body></html>';

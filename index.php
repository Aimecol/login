<?php
// ============================================================
//  index.php  — Entry point: redirect to login
// ============================================================
require_once __DIR__ . '/includes/auth.php';
redirect_if_logged_in();
header('Location: login.php');
exit;

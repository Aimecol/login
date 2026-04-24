<?php
// ============================================================
//  logout.php  — Destroys session and redirects to login
// ============================================================
require_once __DIR__ . '/includes/auth.php';

// Require a valid POST + CSRF to prevent logout CSRF attacks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['_token']) && $_POST['_token'] === ($_SESSION['csrf_token'] ?? '')) {
        set_flash('success', 'You have been signed out successfully.');
        logout(); // terminates script
    }
}

// GET fallback (just redirect to login; session stays intact)
header('Location: ' . base_url('login.php'));
exit;

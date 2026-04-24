<?php
// ============================================================
//  includes/db.php  — MySQLi connection (no PDO)
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // XAMPP default
define('DB_PASS', '');            // XAMPP default (empty)
define('DB_NAME', 'login_system');
define('DB_PORT', 3306);

/**
 * Returns a singleton MySQLi connection.
 * Kills the script with a sanitised error on failure.
 */
function db(): mysqli {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

        if ($conn->connect_error) {
            // Never expose raw error to browser in production
            error_log('DB connect error: ' . $conn->connect_error);
            die('Database connection failed. Check your XAMPP MySQL server.');
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}

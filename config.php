<?php
// config.php
// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Session configuration
ini_set('session.cookie_secure', '1'); // Enforce HTTPS (set to 0 for local testing without HTTPS)
ini_set('session.cookie_httponly', '1'); // Prevent JavaScript access to session cookies
ini_set('session.use_strict_mode', '1'); // Prevent session fixation
ini_set('session.sid_length', '48'); // Longer session ID for security
ini_set('session.sid_bits_per_character', '6');

// Check session save path
if (!is_writable(session_save_path())) {
    die('Session save path is not writable: ' . session_save_path());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'cryp');
define('DB_USER', 'root'); // Replace with your MySQL username
define('DB_PASS', '2020'); // Replace with your MySQL password

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
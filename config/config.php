<?php
/**
 * Application Configuration
 * Human Care Hospital Management System
 */

/* =========================
   SESSION SECURITY (START FIRST)
========================= */
if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookies
    ini_set('session.cookie_httponly', 1);   // Prevent JS access to session cookie
    ini_set('session.cookie_samesite', 'Strict'); // Prevent CSRF via cookies
    ini_set('session.use_strict_mode', 1);  // Reject uninitialized session IDs
    ini_set('session.use_only_cookies', 1); // No session ID in URLs
    
    // Enable secure cookies if on HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// Prevent direct access
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

/* =========================
   APP CONFIG
========================= */
define('APP_NAME', 'Human Care');

// Auto-detect base URL or fall back to configured value
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('APP_URL', $protocol . '://' . $host . '/vscode');
define('BASE_URL', APP_URL . '/');

define('EMAIL_FROM', 'noreply@humancare.com');
define('EMAIL_FROM_NAME', 'Human Care Hospital');
define('ADMIN_EMAIL', 'admin@humancare.com');

/* =========================
   ENVIRONMENT (PRODUCTION / DEVELOPMENT)
========================= */
// Set to 'production' before deploying
define('APP_ENV', 'development');

if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

/* =========================
   CSRF TOKEN HELPERS
========================= */

/**
 * Generate or retrieve the CSRF token for the current session.
 */
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Output a hidden CSRF input field for use inside <form> tags.
 */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Validate the submitted CSRF token against the session token.
 * Call this at the top of every POST handler.
 */
function csrf_validate(): bool {
    $token = $_POST['_csrf_token'] ?? '';
    return hash_equals(csrf_token(), $token);
}

/* =========================
   SECURITY HELPERS
========================= */

/**
 * Sanitize a string for safe HTML output.
 */
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

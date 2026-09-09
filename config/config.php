<?php
/**
 * Application Configuration
 * Human Care Hospital Management System
 */

/* =========================
   LOAD ENVIRONMENT
========================= */

require_once __DIR__ . '/env.php';

/* =========================
   SESSION SECURITY (START FIRST)
========================= */

if (session_status() === PHP_SESSION_NONE) {

    // Harden session cookies
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);

    // Enable secure cookies if on HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }

    session_start();
}

/* =========================
   APP INITIALIZATION
========================= */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

/* =========================
   APP CONFIG
========================= */

define('APP_NAME', env('APP_NAME', 'Human Care'));

define(
    'APP_URL',
    rtrim(env('APP_URL', 'http://localhost/Human%20Care'), '/')
);

define('BASE_URL', APP_URL . '/');

/* =========================
   EMAIL CONFIG
========================= */

define('EMAIL_FROM', env('EMAIL_FROM', ''));
define('EMAIL_FROM_NAME', env('EMAIL_FROM_NAME', 'Human Care Hospital'));
define('ADMIN_EMAIL', env('ADMIN_EMAIL', ''));

/* =========================
   ENVIRONMENT
========================= */

define('APP_ENV', env('APP_ENV', 'development'));

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
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

/**
 * Output a hidden CSRF input field for use inside <form> tags.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' .
        csrf_token() .
        '">';
}

/**
 * Validate the submitted CSRF token against the session token.
 */
function csrf_validate(): bool
{
    $token = $_POST['_csrf_token'] ?? '';

    return hash_equals(csrf_token(), $token);
}

/* =========================
   SECURITY HELPERS
========================= */

/**
 * Sanitize a string for safe HTML output.
 */
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/* =========================
   API KEYS
========================= */

define('GROQ_API_KEY', env('GROQ_API_KEY', ''));
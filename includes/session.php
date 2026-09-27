
<?php
/**
 * Human Care - Central Session Management
 */

// Start session only if it is not already active
if (session_status() !== PHP_SESSION_ACTIVE) {

    // Session security settings (must be set before session_start)
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $is_https = (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

/**
 * Check whether a patient or doctor is logged in.
 */
function isUserLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['user_type'])
        && in_array($_SESSION['user_type'], ['patient', 'doctor'], true);
}

/**
 * Check whether an admin is logged in.
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_logged_in'])
        && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require a logged-in patient or doctor.
 */
function requireUserLogin(): void
{
    if (!isUserLoggedIn()) {
        header('Location: /Human%20Care/login.php');
        exit;
    }
}

/**
 * Require a logged-in admin.
 */
function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: /Human%20Care/admin/admin_login.php');
        exit;
    }
}

/**
 * Destroy the current session and its cookie.
 */
function destroyUserSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }

    session_destroy();
}
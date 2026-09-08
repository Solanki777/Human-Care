<?php

/**
 * CSRF Protection Helper
 */

/**
 * Start session if it has not already been started.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/**
 * Generate and return the CSRF token.
 */
function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['csrf_token'];
}


/**
 * Return the CSRF token for use in forms.
 */
function getCSRFToken(): string
{
    return generateCSRFToken();
}


/**
 * Verify submitted CSRF token.
 */
function verifyCSRFToken(?string $token): bool
{
    if (
        empty($token) ||
        empty($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


/**
 * Generate a new CSRF token.
 *
 * Useful after successful operations
 * or when moving to another verification step.
 */
function regenerateCSRFToken(): string
{
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );

    return $_SESSION['csrf_token'];
}
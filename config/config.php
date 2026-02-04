<?php
if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

// Prevent direct access
if (!defined('APP_INIT')) {
   define('APP_INIT', true);
}

/* =========================
   SESSION (START FIRST)
========================= */
if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

/* =========================
   APP CONFIG
========================= */

define('APP_NAME', 'Human Care');
define('APP_URL', 'http://localhost/vscode');


define('EMAIL_FROM', 'noreply@humancare.com');
define('EMAIL_FROM_NAME', 'Human Care Hospital');

define('BASE_URL', 'http://localhost/vscode/');
define('ADMIN_EMAIL', 'admin@humancare.com');

/* =========================
   ERROR REPORTING (DEV)
========================= */
error_reporting(E_ALL);
ini_set('display_errors', 1);

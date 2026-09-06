<?php

class Auth
{
    /**
     * Check if a user is logged in, optionally verifying user type.
     */
    public static function check($userType = null)
    {
        // Admin check
        if ($userType === 'admin') {
            return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
        }

        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        if ($userType && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== $userType)) {
            return false;
        }

        return true;
    }

    /**
     * Require a specific user type to be logged in, or redirect to login.
     */
    public static function require($userType = null)
    {
        if ($userType === 'admin') {
            if (!self::check('admin')) {
                header("Location: admin_login.php");
                exit();
            }
            return;
        }

        if (!self::check($userType)) {
            header("Location: login.php");
            exit();
        }
    }

    public static function id()
    {
        return $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
    }

    public static function login($id, $type, $name = null)
    {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $id;
        $_SESSION['user_type'] = $type;
        $_SESSION['user_name'] = $name;
    }

    /**
     * Login as admin with proper session variables.
     */
    public static function loginAdmin($id, $name, $role)
    {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = $id;
        $_SESSION['admin_name'] = $name;
        $_SESSION['admin_role'] = $role;
        $_SESSION['admin_logged_in'] = true;
    }

    public static function logout()
    {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }

    public static function logoutAdmin()
    {
        session_unset();
        session_destroy();
        header("Location: admin_login.php");
        exit();
    }
}

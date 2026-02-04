<?php

class Auth
{
    public static function check($userType = null)
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        if ($userType && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== $userType)) {
            return false;
        }

        return true;
    }

    public static function require($userType = null)
    {
        if (!self::check($userType)) {
            header("Location: login.php");
            exit();
        }
    }

    public static function id()
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function login($id, $type, $name = null)
    {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $id;
        $_SESSION['user_type'] = $type;
        $_SESSION['user_name'] = $name;
    }

    public static function logout()
    {
        session_destroy();
        header("Location: login.php");
        exit();
    }
}

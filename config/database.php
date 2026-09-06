<?php
/**
 * Database Configuration
 * Centralized database connection management
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from project root .env
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

class Database {
    private static $connections = [];

    /**
     * Get environment variable
     */
    private static function env($key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    /**
     * Get database connection
     * @param string $type 'admin', 'patients', or 'doctors'
     * @return mysqli
     */
    public static function getConnection($type = 'admin') {

        // Return existing connection if available
        if (isset(self::$connections[$type])) {
            return self::$connections[$type];
        }

        // Database credentials from .env
        $host = self::env('DB_HOST', 'localhost');
        $username = self::env('DB_USERNAME', 'root');
        $password = self::env('DB_PASSWORD', '');

        // Determine database name
        $dbname = match($type) {
            'admin' => self::env('DB_ADMIN'),
            'patients' => self::env('DB_PATIENTS'),
            'doctors' => self::env('DB_DOCTORS'),
            default => throw new Exception("Invalid database type: $type")
        };

        // Make sure database name exists
        if (empty($dbname)) {
            throw new Exception("Database name not configured for type: $type");
        }

        // Create connection
        $conn = new mysqli(
            $host,
            $username,
            $password,
            $dbname
        );

        if ($conn->connect_error) {
            error_log(
                "Database connection failed for {$type}: " .
                $conn->connect_error
            );

            throw new Exception("Database connection failed");
        }

        // Set charset
        $conn->set_charset("utf8mb4");

        // Store connection
        self::$connections[$type] = $conn;

        return $conn;
    }

    /**
     * Close all connections
     */
    public static function closeAll() {
        foreach (self::$connections as $conn) {
            $conn->close();
        }

        self::$connections = [];
    }
}
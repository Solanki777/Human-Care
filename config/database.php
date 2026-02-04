<?php
/**
 * Database Configuration
 * Centralized database connection management
 */

class Database {
    private static $connections = [];
    
    // Database credentials
    private const HOST = 'localhost';
    private const USERNAME = 'root';
    private const PASSWORD = '';
    
    // Database names
    private const DB_ADMIN = 'human_care_admin';
    private const DB_PATIENTS = 'human_care_patients';
    private const DB_DOCTORS = 'human_care_doctors';
    
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
        
        // Determine database name
        $dbname = match($type) {
            'admin' => self::DB_ADMIN,
            'patients' => self::DB_PATIENTS,
            'doctors' => self::DB_DOCTORS,
            default => throw new Exception("Invalid database type: $type")
        };
        
        // Create new connection
        $conn = new mysqli(self::HOST, self::USERNAME, self::PASSWORD, $dbname);
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
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
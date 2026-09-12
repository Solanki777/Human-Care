<?php

require_once __DIR__ . '/../config/database.php';

try {
    $connPatient = Database::getConnection('patients');
    $connDoctor  = Database::getConnection('doctors');
} catch (Exception $e) {
    error_log("Login database connection failed: " . $e->getMessage());
    die("Database connection failed");
}

if ($connPatient->connect_error || $connDoctor->connect_error) {
    die("Database connection failed");
}
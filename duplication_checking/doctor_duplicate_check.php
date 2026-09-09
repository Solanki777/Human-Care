<?php

function checkDoctorDuplicates($email, $phone, $licenseNumber)
{
    require_once __DIR__ . '/../config/database.php';

    try {
        $conn = Database::getConnection('doctors');
    } catch (Exception $e) {
        $result['error'] = "Unable to check doctor records.";
        return $result;
    }

    $result = [
        'email_exists' => false,
        'phone_exists' => false,
        'license_exists' => false,
        'error' => ''
    ];

    
    if ($conn->connect_error) {
        $result['error'] = "Unable to check doctor records.";
        return $result;
    }

    $conn->set_charset("utf8mb4");


    // Check email
    $stmt = $conn->prepare(
        "SELECT id
         FROM doctors
         WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
         LIMIT 1"
    );

    if (!$stmt) {
        $result['error'] = "Unable to check doctor email.";
        $conn->close();
        return $result;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $result['email_exists'] = true;
    }

    $stmt->close();


    // Check phone
    $stmt = $conn->prepare(
        "SELECT id
         FROM doctors
         WHERE TRIM(phone) = TRIM(?)
         LIMIT 1"
    );

    if (!$stmt) {
        $result['error'] = "Unable to check doctor phone.";
        $conn->close();
        return $result;
    }

    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $result['phone_exists'] = true;
    }

    $stmt->close();


    // Check license
    $stmt = $conn->prepare(
        "SELECT id
         FROM doctors
         WHERE TRIM(license_number) = TRIM(?)
         LIMIT 1"
    );

    if (!$stmt) {
        $result['error'] = "Unable to check doctor license.";
        $conn->close();
        return $result;
    }

    $stmt->bind_param("s", $licenseNumber);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $result['license_exists'] = true;
    }

    $stmt->close();
    $conn->close();

    return $result;
}
<?php

function checkPatientDuplicates($email, $phone)
{
    require_once __DIR__ . '/../config/database.php';

    try {
        $conn = Database::getConnection('patients');
    } catch (Exception $e) {
        $result['error'] = "Unable to check patient records.";
        return $result;
    }
    
    $result = [
        'email_exists' => false,
        'phone_exists' => false,
        'error' => ''
    ];

    

    if ($conn->connect_error) {
        $result['error'] = "Unable to check patient records.";
        return $result;
    }

    $conn->set_charset("utf8mb4");

    // Check email
    $stmt = $conn->prepare(
        "SELECT id
         FROM patients
         WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
         LIMIT 1"
    );

    if (!$stmt) {
        $result['error'] = "Unable to check patient email.";
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
         FROM patients
         WHERE TRIM(phone) = TRIM(?)
         LIMIT 1"
    );

    if (!$stmt) {
        $result['error'] = "Unable to check patient phone.";
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
    $conn->close();

    return $result;
}
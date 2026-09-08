<?php

function checkPatientDuplicates($email, $phone)
{
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "if0_42370337_human_care_patients";

    $result = [
        'email_exists' => false,
        'phone_exists' => false,
        'error' => ''
    ];

    $conn = new mysqli(
        $servername,
        $username,
        $password,
        $database
    );

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
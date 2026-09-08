<?php

/**
 * Validate doctor registration data.
 *
 * This file only validates doctor form data.
 * It does NOT insert anything into the database.
 */
function validateDoctorRegistration(
    string $firstName,
    string $lastName,
    string $email,
    string $phone,
    string $dob,
    string $gender,
    string $bloodGroup,
    string $passwordInput,
    string $confirmPassword,
    string $licenseNumber,
    string $specialization
): array {

    $errors = [];

    // ------------------------------------------------------------
    // Allowed values
    // ------------------------------------------------------------

    $allowedGenders = [
        'male',
        'female',
        'other'
    ];

    $allowedBloodGroups = [
        '',
        'A+',
        'A-',
        'B+',
        'B-',
        'AB+',
        'AB-',
        'O+',
        'O-'
    ];

    $allowedSpecializations = [
        'general',
        'cardiology',
        'dermatology',
        'neurology',
        'orthopedics',
        'pediatrics',
        'psychiatry',
        'surgery',
        'other'
    ];


    // ------------------------------------------------------------
    // First name
    // ------------------------------------------------------------

    if (!preg_match("/^[a-zA-Z\s\-']{1,50}$/", $firstName)) {

        $errors[] =
            "First name contains invalid characters or is too long.";
    }


    // ------------------------------------------------------------
    // Last name
    // ------------------------------------------------------------

    if (!preg_match("/^[a-zA-Z\s\-']{1,50}$/", $lastName)) {

        $errors[] =
            "Last name contains invalid characters or is too long.";
    }


    // ------------------------------------------------------------
    // Email
    // ------------------------------------------------------------

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 100
    ) {

        $errors[] =
            "Please enter a valid email address.";
    }


    // ------------------------------------------------------------
    // Phone
    // ------------------------------------------------------------

    if (!preg_match("/^[6-9][0-9]{9}$/", $phone)) {

        $errors[] =
            "Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.";
    }


    // ------------------------------------------------------------
    // Date of birth / Age
    // ------------------------------------------------------------

    $dobTimestamp = strtotime($dob);

    if (
        $dob === '' ||
        $dobTimestamp === false
    ) {

        $errors[] =
            "Please provide a valid date of birth.";

    } else {

        try {

            $dobDate = new DateTime($dob);
            $today = new DateTime('today');

            if ($dobDate > $today) {

                $errors[] =
                    "Date of birth cannot be in the future.";

            } else {

                $age = $dobDate->diff($today)->y;

                if ($age < 18) {

                    $errors[] =
                        "You must be 18 years or older to register.";

                } elseif ($age > 120) {

                    $errors[] =
                        "Please provide a valid date of birth.";
                }
            }

        } catch (Exception $e) {

            $errors[] =
                "Please provide a valid date of birth.";
        }
    }


    // ------------------------------------------------------------
    // Gender
    // ------------------------------------------------------------

    if (!in_array($gender, $allowedGenders, true)) {

        $errors[] =
            "Please select a valid gender.";
    }


    // ------------------------------------------------------------
    // Blood group
    // ------------------------------------------------------------

    if (!in_array($bloodGroup, $allowedBloodGroups, true)) {

        $errors[] =
            "Please select a valid blood group.";
    }


    // ------------------------------------------------------------
    // Password
    // ------------------------------------------------------------

    if (
        strlen($passwordInput) < 8 ||
        !preg_match('/[A-Z]/', $passwordInput) ||
        !preg_match('/[a-z]/', $passwordInput) ||
        !preg_match('/[0-9]/', $passwordInput)
    ) {

        $errors[] =
            "Password must be at least 8 characters and include uppercase, lowercase, and a number.";

    } elseif ($passwordInput !== $confirmPassword) {

        $errors[] =
            "Passwords do not match!";
    }


    // ------------------------------------------------------------
    // Medical license
    // ------------------------------------------------------------

    if (!preg_match("/^[a-zA-Z0-9\-\/]{3,50}$/", $licenseNumber)) {

        $errors[] =
            "Please enter a valid medical license number.";
    }


    // ------------------------------------------------------------
    // Specialization
    // ------------------------------------------------------------

    if (!in_array(
        $specialization,
        $allowedSpecializations,
        true
    )) {

        $errors[] =
            "Please select a valid specialization.";
    }


    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
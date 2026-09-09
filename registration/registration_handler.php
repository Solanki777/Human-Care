<?php

require_once __DIR__ . '/../duplication_checking/patient_duplicate_check.php';
require_once __DIR__ . '/../duplication_checking/doctor_duplicate_check.php';

require_once __DIR__ . '/../registration_validation/patient_registration_validation.php';
require_once __DIR__ . '/../registration_validation/doctor_registration_validation.php';

require_once __DIR__ . '/photo_handler.php';
require_once __DIR__ . '/otp_handler.php';

function processRegistration(array $post, array $files): array
{
    $result = [
        'success' => false,

        'errors' => [
            'general' => '',
            'email' => '',
            'phone' => '',
            'password' => '',
            'file' => ''
        ],

        'old' => [
            'firstName' => '',
            'lastName' => '',
            'email' => '',
            'phone' => '',
            'dob' => '',
            'gender' => '',
            'bloodGroup' => '',
            'userType' => 'patient',
            'licenseNumber' => '',
            'specialization' => ''
        ]
    ];

    /*
    |--------------------------------------------------------------------------
    | Read form data
    |--------------------------------------------------------------------------
    */

    $firstName = trim($post['firstName'] ?? '');
    $lastName = trim($post['lastName'] ?? '');
    $email = trim($post['email'] ?? '');
    $phone = trim($post['phone'] ?? '');
    $dob = trim($post['dob'] ?? '');
    $gender = trim($post['gender'] ?? '');
    $bloodGroup = trim($post['bloodGroup'] ?? '');

    $passwordInput = $post['password'] ?? '';
    $confirmPassword = $post['confirmPassword'] ?? '';

    $userType = trim($post['userType'] ?? '');

    $termsAccepted =
        isset($post['terms']) &&
        $post['terms'] === '1';

    $licenseNumber =
        trim($post['licenseNumber'] ?? '');

    $specialization =
        trim($post['specialization'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Preserve old form values
    |--------------------------------------------------------------------------
    */

    $result['old'] = [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'dob' => $dob,
        'gender' => $gender,
        'bloodGroup' => $bloodGroup,
        'userType' => $userType,
        'licenseNumber' => $licenseNumber,
        'specialization' => $specialization
    ];


    /*
    |--------------------------------------------------------------------------
    | Registration Validation
    |--------------------------------------------------------------------------
    */

    if ($userType === 'patient') {

        $validationResult =
            validatePatientRegistration(
                $firstName,
                $lastName,
                $email,
                $phone,
                $dob,
                $gender,
                $bloodGroup,
                $passwordInput,
                $confirmPassword
            );

    } elseif ($userType === 'doctor') {

        $validationResult =
            validateDoctorRegistration(
                $firstName,
                $lastName,
                $email,
                $phone,
                $dob,
                $gender,
                $bloodGroup,
                $passwordInput,
                $confirmPassword,
                $licenseNumber,
                $specialization
            );

    } else {

        $validationResult = [
            'valid' => false,
            'errors' => [
                'Please select a valid account type.'
            ]
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Handle Validation Errors
    |--------------------------------------------------------------------------
    */

    if (!$validationResult['valid']) {

        $result['errors']['general'] =
            implode(
                ' ',
                $validationResult['errors']
            );

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Terms & Conditions
    |--------------------------------------------------------------------------
    */

    if (!$termsAccepted) {

        $result['errors']['general'] =
            "You must agree to the Terms & Conditions "
            . "and Privacy Policy.";

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Checking
    |--------------------------------------------------------------------------
    */

    if ($userType === 'patient') {

        $duplicateResult =
            checkPatientDuplicates(
                $email,
                $phone
            );

    } else {

        $duplicateResult =
            checkDoctorDuplicates(
                $email,
                $phone,
                $licenseNumber
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Errors
    |--------------------------------------------------------------------------
    */

    if (!empty($duplicateResult['error'])) {

        $result['errors']['general'] =
            $duplicateResult['error'];

        return $result;
    }

    if (!empty($duplicateResult['email_exists'])) {

        $result['errors']['email'] =
            "This email address is already registered.";
    }

    if (!empty($duplicateResult['phone_exists'])) {

        $result['errors']['phone'] =
            "This phone number is already registered.";
    }

    if (
        $userType === 'doctor' &&
        !empty($duplicateResult['license_exists'])
    ) {

        $result['errors']['general'] =
            "This medical license number is already registered.";
    }


    /*
    |--------------------------------------------------------------------------
    | Stop if Duplicate Found
    |--------------------------------------------------------------------------
    */

    if (
        !empty($result['errors']['general']) ||
        !empty($result['errors']['email']) ||
        !empty($result['errors']['phone'])
    ) {
        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Doctor Verification Photo
    |--------------------------------------------------------------------------
    */

    $verificationPhotoTemp = '';
    $verificationPhotoType = '';

    if ($userType === 'doctor') {

        $photoResult =
            handleDoctorVerificationPhoto(
                $files['verificationPhoto'] ?? []
            );

        if (!$photoResult['success']) {

            $result['errors']['file'] =
                $photoResult['error'];

            return $result;
        }

        $verificationPhotoTemp =
            $photoResult['filename'];

        $verificationPhotoType =
            $photoResult['mime_type'];
    }


    /*
    |--------------------------------------------------------------------------
    | Start OTP Verification
    |--------------------------------------------------------------------------
    */

    startOTPVerification([

        'user_type' => $userType,

        'email' => $email,
        'phone' => $phone,

        'first_name' => $firstName,
        'last_name' => $lastName,

        'dob' => $dob,
        'gender' => $gender,
        'blood_group' => $bloodGroup,

        'password' => $passwordInput,

        'license_number' => $licenseNumber,
        'specialization' => $specialization,

        'verification_photo_temp' =>
            $verificationPhotoTemp,

        'verification_photo_type' =>
            $verificationPhotoType
    ]);


    /*
    |--------------------------------------------------------------------------
    | Registration Started Successfully
    |--------------------------------------------------------------------------
    */

    regenerateCSRFToken();

    $result['success'] = true;

    return $result;
}
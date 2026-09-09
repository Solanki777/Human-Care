<?php

function startOTPVerification(array $data): void
{
    $emailOtp = (string) random_int(100000, 999999);
    $phoneOtp = (string) random_int(100000, 999999);

    $expiresAt = time() + 600; // 10 minutes

    $_SESSION['pending_verification'] = [

        'user_type' => $data['user_type'],

        'email' => $data['email'],
        'phone' => $data['phone'],

        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],

        'dob' => $data['dob'],
        'gender' => $data['gender'],
        'blood_group' => $data['blood_group'],

        'password_hash' => password_hash(
            $data['password'],
            PASSWORD_DEFAULT
        ),

        'license_number' =>
            $data['user_type'] === 'doctor'
                ? $data['license_number']
                : '',

        'specialization' =>
            $data['user_type'] === 'doctor'
                ? $data['specialization']
                : '',

        'verification_photo_temp' =>
            $data['verification_photo_temp'] ?? '',

        'verification_photo_type' =>
            $data['verification_photo_type'] ?? '',

        'email_otp_hash' => password_hash(
            $emailOtp,
            PASSWORD_DEFAULT
        ),

        'phone_otp_hash' => password_hash(
            $phoneOtp,
            PASSWORD_DEFAULT
        ),

        // Temporary values used by verify.php
        'email_otp' => $emailOtp,
        'phone_otp' => $phoneOtp,

        'email_otp_expires_at' => $expiresAt,
        'phone_otp_expires_at' => $expiresAt,

        'created_at' => time()
    ];
}
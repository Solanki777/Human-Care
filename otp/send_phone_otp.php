<?php

/**
 * Send Phone OTP
 *
 * If SMS API credentials are configured, the OTP is sent
 * through the configured SMS provider.
 *
 * If SMS API is not configured, the OTP is logged for
 * development/fallback purposes.
 *
 * @param string $phone
 * @param string $otp
 * @return bool
 */

require_once __DIR__ . '/../config/env.php';


function sendPhoneOTP(string $phone, string $otp): bool
{
    // Read SMS configuration from .env
    $apiUrl = env('SMS_API_URL', '');
    $apiKey = env('SMS_API_KEY', '');


    /*
     * ---------------------------------------------------------
     * API NOT CONFIGURED
     * ---------------------------------------------------------
     *
     * Development fallback.
     */
    if (empty($apiUrl) || empty($apiKey)) {

        error_log(
            "HUMAN CARE Phone OTP for {$phone}: {$otp}"
        );

        return true;
    }


    /*
     * ---------------------------------------------------------
     * API CONFIGURED
     * ---------------------------------------------------------
     */

    $message = "Your HUMAN CARE OTP is: {$otp}";

    $payload = [
        'mobile'  => $phone,
        'message' => $message,
        'otp'     => $otp,
    ];


    $ch = curl_init($apiUrl);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);


    $response = curl_exec($ch);


    // cURL error
    if ($response === false) {

        error_log(
            'SMS API cURL error: ' . curl_error($ch)
        );

        curl_close($ch);

        return false;
    }


    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);


    /*
     * Consider 2xx responses successful.
     */
    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }


    error_log(
        "SMS API failed. HTTP {$httpCode}. Response: {$response}"
    );

    return false;
}
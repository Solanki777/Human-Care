<?php

/**
 * Send Phone OTP
 *
 * This function is prepared for SMS API integration.
 *
 * @param string $phone
 * @param string $otp
 * @return bool
 */
function sendPhoneOTP(string $phone, string $otp): bool
{
    /*
     * SMS provider integration will be added here.
     *
     * Example providers:
     * - Twilio
     * - MSG91
     * - Fast2SMS
     * - Textlocal
     *
     * Do NOT put API keys directly in this file.
     */

    // Temporary development mode
    // Remove this once an SMS provider is configured.

    error_log(
        "HUMAN CARE Phone OTP for {$phone}: {$otp}"
    );

    return true;
}
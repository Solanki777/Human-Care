<?php

/**
 * Send Email OTP
 *
 * @param string $email
 * @param string $otp
 * @return bool
 */
function sendEmailOTP(string $email, string $otp): bool
{
    $subject = "HUMAN CARE - Email Verification OTP";

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>HUMAN CARE</h2>

        <p>Hello,</p>

        <p>Your email verification OTP is:</p>

        <h1 style='letter-spacing: 5px;'>{$otp}</h1>

        <p>This OTP is valid for <strong>10 minutes</strong>.</p>

        <p>Please do not share this OTP with anyone.</p>

        <br>

        <p>Regards,<br>
        HUMAN CARE Team</p>
    </body>
    </html>
    ";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: HUMAN CARE <noreply@humancare.com>\r\n";
    $headers .= "Reply-To: noreply@humancare.com\r\n";

    return mail(
        $email,
        $subject,
        $message,
        $headers
    );
}
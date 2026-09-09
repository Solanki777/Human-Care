<?php

/**
 * Send Email OTP
 *
 * @param string $email
 * @param string $otp
 * @return bool
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';


function sendEmailOTP(string $email, string $otp): bool
{
    // Load centralized mail configuration
    $mailConfig = require __DIR__ . '/../config/mail_config.php';

    try {

        $mail = new PHPMailer(true);

        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = $mailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['username'];
        $mail->Password   = $mailConfig['password'];
        $mail->Port       = $mailConfig['port'];

        // Encryption
        if ($mailConfig['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($mailConfig['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        // Sender
        $mail->setFrom(
            $mailConfig['from_email'],
            $mailConfig['from_name']
        );

        // Recipient
        $mail->addAddress($email);

        // Email content
        $mail->isHTML(true);

        $mail->Subject = "HUMAN CARE - Email Verification OTP";

        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>HUMAN CARE</h2>

            <p>Hello,</p>

            <p>Your email verification OTP is:</p>

            <h1 style='letter-spacing: 5px;'>{$otp}</h1>

            <p>
                This OTP is valid for
                <strong>10 minutes</strong>.
            </p>

            <p>Please do not share this OTP with anyone.</p>

            <br>

            <p>
                Regards,<br>
                HUMAN CARE Team
            </p>
        </body>
        </html>
        ";

        // Plain-text fallback
        $mail->AltBody =
            "HUMAN CARE - Email Verification\n\n" .
            "Your email verification OTP is: {$otp}\n\n" .
            "This OTP is valid for 10 minutes.\n" .
            "Please do not share this OTP with anyone.\n\n" .
            "Regards,\n" .
            "HUMAN CARE Team";

        return $mail->send();

    } catch (Exception $e) {

        error_log(
            "HUMAN CARE Email OTP Error: " .
            $mail->ErrorInfo
        );

        return false;
    }
}
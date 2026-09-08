<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';


/**
 * Send Password Reset Email
 *
 * @param string $email
 * @param string $resetLink
 * @return bool
 */
function sendPasswordResetEmail(string $email, string $resetLink): bool
{
    $mailConfig = require __DIR__ . '/../config/mail_config.php';

    try {

        $mail = new PHPMailer(true);

        /*
        ==========================================================
        SMTP CONFIGURATION
        ==========================================================
        */

        $mail->isSMTP();

        $mail->Host       = $mailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['username'];
        $mail->Password   = $mailConfig['password'];
        $mail->Port       = $mailConfig['port'];


        /*
        ==========================================================
        ENCRYPTION
        ==========================================================
        */

        if ($mailConfig['encryption'] === 'tls') {

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        } elseif ($mailConfig['encryption'] === 'ssl') {

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

        }


        /*
        ==========================================================
        SENDER
        ==========================================================
        */

        $mail->setFrom(
            $mailConfig['from_email'],
            $mailConfig['from_name']
        );

        $mail->addAddress($email);


        /*
        ==========================================================
        EMAIL CONTENT
        ==========================================================
        */

        $mail->isHTML(true);

        $mail->Subject = "HUMAN CARE - Password Reset";

        $mail->Body = "
        <html>

        <body style='font-family: Arial, sans-serif;'>

            <h2>HUMAN CARE</h2>

            <p>Hello,</p>

            <p>
                We received a request to reset your
                HUMAN CARE account password.
            </p>

            <p>
                Click the button below to create a new password:
            </p>

            <p style='margin: 30px 0;'>

                <a href='{$resetLink}'
                   style='
                   display:inline-block;
                   padding:12px 24px;
                   background:#667eea;
                   color:white;
                   text-decoration:none;
                   border-radius:8px;
                   font-weight:bold;
                   '>
                    Reset Password
                </a>

            </p>

            <p>
                <strong>
                    This password reset link is valid for 3 minutes.
                </strong>
            </p>

            <p>
                If you did not request a password reset,
                you can safely ignore this email.
            </p>

            <p>
                Please do not share this link with anyone.
            </p>

            <br>

            <p>
                Regards,<br>
                <strong>HUMAN CARE Team</strong>
            </p>

        </body>

        </html>
        ";


        /*
        ==========================================================
        PLAIN TEXT FALLBACK
        ==========================================================
        */

        $mail->AltBody =
            "HUMAN CARE Password Reset\n\n"
            . "Use the following link to reset your password:\n"
            . $resetLink
            . "\n\n"
            . "This link is valid for 3 minutes.";


        /*
        ==========================================================
        SEND
        ==========================================================
        */

        return $mail->send();

    } catch (Exception $e) {

        error_log(
            "HUMAN CARE Password Reset Email Error: "
            . $mail->ErrorInfo
        );

        return false;
    }
}
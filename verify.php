<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$generalError = "";
$success = "";

if (empty($_SESSION['pending_verification'])) {
    header("Location: register.php");
    exit;
}

$pending = $_SESSION['pending_verification'];
$userType = $pending['user_type'];
$userId   = (int)$pending['user_id'];
$email    = $pending['email'];
$phone    = $pending['phone'];

$servername = "sql205.infinityfree.com";
$username = "";
$password = "6yFxYkbKGy";

$dbName = ($userType === 'doctor')
    ? "if0_42370337_human_care_doctors"
    : "if0_42370337_human_care_patients";

$table = ($userType === 'doctor') ? 'doctors' : 'patients';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendVerificationEmail(string $to, string $otp): bool
{
    $mailConfig = require __DIR__ . '/config/mail_config.php';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $mailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['username'];
        $mail->Password   = $mailConfig['password'];
        $mail->SMTPSecure = $mailConfig['encryption'];
        $mail->Port       = $mailConfig['port'];

        $mail->setFrom(
            $mailConfig['from_email'],
            $mailConfig['from_name']
        );

        $mail->addAddress($to);

        $mail->isHTML(true);

        $mail->Subject = "Human Care - Email Verification";

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;'>
                <h2>Human Care</h2>

                <p>Your email verification OTP is:</p>

                <h1 style='letter-spacing:8px;'>
                    {$otp}
                </h1>

                <p>This OTP expires in <strong>10 minutes</strong>.</p>

                <p>If you did not create this account, please ignore this email.</p>
            </div>
        ";

        $mail->AltBody =
            "Your Human Care email verification OTP is {$otp}. "
            . "This OTP expires in 10 minutes.";

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log(
            "Email OTP failed: " . $mail->ErrorInfo
        );

        return false;
    }
}
/*
 * SMS PROVIDER:
 * Replace this function with your SMS provider API.
 *
 * Example providers: Twilio, MSG91, Fast2SMS.
 * The function must return true when the SMS is successfully accepted.
 */
function sendVerificationSms(string $phone, string $otp): bool
{
    // TODO: Connect your SMS provider here.
    // Do NOT display the OTP to the user in production.
    return true;
}

function maskEmail(string $email): string
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($name === '') return $email;
    return substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 1)) . '@' . $domain;
}

function maskPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) <= 4) return $phone;
    return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
}

$conn = new mysqli($servername, $username, $password, $dbName);
if ($conn->connect_error) {
    $generalError = "Unable to connect. Please try again later.";
} else {
    $conn->set_charset("utf8mb4");

    // Resend OTP
    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'resend') {
        if (!isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $generalError = "Security validation failed. Please refresh the page.";
        } else {
            $emailOtp = (string) random_int(100000, 999999);
            $phoneOtp = (string) random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', time() + 600);

            $emailHash = password_hash($emailOtp, PASSWORD_DEFAULT);
            $phoneHash = password_hash($phoneOtp, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "UPDATE {$table}
                 SET email_otp = ?, phone_otp = ?, otp_expires_at = ?
                 WHERE id = ? AND email_verified = 0 AND phone_verified = 0"
            );
            $stmt->bind_param("sssi", $emailHash, $phoneHash, $expires, $userId);

            if ($stmt->execute() && $stmt->affected_rows >= 0) {
                $emailSent = sendVerificationEmail($email, $emailOtp);
                $smsSent   = sendVerificationSms($phone, $phoneOtp);

                if ($emailSent && $smsSent) {
                    $success = "New verification codes have been sent.";
                } else {
                    $generalError = "We could not send one or more verification codes. Check your email/SMS provider configuration.";
                }
            } else {
                $generalError = "Unable to generate new verification codes.";
            }
            $stmt->close();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    // Verify both OTPs
    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'verify') {
        if (!isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $generalError = "Security validation failed. Please refresh the page.";
        } else {
            $emailOtp = trim($_POST['email_otp'] ?? '');
            $phoneOtp = trim($_POST['phone_otp'] ?? '');

            if (!preg_match('/^\d{6}$/', $emailOtp) ||
                !preg_match('/^\d{6}$/', $phoneOtp)) {
                $generalError = "Please enter both 6-digit verification codes.";
            } else {
                $stmt = $conn->prepare(
                    "SELECT email_otp, phone_otp, otp_expires_at, email_verified, phone_verified
                     FROM {$table}
                     WHERE id = ?
                     LIMIT 1"
                );
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$row) {
                    $generalError = "Account not found.";
                } elseif ((int)$row['email_verified'] === 1 &&
                          (int)$row['phone_verified'] === 1) {
                    $success = "Your account is already verified. Redirecting to login...";
                    unset($_SESSION['pending_verification']);
                    echo "<script>setTimeout(() => window.location.href='login.php', 1200);</script>";
                } elseif (empty($row['otp_expires_at']) ||
                          strtotime($row['otp_expires_at']) < time()) {
                    $generalError = "The verification codes have expired. Please click Resend OTP.";
                } elseif (!password_verify($emailOtp, $row['email_otp']) ||
                          !password_verify($phoneOtp, $row['phone_otp'])) {
                    $generalError = "One or both verification codes are incorrect.";
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE {$table}
                         SET email_verified = 1,
                             phone_verified = 1,
                             email_otp = NULL,
                             phone_otp = NULL,
                             otp_expires_at = NULL
                         WHERE id = ?"
                    );
                    $stmt->bind_param("i", $userId);

                    if ($stmt->execute()) {
                        unset($_SESSION['pending_verification']);
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $success = "Both email and mobile number are verified! Redirecting to login...";
                        echo "<script>setTimeout(() => window.location.href='login.php', 1800);</script>";
                    } else {
                        $generalError = "Verification failed. Please try again.";
                    }
                    $stmt->close();
                }
            }
        }
    }

    // If this is the first visit, send the generated OTPs created during registration.
    if ($_SERVER["REQUEST_METHOD"] !== "POST" &&
        isset($_SESSION['pending_verification']['email_otp'],
              $_SESSION['pending_verification']['phone_otp'])) {

        $emailOtp = $_SESSION['pending_verification']['email_otp'];
        $phoneOtp = $_SESSION['pending_verification']['phone_otp'];

        $emailSent = sendVerificationEmail($email, $emailOtp);
        $smsSent   = sendVerificationSms($phone, $phoneOtp);

        unset($_SESSION['pending_verification']['email_otp']);
        unset($_SESSION['pending_verification']['phone_otp']);

        if (!$emailSent || !$smsSent) {
            $generalError = "Account created, but one or more OTPs could not be sent. Please use Resend OTP after configuring your email/SMS provider.";
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Human Care - Verify Account</title>
    <link rel="stylesheet" href="styles/register.css">
    <style>
        body { background: #f5f7f8; }
        .verify-card {
            max-width: 520px;
            margin: 60px auto;
            background: #fff;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 10px 35px rgba(0,0,0,.08);
        }
        .verify-card h2 { margin-bottom: 8px; }
        .verify-subtitle { color: #666; margin-bottom: 25px; }
        .verify-item {
            background: #f8faf9;
            border: 1px solid #e3e9e5;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .verify-item strong { display:block; margin-bottom:6px; }
        .verify-input {
            width: 100%;
            box-sizing: border-box;
            padding: 13px;
            font-size: 20px;
            letter-spacing: 6px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        .verify-btn {
            width: 100%;
            padding: 14px;
            border: 0;
            border-radius: 8px;
            background: #4CAF50;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        .resend-btn {
            width: 100%;
            margin-top: 12px;
            padding: 12px;
            border: 1px solid #4CAF50;
            border-radius: 8px;
            background: white;
            color: #388e3c;
            cursor: pointer;
        }
        .general-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .success-message {
            background: #dcfce7;
            color: #166534;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <h2>Verify Your Account</h2>
        <p class="verify-subtitle">
            Enter the 6-digit code sent to your email and mobile number.
        </p>

        <?php if ($generalError): ?>
            <div class="general-error"><?php echo htmlspecialchars($generalError); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="verify">

            <div class="verify-item">
                <strong>📧 Email</strong>
                <span><?php echo htmlspecialchars(maskEmail($email)); ?></span>
                <input class="verify-input"
                       type="text"
                       name="email_otp"
                       inputmode="numeric"
                       pattern="\d{6}"
                       maxlength="6"
                       placeholder="000000"
                       required>
            </div>

            <div class="verify-item">
                <strong>📱 Mobile Number</strong>
                <span><?php echo htmlspecialchars(maskPhone($phone)); ?></span>
                <input class="verify-input"
                       type="text"
                       name="phone_otp"
                       inputmode="numeric"
                       pattern="\d{6}"
                       maxlength="6"
                       placeholder="000000"
                       required>
            </div>

            <button type="submit" class="verify-btn">Verify Account</button>
        </form>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="resend-btn">Resend OTP</button>
        </form>

        <p style="text-align:center; margin-top:20px;">
            <a href="register.php">← Back to Registration</a>
        </p>
    </div>
</body>
</html>

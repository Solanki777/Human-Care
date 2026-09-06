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

$pending = &$_SESSION['pending_verification'];

$userType = $pending['user_type'] ?? '';
$email    = $pending['email'] ?? '';
$phone    = $pending['phone'] ?? '';

if (!in_array($userType, ['patient', 'doctor'], true) ||
    $email === '' ||
    $phone === '') {

    unset($_SESSION['pending_verification']);
    header("Location: register.php");
    exit;
}

$servername = "sql205.infinityfree.com";
$username   = "if0_42370337";
$password   = "6yFxYkbKGy";

$dbName = ($userType === 'doctor')
    ? "if0_42370337_human_care_doctors"
    : "if0_42370337_human_care_patients";

$table = ($userType === 'doctor') ? 'doctors' : 'patients';

function maskEmail(string $email): string
{
    $parts = explode('@', $email, 2);

    if (count($parts) !== 2) {
        return $email;
    }

    $name = $parts[0];
    $domain = $parts[1];

    if (strlen($name) <= 2) {
        $masked = substr($name, 0, 1) . '*';
    } else {
        $masked = substr($name, 0, 1)
                . str_repeat('*', strlen($name) - 2)
                . substr($name, -1);
    }

    return $masked . '@' . $domain;
}

function maskPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);

    if (strlen($digits) <= 4) {
        return $phone;
    }

    return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
}

/*
|--------------------------------------------------------------------------
| EMAIL
|--------------------------------------------------------------------------
| Replace the From address with an address allowed by your hosting provider.
*/
function sendVerificationEmail(string $to, string $otp): bool
{
    $subject = "Human Care - Email Verification Code";

    $message = "Hello,\n\n"
             . "Your Human Care verification code is: {$otp}\n\n"
             . "This code expires in 10 minutes.\n\n"
             . "If you did not create this account, please ignore this email.\n\n"
             . "Human Care";

    $headers = "From: Human Care <no-reply@yourdomain.com>\r\n";
    $headers .= "Reply-To: no-reply@yourdomain.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($to, $subject, $message, $headers);
}

/*
|--------------------------------------------------------------------------
| SMS
|--------------------------------------------------------------------------
| This is still a placeholder. Connect Twilio/MSG91/Fast2SMS here.
*/
function sendVerificationSms(string $phone, string $otp): bool
{
    // TODO: Connect your real SMS provider.
    return false;
}

function sendInitialOtpsIfNeeded(): void
{
    global $pending, $email, $phone, $generalError, $success;

    if (
        empty($pending['initial_otp_sent']) &&
        isset($pending['email_otp'], $pending['phone_otp'])
    ) {

        $emailOtp = $pending['email_otp'];
        $phoneOtp = $pending['phone_otp'];

        $emailSent = sendVerificationEmail($email, $emailOtp);
        $smsSent   = sendVerificationSms($phone, $phoneOtp);

        // Raw OTPs are no longer needed after this point.
        unset($pending['email_otp']);
        unset($pending['phone_otp']);

        $pending['initial_otp_sent'] = true;

        if ($emailSent && $smsSent) {
            $success = "Verification codes have been sent to your email and mobile.";
        } elseif ($emailSent && !$smsSent) {
            $generalError = "Email OTP was sent, but SMS could not be sent. Configure your SMS provider.";
        } elseif (!$emailSent && $smsSent) {
            $generalError = "SMS OTP was sent, but email could not be sent. Check your email configuration.";
        } else {
            $generalError = "The account is waiting for verification, but the OTPs could not be sent. Check your email/SMS configuration and use Resend OTP.";
        }
    }
}

$conn = new mysqli($servername, $username, $password, $dbName);

if ($conn->connect_error) {

    $generalError = "Unable to connect to the database. Please try again later.";

} else {

    $conn->set_charset("utf8mb4");

    // --------------------------------------------------------
    // VERIFY BOTH OTPs
    // --------------------------------------------------------
    if ($_SERVER["REQUEST_METHOD"] === "POST" &&
        ($_POST['action'] ?? '') === 'verify') {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {

            $generalError = "Security validation failed. Please refresh the page.";

        } else {

            $emailOtp = trim($_POST['email_otp'] ?? '');
            $phoneOtp = trim($_POST['phone_otp'] ?? '');

            if (!preg_match('/^\d{6}$/', $emailOtp) ||
                !preg_match('/^\d{6}$/', $phoneOtp)) {

                $generalError = "Please enter both 6-digit verification codes.";

            } elseif (
                empty($pending['email_otp_hash']) ||
                empty($pending['phone_otp_hash']) ||
                empty($pending['otp_expires_at'])
            ) {

                $generalError = "Verification codes are no longer available. Please click Resend OTP.";

            } elseif ((int)$pending['otp_expires_at'] < time()) {

                $generalError = "Your verification codes have expired. Please click Resend OTP.";

            } elseif (!password_verify($emailOtp, $pending['email_otp_hash'])) {

                $generalError = "The email verification code is incorrect.";

            } elseif (!password_verify($phoneOtp, $pending['phone_otp_hash'])) {

                $generalError = "The mobile verification code is incorrect.";

            } else {

                // ============================================================
                // BOTH OTPs ARE CORRECT.
                // NOW, AND ONLY NOW, CREATE THE DATABASE ACCOUNT.
                // ============================================================

                if ($userType === 'patient') {

                    $stmt = $conn->prepare(
                        "INSERT INTO patients
                        (first_name, last_name, email, phone, dob, gender, blood_group, password,
                         email_verified, phone_verified)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
                    );

                    if (!$stmt) {
                        $generalError = "Unable to create your account. Please try again.";
                    } else {

                        $stmt->bind_param(
                            "ssssssss",
                            $pending['first_name'],
                            $pending['last_name'],
                            $pending['email'],
                            $pending['phone'],
                            $pending['dob'],
                            $pending['gender'],
                            $pending['blood_group'],
                            $pending['password_hash']
                        );

                        if ($stmt->execute()) {

                            unset($_SESSION['pending_verification']);

                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                            $success = "Email and mobile verified. Your account has been created successfully! Redirecting to login...";

                            echo '<script>
                                setTimeout(function() {
                                    window.location.href = "login.php";
                                }, 1800);
                            </script>';

                        } else {

                            if ($stmt->errno === 1062) {
                                $generalError = "This email is already registered. Please use another email.";
                            } else {
                                $generalError = "Unable to create your account. Please try again.";
                            }
                        }

                        $stmt->close();
                    }

                } else {

                    $stmt = $conn->prepare(
                        "INSERT INTO doctors
                        (first_name, last_name, email, phone, dob, gender, password,
                         specialty, license_number, email_verified, phone_verified)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
                    );

                    if (!$stmt) {
                        $generalError = "Unable to create your doctor account. Please try again.";
                    } else {

                        $stmt->bind_param(
                            "sssssssss",
                            $pending['first_name'],
                            $pending['last_name'],
                            $pending['email'],
                            $pending['phone'],
                            $pending['dob'],
                            $pending['gender'],
                            $pending['password_hash'],
                            $pending['specialization'],
                            $pending['license_number']
                        );

                        if ($stmt->execute()) {

                            $newDoctorId = (int)$stmt->insert_id;

                            /*
                             * IMPORTANT:
                             * Your current doctors table has no verification_photo
                             * column shown in the DESCRIBE output, so the uploaded
                             * photo remains as a file in uploads/doctor_verification/.
                             *
                             * Your existing admin verification system remains:
                             * verification_status = pending
                             * is_verified = 0
                             */

                            unset($_SESSION['pending_verification']);

                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                            $success = "Email and mobile verified. Your doctor account has been created and sent for admin verification. Redirecting to login...";

                            echo '<script>
                                setTimeout(function() {
                                    window.location.href = "login.php";
                                }, 2200);
                            </script>';

                        } else {

                            if ($stmt->errno === 1062) {
                                $generalError = "This email or medical license number is already registered.";
                            } else {
                                $generalError = "Unable to create your doctor account. Please try again.";
                            }
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }

    // --------------------------------------------------------
    // RESEND OTP
    // --------------------------------------------------------
    if ($_SERVER["REQUEST_METHOD"] === "POST" &&
        ($_POST['action'] ?? '') === 'resend') {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {

            $generalError = "Security validation failed. Please refresh the page.";

        } else {

            $newEmailOtp = (string)random_int(100000, 999999);
            $newPhoneOtp = (string)random_int(100000, 999999);

            $pending['email_otp_hash'] = password_hash($newEmailOtp, PASSWORD_DEFAULT);
            $pending['phone_otp_hash'] = password_hash($newPhoneOtp, PASSWORD_DEFAULT);
            $pending['otp_expires_at'] = time() + 600;

            $emailSent = sendVerificationEmail($email, $newEmailOtp);
            $smsSent   = sendVerificationSms($phone, $newPhoneOtp);

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            if ($emailSent && $smsSent) {
                $success = "New verification codes have been sent.";
            } elseif ($emailSent && !$smsSent) {
                $generalError = "Email OTP was sent, but SMS could not be sent. Configure your SMS provider.";
            } elseif (!$emailSent && $smsSent) {
                $generalError = "SMS OTP was sent, but email could not be sent. Check your email configuration.";
            } else {
                $generalError = "New OTPs were generated, but they could not be sent. Check your email/SMS configuration.";
            }
        }
    }

    // On first GET, send the OTPs that register.php generated.
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        sendInitialOtpsIfNeeded();
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
body {
    background: #f5f7f8;
}

.verify-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 15px;
}

.verify-card {
    width: 100%;
    max-width: 520px;
    background: #fff;
    border-radius: 18px;
    padding: 35px;
    box-sizing: border-box;
    box-shadow: 0 12px 40px rgba(0,0,0,.08);
}

.verify-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #e8f5e9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    margin: 0 auto 20px;
}

.verify-header {
    text-align: center;
    margin-bottom: 25px;
}

.verify-header h2 {
    margin: 0 0 8px;
    font-size: 28px;
}

.verify-header p {
    color: #666;
    line-height: 1.5;
    margin: 0;
}

.verification-info {
    background: #f8faf9;
    border: 1px solid #e1e8e3;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
}

.verification-info-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 7px 0;
}

.verification-info-row:not(:last-child) {
    border-bottom: 1px solid #e6ebe7;
}

.verification-label {
    color: #666;
}

.verification-value {
    font-weight: 600;
    word-break: break-word;
}

.otp-group {
    margin-bottom: 18px;
}

.otp-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.otp-input {
    width: 100%;
    box-sizing: border-box;
    padding: 14px 12px;
    border: 1px solid #ccc;
    border-radius: 9px;
    font-size: 22px;
    letter-spacing: 7px;
    text-align: center;
    outline: none;
}

.otp-input:focus {
    border-color: #4CAF50;
    box-shadow: 0 0 0 3px rgba(76,175,80,.12);
}

.verify-btn {
    width: 100%;
    border: none;
    border-radius: 9px;
    padding: 14px;
    background: #4CAF50;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

.resend-form {
    margin-top: 12px;
}

.resend-btn {
    width: 100%;
    border: 1px solid #4CAF50;
    border-radius: 9px;
    padding: 12px;
    background: #fff;
    color: #388e3c;
    font-size: 15px;
    cursor: pointer;
}

.general-error,
.success-message {
    padding: 13px 15px;
    border-radius: 9px;
    margin-bottom: 18px;
    line-height: 1.45;
    font-size: 14px;
}

.general-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.success-message {
    background: #dcfce7;
    color: #166534;
    border-left: 4px solid #22c55e;
}

.expiry-note {
    text-align: center;
    color: #777;
    font-size: 13px;
    margin: 18px 0 0;
}

.back-link {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #388e3c;
    text-decoration: none;
}

@media (max-width: 520px) {
    .verify-card {
        padding: 25px 18px;
    }

    .verification-info-row {
        flex-direction: column;
        gap: 2px;
    }

    .otp-input {
        font-size: 20px;
        letter-spacing: 5px;
    }
}
</style>
</head>

<body>

<div class="verify-container">
    <div class="verify-card">

        <div class="verify-icon">🔐</div>

        <div class="verify-header">
            <h2>Verify Your Account</h2>
            <p>
                Verify your email address and mobile number.
                Your account will only be created after both are verified.
            </p>
        </div>

        <?php if ($generalError): ?>
            <div class="general-error">
                <?php echo htmlspecialchars($generalError); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="verification-info">

            <div class="verification-info-row">
                <span class="verification-label">Account Type</span>
                <span class="verification-value">
                    <?php echo $userType === 'doctor' ? 'Doctor' : 'Patient'; ?>
                </span>
            </div>

            <div class="verification-info-row">
                <span class="verification-label">Email</span>
                <span class="verification-value">
                    <?php echo htmlspecialchars(maskEmail($email)); ?>
                </span>
            </div>

            <div class="verification-info-row">
                <span class="verification-label">Mobile</span>
                <span class="verification-value">
                    <?php echo htmlspecialchars(maskPhone($phone)); ?>
                </span>
            </div>

        </div>

        <form method="POST" autocomplete="off">

            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <input type="hidden" name="action" value="verify">

            <div class="otp-group">
                <label for="email_otp">📧 Email Verification Code</label>

                <input
                    type="text"
                    id="email_otp"
                    name="email_otp"
                    class="otp-input"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    minlength="6"
                    placeholder="000000"
                    required
                >
            </div>

            <div class="otp-group">
                <label for="phone_otp">📱 Mobile Verification Code</label>

                <input
                    type="text"
                    id="phone_otp"
                    name="phone_otp"
                    class="otp-input"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    minlength="6"
                    placeholder="000000"
                    required
                >
            </div>

            <button type="submit" class="verify-btn">
                Verify Account
            </button>

        </form>

        <form method="POST" class="resend-form">

            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <input type="hidden" name="action" value="resend">

            <button type="submit" class="resend-btn">
                Resend OTP
            </button>

        </form>

        <p class="expiry-note">
            OTPs are valid for 10 minutes.
        </p>

        <a href="register.php" class="back-link">
            ← Back to Registration
        </a>

    </div>
</div>

<script>
document.querySelectorAll('.otp-input').forEach(function(input) {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });
});
</script>

</body>
</html>

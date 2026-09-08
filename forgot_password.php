<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/security_csrf/csrf.php';
require_once __DIR__ . '/password_reset/password_reset_handler.php';
require_once __DIR__ . '/otp/send_password_reset_email.php';

$servername = "localhost";
$username = "root";
$password = "";

$connPatient = new mysqli(
    $servername,
    $username,
    $password,
    "if0_42370337_human_care_patients"
);

$connDoctor = new mysqli(
    $servername,
    $username,
    $password,
    "if0_42370337_human_care_doctors"
);

if ($connPatient->connect_error || $connDoctor->connect_error) {
    die("Database connection failed");
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {

        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = "Please enter your email address.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {

            $userType = null;
            $userId = null;

            /*
            ==========================================================
            CHECK PATIENT
            ==========================================================
            */

            $stmt = $connPatient->prepare(
                "SELECT id, email FROM patients WHERE email = ? LIMIT 1"
            );

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                $userType = "patient";
                $userId = (int)$user["id"];
            }

            $stmt->close();


            /*
            ==========================================================
            CHECK DOCTOR
            ==========================================================
            */

            if ($userType === null) {

                $stmt = $connDoctor->prepare(
                    "SELECT id, email FROM doctors WHERE email = ? LIMIT 1"
                );

                $stmt->bind_param("s", $email);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result->num_rows === 1) {

                    $user = $result->fetch_assoc();

                    $userType = "doctor";
                    $userId = (int)$user["id"];
                }

                $stmt->close();
            }


            /*
            ==========================================================
            USER FOUND
            ==========================================================
            */

            if ($userType !== null) {

                // Generate secure token
                $resetToken = generatePasswordResetToken();

                // Store only hash in database
                $resetTokenHash = hashPasswordResetToken($resetToken);

                // Token expires after 3 minutes
                $expiresAt = getPasswordResetExpiry();


                /*
                ======================================================
                SAVE TOKEN
                ======================================================
                */

                if ($userType === "patient") {

                    $stmt = $connPatient->prepare(
                        "UPDATE patients
                         SET password_reset_token = ?,
                             password_reset_expires_at = ?
                         WHERE id = ?"
                    );

                } else {

                    $stmt = $connDoctor->prepare(
                        "UPDATE doctors
                         SET password_reset_token = ?,
                             password_reset_expires_at = ?
                         WHERE id = ?"
                    );
                }

                $stmt->bind_param(
                    "ssi",
                    $resetTokenHash,
                    $expiresAt,
                    $userId
                );

                $stmt->execute();
                $stmt->close();


                /*
                ======================================================
                CREATE RESET LINK
                ======================================================
                */

                $resetLink =
                    "http://localhost/Human%20Care/reset_password.php?token="
                    . urlencode($resetToken);


                /*
                ======================================================
                DEVELOPMENT MODE
                ======================================================

                For now we log the link.

                Later we will replace this with real email sending.
                ======================================================
                */

                if (!sendPasswordResetEmail($email, $resetLink)) {
                $error = "Unable to send password reset email. Please try again later.";
            } else {
                $message =
                    "If an account exists with this email address, "
                    . "a password reset link has been sent. "
                    . "The link is valid for 3 minutes.";
            }


                /*
                ======================================================
                SUCCESS MESSAGE
                ======================================================
                */

                $message =
                    "If an account exists with this email address, "
                    . "a password reset link has been sent. "
                    . "The link is valid for 3 minutes.";

            } else {

                /*
                Do NOT reveal whether an email exists.
                */

                $message =
                    "If an account exists with this email address, "
                    . "a password reset link has been sent. "
                    . "The link is valid for 3 minutes.";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Forgot Password - Human Care</title>

    <link
        rel="stylesheet"
        href="styles/login.css"
    >

    <style>

        .forgot-card {
            width: 100%;
            max-width: 450px;
        }

        .info-text {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .success-message {
            background: #e8f8ee;
            color: #217a42;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            font-size: 14px;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-size: 14px;
        }

        .back-login {
            text-align: center;
            margin-top: 20px;
        }

        .back-login a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

    </style>

</head>

<body>

<div class="login-container">

    <div class="login-left">

        <div class="logo">
            <div class="logo-icon">❤️</div>
            HUMAN CARE
        </div>

        <p class="tagline">
            Your Health, Our Priority
        </p>

        <ul class="features">

            <li>Easy appointment booking</li>

            <li>24/7 online consultation</li>

            <li>Doctor and Patient Validation</li>

            <li>Education</li>

        </ul>

    </div>


    <div class="login-right">

        <div class="login-header">

            <h2>Forgot Password?</h2>

            <p>
                Enter your registered email address
            </p>

        </div>


        <?php if (!empty($message)): ?>

            <div class="success-message">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <p class="info-text">

            Enter the email address associated with your
            HUMAN CARE account. We will send you a secure
            password reset link.

            <br><br>

            <strong>
                The reset link will expire after 3 minutes.
            </strong>

        </p>


        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(getCSRFToken()); ?>"
            >


            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <div class="input-wrapper">

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your registered email"
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >

                </div>

            </div>


            <button
                type="submit"
                class="login-btn"
            >
                Send Reset Link
            </button>

        </form>


        <div class="back-login">

            <a href="login.php">
                ← Back to Login
            </a>

        </div>

    </div>

</div>

</body>

</html>
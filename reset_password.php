<?php

session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/security_csrf/csrf.php';

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

$error = "";
$success = "";

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

$userType = null;
$userId = null;


/*
============================================================
CHECK TOKEN
============================================================
*/

if (empty($token)) {

    $error = "Invalid password reset link.";

} else {

    $tokenHash = hash('sha256', $token);

    /*
    ========================================================
    CHECK PATIENT
    ========================================================
    */

    $stmt = $connPatient->prepare(
        "SELECT id, password_reset_expires_at
         FROM patients
         WHERE password_reset_token = ?
         LIMIT 1"
    );

    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (
            empty($user['password_reset_expires_at']) ||
            strtotime($user['password_reset_expires_at']) < time()
        ) {

            $error = "This password reset link has expired. Please request a new one.";

        } else {

            $userType = "patient";
            $userId = (int)$user['id'];
        }
    }

    $stmt->close();


    /*
    ========================================================
    CHECK DOCTOR
    ========================================================
    */

    if ($userType === null && empty($error)) {

        $stmt = $connDoctor->prepare(
            "SELECT id, password_reset_expires_at
             FROM doctors
             WHERE password_reset_token = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if (
                empty($user['password_reset_expires_at']) ||
                strtotime($user['password_reset_expires_at']) < time()
            ) {

                $error = "This password reset link has expired. Please request a new one.";

            } else {

                $userType = "doctor";
                $userId = (int)$user['id'];
            }
        }

        $stmt->close();
    }


    /*
    ========================================================
    TOKEN NOT FOUND
    ========================================================
    */

    if ($userType === null && empty($error)) {
        $error = "Invalid or expired password reset link.";
    }
}


/*
============================================================
PASSWORD RESET
============================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    empty($error) &&
    $userType !== null
) {

    /*
    ========================================================
    CSRF CHECK
    ========================================================
    */

    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {

        $error = "Security validation failed. Please refresh the page and try again.";

    } else {

        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';


        /*
        ====================================================
        PASSWORD VALIDATION
        ====================================================
        */

        if (empty($newPassword) || empty($confirmPassword)) {

            $error = "Please fill in both password fields.";

        } elseif (strlen($newPassword) < 8) {

            $error = "Password must be at least 8 characters long.";

        } elseif ($newPassword !== $confirmPassword) {

            $error = "Passwords do not match.";

        } else {

            /*
            =================================================
            HASH NEW PASSWORD
            =================================================
            */

            $passwordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );


            /*
            =================================================
            UPDATE PATIENT
            =================================================
            */

            if ($userType === "patient") {

                $stmt = $connPatient->prepare(
                    "UPDATE patients
                     SET password = ?,
                         password_reset_token = NULL,
                         password_reset_expires_at = NULL
                     WHERE id = ?"
                );

            }

            /*
            =================================================
            UPDATE DOCTOR
            =================================================
            */

            else {

                $stmt = $connDoctor->prepare(
                    "UPDATE doctors
                     SET password = ?,
                         password_reset_token = NULL,
                         password_reset_expires_at = NULL
                     WHERE id = ?"
                );
            }


            $stmt->bind_param(
                "si",
                $passwordHash,
                $userId
            );

            if ($stmt->execute()) {

                $success =
                    "Your password has been changed successfully.";

            } else {

                $error =
                    "Unable to change your password. Please try again.";
            }

            $stmt->close();
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

    <title>Reset Password - Human Care</title>

    <link
        rel="stylesheet"
        href="styles/login.css"
    >

    <style>

        .password-requirements {
            font-size: 12px;
            margin-top: 10px;
            line-height: 1.8;
        }

        .password-requirements .valid {
            color: #28a745;
        }

        .password-requirements .invalid {
            color: #dc3545;
        }

        #changePasswordBtn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .reset-info {
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

        .password-requirements {
            font-size: 12px;
            color: #777;
            margin-top: 8px;
            line-height: 1.5;
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

            <div class="logo-icon">
                ❤️
            </div>

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

            <h2>
                Reset Password
            </h2>

            <p>
                Create a new password for your account
            </p>

        </div>


        <?php if (!empty($error)): ?>

            <div class="error-message">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($success)): ?>

            <div class="success-message">

                <?php echo htmlspecialchars($success); ?>

            </div>

            <div class="back-login">

                <a href="login.php">
                    ← Go to Login
                </a>

            </div>

        <?php elseif ($userType !== null && empty($error)): ?>


            <p class="reset-info">

                Enter your new password below.

                <br>

                <strong>
                    This reset link is valid for 3 minutes.
                </strong>

            </p>


            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(getCSRFToken()); ?>"
                >

                <input
                    type="hidden"
                    name="token"
                    value="<?php echo htmlspecialchars($token); ?>"
                >


                <div class="form-group">

                    <label for="new_password">
                        New Password
                    </label>

                    <div class="input-wrapper">

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            placeholder="Enter new password"
                            required
                        >

                    </div>

                    <div class="password-requirements">
                    <div id="length" class="invalid">✖ At least 8 characters</div>
                    <div id="lowercase" class="invalid">✖ At least 1 lowercase letter</div>
                    <div id="uppercase" class="invalid">✖ At least 1 uppercase letter</div>
                    <div id="digit" class="invalid">✖ At least 1 digit</div>
                    <div id="special" class="invalid">✖ At least 1 special character</div>
                    <div id="match" class="invalid">✖ Passwords match</div>
                </div>

                </div>


                <div class="form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <div class="input-wrapper">

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm new password"
                            required
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    class="login-btn"
                    id="changePasswordBtn"
                    disabled
                >
                    Change Password
                </button>

            </form>


            <div class="back-login">

                <a href="login.php">
                    ← Back to Login
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>
<script>
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const changePasswordBtn = document.getElementById('changePasswordBtn');

function updateRequirement(id, valid) {
    const element = document.getElementById(id);

    if (valid) {
        element.classList.remove('invalid');
        element.classList.add('valid');
        element.textContent = '✔ ' + element.textContent.substring(2);
    } else {
        element.classList.remove('valid');
        element.classList.add('invalid');
        element.textContent = '✖ ' + element.textContent.substring(2);
    }
}

function validatePassword() {
    const password = newPassword.value;
    const confirm = confirmPassword.value;

    const hasLength = password.length >= 8;
    const hasLowercase = /[a-z]/.test(password);
    const hasUppercase = /[A-Z]/.test(password);
    const hasDigit = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);
    const passwordsMatch =
        password.length > 0 &&
        password === confirm;

    updateRequirement('length', hasLength);
    updateRequirement('lowercase', hasLowercase);
    updateRequirement('uppercase', hasUppercase);
    updateRequirement('digit', hasDigit);
    updateRequirement('special', hasSpecial);
    updateRequirement('match', passwordsMatch);

    const passwordValid =
        hasLength &&
        hasLowercase &&
        hasUppercase &&
        hasDigit &&
        hasSpecial &&
        passwordsMatch;

    changePasswordBtn.disabled = !passwordValid;
}

newPassword.addEventListener('input', validatePassword);
confirmPassword.addEventListener('input', validatePassword);
</script>

</body>

</html>
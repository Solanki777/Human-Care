
<?php

// Make sure required security functions are available
require_once __DIR__ . '/../security/security_functions.php';

// Initialize variables used by login.php
$error = "";
$verification_message = "";

// Get client IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Only process login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get submitted values safely
    $email = trim($_POST["email"] ?? "");
    $passwordInput = $_POST["password"] ?? "";
    $userType = $_POST["user_type"] ?? "patient";


    // ===========================================================
    // BASIC VALIDATION
    // ===========================================================

    if (empty($email) || empty($passwordInput)) {

        $error = "Please fill in both fields.";

    } else {

        // ===========================================================
        // SECURITY LAYER
        // ===========================================================

        $threat = check_login_threat($ip, $email, $passwordInput);

        if ($threat) {

            block_ip(
                $ip,
                $threat['label'],
                $threat['reason']
            );

            log_security_event(
                $ip,
                $email,
                'failed',
                null,
                $threat['type']
            );

            // Get block information
            $blockDetails = get_block_details($ip);

            $threatTypeRaw = $blockDetails['threat_type'] ?? $threat['type'];
            $reason = $blockDetails['reason'] ?? $threat['reason'];
            $blockedBy = $blockDetails['blocked_by'] ?? 'php';
            $blockedAtRaw = $blockDetails['blocked_at'] ?? date('Y-m-d H:i:s');
            $expiresAtRaw = $blockDetails['expires_at'] ?? null;


            // ===========================================================
            // DISPLAY BLOCK PAGE
            // ===========================================================

            $threatLabels = [
                'brute_force'          => 'Brute Force Attack',
                'sql_injection'        => 'SQL Injection Attempt',
                'suspicious_rate'      => 'Suspicious Login Rate',
                'blocked_ip_reuse'     => 'Blocked IP Reuse',
                'credential_stuffing'  => 'Credential Stuffing Attack',
                'password_spraying'    => 'Password Spraying Attack',
            ];

            $attackType =
                $threatLabels[$threatTypeRaw]
                ?? ucwords(str_replace('_', ' ', $threatTypeRaw));


            $blockedByLabel = match (strtolower($blockedBy)) {

                'php' => 'PHP Security Layer',

                'nexora' => 'Nexora AI',

                default => $blockedBy
            };


            $blockedAtLabel = $blockedAtRaw
                ? date('d M Y, H:i:s', strtotime($blockedAtRaw))
                : 'Now';


            $expiresAtLabel = $expiresAtRaw
                ? date('d M Y, H:i:s', strtotime($expiresAtRaw))
                : 'Permanent';


            ?>

            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">

                <meta
                    name="viewport"
                    content="width=device-width, initial-scale=1.0"
                >

                <title>Access Blocked – Human Care</title>

                <link rel="stylesheet" href="styles/login.css">
            </head>

            <body>

                <div class="block-card">

                    <div class="block-icon">🚫</div>

                    <h1>Access Blocked</h1>

                    <p class="subtitle">
                        Your IP address has been flagged and blocked by the security system.
                    </p>


                    <div class="detail-grid">

                        <div class="detail-row">

                            <span class="detail-label">
                                IP Address
                            </span>

                            <span class="detail-value">

                                <span class="badge badge-red">
                                    <?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?>
                                </span>

                            </span>

                        </div>


                        <hr class="block-divider">


                        <div class="detail-row">

                            <span class="detail-label">
                                Attack Type
                            </span>

                            <span class="detail-value">

                                <span class="badge badge-red">
                                    <?= htmlspecialchars($attackType, ENT_QUOTES, 'UTF-8') ?>
                                </span>

                            </span>

                        </div>


                        <hr class="block-divider">


                        <div class="detail-row">

                            <span class="detail-label">
                                Reason
                            </span>

                            <span class="detail-value">
                                <?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>
                            </span>

                        </div>


                        <hr class="block-divider">


                        <div class="detail-row">

                            <span class="detail-label">
                                Blocked By
                            </span>

                            <span class="detail-value">

                                <?php if (strtolower($blockedBy) === 'nexora'): ?>

                                    <span class="badge badge-purple">
                                        🤖 <?= htmlspecialchars($blockedByLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-blue">
                                        🛡️ <?= htmlspecialchars($blockedByLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>

                                <?php endif; ?>

                            </span>

                        </div>


                        <hr class="block-divider">


                        <div class="detail-row">

                            <span class="detail-label">
                                Blocked At
                            </span>

                            <span class="detail-value">
                                <?= htmlspecialchars($blockedAtLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>

                        </div>


                        <hr class="block-divider">


                        <div class="detail-row">

                            <span class="detail-label">
                                Expires At
                            </span>

                            <span class="detail-value">
                                <?= htmlspecialchars($expiresAtLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>

                        </div>

                    </div>


                    <p class="footer-note">

                        If you believe this is a mistake, please contact your system administrator.<br>

                        <a href="mailto:admin@humancare.local">
                            admin@humancare.local
                        </a>

                    </p>


                    <div class="nexora-badge">

                        🛡️ Protected by

                        <strong style="color:#a0aaff; margin: 0 3px;">
                            Nexora
                        </strong>

                        Autonomous AI Security

                    </div>

                </div>

            </body>

            </html>

            <?php

            exit;
        }


        // ===========================================================
        // PATIENT LOGIN
        // ===========================================================

        if ($userType === "patient") {

            $stmt = $connPatient->prepare(
                "SELECT * FROM patients WHERE email = ?"
            );


            if (!$stmt) {

                error_log(
                    "Patient login prepare failed: " .
                    $connPatient->error
                );

                $error = "Unable to process login right now.";

            } else {

                $stmt->bind_param("s", $email);

                $stmt->execute();

                $result = $stmt->get_result();


                if ($result->num_rows === 1) {

                    $patient = $result->fetch_assoc();


                    // ===================================================
                    // PASSWORD
                    // ===================================================

                    if (password_verify(
                        $passwordInput,
                        $patient["password"]
                    )) {


                        // =================================================
                        // EMAIL + PHONE VERIFICATION
                        // =================================================

                        if (
                            (int)($patient["email_verified"] ?? 0) !== 1 ||
                            (int)($patient["phone_verified"] ?? 0) !== 1
                        ) {

                            $_SESSION["pending_verification"] = [

                                "user_type" => "patient",

                                "user_id" => (int)$patient["id"],

                                "email" => $patient["email"],

                                "phone" => $patient["phone"]

                            ];


                            $verification_message = "contact";

                            $error =
                                "Please verify your email address and mobile number before logging in.";


                            log_security_event(
                                $ip,
                                $email,
                                'failed',
                                'patient'
                            );


                        } else {

                            // =============================================
                            // SUCCESSFUL PATIENT LOGIN
                            // =============================================

                            session_regenerate_id(true);

                            $_SESSION["user_id"] =
                                $patient["id"];

                            $_SESSION["user_name"] =
                                $patient["first_name"];

                            $_SESSION["user_type"] =
                                "patient";


                            log_security_event(
                                $ip,
                                $email,
                                'success',
                                'patient'
                            );


                            header("Location: patient/index.php");

                            exit;
                        }


                    } else {

                        $error =
                            "Invalid email or password.";


                        log_security_event(
                            $ip,
                            $email,
                            'failed',
                            'patient'
                        );
                    }


                } else {

                    $error =
                        "Invalid email or password.";


                    log_security_event(
                        $ip,
                        $email,
                        'failed',
                        'patient'
                    );
                }


                $stmt->close();
            }
        }


        // ===========================================================
        // DOCTOR LOGIN
        // ===========================================================

        elseif ($userType === "doctor") {

            $stmt = $connDoctor->prepare(
                "SELECT * FROM doctors WHERE email = ?"
            );


            if (!$stmt) {

                error_log(
                    "Doctor login prepare failed: " .
                    $connDoctor->error
                );

                $error =
                    "Unable to process login right now.";

            } else {

                $stmt->bind_param("s", $email);

                $stmt->execute();

                $result = $stmt->get_result();


                if ($result->num_rows === 1) {

                    $doctor = $result->fetch_assoc();


                    // ===================================================
                    // PASSWORD
                    // ===================================================

                    if (password_verify(
                        $passwordInput,
                        $doctor["password"]
                    )) {


                        // =================================================
                        // EMAIL + PHONE VERIFICATION
                        // =================================================

                        if (
                            (int)($doctor["email_verified"] ?? 0) !== 1 ||
                            (int)($doctor["phone_verified"] ?? 0) !== 1
                        ) {

                            $_SESSION["pending_verification"] = [

                                "user_type" => "doctor",

                                "user_id" => (int)$doctor["id"],

                                "email" => $doctor["email"],

                                "phone" => $doctor["phone"]

                            ];


                            $verification_message =
                                "contact";


                            $error =
                                "Please verify your email address and mobile number before logging in.";


                            log_security_event(
                                $ip,
                                $email,
                                'failed',
                                'doctor'
                            );


                        }


                        // =================================================
                        // ADMIN APPROVAL
                        // =================================================

                        elseif (
                            $doctor["verification_status"] !== "approved" ||
                            (int)$doctor["is_verified"] !== 1
                        ) {


                            if (
                                $doctor["verification_status"] === "pending"
                            ) {

                                $verification_message =
                                    "pending";


                                $error =
                                    "⏳ Your account is pending admin verification. Please wait for approval email.";


                            } elseif (
                                $doctor["verification_status"] === "rejected"
                            ) {

                                $verification_message =
                                    "rejected";


                                $error =
                                    "❌ Your account has been rejected. Reason: " .
                                    ($doctor["rejection_reason"] ?? "Not specified");


                            } else {

                                $error =
                                    "Your account is not verified yet. Please contact support.";
                            }


                            log_security_event(
                                $ip,
                                $email,
                                'failed',
                                'doctor'
                            );
                        }


                        // =================================================
                        // SUCCESSFUL DOCTOR LOGIN
                        // =================================================

                        else {

                            session_regenerate_id(true);

                            $_SESSION["user_id"] =
                                $doctor["id"];

                            $_SESSION["user_name"] =
                                $doctor["first_name"];

                            $_SESSION["user_type"] =
                                "doctor";


                            log_security_event(
                                $ip,
                                $email,
                                'success',
                                'doctor'
                            );


                            header(
                                "Location: doctor/doctor_dashboard.php"
                            );

                            exit;
                        }


                    } else {

                        $error =
                            "Invalid email or password.";


                        log_security_event(
                            $ip,
                            $email,
                            'failed',
                            'doctor'
                        );
                    }


                } else {

                    $error =
                        "Invalid email or password.";


                    log_security_event(
                        $ip,
                        $email,
                        'failed',
                        'doctor'
                    );
                }


                $stmt->close();
            }
        }


        // ===========================================================
        // INVALID USER TYPE
        // ===========================================================

        else {

            $error = "Invalid user type selected.";
        }
    }
}
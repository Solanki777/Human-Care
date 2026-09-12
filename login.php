<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===============================================================
// SECURITY LAYER (added) — see security/security_functions.php
// Fails safe: if the security DB is unreachable, these calls
// degrade harmlessly and login continues to work exactly as before.
// ===============================================================
require_once __DIR__ . '/security/security_functions.php';

$ip = $_SERVER['REMOTE_ADDR'];

if (is_ip_blocked($ip)) {
    // ------------------------------------------------------------------
    // IMPROVED BLOCK PAGE — shows attack type, reason, who blocked, when
    // (replaces the original one-liner die() message)
    // ------------------------------------------------------------------
    $blockDetails = get_block_details($ip);

    $threatTypeRaw  = $blockDetails['threat_type'] ?? 'Unknown';
    $reason         = $blockDetails['reason']      ?? 'Suspicious activity detected.';
    $blockedBy      = $blockDetails['blocked_by']  ?? 'Security System';
    $blockedAtRaw   = $blockDetails['blocked_at']  ?? '';
    $expiresAtRaw   = $blockDetails['expires_at']  ?? null;

    // Human-readable labels
    $threatLabels = [
        'brute_force'        => 'Brute Force Attack',
        'sql_injection'      => 'SQL Injection Attempt',
        'suspicious_rate'    => 'Suspicious Login Rate',
        'blocked_ip_reuse'   => 'Blocked IP Reuse',
        'credential_stuffing'=> 'Credential Stuffing Attack',
        'password_spraying'  => 'Password Spraying Attack',
    ];
    $attackType = $threatLabels[$threatTypeRaw] ?? ucwords(str_replace('_', ' ', $threatTypeRaw));

    $blockedByLabel = match(strtolower($blockedBy)) {
        'php'    => 'PHP Security Layer',
        'nexora' => 'Nexora AI',
        default  => htmlspecialchars($blockedBy),
    };

    $blockedAtLabel  = $blockedAtRaw  ? date('d M Y, H:i:s', strtotime($blockedAtRaw))  : 'Unknown';
    $expiresAtLabel  = $expiresAtRaw  ? date('d M Y, H:i:s', strtotime($expiresAtRaw))  : 'Permanent';

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Blocked – Human Care</title>
        <link rel="stylesheet" href="styles/login.css">
       
    </head>
    <body>
        <div class="block-card">
            <div class="block-icon">🚫</div>
            <h1>Access Blocked</h1>
            <p class="subtitle">Your IP address has been flagged and blocked by the security system.</p>

            <div class="detail-grid">
                <div class="detail-row">
                    <span class="detail-label">IP Address</span>
                    <span class="detail-value"><span class="badge badge-red"><?= htmlspecialchars($ip) ?></span></span>
                </div>
                <hr class="block-divider">
                <div class="detail-row">
                    <span class="detail-label">Attack Type</span>
                    <span class="detail-value"><span class="badge badge-red"><?= htmlspecialchars($attackType) ?></span></span>
                </div>
                <hr class="block-divider">
                <div class="detail-row">
                    <span class="detail-label">Reason</span>
                    <span class="detail-value"><?= htmlspecialchars($reason) ?></span>
                </div>
                <hr class="block-divider">
                <div class="detail-row">
                    <span class="detail-label">Blocked By</span>
                    <span class="detail-value">
                        <?php if (strtolower($blockedBy) === 'nexora'): ?>
                            <span class="badge badge-purple">🤖 <?= $blockedByLabel ?></span>
                        <?php else: ?>
                            <span class="badge badge-blue">🛡️ <?= $blockedByLabel ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <hr class="block-divider">
                <div class="detail-row">
                    <span class="detail-label">Blocked At</span>
                    <span class="detail-value"><?= htmlspecialchars($blockedAtLabel) ?></span>
                </div>
                <hr class="block-divider">
                <div class="detail-row">
                    <span class="detail-label">Expires At</span>
                    <span class="detail-value"><?= htmlspecialchars($expiresAtLabel) ?></span>
                </div>
            </div>

            <p class="footer-note">
                If you believe this is a mistake, please contact your system administrator.<br>
                <a href="mailto:admin@humancare.local">admin@humancare.local</a>
            </p>

            <div class="nexora-badge">
                🛡️ Protected by <strong style="color:#a0aaff; margin: 0 3px;">Nexora</strong> Autonomous AI Security
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}



/* ===============================
   CONNECT DATABASES
================================ */

require_once __DIR__ . '/config/database.php';

try {
    $connPatient = Database::getConnection('patients');
    $connDoctor  = Database::getConnection('doctors');
} catch (Exception $e) {
    error_log("Login database connection failed: " . $e->getMessage());
    die("Database connection failed");
}



if ($connPatient->connect_error || $connDoctor->connect_error) {
    die("Database connection failed");
}

$error = "";
$verification_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $passwordInput = trim($_POST["password"]);

    // ===========================================================
    // SECURITY LAYER (added): fast, synchronous threat checks
    // ===========================================================
    $threat = check_login_threat($ip, $email, $passwordInput);
    if ($threat) {
        block_ip($ip, $threat['label'], $threat['reason']);
        log_security_event($ip, $email, 'failed', null, $threat['type']);

        // Use improved block page for threat-triggered blocks too
        $blockDetails = get_block_details($ip);
        $threatTypeRaw  = $blockDetails['threat_type'] ?? $threat['type'];
        $reason         = $blockDetails['reason']      ?? $threat['reason'];
        $blockedBy      = $blockDetails['blocked_by']  ?? 'php';
        $blockedAtRaw   = $blockDetails['blocked_at']  ?? date('Y-m-d H:i:s');
        $expiresAtRaw   = $blockDetails['expires_at']  ?? null;

        $threatLabels = [
            'brute_force'        => 'Brute Force Attack',
            'sql_injection'      => 'SQL Injection Attempt',
            'suspicious_rate'    => 'Suspicious Login Rate',
            'blocked_ip_reuse'   => 'Blocked IP Reuse',
            'credential_stuffing'=> 'Credential Stuffing Attack',
            'password_spraying'  => 'Password Spraying Attack',
        ];
        $attackType    = $threatLabels[$threatTypeRaw] ?? ucwords(str_replace('_', ' ', $threatTypeRaw));
        $blockedByLabel = match(strtolower($blockedBy)) {
            'php'    => 'PHP Security Layer',
            'nexora' => 'Nexora AI',
            default  => htmlspecialchars($blockedBy),
        };
        $blockedAtLabel = $blockedAtRaw ? date('d M Y, H:i:s', strtotime($blockedAtRaw)) : 'Now';
        $expiresAtLabel = $expiresAtRaw ? date('d M Y, H:i:s', strtotime($expiresAtRaw)) : 'Permanent';

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Blocked – Human Care</title>
            <link rel="stylesheet" href="styles/login.css">
            
        </head>
        <body>
            <div class="block-card">
                <div class="block-icon">🚫</div>
                <h1>Access Blocked</h1>
                <p class="subtitle">Your IP address has been flagged and blocked by the security system.</p>
                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="detail-label">IP Address</span>
                        <span class="detail-value"><span class="badge badge-red"><?= htmlspecialchars($ip) ?></span></span>
                    </div>
                    <hr class="block-divider">
                    <div class="detail-row">
                        <span class="detail-label">Attack Type</span>
                        <span class="detail-value"><span class="badge badge-red"><?= htmlspecialchars($attackType) ?></span></span>
                    </div>
                    <hr class="block-divider">
                    <div class="detail-row">
                        <span class="detail-label">Reason</span>
                        <span class="detail-value"><?= htmlspecialchars($reason) ?></span>
                    </div>
                    <hr class="block-divider">
                    <div class="detail-row">
                        <span class="detail-label">Blocked By</span>
                        <span class="detail-value">
                            <?php if (strtolower($blockedBy) === 'nexora'): ?>
                                <span class="badge badge-purple">🤖 <?= $blockedByLabel ?></span>
                            <?php else: ?>
                                <span class="badge badge-blue">🛡️ <?= $blockedByLabel ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <hr class="block-divider">
                    <div class="detail-row">
                        <span class="detail-label">Blocked At</span>
                        <span class="detail-value"><?= htmlspecialchars($blockedAtLabel) ?></span>
                    </div>
                    <hr class="block-divider">
                    <div class="detail-row">
                        <span class="detail-label">Expires At</span>
                        <span class="detail-value"><?= htmlspecialchars($expiresAtLabel) ?></span>
                    </div>
                </div>
                <p class="footer-note">
                    If you believe this is a mistake, please contact your system administrator.<br>
                    <a href="mailto:admin@humancare.local">admin@humancare.local</a>
                </p>
                <div class="nexora-badge">
                    🛡️ Protected by <strong style="color:#a0aaff; margin: 0 3px;">Nexora</strong> Autonomous AI Security
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    if (empty($email) || empty($passwordInput)) {
        $error = "Please fill in both fields.";
    } 
    else {

        /* ===============================
           CHECK PATIENT LOGIN
        =============================== */
        $stmt = $connPatient->prepare("SELECT * FROM patients WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $patient = $result->fetch_assoc();

            if (password_verify($passwordInput, $patient["password"])) {

                // Email/mobile verification must be completed before login.
                if ((int)($patient["email_verified"] ?? 0) !== 1 ||
                    (int)($patient["phone_verified"] ?? 0) !== 1) {

                    $_SESSION["pending_verification"] = [
                        "user_type" => "patient",
                        "user_id"   => (int)$patient["id"],
                        "email"     => $patient["email"],
                        "phone"     => $patient["phone"]
                    ];

                    $verification_message = "contact";
                    $error = "Please verify your email address and mobile number before logging in.";

                    log_security_event($ip, $email, 'failed', 'patient');
                } else {

                    $_SESSION["user_id"]   = $patient["id"];
                    $_SESSION["user_name"] = $patient["first_name"];
                    $_SESSION["user_type"] = "patient";

                    log_security_event($ip, $email, 'success', 'patient');

                    header("Location: patient/index.php");
                    exit;
                }
            } else {
                $error = "Invalid email or password.";
                log_security_event($ip, $email, 'failed', 'patient');
            }
        } else {

            /* ===============================
               CHECK DOCTOR LOGIN
            =============================== */
            $stmt = $connDoctor->prepare("SELECT * FROM doctors WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $doctor = $result->fetch_assoc();

                if (password_verify($passwordInput, $doctor["password"])) {

                    // Email/mobile verification is separate from admin/doctor approval.
                    if ((int)($doctor["email_verified"] ?? 0) !== 1 ||
                        (int)($doctor["phone_verified"] ?? 0) !== 1) {

                        $_SESSION["pending_verification"] = [
                            "user_type" => "doctor",
                            "user_id"   => (int)$doctor["id"],
                            "email"     => $doctor["email"],
                            "phone"     => $doctor["phone"]
                        ];

                        $verification_message = "contact";
                        $error = "Please verify your email address and mobile number before logging in.";

                        log_security_event($ip, $email, 'failed', 'doctor');

                    } elseif ($doctor["verification_status"] !== "approved" || $doctor["is_verified"] != 1) {
                        
                        if ($doctor["verification_status"] === "pending") {
                            $verification_message = "pending";
                            $error = "⏳ Your account is pending admin verification. Please wait for approval email.";
                        } elseif ($doctor["verification_status"] === "rejected") {
                            $verification_message = "rejected";
                            $error = "❌ Your account has been rejected. Reason: " . ($doctor["rejection_reason"] ?? "Not specified");
                        } else {
                            $error = "Your account is not verified yet. Please contact support.";
                        }
                        
                    } else {
                        $_SESSION["user_id"]   = $doctor["id"];
                        $_SESSION["user_name"] = $doctor["first_name"];
                        $_SESSION["user_type"] = "doctor";

                        log_security_event($ip, $email, 'success', 'doctor');

                        header("Location: doctor_dashboard.php");
                        exit;
                    }
                } else {
                    $error = "Invalid email or password.";
                    log_security_event($ip, $email, 'failed', 'doctor');
                }
            } 
            else {
                $error = "Invalid email or password.";
                log_security_event($ip, $email, 'failed', null);
            }
        }
        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Human Care - Login</title>
    <link rel="stylesheet" href="styles/login.css">
    
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                <div class="logo-icon">❤️</div>
                HUMAN CARE
            </div>
            <p class="tagline">Your Health, Our Priority</p>
            <ul class="features">
                <li>Easy appointment booking</li>
                <li>24/7 online consultation</li>
                <li>Doctor and Patient Validation</li>
                <li>Education</li>
            </ul>
        </div>

        <div class="login-right">
            <div class="login-header">
                <h2>Welcome Back!</h2>
                <p>Login to access your healthcare dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message" style="display: block; background: #fee; color: #c33; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c33; font-size: 14px;">
                    <?php echo $error; ?>
                </div>
                
                <?php if ($verification_message === "contact"): ?>
                    <div class="verification-box">
                        <strong>📧📱 Verify Your Contact Details</strong>
                        <p>Your email address and mobile number must be verified before you can log in.</p>
                        <a href="verify.php">🔐 Verify Email & Mobile</a>
                    </div>
                <?php elseif ($verification_message === "pending"): ?>
                    <div class="verification-box">
                        <strong>Check Your Verification Status</strong>
                        <p>You can check your registration status and get updates about your application.</p>
                        <a href="check_status.php">🔍 Check Status Now</a>
                    </div>
                <?php elseif ($verification_message === "rejected"): ?>
                    <div class="verification-box">
                        <strong>Need Help?</strong>
                        <p>Contact our support team or check your status for more details.</p>
                        <a href="check_status.php">🔍 View Full Details</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="">
                
                <div class="user-type-selection">
                    <label class="selection-title">Login As</label>

                    <div class="user-type-options">
                        <label class="user-type-card">
                            <input type="radio" name="user_type" value="patient" checked>
                            <div class="user-type-content">
                                <span class="user-type-icon">👤</span>
                                <span class="user-type-name">Patient</span>
                                <span class="user-type-description">Patient Portal</span>
                            </div>
                        </label>

                        <label class="user-type-card">
                            <input type="radio" name="user_type" value="doctor">
                            <div class="user-type-content">
                                <span class="user-type-icon">👨‍⚕️</span>
                                <span class="user-type-name">Doctor</span>
                                <span class="user-type-description">Doctor Portal</span>
                            </div>
                        </label>
                    </div>
                </div>


                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" id="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn">Login</button>

                <div class="login-divider">or</div>

                <div class="signup-link">
                    Don't have an account? <a href="register.php">Sign Up Now</a><br>
                    <small style="color: #666; margin-top: 10px; display: inline-block;">
                        Doctor? <a href="check_status.php" style="color: #667eea;">Check verification status</a>
                    </small>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
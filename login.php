<?php
session_start();

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
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
                font-family: 'Segoe UI', sans-serif;
                color: #fff;
                padding: 20px;
            }
            .block-card {
                background: rgba(255,255,255,0.05);
                backdrop-filter: blur(12px);
                border: 1px solid rgba(255,80,80,0.35);
                border-radius: 16px;
                padding: 44px 40px;
                max-width: 560px;
                width: 100%;
                text-align: center;
                box-shadow: 0 8px 40px rgba(255,50,50,0.18);
            }
            .block-icon { font-size: 62px; margin-bottom: 18px; }
            h1 {
                font-size: 26px;
                font-weight: 700;
                color: #ff5f5f;
                margin-bottom: 8px;
            }
            .subtitle {
                font-size: 14px;
                color: rgba(255,255,255,0.55);
                margin-bottom: 32px;
            }
            .detail-grid {
                text-align: left;
                background: rgba(255,255,255,0.04);
                border-radius: 10px;
                padding: 20px 24px;
                margin-bottom: 28px;
                display: grid;
                gap: 14px;
            }
            .detail-row {
                display: grid;
                grid-template-columns: 160px 1fr;
                gap: 8px;
                align-items: start;
            }
            .detail-label {
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: rgba(255,255,255,0.45);
                padding-top: 2px;
            }
            .detail-value {
                font-size: 14px;
                color: #fff;
                word-break: break-word;
            }
            .badge {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
            }
            .badge-red    { background: rgba(255,80,80,0.2);  color: #ff7070; border: 1px solid rgba(255,80,80,0.4); }
            .badge-purple { background: rgba(150,80,255,0.2); color: #bb88ff; border: 1px solid rgba(150,80,255,0.4); }
            .badge-blue   { background: rgba(80,150,255,0.2); color: #70aaff; border: 1px solid rgba(80,150,255,0.4); }
            .divider {
                border: none;
                border-top: 1px solid rgba(255,255,255,0.08);
                margin: 4px 0;
            }
            .footer-note {
                font-size: 12px;
                color: rgba(255,255,255,0.35);
                line-height: 1.6;
            }
            .footer-note a { color: rgba(100,180,255,0.7); text-decoration: none; }
            .footer-note a:hover { text-decoration: underline; }
            .nexora-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin-top: 20px;
                padding: 8px 16px;
                background: rgba(80,100,255,0.12);
                border: 1px solid rgba(80,100,255,0.25);
                border-radius: 20px;
                font-size: 12px;
                color: rgba(180,190,255,0.7);
            }
        </style>
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
                <hr class="divider">
                <div class="detail-row">
                    <span class="detail-label">Attack Type</span>
                    <span class="detail-value"><span class="badge badge-red"><?= htmlspecialchars($attackType) ?></span></span>
                </div>
                <hr class="divider">
                <div class="detail-row">
                    <span class="detail-label">Reason</span>
                    <span class="detail-value"><?= htmlspecialchars($reason) ?></span>
                </div>
                <hr class="divider">
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
                <hr class="divider">
                <div class="detail-row">
                    <span class="detail-label">Blocked At</span>
                    <span class="detail-value"><?= htmlspecialchars($blockedAtLabel) ?></span>
                </div>
                <hr class="divider">
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



$servername = "localhost";
$username = "root";
$password = "";

/* ===============================
   CONNECT DATABASES
================================ */
$connPatient = new mysqli($servername, $username, $password, "human_care_patients");
$connDoctor  = new mysqli($servername, $username, $password, "human_care_doctors");

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
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
                    font-family: 'Segoe UI', sans-serif;
                    color: #fff;
                    padding: 20px;
                }
                .block-card {
                    background: rgba(255,255,255,0.05);
                    backdrop-filter: blur(12px);
                    border: 1px solid rgba(255,80,80,0.35);
                    border-radius: 16px;
                    padding: 44px 40px;
                    max-width: 560px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 8px 40px rgba(255,50,50,0.18);
                }
                .block-icon { font-size: 62px; margin-bottom: 18px; }
                h1 { font-size: 26px; font-weight: 700; color: #ff5f5f; margin-bottom: 8px; }
                .subtitle { font-size: 14px; color: rgba(255,255,255,0.55); margin-bottom: 32px; }
                .detail-grid {
                    text-align: left;
                    background: rgba(255,255,255,0.04);
                    border-radius: 10px;
                    padding: 20px 24px;
                    margin-bottom: 28px;
                    display: grid;
                    gap: 14px;
                }
                .detail-row { display: grid; grid-template-columns: 160px 1fr; gap: 8px; align-items: start; }
                .detail-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(255,255,255,0.45); padding-top: 2px; }
                .detail-value { font-size: 14px; color: #fff; word-break: break-word; }
                .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
                .badge-red    { background: rgba(255,80,80,0.2);  color: #ff7070; border: 1px solid rgba(255,80,80,0.4); }
                .badge-purple { background: rgba(150,80,255,0.2); color: #bb88ff; border: 1px solid rgba(150,80,255,0.4); }
                .badge-blue   { background: rgba(80,150,255,0.2); color: #70aaff; border: 1px solid rgba(80,150,255,0.4); }
                .divider { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 4px 0; }
                .footer-note { font-size: 12px; color: rgba(255,255,255,0.35); line-height: 1.6; }
                .footer-note a { color: rgba(100,180,255,0.7); text-decoration: none; }
                .nexora-badge { display: inline-flex; align-items: center; gap: 6px; margin-top: 20px; padding: 8px 16px; background: rgba(80,100,255,0.12); border: 1px solid rgba(80,100,255,0.25); border-radius: 20px; font-size: 12px; color: rgba(180,190,255,0.7); }
            </style>
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
                    <hr class="divider">
                    <div class="detail-row">
                        <span class="detail-label">Attack Type</span>
                        <span class="detail-value"><span class="badge badge-red"><?= htmlspecialchars($attackType) ?></span></span>
                    </div>
                    <hr class="divider">
                    <div class="detail-row">
                        <span class="detail-label">Reason</span>
                        <span class="detail-value"><?= htmlspecialchars($reason) ?></span>
                    </div>
                    <hr class="divider">
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
                    <hr class="divider">
                    <div class="detail-row">
                        <span class="detail-label">Blocked At</span>
                        <span class="detail-value"><?= htmlspecialchars($blockedAtLabel) ?></span>
                    </div>
                    <hr class="divider">
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

                $_SESSION["user_id"]   = $patient["id"];
                $_SESSION["user_name"] = $patient["first_name"];
                $_SESSION["user_type"] = "patient";

                log_security_event($ip, $email, 'success', 'patient');

                header("Location: index.php");
                exit;
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

                    if ($doctor["verification_status"] !== "approved" || $doctor["is_verified"] != 1) {
                        
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
    <style>
        .verification-box {
            background: #e0e7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .verification-box strong {
            display: block;
            margin-bottom: 8px;
            color: #333;
        }
        
        .verification-box a {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .verification-box a:hover {
            background: #5568d3;
            transform: translateY(-2px);
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
                
                <?php if ($verification_message === "pending"): ?>
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
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn">Login</button>

                <div class="divider">or</div>

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
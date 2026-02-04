<?php
session_start();

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

                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid email or password.";
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

                    // ✅ CHECK IF DOCTOR IS VERIFIED
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
                        // Doctor is verified, allow login
                        $_SESSION["user_id"]   = $doctor["id"];
                        $_SESSION["user_name"] = $doctor["first_name"];
                        $_SESSION["user_type"] = "doctor";

                        header("Location: doctor_dashboard.php");
                        exit;
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
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
                <li>Access medical records anytime</li>
                <li>Prescription management</li>
                <li>Health tracking & reminders</li>
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
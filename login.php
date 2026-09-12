<?php

require_once __DIR__ . '/includes/session.php';

// rest of your existing login.php code...
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/auth/block_handler.php';
require_once __DIR__ . '/auth/login_database.php';
require_once __DIR__ . '/auth/login_handler.php';

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
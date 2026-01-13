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

                header("Location: dashboard.php");
                exit;
            }
        }

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

                $_SESSION["user_id"]   = $doctor["id"];
                $_SESSION["user_name"] = $doctor["first_name"];
                $_SESSION["user_type"] = "doctor";

                header("Location: doctor_dashboard.php");
                exit;
            }
        }

        /* ===============================
           INVALID LOGIN
        =============================== */
        $error = "Invalid email or password.";
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
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
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
                    Don't have an account? <a href="register.php">Sign Up Now</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

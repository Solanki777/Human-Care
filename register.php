<?php
// === DATABASE CONNECTION ===
$servername = "localhost";
$username = "root"; // default for XAMPP
$password = "";     // default for XAMPP (keep empty)
$dbname = "human_care";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
}

$success = "";
$emailError = "";
$passwordError = "";

// === FORM SUBMISSION HANDLING ===
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = $_POST["firstName"];
    $lastName = $_POST["lastName"];
    $email = $_POST["email"];
    $phone = $_POST["phone"];
    $dob = $_POST["dob"];
    $gender = $_POST["gender"];
    $bloodGroup = $_POST["bloodGroup"];
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirmPassword"];

    // check password match
    if ($password !== $confirmPassword) {
        $passwordError = "Passwords do not match!";
    } else {
        // check if email already exists
        $check = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $emailError = "This email is already registered!";
        } else {
            // hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, dob, gender, blood_group, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $firstName, $lastName, $email, $phone, $dob, $gender, $bloodGroup, $hashedPassword);

            if ($stmt->execute()) {
                $success = "Registration successful! Redirecting to login...";
                echo "<script>
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                      </script>";
            } else {
                $success = "❌ Registration failed. Try again!";
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Human Care - Register</title>
    <link rel="stylesheet" href="styles/register.css">
</head>
<body>
    <div class="register-container">
        <div class="register-left">
            <div class="logo">
                <div class="logo-icon">❤️</div>
                HUMAN CARE
            </div>
            <p class="tagline">Join our healthcare community today!</p>
            <ul class="features">
                <li>Easy appointment booking</li>
                <li>24/7 online consultation</li>
                <li>Access medical records anytime</li>
                <li>Find doctors & specialists</li>
                <li>Prescription management</li>
                <li>Health tracking & reminders</li>
                <li>Free health education resources</li>
            </ul>
        </div>

        <div class="register-right">
            <div class="nav-links">
                <a href="public.html" class="nav-link">← Back to Home</a>
            </div>

            <div class="register-header">
                <h2>Create Your Account</h2>
                <p>Start your healthcare journey with us</p>
            </div>

            <?php if ($success): ?>
                <div class="success-message" style="display:block;"><?php echo $success; ?></div>
            <?php else: ?>
                <div class="success-message" id="successMessage">Registration successful! Redirecting to login...</div>
            <?php endif; ?>

            <form id="registerForm" method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" id="firstName" name="firstName" placeholder="John" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="lastName">Last Name <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" id="lastName" name="lastName" placeholder="Doe" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="john.doe@example.com" required>
                    </div>
                    <?php if ($emailError): ?>
                        <div class="error-message" style="display:block;"><?php echo $emailError; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="tel" id="phone" name="phone" placeholder="+91 1234567890" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="dob">Date of Birth <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="date" id="dob" name="dob" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bloodGroup">Blood Group</label>
                        <div class="input-wrapper">
                            <select id="bloodGroup" name="bloodGroup">
                                <option value="">Select Blood Group</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Re-enter your password" required>
                    </div>
                    <?php if ($passwordError): ?>
                        <div class="error-message" style="display:block;"><?php echo $passwordError; ?></div>
                    <?php endif; ?>
                </div>

                <div class="terms-check">
                    <input type="checkbox" id="terms" required>
                    <label for="terms">
                        I agree to the <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="register-btn" id="registerBtn">Create Account</button>

                <div class="divider">or</div>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login Here</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

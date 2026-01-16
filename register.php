<?php
$servername = "localhost";
$username   = "root";
$password   = "";

$success       = "";
$emailError    = "";
$passwordError = "";
$fileError     = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $firstName       = $_POST["firstName"];
    $lastName        = $_POST["lastName"];
    $email           = $_POST["email"];
    $phone           = $_POST["phone"];
    $dob             = $_POST["dob"];
    $gender          = $_POST["gender"];
    $bloodGroup      = $_POST["bloodGroup"];
    $passwordInput   = $_POST["password"];
    $confirmPassword = $_POST["confirmPassword"];
    $userType        = $_POST["userType"]; // patient / doctor

    $licenseNumber   = $_POST["licenseNumber"] ?? null;
    $specialization  = $_POST["specialization"] ?? null;
    $verificationPhoto = null;

    // ===============================
    // PASSWORD CHECK
    // ===============================
    if ($passwordInput !== $confirmPassword) {
        $passwordError = "Passwords do not match!";
    } else {

        /* =====================================================
           PATIENT REGISTRATION
        ===================================================== */
        if ($userType === "patient") {

            $conn = new mysqli($servername, $username, $password, "human_care_patients");

            $check = $conn->prepare(
                "SELECT id FROM patients WHERE email = ?"
            );
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $emailError = "Email already registered!";
            } else {

                $hashedPassword = password_hash($passwordInput, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    "INSERT INTO patients
                    (first_name, last_name, email, phone, dob, gender, blood_group, password)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "ssssssss",
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $dob,
                    $gender,
                    $bloodGroup,
                    $hashedPassword
                );

                $stmt->execute();

                $success = "Patient registration successful! Redirecting to login...";
                echo "<script>
                        setTimeout(() => window.location.href='login.php', 2500);
                      </script>";
            }
        }

        /* =====================================================
           DOCTOR REGISTRATION
        ===================================================== */
        if ($userType === "doctor") {

            $conn = new mysqli($servername, $username, $password, "human_care_doctors");

            // ✅ CHECK EMAIL OR LICENSE DUPLICATE
            $check = $conn->prepare(
                "SELECT id, email, license_number
                 FROM doctors
                 WHERE email = ? OR license_number = ?"
            );
            $check->bind_param("ss", $email, $licenseNumber);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {

                $checkDetail = $conn->prepare(
                    "SELECT email, license_number
                     FROM doctors
                     WHERE email = ? OR license_number = ?
                     LIMIT 1"
                );
                $checkDetail->bind_param("ss", $email, $licenseNumber);
                $checkDetail->execute();
                $resultDetail = $checkDetail->get_result()->fetch_assoc();

                if ($resultDetail["email"] === $email) {
                    $emailError = "This email is already registered!";
                } else {
                    $emailError = "This medical license number is already registered!";
                }

            } else {

                // ===============================
                // UPLOAD VERIFICATION IMAGE
                // ===============================
                if (!empty($_FILES["verificationPhoto"]["name"])) {

                    $uploadDir = "uploads/doctor_verification/";
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileName   = uniqid() . "_" . basename($_FILES["verificationPhoto"]["name"]);
                    $uploadPath = $uploadDir . $fileName;

                    move_uploaded_file(
                        $_FILES["verificationPhoto"]["tmp_name"],
                        $uploadPath
                    );

                    $verificationPhoto = $uploadPath;
                }

                $hashedPassword = password_hash($passwordInput, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    "INSERT INTO doctors
                    (first_name, last_name, email, phone, dob, gender, password, specialty, license_number)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "sssssssss",
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $dob,
                    $gender,
                    $hashedPassword,
                    $specialization,
                    $licenseNumber
                );

                $stmt->execute();

                $success = "Doctor registration successful! Redirecting to login...";
                echo "<script>
                        setTimeout(() => window.location.href='login.php', 3000);
                      </script>";
            }
        }
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
    <style>
        .user-type-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .user-type-option {
            flex: 1;
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-type-option:hover {
            border-color: #4CAF50;
            background-color: #f9f9f9;
        }

        .user-type-option.active {
            border-color: #4CAF50;
            background-color: #e8f5e9;
        }

        .user-type-option input[type="radio"] {
            display: none;
        }

        .user-type-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .user-type-title {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .user-type-desc {
            font-size: 13px;
            color: #666;
        }

        .doctor-fields {
            display: none;
        }

        .doctor-fields.show {
            display: block;
        }

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-label {
            display: block;
            padding: 12px;
            background-color: #f5f5f5;
            border: 2px dashed #ccc;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            background-color: #e8f5e9;
            border-color: #4CAF50;
        }

        .file-upload-input {
            position: absolute;
            left: -9999px;
        }

        .file-name {
            margin-top: 8px;
            font-size: 13px;
            color: #4CAF50;
        }
    </style>
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
                <!-- <li>Find doctors & specialists</li> -->
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

            <form id="registerForm" method="POST" action="" enctype="multipart/form-data">
                <!-- User Type Selection -->
                <div class="user-type-selector">
                    <label class="user-type-option active" id="patientOption">
                        <input type="radio" name="userType" value="patient" checked>
                        <div class="user-type-icon">🧑‍⚕️</div>
                        <div class="user-type-title">Patient</div>
                        <div class="user-type-desc">Book appointments & consult doctors</div>
                    </label>

                    <label class="user-type-option" id="doctorOption">
                        <input type="radio" name="userType" value="doctor">
                        <div class="user-type-icon">👨‍⚕️</div>
                        <div class="user-type-title">Doctor</div>
                        <div class="user-type-desc">Provide medical services & consultations</div>
                    </label>
                </div>

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

                <!-- Doctor-specific fields -->
                <div id="doctorFields" class="doctor-fields">
                    <div class="form-group">
                        <label for="licenseNumber">Medical License Number <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" id="licenseNumber" name="licenseNumber"
                                placeholder="Enter your medical license number">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="specialization">Specialization <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <select id="specialization" name="specialization">
                                <option value="">Select Specialization</option>
                                <option value="general">General Physician</option>
                                <option value="cardiology">Cardiology</option>
                                <option value="dermatology">Dermatology</option>
                                <option value="neurology">Neurology</option>
                                <option value="orthopedics">Orthopedics</option>
                                <option value="pediatrics">Pediatrics</option>
                                <option value="psychiatry">Psychiatry</option>
                                <option value="surgery">Surgery</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="verificationPhoto">Verification Photo (License/ID) <span
                                class="required">*</span></label>
                        <div class="file-upload-wrapper">
                            <label for="verificationPhoto" class="file-upload-label">
                                📷 Click to upload your medical license or ID photo
                                <div class="file-name" id="fileName"></div>
                            </label>
                            <input type="file" id="verificationPhoto" name="verificationPhoto" class="file-upload-input"
                                accept="image/*">
                        </div>
                        <small style="color: #666; font-size: 12px;">Upload a clear photo of your medical license (JPG,
                            PNG - Max 5MB)</small>
                        <?php if ($fileError): ?>
                            <div class="error-message" style="display:block; margin-top: 8px;"><?php echo $fileError; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Create a strong password"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="confirmPassword" name="confirmPassword"
                            placeholder="Re-enter your password" required>
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

    <script>
        // User type selection
        const patientOption = document.getElementById('patientOption');
        const doctorOption = document.getElementById('doctorOption');
        const doctorFields = document.getElementById('doctorFields');
        const licenseInput = document.getElementById('licenseNumber');
        const specializationInput = document.getElementById('specialization');
        const photoInput = document.getElementById('verificationPhoto');

        patientOption.addEventListener('click', function () {
            patientOption.classList.add('active');
            doctorOption.classList.remove('active');
            doctorFields.classList.remove('show');

            // Remove required attribute from doctor fields
            licenseInput.removeAttribute('required');
            specializationInput.removeAttribute('required');
            photoInput.removeAttribute('required');
        });

        doctorOption.addEventListener('click', function () {
            doctorOption.classList.add('active');
            patientOption.classList.remove('active');
            doctorFields.classList.add('show');

            // Add required attribute to doctor fields
            licenseInput.setAttribute('required', 'required');
            specializationInput.setAttribute('required', 'required');
            photoInput.setAttribute('required', 'required');
        });

        // File upload display
        photoInput.addEventListener('change', function (e) {
            const fileName = e.target.files[0]?.name;
            document.getElementById('fileName').textContent = fileName ? `Selected: ${fileName}` : '';
        });
    </script>
</body>

</html>
<?php
session_start();

// ============================================================
// SECURITY: CSRF Protection
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$servername = "localhost";
$username = "root";
$password = "";

$success       = "";
$emailError    = "";
$passwordError = "";
$fileError     = "";
$generalError  = "";

$old = [
    'firstName' => '', 'lastName' => '', 'email' => '', 'phone' => '',
    'dob' => '', 'gender' => '', 'bloodGroup' => '', 'userType' => 'patient',
    'licenseNumber' => '', 'specialization' => ''
];

/**
 * Generates a strong OTP containing uppercase, lowercase, number and special
 * character, then cryptographically shuffles the characters.
 */
function generateStrongOtp(int $length = 10): string
{
    if ($length < 4) {
        $length = 4;
    }

    $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower   = 'abcdefghijkmnopqrstuvwxyz';
    $numbers = '23456789';
    $special = '@#$%&*!?';

    $otp = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $numbers[random_int(0, strlen($numbers) - 1)],
        $special[random_int(0, strlen($special) - 1)]
    ];

    $all = $upper . $lower . $numbers . $special;

    while (count($otp) < $length) {
        $otp[] = $all[random_int(0, strlen($all) - 1)];
    }

    // Fisher-Yates shuffle using random_int.
    for ($i = count($otp) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$otp[$i], $otp[$j]] = [$otp[$j], $otp[$i]];
    }

    return implode('', $otp);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {

        $generalError = "Security validation failed. Please refresh the page and try again.";

    } else {

        $firstName = trim($_POST["firstName"] ?? '');
        $lastName  = trim($_POST["lastName"] ?? '');
        $email     = trim($_POST["email"] ?? '');
        $phone     = trim($_POST["phone"] ?? '');
        $dob       = trim($_POST["dob"] ?? '');
        $gender    = trim($_POST["gender"] ?? '');
        $bloodGroup = trim($_POST["bloodGroup"] ?? '');
        $passwordInput   = $_POST["password"] ?? '';
        $confirmPassword = $_POST["confirmPassword"] ?? '';
        $userType  = trim($_POST["userType"] ?? '');

        $licenseNumber  = trim($_POST["licenseNumber"] ?? '');
        $specialization = trim($_POST["specialization"] ?? '');

        $old = compact(
            'firstName', 'lastName', 'email', 'phone', 'dob',
            'gender', 'bloodGroup', 'userType',
            'licenseNumber', 'specialization'
        );

        $allowedUserTypes = ['patient', 'doctor'];
        $allowedGenders = ['male', 'female', 'other'];
        $allowedBloodGroups = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $allowedSpecializations = [
            '', 'general', 'cardiology', 'dermatology', 'neurology',
            'orthopedics', 'pediatrics', 'psychiatry', 'surgery', 'other'
        ];

        $validationErrors = [];

        if (!in_array($userType, $allowedUserTypes, true)) {
            $validationErrors[] = "Invalid account type selected.";
        }

        if (!preg_match("/^[a-zA-Z\s\-']{1,50}$/", $firstName)) {
            $validationErrors[] = "First name contains invalid characters or is too long.";
        }

        if (!preg_match("/^[a-zA-Z\s\-']{1,50}$/", $lastName)) {
            $validationErrors[] = "Last name contains invalid characters or is too long.";
        }

        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $old['email'] = $email;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $validationErrors[] = "Please enter a valid email address.";
        }

        // Mobile number validation:
        // Human Care uses Indian mobile numbers: exactly 10 digits,
        // starting with 6, 7, 8, or 9. Spaces, +91, brackets and hyphens
        // are intentionally rejected so the stored value is consistent.
        if (!preg_match("/^[6-9][0-9]{9}$/", $phone)) {
            $validationErrors[] = "Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.";
        }

        // Age validation:
        // Registration is allowed only for users who are 18 years or older.
        $dobTimestamp = strtotime($dob);

        if ($dob === '' || $dobTimestamp === false) {

            $validationErrors[] = "Please provide a valid date of birth.";

        } else {

            try {
                $dobDate = new DateTime($dob);
                $today = new DateTime('today');

                if ($dobDate > $today) {

                    $validationErrors[] = "Date of birth cannot be in the future.";

                } else {

                    $age = $dobDate->diff($today)->y;

                    if ($age < 18) {
                        $validationErrors[] = "You must be 18 years or older to register.";
                    } elseif ($age > 120) {
                        $validationErrors[] = "Please provide a valid date of birth.";
                    }
                }

            } catch (Exception $e) {
                $validationErrors[] = "Please provide a valid date of birth.";
            }
        }

        if (!in_array($gender, $allowedGenders, true)) {
            $validationErrors[] = "Please select a valid gender.";
        }

        if (!in_array($bloodGroup, $allowedBloodGroups, true)) {
            $validationErrors[] = "Please select a valid blood group.";
        }

        if (strlen($passwordInput) < 8 ||
            !preg_match('/[A-Z]/', $passwordInput) ||
            !preg_match('/[a-z]/', $passwordInput) ||
            !preg_match('/[0-9]/', $passwordInput)) {

            $passwordError = "Password must be at least 8 characters and include uppercase, lowercase, and a number.";

        } elseif ($passwordInput !== $confirmPassword) {
            $passwordError = "Passwords do not match!";
        }

        if ($userType === 'doctor') {

            if (!preg_match("/^[a-zA-Z0-9\-\/]{3,50}$/", $licenseNumber)) {
                $validationErrors[] = "Please enter a valid medical license number.";
            }

            if (!in_array($specialization, $allowedSpecializations, true) ||
                $specialization === '') {

                $validationErrors[] = "Please select a valid specialization.";
            }
        }

        if (!empty($validationErrors)) {
            $generalError = implode(' ', $validationErrors);
        }

        // ========================================================
        // IMPORTANT:
        // Do NOT INSERT INTO patients/doctors here.
        // We only check duplicates and save registration data
        // temporarily in the PHP session.
        // ========================================================
        if (empty($generalError) && empty($passwordError)) {

            $dbName = ($userType === 'doctor')
                ? "if0_42370337_human_care_doctors"
                : "if0_42370337_human_care_patients";

            $conn = new mysqli($servername, $username, $password, $dbName);

            if ($conn->connect_error) {

                $generalError = "Unable to connect. Please try again later.";

            } else {

                $conn->set_charset("utf8mb4");

                // ------------------------------------------------
                // Check duplicate email BEFORE temporary storage.
                // This does not create/update an account.
                // ------------------------------------------------
                if ($userType === 'patient') {

                    $check = $conn->prepare(
                        "SELECT id FROM patients WHERE email = ? LIMIT 1"
                    );
                    $check->bind_param("s", $email);
                    $check->execute();
                    $check->store_result();

                    if ($check->num_rows > 0) {
                        $emailError = "Email already registered!";
                    }

                    $check->close();

                } else {

                    $check = $conn->prepare(
                        "SELECT email, license_number
                         FROM doctors
                         WHERE email = ? OR license_number = ?
                         LIMIT 1"
                    );

                    $check->bind_param("ss", $email, $licenseNumber);
                    $check->execute();

                    $result = $check->get_result();
                    $existing = $result->fetch_assoc();

                    if ($existing) {
                        if ($existing['email'] === $email) {
                            $emailError = "This email is already registered!";
                        } else {
                            $emailError = "This medical license number is already registered!";
                        }
                    }

                    $check->close();
                }

                // ------------------------------------------------
                // Doctor photo is validated and stored as a file,
                // but NO doctor database row is created yet.
                // ------------------------------------------------
                $verificationPhoto = null;

                if (empty($emailError) && $userType === 'doctor') {

                    if (!empty($_FILES["verificationPhoto"]["name"])) {

                        $file = $_FILES["verificationPhoto"];

                        if ($file["error"] !== UPLOAD_ERR_OK) {

                            $fileError = "File upload failed. Please try again.";

                        } elseif ($file["size"] > 5 * 1024 * 1024) {

                            $fileError = "File is too large. Maximum size is 5MB.";

                        } else {

                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $realMime = finfo_file($finfo, $file["tmp_name"]);
                            finfo_close($finfo);

                            $allowedMimes = [
                                'image/jpeg',
                                'image/png',
                                'image/webp'
                            ];

                            $mimeToExt = [
                                'image/jpeg' => 'jpg',
                                'image/png'  => 'png',
                                'image/webp' => 'webp'
                            ];

                            if (!in_array($realMime, $allowedMimes, true)) {

                                $fileError = "Only JPG, PNG, or WEBP images are allowed.";

                            } else {

                                $uploadDir = "uploads/doctor_verification/";

                                if (!is_dir($uploadDir)) {
                                    if (!mkdir($uploadDir, 0755, true)) {
                                        $fileError = "Unable to prepare upload directory.";
                                    }
                                }

                                if (empty($fileError)) {

                                    $safeExt = $mimeToExt[$realMime];
                                    $fileName = bin2hex(random_bytes(16)) . '.' . $safeExt;
                                    $uploadPath = $uploadDir . $fileName;

                                    if (move_uploaded_file($file["tmp_name"], $uploadPath)) {
                                        $verificationPhoto = $uploadPath;
                                    } else {
                                        $fileError = "Failed to save uploaded file. Please try again.";
                                    }
                                }
                            }
                        }

                    } else {
                        $fileError = "Please upload a verification photo.";
                    }
                }

                // ------------------------------------------------
                // Only after validation succeeds:
                // SAVE EVERYTHING TEMPORARILY IN SESSION.
                // No DB INSERT happens here.
                // ------------------------------------------------
                if (empty($emailError) && empty($fileError)) {

                    // Generate strong 10-character OTPs.
                    // Each OTP contains at least:
                    // - uppercase letter
                    // - lowercase letter
                    // - number
                    // - special character
                    $emailOtp = generateStrongOtp(10);
                    $phoneOtp = generateStrongOtp(10);

                    // OTPs are stored hashed in the session.
                    // The raw OTPs are kept only long enough for verify.php
                    // to send them, then removed.
                    $otpExpiresAt = time() + 600;

                    $_SESSION['pending_verification'] = [
                        'user_type'          => $userType,
                        'email'              => $email,
                        'phone'              => $phone,
                        'first_name'         => $firstName,
                        'last_name'          => $lastName,
                        'dob'                => $dob,
                        'gender'             => $gender,
                        'blood_group'        => $bloodGroup,
                        'password_hash'      => password_hash($passwordInput, PASSWORD_DEFAULT),
                        'license_number'     => $userType === 'doctor' ? $licenseNumber : '',
                        'specialization'     => $userType === 'doctor' ? $specialization : '',
                        'verification_photo' => $verificationPhoto,
                        'email_otp_hash'     => password_hash($emailOtp, PASSWORD_DEFAULT),
                        'phone_otp_hash'     => password_hash($phoneOtp, PASSWORD_DEFAULT),
                        'email_otp'          => $emailOtp,
                        'phone_otp'          => $phoneOtp,
                        'otp_expires_at'     => $otpExpiresAt,
                        'created_at'         => time()
                    ];

                    // Prevent the registration form from being replayed.
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: verify.php");
                    exit;
                }

                $conn->close();
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
    <script src="scripts/register.js" defer></script>
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

            <?php if ($generalError): ?>
                <div class="general-error"><?php echo htmlspecialchars($generalError); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message" style="display:block;"><?php echo htmlspecialchars($success); ?></div>
            <?php else: ?>
                <div class="success-message" id="successMessage">Registration successful! Redirecting to login...</div>
            <?php endif; ?>

            <form id="registerForm" method="POST" action="" enctype="multipart/form-data" autocomplete="off">
                <!-- SECURITY: CSRF token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- User Type Selection -->
                <div class="user-type-selector">
                    <label class="user-type-option <?php echo $old['userType'] !== 'doctor' ? 'active' : ''; ?>" id="patientOption">
                        <input type="radio" name="userType" value="patient" <?php echo $old['userType'] !== 'doctor' ? 'checked' : ''; ?>>
                        <div class="user-type-icon">🧑‍⚕️</div>
                        <div class="user-type-title">Patient</div>
                        <div class="user-type-desc">Book appointments & consult doctors</div>
                    </label>

                    <label class="user-type-option <?php echo $old['userType'] === 'doctor' ? 'active' : ''; ?>" id="doctorOption">
                        <input type="radio" name="userType" value="doctor" <?php echo $old['userType'] === 'doctor' ? 'checked' : ''; ?>>
                        <div class="user-type-icon">👨‍⚕️</div>
                        <div class="user-type-title">Doctor</div>
                        <div class="user-type-desc">Provide medical services & consultations</div>
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" id="firstName" name="firstName" placeholder="John"
                                value="<?php echo htmlspecialchars($old['firstName']); ?>"
                                pattern="[a-zA-Z\s\-']{1,50}" maxlength="50" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="lastName">Last Name <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" id="lastName" name="lastName" placeholder="Doe"
                                value="<?php echo htmlspecialchars($old['lastName']); ?>"
                                pattern="[a-zA-Z\s\-']{1,50}" maxlength="50" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="john.doe@example.com"
                            value="<?php echo htmlspecialchars($old['email']); ?>"
                            maxlength="100" required>
                    </div>
                    <?php if ($emailError): ?>
                        <div class="error-message" style="display:block;"><?php echo htmlspecialchars($emailError); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label for="phone">
                            Phone Number <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                placeholder="9876543210"
                                value="<?php echo htmlspecialchars($old['phone']); ?>"
                                inputmode="numeric"
                                pattern="[6-9][0-9]{9}"
                                maxlength="10"
                                minlength="10"
                                autocomplete="tel"
                                required
                            >
                        </div>

                        <small class="field-hint">
                            Enter a valid 10-digit Indian mobile number.
                        </small>
                    </div>


                    <div class="form-group">
                        <label for="dob">
                            Date of Birth <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">
                            <input
                                type="date"
                                id="dob"
                                name="dob"
                                value="<?php echo htmlspecialchars($old['dob']); ?>"
                                max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                                required
                            >
                        </div>

                        <small class="field-hint">
                            You must be 18 years or older to register.
                        </small>
                    </div>

                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $old['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $old['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $old['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bloodGroup">Blood Group</label>
                        <div class="input-wrapper">
                            <select id="bloodGroup" name="bloodGroup">
                                <option value="">Select Blood Group</option>
                                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                    <option value="<?php echo $bg; ?>" <?php echo $old['bloodGroup'] === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                                <?php endforeach; ?>
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
                                placeholder="Enter your medical license number"
                                value="<?php echo htmlspecialchars($old['licenseNumber']); ?>"
                                pattern="[a-zA-Z0-9\-\/]{3,50}" maxlength="50">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="specialization">Specialization <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <select id="specialization" name="specialization">
                                <option value="">Select Specialization</option>
                                <?php
                                $specOptions = [
                                    'general' => 'General Physician', 'cardiology' => 'Cardiology',
                                    'dermatology' => 'Dermatology', 'neurology' => 'Neurology',
                                    'orthopedics' => 'Orthopedics', 'pediatrics' => 'Pediatrics',
                                    'psychiatry' => 'Psychiatry', 'surgery' => 'Surgery', 'other' => 'Other'
                                ];
                                foreach ($specOptions as $val => $label):
                                ?>
                                    <option value="<?php echo $val; ?>" <?php echo $old['specialization'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
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
                                accept="image/jpeg,image/png,image/webp">
                        </div>
                        <small style="color: #666; font-size: 12px;">Upload a clear photo of your medical license (JPG,
                            PNG, WEBP - Max 5MB)</small>
                        <?php if ($fileError): ?>
                            <div class="error-message" style="display:block; margin-top: 8px;"><?php echo htmlspecialchars($fileError); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Create a strong password"
                            minlength="8" autocomplete="new-password" required>
                    </div>
                    <small style="color: #666; font-size: 12px;">Min 8 characters, with uppercase, lowercase, and a number</small>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="confirmPassword" name="confirmPassword"
                            placeholder="Re-enter your password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <?php if ($passwordError): ?>
                        <div class="error-message" style="display:block;"><?php echo htmlspecialchars($passwordError); ?></div>
                    <?php endif; ?>
                </div>

                <div class="terms-check">
                    <input type="checkbox" id="terms" name="terms" value="1" required>
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
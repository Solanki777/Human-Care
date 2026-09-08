<?php
session_start();

require_once __DIR__ . '/duplication_checking/patient_duplicate_check.php';
require_once __DIR__ . '/duplication_checking/doctor_duplicate_check.php';

require_once __DIR__ . '/registration_validation/patient_registration_validation.php';
require_once __DIR__ . '/registration_validation/doctor_registration_validation.php';

// ============================================================
// SECURITY: CSRF Protection
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$emailError    = "";
$phoneError    = "";
$passwordError = "";
$fileError     = "";
$generalError  = "";

$old = [
    'firstName' => '', 'lastName' => '', 'email' => '', 'phone' => '',
    'dob' => '', 'gender' => '', 'bloodGroup' => '', 'userType' => 'patient',
    'licenseNumber' => '', 'specialization' => ''
];


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


// ============================================================
// REGISTRATION VALIDATION
// ============================================================

    if ($userType === 'patient') {

        $validationResult = validatePatientRegistration(
            $firstName,
            $lastName,
            $email,
            $phone,
            $dob,
            $gender,
            $bloodGroup,
            $passwordInput,
            $confirmPassword
        );

    } elseif ($userType === 'doctor') {

        $validationResult = validateDoctorRegistration(
            $firstName,
            $lastName,
            $email,
            $phone,
            $dob,
            $gender,
            $bloodGroup,
            $passwordInput,
            $confirmPassword,
            $licenseNumber,
            $specialization
        );

    } else {

        $validationResult = [
            'valid' => false,
            'errors' => ['Please select a valid account type.']
        ];
    }


    // ============================================================
    // HANDLE VALIDATION ERRORS
    // ============================================================

    if (!$validationResult['valid']) {
        $generalError = implode(' ', $validationResult['errors']);
    }


    // ============================================================
    // DUPLICATE CHECKING
    // ============================================================

    if (empty($generalError)) {

        if ($userType === 'patient') {

            $duplicateResult = checkPatientDuplicates(
                $email,
                $phone
            );

        } else {

            $duplicateResult = checkDoctorDuplicates(
                $email,
                $phone,
                $licenseNumber
            );
        }

        if (!empty($duplicateResult['error'])) {
            $generalError = $duplicateResult['error'];
        }

        if (!empty($duplicateResult['email_exists'])) {
            $emailError = "This email address is already registered.";
        }

        if (!empty($duplicateResult['phone_exists'])) {
            $phoneError = "This phone number is already registered.";
        }

        if (
            $userType === 'doctor' &&
            !empty($duplicateResult['license_exists'])
        ) {
            $generalError = "This medical license number is already registered.";
        }
    }


    // ============================================================
    // START OTP VERIFICATION
    // ============================================================

    if (
        empty($generalError) &&
        empty($emailError) &&
        empty($phoneError) &&
        empty($fileError) &&
        empty($passwordError)
    ) {

        $emailOtp = (string) random_int(100000, 999999);
        $phoneOtp = (string) random_int(100000, 999999);

        $otpExpiresAt = time() + 600; // 10 minutes


        $_SESSION['pending_verification'] = [

            'user_type' => $userType,

            'email' => $email,
            'phone' => $phone,

            'first_name' => $firstName,
            'last_name' => $lastName,

            'dob' => $dob,
            'gender' => $gender,
            'blood_group' => $bloodGroup,

            'password_hash' => password_hash(
                $passwordInput,
                PASSWORD_DEFAULT
            ),

            'license_number' =>
                $userType === 'doctor'
                    ? $licenseNumber
                    : '',

            'specialization' =>
                $userType === 'doctor'
                    ? $specialization
                    : '',

            'email_otp_hash' => password_hash(
                $emailOtp,
                PASSWORD_DEFAULT
            ),

            'phone_otp_hash' => password_hash(
                $phoneOtp,
                PASSWORD_DEFAULT
            ),

            // Temporary values for sending OTP
            'email_otp' => $emailOtp,
            'phone_otp' => $phoneOtp,

            'otp_expires_at' => $otpExpiresAt,

            'created_at' => time()
        ];


        // Generate a fresh CSRF token
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );


        header("Location: verify.php");
        exit;
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
                        <?php if ($phoneError): ?>
                            <div class="error-message" style="display:block;">
                                <?php echo htmlspecialchars($phoneError); ?>
                            </div>
                        <?php endif; ?>
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
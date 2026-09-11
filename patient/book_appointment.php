<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../classes/AppointmentService.php';



Auth::require('patient');

$success = "";
$error = "";
$doctors = [];

// ------------------------------------------------------------------ //
// Load patient info (display only — read once for the form)
// ------------------------------------------------------------------ //
$patient_id = Auth::id();
$patients_conn = Database::getConnection('patients');
$stmt = $patients_conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------------ //
// Load approved doctors for the dropdown
// ------------------------------------------------------------------ //
$doctors_conn = Database::getConnection('doctors');
$doctors_result = $doctors_conn->query("
    SELECT id, first_name, last_name, specialty, consultation_fee, available_days, available_time
    FROM doctors
    WHERE is_verified = 1 AND verification_status = 'approved' AND is_deleted = 0
    ORDER BY specialty, last_name
");

if ($doctors_result) {
    $doctors = $doctors_result->fetch_all(MYSQLI_ASSOC);
}

// ------------------------------------------------------------------ //
// Handle form submission
// ------------------------------------------------------------------ //
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate form fields
    $validator = new Validator();
    $valid = $validator->validate($_POST, [
        'doctor_id' => 'required|numeric',
        'appointment_date' => 'required|date|futureDate',
        'appointment_time' => 'required',
        'reason' => 'required|min:10|max:500',
        'consultation_type' => 'required',
    ]);

    if (!$valid) {
        $error = $validator->firstError();
    } else {

        // Sanitize inputs
        $doctor_id = intval($_POST['doctor_id']);
        $appointment_date = Validator::sanitize($_POST['appointment_date']);
        $appointment_time = Validator::sanitize($_POST['appointment_time']);
        $consultation_type = Validator::sanitize($_POST['consultation_type']);
        $reason = Validator::sanitize($_POST['reason']);
        $symptoms = Validator::sanitize($_POST['symptoms'] ?? '');

        // Delegate all booking logic to the service
        $result = AppointmentService::createAppointment(
            $patient_id,
            $doctor_id,
            $appointment_date,
            $appointment_time,
            $consultation_type,
            $reason,
            $symptoms
        );

        if ($result['success']) {
            $success = $result['message'];
            $_POST = []; // Clear form on success
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/book_appointment.css">
    <link rel="stylesheet" href="styles/sidebar.css">
   </head>

<body>
    <?php $active_page = 'book'; ?>

    <?php include 'includes/public_sidebar.php'; ?>

    <div class="booking-container">
        <div class="booking-header">
            <h1>📅 Book an Appointment</h1>
            <p>Schedule your consultation with our expert doctors</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <!-- Patient Information -->
            <div class="form-section">
                <div class="section-title">
                    👤 Patient Information
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text"
                            value="<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>"
                            disabled>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($patient['email']); ?>" disabled>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" value="<?php echo htmlspecialchars($patient['phone']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="text"
                            value="<?php echo (new DateTime($patient['dob']))->diff(new DateTime())->y; ?> years"
                            disabled>
                    </div>
                </div>
            </div>

            <!-- Doctor Selection -->
            <div class="form-section">
                <div class="section-title">
                    👨‍⚕️ Select Doctor
                </div>

                <div class="form-group">
                    <label>Choose Doctor <span class="required">*</span></label>
                    <select name="doctor_id" id="doctorSelect" required onchange="showDoctorInfo(this.value)">
                        <option value="">-- Select a Doctor --</option>
                        <?php
                        $specialties = [];
                        foreach ($doctors as $doctor) {
                            if (!in_array($doctor['specialty'], $specialties)) {
                                if (!empty($specialties))
                                    echo '</optgroup>';
                                echo '<optgroup label="' . htmlspecialchars($doctor['specialty']) . '">';
                                $specialties[] = $doctor['specialty'];
                            }
                            echo '<option value="' . $doctor['id'] . '"'
                                . ' data-specialty="' . htmlspecialchars($doctor['specialty']) . '"'
                                . ' data-fee="' . $doctor['consultation_fee'] . '"'
                                . ' data-days="' . htmlspecialchars($doctor['available_days']) . '"'
                                . ' data-time="' . htmlspecialchars($doctor['available_time']) . '">';
                            echo 'Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']);
                            if ($doctor['consultation_fee']) {
                                echo ' - ₹' . number_format($doctor['consultation_fee']);
                            }
                            echo '</option>';
                        }
                        if (!empty($specialties))
                            echo '</optgroup>';
                        ?>
                    </select>
                    <div class="form-hint">Select the doctor you want to consult</div>
                </div>

                <div id="doctorInfo" class="doctor-info"></div>
            </div>

            <!-- Appointment Details -->
            <div class="form-section">
                <div class="section-title">
                    📅 Appointment Date &amp; Time
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Preferred Date <span class="required">*</span></label>
                        <input type="date" name="appointment_date" required
                            min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                            max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                            value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? ''); ?>">
                        <div class="form-hint">Select a date within the next 30 days</div>
                    </div>

                    <div class="form-group">
                        <label>Preferred Time <span class="required">*</span></label>
                        <select name="appointment_time" required>
                            <option value="">-- Select Time --</option>
                            <option value="09:00:00">9:00 AM</option>
                            <option value="09:30:00">9:30 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="10:30:00">10:30 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="11:30:00">11:30 AM</option>
                            <option value="12:00:00">12:00 PM</option>
                            <option value="14:00:00">2:00 PM</option>
                            <option value="14:30:00">2:30 PM</option>
                            <option value="15:00:00">3:00 PM</option>
                            <option value="15:30:00">3:30 PM</option>
                            <option value="16:00:00">4:00 PM</option>
                            <option value="16:30:00">4:30 PM</option>
                            <option value="17:00:00">5:00 PM</option>
                        </select>
                        <div class="form-hint">Select your preferred time slot</div>
                    </div>
                </div>
            </div>

            <!-- Consultation Type -->
            <div class="form-section">
                <div class="section-title">
                    💼 Consultation Type
                </div>

                <div class="consultation-types">
                    <div class="consultation-type">
                        <input type="radio" id="in-person" name="consultation_type" value="in-person" checked>
                        <label for="in-person">
                            <div style="font-size: 24px; margin-bottom: 5px;">🏥</div>
                            <div>In-Person</div>
                            <div style="font-size: 11px; color: #999; margin-top: 5px;">Visit hospital</div>
                        </label>
                    </div>

                    <div class="consultation-type">
                        <input type="radio" id="online" name="consultation_type" value="online">
                        <label for="online">
                            <div style="font-size: 24px; margin-bottom: 5px;">💻</div>
                            <div>Online</div>
                            <div style="font-size: 11px; color: #999; margin-top: 5px;">Video call</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Reason for Visit -->
            <div class="form-section">
                <div class="section-title">
                    📝 Reason for Visit
                </div>

                <div class="form-group">
                    <label>Chief Complaint <span class="required">*</span></label>
                    <textarea name="reason" required placeholder="Please describe your main health concern..."
                        maxlength="500"><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                    <div class="form-hint">Minimum 10 characters, maximum 500 characters</div>
                </div>

                <div class="form-group">
                    <label>Additional Symptoms (Optional)</label>
                    <textarea name="symptoms" placeholder="Any other symptoms or information you'd like to share..."
                        maxlength="1000"><?php echo htmlspecialchars($_POST['symptoms'] ?? ''); ?></textarea>
                    <div class="form-hint">This helps the doctor prepare for your consultation</div>
                </div>
            </div>

            <!-- Important Notice -->
            <div
                style="background: #fef3c7; padding: 15px; border-radius: 10px; border-left: 4px solid #f59e0b; margin-bottom: 25px;">
                <strong>⏳ Please Note:</strong>
                <p style="margin: 10px 0 0 0; font-size: 14px; color: #92400e;">
                    Your appointment request will be reviewed by our admin team. You will receive a confirmation email
                    once approved. This typically takes less than 24 hours.
                </p>
            </div>

            <button type="submit" class="submit-btn">
                📅 Submit Appointment Request
            </button>
        </form>
    </div>
<script src="js/book_appointment.js"></script>

</body>

</html>
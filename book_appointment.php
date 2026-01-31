<?php
require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'classes/Database.php';
require_once 'classes/Validator.php';
require_once 'classes/EmailTemplate.php';

Auth::require('patient');

$success = "";
$error = "";
$doctors = [];

// Get patient info
$patient_id = Auth::id();
$patients_conn = Database::getConnection('patients');
$stmt = $patients_conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get all approved doctors
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validate input
    $validator = new Validator();
    $valid = $validator->validate($_POST, [
        'doctor_id' => 'required|numeric',
        'appointment_date' => 'required|date|futureDate',
        'appointment_time' => 'required',
        'reason' => 'required|min:10|max:500',
        'consultation_type' => 'required'
    ]);
    
    if (!$valid) {
        $error = $validator->firstError();
    } else {
        
        $doctor_id = intval($_POST['doctor_id']);
        $appointment_date = Validator::sanitize($_POST['appointment_date']);
        $appointment_time = Validator::sanitize($_POST['appointment_time']);
        $consultation_type = Validator::sanitize($_POST['consultation_type']);
        $reason = Validator::sanitize($_POST['reason']);
        $symptoms = Validator::sanitize($_POST['symptoms'] ?? '');
        
        // Get doctor details
        $stmt = $doctors_conn->prepare("SELECT first_name, last_name, specialty FROM doctors WHERE id = ? AND is_verified = 1");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $doctor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$doctor) {
            $error = "Selected doctor is not available";
        } else {
            
            $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];
            
            // Insert appointment into admin database
            $admin_conn = Database::getConnection('admin');
            $stmt = $admin_conn->prepare("
                INSERT INTO appointments (
                    patient_id, patient_name, patient_email, patient_phone, patient_age,
                    doctor_id, doctor_name, doctor_specialty,
                    appointment_date, appointment_time, consultation_type,
                    reason_for_visit, symptoms, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $patient_age = (new DateTime($patient['dob']))->diff(new DateTime())->y;
            $patient_name = $patient['first_name'] . ' ' . $patient['last_name'];
            
            $stmt->bind_param(
                "issssisssssss",
                $patient_id,
                $patient_name,
                $patient['email'],
                $patient['phone'],
                $patient_age,
                $doctor_id,
                $doctor_name,
                $doctor['specialty'],
                $appointment_date,
                $appointment_time,
                $consultation_type,
                $reason,
                $symptoms
            );
            
            if ($stmt->execute()) {
                $appointment_id = $stmt->insert_id;
                
                // Log appointment creation
                $history_stmt = $admin_conn->prepare("
                    INSERT INTO appointment_history (appointment_id, action, performed_by, performed_by_type, new_status, notes)
                    VALUES (?, 'created', ?, 'patient', 'pending', 'Appointment booked by patient')
                ");
                $history_stmt->bind_param("ii", $appointment_id, $patient_id);
                $history_stmt->execute();
                
                // Send confirmation email to patient
                $emailData = [
                    'patient_name' => $patient_name,
                    'doctor_name' => $doctor_name,
                    'doctor_specialty' => $doctor['specialty'],
                    'appointment_date' => date('F d, Y', strtotime($appointment_date)),
                    'appointment_time' => date('h:i A', strtotime($appointment_time)),
                    'reason' => $reason
                ];
                
                $emailContent = EmailTemplate::appointmentPending($emailData);
                EmailTemplate::send($patient['email'], 'Appointment Request Received', $emailContent);
                
                // Send notification email to admin
                $emailData['patient_phone'] = $patient['phone'];
                $emailData['patient_email'] = $patient['email'];
                $emailData['created_at'] = date('F d, Y h:i A');
                
                $adminEmailContent = EmailTemplate::newAppointmentAdmin($emailData);
                EmailTemplate::send(ADMIN_EMAIL, 'New Appointment Request', $adminEmailContent);
                
                // Create notification for admin
                $notif_stmt = $admin_conn->prepare("
                    INSERT INTO appointment_notifications (appointment_id, recipient_type, recipient_id, notification_type, message)
                    VALUES (?, 'admin', 1, 'new', ?)
                ");
                $notif_message = "New appointment request from $patient_name for Dr. $doctor_name";
                $notif_stmt->bind_param("is", $appointment_id, $notif_message);
                $notif_stmt->execute();
                
                $success = "Appointment request submitted successfully! You will receive a confirmation email once approved by admin.";
                
                // Clear form
                $_POST = [];
                
            } else {
                $error = "Failed to book appointment. Please try again.";
                error_log("Appointment insert failed: " . $stmt->error);
            }
            
            $stmt->close();
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
    <style>
        .booking-container {
            max-width: 800px;
            margin: 100px auto 50px;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .booking-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .booking-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .booking-header p {
            color: #666;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .required {
            color: #ef4444;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .doctor-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            display: none;
        }
        
        .doctor-info.active {
            display: block;
        }
        
        .consultation-types {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .consultation-type {
            position: relative;
        }
        
        .consultation-type input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .consultation-type label {
            display: block;
            padding: 15px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .consultation-type input[type="radio"]:checked + label {
            background: #e0e7ff;
            border-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar (reuse from other pages) -->
    <aside class="sidebar" id="sidebar">
        <!-- ... sidebar content ... -->
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

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
                        <input type="text" value="<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>" disabled>
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
                        <input type="text" value="<?php echo (new DateTime($patient['dob']))->diff(new DateTime())->y; ?> years" disabled>
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
                                if (!empty($specialties)) echo '</optgroup>';
                                echo '<optgroup label="' . htmlspecialchars($doctor['specialty']) . '">';
                                $specialties[] = $doctor['specialty'];
                            }
                            echo '<option value="' . $doctor['id'] . '" data-specialty="' . htmlspecialchars($doctor['specialty']) . '" data-fee="' . $doctor['consultation_fee'] . '" data-days="' . htmlspecialchars($doctor['available_days']) . '" data-time="' . htmlspecialchars($doctor['available_time']) . '">';
                            echo 'Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']);
                            if ($doctor['consultation_fee']) {
                                echo ' - ₹' . number_format($doctor['consultation_fee']);
                            }
                            echo '</option>';
                        }
                        if (!empty($specialties)) echo '</optgroup>';
                        ?>
                    </select>
                    <div class="form-hint">Select the doctor you want to consult</div>
                </div>
                
                <div id="doctorInfo" class="doctor-info"></div>
            </div>

            <!-- Appointment Details -->
            <div class="form-section">
                <div class="section-title">
                    📅 Appointment Date & Time
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Preferred Date <span class="required">*</span></label>
                        <input type="date" name="appointment_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" value="<?php echo $_POST['appointment_date'] ?? ''; ?>">
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
                    <textarea name="reason" required placeholder="Please describe your main health concern..." maxlength="500"><?php echo $_POST['reason'] ?? ''; ?></textarea>
                    <div class="form-hint">Minimum 10 characters, maximum 500 characters</div>
                </div>
                
                <div class="form-group">
                    <label>Additional Symptoms (Optional)</label>
                    <textarea name="symptoms" placeholder="Any other symptoms or information you'd like to share..." maxlength="1000"><?php echo $_POST['symptoms'] ?? ''; ?></textarea>
                    <div class="form-hint">This helps the doctor prepare for your consultation</div>
                </div>
            </div>

            <!-- Important Notice -->
            <div style="background: #fef3c7; padding: 15px; border-radius: 10px; border-left: 4px solid #f59e0b; margin-bottom: 25px;">
                <strong>⏳ Please Note:</strong>
                <p style="margin: 10px 0 0 0; font-size: 14px; color: #92400e;">
                    Your appointment request will be reviewed by our admin team. You will receive a confirmation email once approved. This typically takes less than 24 hours.
                </p>
            </div>

            <button type="submit" class="submit-btn">
                📅 Submit Appointment Request
            </button>
        </form>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
        
        function showDoctorInfo(doctorId) {
            const select = document.getElementById('doctorSelect');
            const infoDiv = document.getElementById('doctorInfo');
            
            if (!doctorId) {
                infoDiv.classList.remove('active');
                return;
            }
            
            const option = select.options[select.selectedIndex];
            const specialty = option.getAttribute('data-specialty');
            const fee = option.getAttribute('data-fee');
            const days = option.getAttribute('data-days');
            const time = option.getAttribute('data-time');
            
            infoDiv.innerHTML = `
                <strong>Doctor Information:</strong><br>
                <div style="margin-top: 10px;">
                    <div style="margin-bottom: 5px;">🏥 <strong>Specialty:</strong> ${specialty}</div>
                    <div style="margin-bottom: 5px;">💰 <strong>Consultation Fee:</strong> ₹${parseFloat(fee).toLocaleString()}</div>
                    <div style="margin-bottom: 5px;">📅 <strong>Available Days:</strong> ${days || 'Contact for schedule'}</div>
                    <div>⏰ <strong>Available Time:</strong> ${time || 'Contact for schedule'}</div>
                </div>
            `;
            infoDiv.classList.add('active');
        }
    </script>
</body>
</html>
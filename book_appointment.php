<?php
session_start();

// Check if patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;

if ($doctor_id === 0) {
    header("Location: doctors.php");
    exit();
}

// Connect to databases
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
$patients_conn = new mysqli("localhost", "root", "", "human_care_patients");

// Get doctor information (only verified and not deleted)
$stmt = $doctors_conn->prepare("SELECT * FROM doctors WHERE id = ? AND is_verified = 1 AND verification_status = 'approved' AND (is_deleted = 0 OR is_deleted IS NULL)");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: doctors.php");
    exit();
}

$doctor = $result->fetch_assoc();
$stmt->close();

// Get patient information
$patient_id = $_SESSION['user_id'];
$stmt = $patients_conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

$message = "";
$error = "";

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $reason = trim($_POST['reason']);
    
    // Combine date and time
    $appointment_datetime = $appointment_date . ' ' . $appointment_time;
    
    // Validation
    if (empty($appointment_date) || empty($appointment_time) || empty($reason)) {
        $error = "Please fill in all required fields.";
    } elseif (strtotime($appointment_datetime) < time()) {
        $error = "Cannot book appointment in the past.";
    } else {
        // Insert appointment
        $stmt = $doctors_conn->prepare("INSERT INTO doctor_appointments 
            (doctor_id, patient_id, patient_name, patient_email, patient_phone, appointment_date, reason, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        $patient_name = $patient['first_name'] . ' ' . $patient['last_name'];
        $patient_email = $patient['email'];
        $patient_phone = $patient['phone'];
        
        $stmt->bind_param("iisssss", 
            $doctor_id, 
            $patient_id, 
            $patient_name, 
            $patient_email, 
            $patient_phone, 
            $appointment_datetime, 
            $reason
        );
        
        if ($stmt->execute()) {
            $appointment_id = $stmt->insert_id;
            
            // Send email notification to doctor
            $to = $doctor['email'];
            $subject = "New Appointment Request - Human Care Hospital";
            $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];
            
            $email_message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .detail-box { background: white; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0; }
                    .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔔 New Appointment Request</h1>
                    </div>
                    <div class='content'>
                        <p>Dear Dr. $doctor_name,</p>
                        
                        <p>You have received a new appointment request:</p>
                        
                        <div class='detail-box'>
                            <strong>Patient:</strong> $patient_name<br>
                            <strong>Date & Time:</strong> " . date('F d, Y h:i A', strtotime($appointment_datetime)) . "<br>
                            <strong>Reason:</strong> $reason<br>
                            <strong>Contact:</strong> $patient_phone
                        </div>
                        
                        <p>Please login to your dashboard to approve or reject this appointment.</p>
                        
                        <p style='text-align: center;'>
                            <a href='http://localhost/humancare/login.php' class='button'>Login to Dashboard</a>
                        </p>
                        
                        <p>Best regards,<br>
                        <strong>Human Care Hospital Team</strong></p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Human Care Hospital <noreply@humancare.com>" . "\r\n";
            
            mail($to, $subject, $email_message, $headers);
            
            $message = "Appointment request sent successfully! The doctor will review and respond soon.";
            
            // Redirect to patient dashboard after 3 seconds
            header("refresh:3;url=dashboard.php");
        } else {
            $error = "Failed to book appointment. Please try again.";
        }
        $stmt->close();
    }
}

$doctors_conn->close();
$patients_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></title>
    <link rel="stylesheet" href="styles/main.css">
    <style>
        .booking-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }

        .booking-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .booking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .booking-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .doctor-info-box {
            background: white;
            color: #333;
            padding: 25px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .doctor-info-box h3 {
            color: #667eea;
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .booking-form {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 15px;
        }

        .required {
            color: #ef4444;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
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
            min-height: 120px;
            resize: vertical;
        }

        .form-hint {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .cancel-btn {
            width: 100%;
            padding: 14px;
            background: #f3f4f6;
            color: #333;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
            font-size: 16px;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
            font-size: 16px;
        }

        .info-box {
            background: #e0e7ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }

        .info-box h4 {
            color: #667eea;
            margin-bottom: 10px;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .time-slot {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .time-slot:hover {
            border-color: #667eea;
            background: #e0e7ff;
        }

        .time-slot.selected {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <!-- Menu Toggle Button -->
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">❤️</div>
            HUMAN CARE
        </div>

        <ul class="sidebar-nav">
            <li><a href="index.php">
                <span class="nav-icon">🏠</span>
                <span>Home</span>
            </a></li>
            <li><a href="doctors.php">
                <span class="nav-icon">👨‍⚕️</span>
                <span>Our Doctors</span>
            </a></li>
            <li><a href="dashboard.php" class="active">
                <span class="nav-icon">📊</span>
                <span>My Dashboard</span>
            </a></li>
        </ul>

        <div class="user-box-sidebar">
            <div class="user-name-sidebar">
                👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
            <a href="dashboard.php" class="login-btn-sidebar">My Dashboard</a>
            <a href="logout.php" class="logout-btn-sidebar">Logout</a>
        </div>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="booking-container">
        <div class="booking-card">
            <div class="booking-header">
                <h1>📅 Book Appointment</h1>
                <p>Schedule your consultation with our expert doctor</p>
                
                <div class="doctor-info-box">
                    <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                    <div class="info-row">
                        <span>Specialty:</span>
                        <strong><?php echo htmlspecialchars($doctor['specialty']); ?></strong>
                    </div>
                    <div class="info-row">
                        <span>Experience:</span>
                        <strong><?php echo $doctor['experience_years']; ?> Years</strong>
                    </div>
                    <?php if ($doctor['consultation_fee']): ?>
                    <div class="info-row">
                        <span>Consultation Fee:</span>
                        <strong>₹<?php echo number_format($doctor['consultation_fee']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($doctor['available_days']): ?>
                    <div class="info-row">
                        <span>Available Days:</span>
                        <strong><?php echo htmlspecialchars($doctor['available_days']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($doctor['available_time']): ?>
                    <div class="info-row">
                        <span>Available Time:</span>
                        <strong><?php echo htmlspecialchars($doctor['available_time']); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="booking-form">
                <?php if ($message): ?>
                    <div class="success-message">
                        ✅ <?php echo $message; ?>
                        <p style="margin-top: 10px; font-size: 14px;">Redirecting to dashboard...</p>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="error-message">❌ <?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (!$message): ?>
                <div class="info-box">
                    <h4>📋 Important Information</h4>
                    <ul style="margin: 10px 0 0 20px; font-size: 14px;">
                        <li>Your appointment is pending doctor approval</li>
                        <li>You will receive email notification once confirmed</li>
                        <li>Please arrive 10 minutes before scheduled time</li>
                        <li>Bring your medical records if available</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Appointment Date <span class="required">*</span></label>
                            <input type="date" 
                                   name="appointment_date" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                                   required>
                            <div class="form-hint">Select a date within the next 30 days</div>
                        </div>

                        <div class="form-group">
                            <label>Preferred Time <span class="required">*</span></label>
                            <select name="appointment_time" required>
                                <option value="">Select Time</option>
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
                                <option value="17:30:00">5:30 PM</option>
                            </select>
                            <div class="form-hint">Choose your preferred time slot</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reason for Visit <span class="required">*</span></label>
                        <textarea name="reason" 
                                  required 
                                  placeholder="Please describe your symptoms or reason for consultation..."
                                  maxlength="500"></textarea>
                        <div class="form-hint">Be specific to help the doctor prepare for your visit</div>
                    </div>

                    <button type="submit" class="submit-btn">
                        📅 Request Appointment
                    </button>
                    
                    <a href="doctor_profile.php?id=<?php echo $doctor_id; ?>" class="cancel-btn">
                        ← Back to Doctor Profile
                    </a>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="scripts/main.js"></script>
</body>
</html>
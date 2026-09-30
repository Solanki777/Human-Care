<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

$active_page = 'doctors';
require_once __DIR__ . '/../config/config.php';


// Get patient ID from URL
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id === 0) {
    header("Location: admin_patients.php");
    exit();
}

// Connect to patients database
$patients_conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_PATIENTS
);


if ($patients_conn->connect_error) {
    die("Connection failed: " . $patients_conn->connect_error);
}

// Get patient details
$stmt = $patients_conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin_patients.php");
    exit();
}

$patient = $result->fetch_assoc();
$stmt->close();

// Connect to admin database for appointments
$admin_conn  = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_ADMIN
);

// Get all appointments for this patient (ordered by appointment time in ascending order)
$appointments_stmt = $admin_conn->prepare("
    SELECT * FROM appointments 
    WHERE patient_id = ? 
    ORDER BY appointment_date ASC, appointment_time ASC
");
$appointments_stmt->bind_param("i", $patient_id);
$appointments_stmt->execute();
$appointments = $appointments_stmt->get_result();
$appointments_stmt->close();

// Get appointment counts
$total_appointments = 0;
$approved_appointments = 0;
$pending_appointments = 0;
$rejected_appointments = 0;

$count_stmt = $admin_conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM appointments 
    WHERE patient_id = ?
");
$count_stmt->bind_param("i", $patient_id);
$count_stmt->execute();
$counts = $count_stmt->get_result()->fetch_assoc();
$count_stmt->close();

$total_appointments = $counts['total'];
$approved_appointments = $counts['approved'];
$pending_appointments = $counts['pending'];
$rejected_appointments = $counts['rejected'];

// Get doctor pending count for sidebar
$doctors_conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_DOCTORS
);
$pending_doctors = $doctors_conn->query("SELECT COUNT(*) as count FROM doctors WHERE verification_status = 'pending'")->fetch_assoc()['count'];
$doctors_conn->close();

// Get patient pending count for sidebar
$pending_patients_count = $patients_conn->query("SELECT COUNT(*) as count FROM patients WHERE verification_status = 'pending'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Profile - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>

    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/sidebar.css">

    <link rel="stylesheet" href="styles/admin_patient_profile.css">
    
</head>
<body>
    <?php
    require_once __DIR__ . '/includes/admin_sidebar.php';
    ?>
    

    <!-- Main Content -->
    <main class="main-content">
        <a href="admin_patients.php" class="back-btn">
            ← Back to Patients List
        </a>

        <!-- Patient Profile -->
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo $patient['gender'] === 'male' ? '👨' : ($patient['gender'] === 'female' ? '👩' : '👤'); ?>
                </div>
                <div class="profile-name">
                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                </div>
                <div class="profile-email">
                    📧 <?php echo htmlspecialchars($patient['email']); ?>
                </div>
                <?php if ($patient['is_verified']): ?>
                    <span class="profile-status status-verified">✓ Verified Account</span>
                <?php elseif ($patient['verification_status'] === 'pending'): ?>
                    <span class="profile-status status-pending">⏳ Pending Verification</span>
                <?php else: ?>
                    <span class="profile-status status-suspended">🚫 Suspended Account</span>
                <?php endif; ?>
            </div>

            <div class="profile-body">
                <div class="section-title">
                    📋 Personal Information
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">📞 Phone Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['phone']); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">🎂 Date of Birth</div>
                        <div class="info-value">
                            <?php 
                            $dob = new DateTime($patient['dob']);
                            $now = new DateTime();
                            $age = $now->diff($dob)->y;
                            echo $dob->format('F d, Y') . " ($age years old)"; 
                            ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">👤 Gender</div>
                        <div class="info-value"><?php echo ucfirst($patient['gender']); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">🩸 Blood Group</div>
                        <div class="info-value"><?php echo $patient['blood_group'] ?? 'Not specified'; ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">📅 Registration Date</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($patient['registered_date'])); ?></div>
                    </div>

                    <?php if ($patient['emergency_contact']): ?>
                    <div class="info-item">
                        <div class="info-label">🚨 Emergency Contact</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['emergency_contact']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($patient['address']): ?>
                <div class="section-title">
                    📍 Address
                </div>
                <div class="info-item" style="margin-bottom: 20px;">
                    <div class="info-value"><?php echo htmlspecialchars($patient['address']); ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($patient['medical_history'])): ?>

                <div class="section-title">
                    🏥 Medical History
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointments Section -->
        <div class="appointments-section">
            <div class="section-title">
                📅 Appointment History
            </div>

            <!-- Appointment Statistics -->
            <div class="appointment-stats">
                <div class="stat-box total">
                    <div class="stat-number"><?php echo $total_appointments; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-box approved">
                    <div class="stat-number"><?php echo $approved_appointments; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-box pending">
                    <div class="stat-number"><?php echo $pending_appointments; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-box rejected">
                    <div class="stat-number"><?php echo $rejected_appointments; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Appointments List -->
            <?php if ($appointments->num_rows > 0): ?>
                <?php while ($appointment = $appointments->fetch_assoc()): ?>
                    <div class="appointment-card">
                        <div class="appointment-header">
                            <div>
                                <div class="doctor-name">
                                    👨‍⚕️ Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                </div>
                                <?php if ($appointment['doctor_specialty']): ?>
                                    <div style="font-size: 13px; color: #666; margin-top: 3px;">
                                        <?php echo htmlspecialchars($appointment['doctor_specialty']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="appointment-status <?php echo $appointment['status']; ?>">
                                <?php 
                                if ($appointment['status'] === 'approved') echo '✅ Approved';
                                elseif ($appointment['status'] === 'pending') echo '⏳ Pending';
                                else echo '❌ Rejected';
                                ?>
                            </span>
                        </div>

                        <div class="appointment-date-time">
                            <span class="date-badge">
                                📅 <?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?>
                            </span>
                            <span class="time-badge">
                                🕐 <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            </span>
                        </div>

                        <?php if (!empty($appointment['reason'])): ?>

                        <div class="appointment-reason">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (
    $appointment['status'] === 'rejected' 
    && !empty($appointment['rejection_reason'])
): ?>

                        <div class="rejection-reason">
                            <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($appointment['rejection_reason']); ?>
                        </div>
                        <?php endif; ?>

                        <div class="appointment-created">
                            Booked on: <?php echo date('F d, Y \a\t h:i A', strtotime($appointment['created_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-appointments">
                    <div class="no-appointments-icon">📭</div>
                    <h3>No Appointments Yet</h3>
                    <p>This patient hasn't booked any appointments.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
    </script>
</body>
</html>
<?php 
$patients_conn->close();
$admin_conn->close();
?>
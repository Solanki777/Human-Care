<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}
$active_page = 'dashboard'; // Change based on page


require_once __DIR__.'/../classes/msg.php';
require_once __DIR__.'/../config/config.php';



// Get doctor info from doctors database
$doctors_conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_DOCTORS
);

if ($doctors_conn->connect_error) {
    die("Connection failed: " . $doctors_conn->connect_error);
}

$doctor_id = $_SESSION['user_id'];
$stmt = $doctors_conn->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$doctor) {
    header("Location: login.php");
    exit();
}

$doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];

// Connect to admin database for appointments
$admin_conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_ADMIN
);
if ($admin_conn->connect_error) {
    die("Connection failed: " . $admin_conn->connect_error);
}

// Get chat instance for unread count
$chat = new Chat();
$unreadCount = $chat->getUnreadCount($doctor_id, 'doctor');

// Get appointment counts
$stmt = $admin_conn->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE doctor_name = ? 
    AND status = 'completed'
");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$total_appointments = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_name = ? AND status = 'approved'");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$approved_appointments = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_name = ? AND status = 'pending'");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$pending_appointments = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_name = ? AND status = 'cancelled'");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$cancelled_appointments = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $admin_conn->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_name = ?");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$total_patients = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();



$today = date('Y-m-d');
$stmt = $admin_conn->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE doctor_name = ? 
    AND appointment_date = ? 
    AND status = 'approved'
");


$stmt->bind_param("ss", $doctor_name, $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get upcoming approved appointments with chat room info
$stmt = $admin_conn->prepare("
    SELECT 
        a.*,
        cr.id as chat_room_id,
        cr.doctor_unread_count
    FROM appointments a
    LEFT JOIN chat_rooms cr ON cr.appointment_id = a.id
    WHERE a.doctor_name = ? 
    AND a.appointment_date >= ?
    AND a.status = 'approved'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->bind_param("ss", $doctor_name, $today);
$stmt->execute();
$upcoming_appointments = $stmt->get_result();
$stmt->close();

// Get cancelled appointments (recent 10)
$stmt = $admin_conn->prepare("
    SELECT * 
    FROM appointments 
    WHERE doctor_name = ? 
    AND status = 'cancelled'
    ORDER BY verified_at DESC
    LIMIT 10
");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$cancelled_appointments_list = $stmt->get_result();
$stmt->close();

$stmt = $admin_conn->prepare("
    SELECT *
    FROM appointments
    WHERE doctor_name = ?
    AND status = 'completed'
    ORDER BY completed_at DESC
    LIMIT 10
");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$completed_appointments_list = $stmt->get_result();
$stmt->close();



$filter = isset($_GET['view']) ? $_GET['view'] : 'upcoming';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Human Care</title>
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    
</head>

<body>
    

    <?php include 'includes/doctor_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h2>Welcome back, Dr. <?php echo htmlspecialchars($doctor['first_name']); ?> 👋</h2>
            <p>Here's your practice overview for <?php echo date('l, F d, Y'); ?></p>
            <div class="quick-stats">
                <div class="quick-stat">
                    <div class="quick-stat-number"><?php echo $today_appointments; ?></div>
                    <div class="quick-stat-label">Appointments Today</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-number"><?php echo $approved_appointments; ?></div>
                    <div class="quick-stat-label">Upcoming Approved</div>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <div class="quick-stat">
                        <div class="quick-stat-number"><?php echo $unreadCount; ?></div>
                        <div class="quick-stat-label">Unread Messages</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card patients">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $total_patients; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card total">
                <div class="stat-icon">📋</div>
                <div class="stat-number"><?php echo $total_appointments; ?></div>
                <div class="stat-label">Completed Appointments</div>
            </div>
            <div class="stat-card today">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $approved_appointments; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $pending_appointments; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card cancelled">
                <div class="stat-icon">🚫</div>
                <div class="stat-number"><?php echo $cancelled_appointments; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <!-- View Tabs -->
        <div class="view-tabs">
            <a href="?view=upcoming" class="view-tab <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                📅 Upcoming Appointments
            </a>
            <a href="?view=cancelled" class="view-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                🚫 Cancelled Appointments
            </a>
            <a href="?view=completed" class="view-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                ✔️ Completed Appointments
            </a>


        </div>

        <!-- Appointments Section -->
        <div class="appointments-section">
            <?php if ($filter === 'upcoming'): ?>
                <h3 style="font-size: 20px; margin-bottom: 20px; color: #333;">📅 Upcoming Appointments</h3>

                <?php if ($upcoming_appointments->num_rows === 0): ?>
                    <div class="no-appointments">
                        <div class="no-appointments-icon">✅</div>
                        <h3 style="font-size: 18px; color: #666; margin-bottom: 10px;">No Upcoming Appointments</h3>
                        <p>You have no scheduled appointments at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php while ($appt = $upcoming_appointments->fetch_assoc()): ?>
                        <div class="appointment-card">
                            <div class="appointment-header">
                                <div>
                                    <div class="patient-name">👤 <?= htmlspecialchars($appt['patient_name']) ?></div>
                                    <div class="appointment-date">
                                        📅 <?= date('l, F j, Y', strtotime($appt['appointment_date'])) ?>
                                        <span class="appointment-time">🕐
                                            <?= date('h:i A', strtotime($appt['appointment_time'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="appointment-details">
                                <p><strong>📧 Email:</strong> <?= htmlspecialchars($appt['patient_email']) ?></p>
                                <p><strong>📱 Phone:</strong> <?= htmlspecialchars($appt['patient_phone']) ?></p>
                                <p><strong>🩺 Reason:</strong> <?= htmlspecialchars($appt['reason_for_visit']) ?></p>
                                <p><strong>💼 Type:</strong> <?= ucfirst($appt['consultation_type']) ?></p>
                            </div>

                            <div class="appointment-actions">
                                <button onclick="openChatForAppointment(<?= (int)$appt['id'] ?>, <?= $appt['chat_room_id'] ? (int)$appt['chat_room_id'] : 'null' ?>)" class="chat-btn" style="border:none; cursor:pointer;">
                                    💬 Chat with Patient
                                    <?php if ($appt['doctor_unread_count'] > 0): ?>
                                        <span class="unread-badge"><?= $appt['doctor_unread_count'] ?></span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            <?php elseif ($filter === 'completed'): ?>

                <h3>✔️ Completed Appointments</h3>

                <?php if ($completed_appointments_list->num_rows === 0): ?>
                    <p>No completed appointments yet.</p>
                <?php else: ?>
                    <?php while ($appt = $completed_appointments_list->fetch_assoc()): ?>
                        <div class="appointment-card" style="border-left-color:#10b981;">
                            <strong>👤 <?= htmlspecialchars($appt['patient_name']) ?></strong><br>
                            📅 <?= date('M d, Y', strtotime($appt['appointment_date'])) ?>
                            🕐 <?= date('h:i A', strtotime($appt['appointment_time'])) ?><br>

                            ✔ Completed on:
                            <?= date('M d, Y h:i A', strtotime($appt['completed_at'])) ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>


            <?php else: ?>
                <h3 style="font-size: 20px; margin-bottom: 20px; color: #333;">🚫 Cancelled Appointments</h3>

                <?php if ($cancelled_appointments_list->num_rows === 0): ?>
                    <div class="no-appointments">
                        <div class="no-appointments-icon">✅</div>
                        <h3 style="font-size: 18px; color: #666; margin-bottom: 10px;">No Cancelled Appointments</h3>
                        <p>No appointments have been cancelled.</p>
                    </div>
                <?php else: ?>
                    <?php while ($appt = $cancelled_appointments_list->fetch_assoc()): ?>
                        <div class="appointment-card cancelled">
                            <div class="appointment-header">
                                <div>
                                    <div class="patient-name">👤 <?= htmlspecialchars($appt['patient_name']) ?></div>
                                    <div class="appointment-date">
                                        📅 <?= date('l, F j, Y', strtotime($appt['appointment_date'])) ?>
                                        <span class="appointment-time">🕐
                                            <?= date('h:i A', strtotime($appt['appointment_time'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="appointment-details">
                                <p><strong>📧 Email:</strong> <?= htmlspecialchars($appt['patient_email']) ?></p>
                                <p><strong>📱 Phone:</strong> <?= htmlspecialchars($appt['patient_phone']) ?></p>
                                <p><strong>🩺 Original Reason:</strong> <?= htmlspecialchars($appt['reason_for_visit']) ?></p>
                            </div>

                            <?php if ($appt['rejection_reason']): ?>
                                <div class="cancellation-box">
                                    <strong>🚫 Cancellation Reason (Admin):</strong>
                                    <p><?= htmlspecialchars($appt['rejection_reason']) ?></p>
                                    <?php if ($appt['verified_at']): ?>
                                        <p style="margin-top: 8px; font-size: 12px;">
                                            <strong>Cancelled on:</strong> <?= date('M j, Y \a\t h:i A', strtotime($appt['verified_at'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <script src="js/dashboard.js"></script>

   
</body>

</html>

<?php
$doctors_conn->close();
$admin_conn->close();
?>
<?php
session_start();

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

require_once 'classes/Chat.php';

// Get doctor info from doctors database
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
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
$admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
if ($admin_conn->connect_error) {
    die("Connection failed: " . $admin_conn->connect_error);
}

// Get chat instance for unread count
$chat = new Chat();
$unreadCount = $chat->getUnreadCount($doctor_id, 'doctor');

// Get appointment counts
$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_name = ?");
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
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        /* Additional styles for doctor dashboard */
        .doctor-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
            display: inline-block;
        }

        .specialty-tag {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 5px;
        }

        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .welcome-card h2 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .quick-stats {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .quick-stat {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .quick-stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .quick-stat-label {
            font-size: 13px;
            opacity: 0.9;
        }

        /* Stats row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
        }

        .stat-card.patients {
            border-left: 4px solid #10b981;
        }

        .stat-card.total {
            border-left: 4px solid #3b82f6;
        }

        .stat-card.today {
            border-left: 4px solid #10b981;
        }

        .stat-card.pending {
            border-left: 4px solid #f59e0b;
        }

        .stat-card.cancelled {
            border-left: 4px solid #ef4444;
        }

        /* View tabs */
        .view-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .view-tab {
            padding: 12px 24px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            transition: all 0.3s;
        }

        .view-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .view-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        /* Appointments section */
        .appointments-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .appointment-card {
            background: #f9fafb;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }

        .appointment-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .appointment-card.cancelled {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .patient-name {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .appointment-date {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .appointment-time {
            color: #667eea;
            font-weight: 600;
        }

        .appointment-details {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }

        .appointment-details p {
            margin: 8px 0;
            font-size: 14px;
            color: #555;
        }

        .appointment-details strong {
            color: #333;
        }

        /* Chat Button Styles */
        .appointment-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .chat-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
        }

        .chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .chat-btn-icon {
            font-size: 16px;
        }

        .unread-badge {
            background: #ff4757;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
        }

        .badge {
            background: #ff4757;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        /* Cancellation box */
        .cancellation-box {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .cancellation-box strong {
            display: block;
            margin-bottom: 8px;
            color: #991b1b;
            font-size: 14px;
        }

        .cancellation-box p {
            color: #991b1b;
            font-size: 14px;
            margin: 5px 0;
            line-height: 1.6;
        }

        /* No appointments */
        .no-appointments {
            text-align: center;
            padding: 60px 20px;
            background: #f9fafb;
            border-radius: 15px;
        }

        .no-appointments-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .welcome-card {
                padding: 25px;
            }

            .welcome-card h2 {
                font-size: 24px;
            }

            .quick-stats {
                flex-direction: column;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .appointment-date {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>

<body>
    <!-- Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">❤️</div>
            HUMAN CARE
        </div>

        <!-- Doctor Profile -->
        <div class="user-profile">
            <div class="user-avatar">👨‍⚕️</div>
            <div class="user-info">
                <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                <span class="doctor-badge">DOCTOR</span>
                <p class="specialty-tag"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link active" href="doctor_dashboard.php">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="doctor_chat.php">
                        <span class="nav-icon">💬</span>
                        <span>Patient Chats</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="doctor_profile.php">
                        <span class="nav-icon">👤</span>
                        <span>My Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="doctor_add_education.php">
                        <span class="nav-icon">📚</span>
                        <span>Edit Learning Page</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Logout Button -->
        <form method="post" action="logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

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
                <div class="stat-label">Total Appointments</div>
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

                            <?php if ($appt['chat_room_id']): ?>
                                <div class="appointment-actions">
                                    <a href="doctor_chat.php?room_id=<?= $appt['chat_room_id'] ?>" class="chat-btn">
                                        <span class="chat-btn-icon">💬</span>
                                        <span>Chat with Patient</span>
                                        <?php if ($appt['doctor_unread_count'] > 0): ?>
                                            <span class="unread-badge"><?= $appt['doctor_unread_count'] ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            <?php endif; ?>
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

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');

            if (overlay) {
                overlay.addEventListener('click', function () {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }
        });
    </script>
</body>

</html>

<?php
$doctors_conn->close();
$admin_conn->close();
?>
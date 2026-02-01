<?php
session_start();

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

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

// Get total appointments count for this doctor
$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_name = ?");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$total_appointments = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get approved appointments count
$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_name = ? AND status = 'approved'");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$approved_appointments = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get pending appointments count
$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_name = ? AND status = 'pending'");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$pending_appointments = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get total unique patients count
$stmt = $admin_conn->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_name = ?");
$stmt->bind_param("s", $doctor_name);
$stmt->execute();
$total_patients = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get today's appointments count
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

// Get all upcoming appointments (today and future) sorted by date and time ascending
$stmt = $admin_conn->prepare("
    SELECT * 
    FROM appointments 
    WHERE doctor_name = ? 
    AND appointment_date >= ?
    AND status = 'approved'
    ORDER BY appointment_date ASC, appointment_time ASC
");
$stmt->bind_param("ss", $doctor_name, $today);
$stmt->execute();
$upcoming_appointments = $stmt->get_result();
$stmt->close();

// Get recent past appointments (last 5)
$stmt = $admin_conn->prepare("
    SELECT * 
    FROM appointments 
    WHERE doctor_name = ? 
    AND appointment_date < ?
    AND status = 'approved'
    ORDER BY appointment_date DESC, appointment_time DESC
    LIMIT 5
");
$stmt->bind_param("ss", $doctor_name, $today);
$stmt->execute();
$past_appointments = $stmt->get_result();
$stmt->close();

$doctors_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Human Care</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .doctor-badge {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .specialty-tag {
            background: #e0e7ff;
            color: #667eea;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-top: 5px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card.patients .stat-number { color: #3b82f6; }
        .stat-card.total .stat-number { color: #8b5cf6; }
        .stat-card.today .stat-number { color: #10b981; }
        .stat-card.pending .stat-number { color: #f59e0b; }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .section-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .appointment-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        
        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-left-color: #10b981;
        }

        .appointment-card.past {
            border-left-color: #9ca3af;
            opacity: 0.8;
        }

        .appointment-card.today {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .patient-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .patient-id {
            font-size: 12px;
            color: #999;
        }

        .appointment-datetime {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .date-badge, .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
        }

        .date-badge {
            background: #dbeafe;
            color: #1e40af;
        }

        .time-badge {
            background: #fce7f3;
            color: #9f1239;
        }

        .today-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .appointment-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .detail-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .detail-item {
            font-size: 14px;
            color: #666;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            margin-right: 5px;
        }

        .appointment-reason {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            font-size: 14px;
            color: #666;
        }

        .appointment-status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .no-appointments {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-appointments-icon {
            font-size: 64px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .profile-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .detail-item {
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .detail-label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .detail-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 15px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .welcome-card h2 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-card p {
            font-size: 16px;
            opacity: 0.9;
        }

        .quick-stats {
            display: flex;
            gap: 20px;
            margin-top: 20px;
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
            font-size: 12px;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .profile-details {
                grid-template-columns: 1fr;
            }

            .appointment-datetime {
                flex-direction: column;
            }

            .quick-stats {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

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
                    <a class="nav-link" href="doctor_add_education.php">
                        <span class="nav-icon">👤</span>
                        <span>Edit learning page</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <span class="nav-icon">🌐</span>
                        <span>View Website</span>
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
                    <div class="quick-stat-number"><?php echo $upcoming_appointments->num_rows; ?></div>
                    <div class="quick-stat-label">Upcoming Total</div>
                </div>
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
                <div class="stat-label">Approved Appointments</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $pending_appointments; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>

        <!-- Profile Overview -->
        <div class="profile-section">
            <h3 class="section-title">📋 Your Profile Overview</h3>
            <div class="profile-details">
                <div class="detail-item">
                    <div class="detail-label">Specialty</div>
                    <div class="detail-value"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Qualification</div>
                    <div class="detail-value"><?php echo htmlspecialchars($doctor['qualification']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Experience</div>
                    <div class="detail-value"><?php echo $doctor['experience_years']; ?> Years</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">License Number</div>
                    <div class="detail-value"><?php echo htmlspecialchars($doctor['license_number']); ?></div>
                </div>
                <?php if ($doctor['hospital_affiliation']): ?>
                <div class="detail-item">
                    <div class="detail-label">Hospital</div>
                    <div class="detail-value"><?php echo htmlspecialchars($doctor['hospital_affiliation']); ?></div>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <div class="detail-label">Consultation Fee</div>
                    <div class="detail-value">₹<?php echo number_format($doctor['consultation_fee']); ?></div>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="section-container">
            <h3 class="section-title">📅 Upcoming Appointments</h3>
            <?php if ($upcoming_appointments->num_rows > 0): ?>
                <?php 
                $appointments_array = [];
                while ($row = $upcoming_appointments->fetch_assoc()) {
                    $appointments_array[] = $row;
                }
                
                foreach ($appointments_array as $appointment): 
                    $is_today = ($appointment['appointment_date'] === $today);
                ?>
                    <div class="appointment-card <?php echo $is_today ? 'today' : ''; ?>">
                        <div class="appointment-header">
                            <div>
                                <div class="patient-name">
                                    👤 <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                </div>
                                <div class="patient-id">Patient ID: #<?php echo $appointment['patient_id']; ?></div>
                            </div>
                            <div class="appointment-datetime">
                                <?php if ($is_today): ?>
                                    <span class="today-badge">📍 TODAY</span>
                                <?php endif; ?>
                                <span class="date-badge">
                                    📅 <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                </span>
                                <span class="time-badge">
                                    🕐 <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="appointment-details">
                            <div class="detail-row">
                                <div class="detail-item">
                                    <span class="detail-label">📧 Email:</span>
                                    <?php echo htmlspecialchars($appointment['patient_email']); ?>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">📞 Phone:</span>
                                    <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">🗓️ Booked On:</span>
                                    <?php echo date('M d, Y', strtotime($appointment['created_at'])); ?>
                                </div>
                            </div>

                            <?php if (!empty($appointment['reason'])): ?>

                            <div class="appointment-reason">
                                <strong>📝 Reason for Visit:</strong><br>
                                <?php echo htmlspecialchars($appointment['reason'] ?? ''); ?>

                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-appointments">
                    <div class="no-appointments-icon">📅</div>
                    <h3>No Upcoming Appointments</h3>
                    <p>You don't have any upcoming appointments scheduled at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Past Appointments -->
        <?php if ($past_appointments->num_rows > 0): ?>
        <div class="section-container">
            <h3 class="section-title">📜 Recent Past Appointments</h3>
            <?php while ($appointment = $past_appointments->fetch_assoc()): ?>
                <div class="appointment-card past">
                    <div class="appointment-header">
                        <div>
                            <div class="patient-name">
                                👤 <?php echo htmlspecialchars($appointment['patient_name']); ?>
                            </div>
                            <div class="patient-id">Patient ID: #<?php echo $appointment['patient_id']; ?></div>
                        </div>
                        <div class="appointment-datetime">
                            <span class="date-badge">
                                📅 <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                            </span>
                            <span class="time-badge">
                                🕐 <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="appointment-details">
                        <div class="detail-row">
                            <div class="detail-item">
                                <span class="detail-label">📧 Email:</span>
                                <?php echo htmlspecialchars($appointment['patient_email']); ?>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">📞 Phone:</span>
                                <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                            </div>
                        </div>

                        <?php if ($appointment['reason']): ?>
                        <div class="appointment-reason">
                            <strong>📝 Reason:</strong> <?php echo htmlspecialchars($appointment['reason'] ?? ''); ?>

                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
    </script>
</body>
</html>
<?php $admin_conn->close(); ?>
<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Get patient ID from URL
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id === 0) {
    header("Location: admin_patients.php");
    exit();
}

// Connect to patients database
$patients_conn = new mysqli("localhost", "root", "", "human_care_patients");

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
$admin_conn = new mysqli("localhost", "root", "", "human_care_admin");

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
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
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
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .profile-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            color: white;
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            margin: 0 auto 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .profile-name {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .profile-email {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 15px;
        }

        .profile-status {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .profile-body {
            padding: 30px;
        }

        .section-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            padding: 20px;
            background: #f9fafb;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #f3f4f6;
            color: #333;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: #e5e7eb;
            transform: translateX(-5px);
        }

        .appointments-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 30px;
        }

        .appointment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-box {
            text-align: center;
            padding: 20px;
            background: #f9fafb;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .stat-box.total .stat-number { color: #3b82f6; }
        .stat-box.approved .stat-number { color: #10b981; }
        .stat-box.pending .stat-number { color: #f59e0b; }
        .stat-box.rejected .stat-number { color: #ef4444; }

        .appointment-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }

        .appointment-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateX(5px);
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .doctor-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .appointment-date-time {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
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

        .appointment-status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .appointment-status.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .appointment-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .appointment-status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .appointment-reason {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            font-size: 14px;
            color: #666;
        }

        .rejection-reason {
            margin-top: 10px;
            padding: 10px;
            background: #fee2e2;
            border-radius: 6px;
            font-size: 13px;
            color: #991b1b;
        }

        .no-appointments {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .no-appointments-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .admin-badge {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .pending-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .nav-link.admin-nav {
            background: rgba(30, 60, 114, 0.1);
        }

        .nav-link.admin-nav:hover {
            background: rgba(30, 60, 114, 0.2);
        }

        .appointment-created {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .appointment-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .appointment-date-time {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">🛡️</div>
            ADMIN PANEL
        </div>

        <!-- Admin Profile -->
        <div class="user-profile">
            <div class="user-avatar">👨‍💼</div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h3>
                <span class="admin-badge">ADMINISTRATOR</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_dashboard.php">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_doctors.php">
                        <span class="nav-icon">👨‍⚕️</span>
                        <span>Manage Doctors</span>
                        <?php if ($pending_doctors > 0): ?>
                            <span class="pending-badge"><?php echo $pending_doctors; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav active" href="admin_patients.php">
                        <span class="nav-icon">👥</span>
                        <span>Manage Patients</span>
                        <?php if ($pending_patients_count > 0): ?>
                            <span class="pending-badge"><?php echo $pending_patients_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_appointments.php">
                        <span class="nav-icon">📅</span>
                        <span>Appointments</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Logout Button -->
        <form method="post" action="admin_logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

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
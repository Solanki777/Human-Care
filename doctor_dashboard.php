<?php
session_start();

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost:3306";
$username = "root";
$password = "";
$dbname = "human_care_doctors";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get doctor info
$doctor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get today's appointments count
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM doctor_appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status = 'scheduled'");
$stmt->bind_param("is", $doctor_id, $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get total patients count
$stmt = $conn->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM doctor_appointments WHERE doctor_id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$total_patients = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get upcoming appointments
$stmt = $conn->prepare("SELECT * FROM doctor_appointments WHERE doctor_id = ? AND appointment_date >= NOW() AND status = 'scheduled' ORDER BY appointment_date ASC LIMIT 5");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$upcoming_appointments = $stmt->get_result();

$conn->close();
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
        
        .appointment-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        
        .appointment-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .patient-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .appointment-time {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .appointment-details {
            color: #666;
            font-size: 14px;
            line-height: 1.8;
        }
        
        .appointment-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-complete {
            background: #10b981;
            color: white;
        }
        
        .btn-complete:hover {
            background: #059669;
        }
        
        .btn-cancel {
            background: #ef4444;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #dc2626;
        }
        
        .btn-view {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-view:hover {
            background: #e5e7eb;
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
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .profile-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        
        .profile-info h2 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .no-appointments {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-appointments-icon {
            font-size: 60px;
            margin-bottom: 15px;
            opacity: 0.5;
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
                    <a class="nav-link active" onclick="showSection('dashboard')">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('appointments')">
                        <span class="nav-icon">📅</span>
                        <span>Appointments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('patients')">
                        <span class="nav-icon">👥</span>
                        <span>My Patients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('schedule')">
                        <span class="nav-icon">🕐</span>
                        <span>My Schedule</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('profile')">
                        <span class="nav-icon">⚙️</span>
                        <span>Profile Settings</span>
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
        <!-- Dashboard Section -->
        <section id="dashboard" class="section active">
            <div class="hero-banner">
                <h2>Welcome back, Dr. <?php echo htmlspecialchars($doctor['first_name']); ?> 👋</h2>
                <p>Here's your practice overview for today</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-number"><?php echo $today_appointments; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?php echo $total_patients; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-number"><?php echo $doctor['experience_years']; ?></div>
                    <div class="stat-label">Years Experience</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number">₹<?php echo number_format($doctor['consultation_fee']); ?></div>
                    <div class="stat-label">Consultation Fee</div>
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
                        <div class="detail-label">License Number</div>
                        <div class="detail-value"><?php echo htmlspecialchars($doctor['license_number']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Hospital</div>
                        <div class="detail-value"><?php echo htmlspecialchars($doctor['hospital_affiliation'] ?? 'Not set'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="profile-section">
                <h3 class="section-title">📅 Upcoming Appointments</h3>
                <?php if ($upcoming_appointments->num_rows > 0): ?>
                    <?php while ($appointment = $upcoming_appointments->fetch_assoc()): ?>
                        <div class="appointment-item">
                            <div class="appointment-header">
                                <div class="patient-name">👤 <?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                <div class="appointment-time">
                                    <?php echo date('M d, Y - h:i A', strtotime($appointment['appointment_date'])); ?>
                                </div>
                            </div>
                            <div class="appointment-details">
                                <p><strong>📧 Email:</strong> <?php echo htmlspecialchars($appointment['patient_email']); ?></p>
                                <p><strong>📞 Phone:</strong> <?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                                <p><strong>📝 Reason:</strong> <?php echo htmlspecialchars($appointment['reason'] ?? 'General Consultation'); ?></p>
                            </div>
                            <div class="appointment-actions">
                                <button class="btn-small btn-complete" onclick="alert('Mark as completed')">✓ Complete</button>
                                <button class="btn-small btn-cancel" onclick="alert('Cancel appointment')">✗ Cancel</button>
                                <button class="btn-small btn-view" onclick="alert('View patient details')">👁️ View Details</button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-appointments">
                        <div class="no-appointments-icon">📅</div>
                        <p>No upcoming appointments scheduled</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Appointments Section -->
        <section id="appointments" class="section hidden">
            <h2>📅 All Appointments</h2>
            <p>Manage all your appointments here...</p>
        </section>

        <!-- Patients Section -->
        <section id="patients" class="section hidden">
            <h2>👥 My Patients</h2>
            <p>View and manage your patient records...</p>
        </section>

        <!-- Schedule Section -->
        <section id="schedule" class="section hidden">
            <h2>🕐 My Schedule</h2>
            <p>Set your availability and working hours...</p>
        </section>

        <!-- Profile Section -->
        <section id="profile" class="section hidden">
            <h2>⚙️ Profile Settings</h2>
            <p>Update your professional information...</p>
        </section>
    </main>

    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
                section.classList.add('hidden');
            });
            document.getElementById(sectionId).classList.remove('hidden');
            document.getElementById(sectionId).classList.add('active');

            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            event.target.closest('.nav-link').classList.add('active');
            
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
    </script>
</body>
</html>
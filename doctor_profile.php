<?php 
session_start();
// Decide doctor ID source
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'doctor') {
    // Doctor viewing his own profile
    $doctor_id = $_SESSION['user_id'];
} elseif (isset($_GET['id'])) {
    // Public / patient viewing doctor profile
    $doctor_id = intval($_GET['id']);
} else {
    header("Location: doctors.php");
    exit();
}

// Connect to doctors database
$conn = new mysqli("localhost", "root", "", "human_care_doctors");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get doctor information (only verified and not deleted doctors)
$stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ? AND is_verified = 1 AND verification_status = 'approved' AND (is_deleted = 0 OR is_deleted IS NULL)");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: doctors.php");
    exit();
}

$doctor = $result->fetch_assoc();
$stmt->close();

// Get appointment count (if you have appointments)
$appointment_count = $conn->query("SELECT COUNT(*) as count FROM doctor_appointments WHERE doctor_id = $doctor_id")->fetch_assoc()['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?> - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
    <style>
        .profile-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 20px 40px;
            margin-top: 0;
        }

        .profile-container {
            max-width: 1200px;
            margin: -60px auto 0;
            padding: 0 20px;
            position: relative;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .profile-header {
            display: flex;
            gap: 40px;
            padding: 40px;
            background: linear-gradient(to bottom, #f9fafb 0%, white 100%);
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .doctor-avatar-large {
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.3);
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
            min-width: 300px;
        }

        .doctor-name-large {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .specialty-large {
            font-size: 20px;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .verified-badge-large {
            background: #d1fae5;
            color: #065f46;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
        }

        .quick-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
        }

        .profile-section {
            padding: 40px;
            border-bottom: 1px solid #e0e0e0;
        }

        .profile-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-label {
            font-size: 13px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }

        .info-icon {
            font-size: 20px;
            margin-right: 5px;
        }

        .about-section {
            background: #f9fafb;
            padding: 25px;
            border-radius: 12px;
            line-height: 1.8;
            color: #333;
            font-size: 16px;
        }

        .consultation-fee-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-top: 20px;
        }

        .fee-label {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .fee-amount {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .fee-note {
            font-size: 14px;
            opacity: 0.8;
        }

        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .availability-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #e0e0e0;
        }

        .availability-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .availability-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .availability-value {
            font-size: 18px;
            color: #333;
            font-weight: 600;
        }

        .qualifications-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }

        .qualification-badge {
            background: #e0e7ff;
            color: #667eea;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 15px;
            font-weight: 600;
        }

        .action-section {
            background: #f9fafb;
            padding: 30px;
            text-align: center;
        }

        .book-appointment-btn {
            display: inline-block;
            padding: 18px 50px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
        }

        .book-appointment-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
        }

        .back-btn {
            display: inline-block;
            padding: 12px 30px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #667eea;
            color: white;
        }

        .contact-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-icon-box {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #e0e7ff 0%, #f3e7ff 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .contact-details {
            flex: 1;
        }

        .contact-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 3px;
        }

        .contact-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .no-info {
            color: #999;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .doctor-name-large {
                font-size: 28px;
            }

            .quick-stats {
                justify-content: center;
            }

            .profile-section {
                padding: 25px;
            }
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
            <li><a href="doctors.php" class="active">
                <span class="nav-icon">👨‍⚕️</span>
                <span>Our Doctors</span>
            </a></li>
            <li><a href="education.php">
                <span class="nav-icon">📚</span>
                <span>Health Education</span>
            </a></li>
            <li><a href="contact.php">
                <span class="nav-icon">💬</span>
                <span>Contact Us</span>
            </a></li>
        </ul>

        <div class="user-box-sidebar">
            <?php if (isset($_SESSION['user_name'])): ?>
                <div class="user-name-sidebar">
                    👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
                <a href="dashboard.php" class="login-btn-sidebar">My Dashboard</a>
                <a href="logout.php" class="logout-btn-sidebar">Logout</a>
            <?php else: ?>
                <a href="login.php" class="login-btn-sidebar">Login / Sign Up</a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Profile Hero -->
    <div class="profile-hero">
        <div class="container">
            <a href="doctors.php" class="back-btn">← Back to All Doctors</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="profile-container">
        <!-- Profile Card -->
        <div class="profile-card">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="doctor-avatar-large">👨‍⚕️</div>
                
                <div class="profile-info">
                    <h1 class="doctor-name-large">
                        Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                    </h1>
                    <div class="specialty-large">
                        <?php echo htmlspecialchars($doctor['specialty']); ?>
                    </div>
                    <div class="verified-badge-large">
                        ✓ Verified & Licensed Doctor
                    </div>
                    
                    <div class="quick-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $doctor['experience_years']; ?>+</div>
                            <div class="stat-label">Years Experience</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $appointment_count; ?>+</div>
                            <div class="stat-label">Appointments</div>
                        </div>
                        <?php if ($doctor['consultation_fee']): ?>
                        <div class="stat-item">
                            <div class="stat-number">₹<?php echo number_format($doctor['consultation_fee']); ?></div>
                            <div class="stat-label">Consultation Fee</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- About Section -->
            <?php if ($doctor['about']): ?>
            <div class="profile-section">
                <h2 class="section-title">
                    📋 About Dr. <?php echo htmlspecialchars($doctor['first_name']); ?>
                </h2>
                <div class="about-section">
                    <?php echo nl2br(htmlspecialchars($doctor['about'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Professional Information -->
            <div class="profile-section">
                <h2 class="section-title">
                    🎓 Professional Information
                </h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <span class="info-icon">🎓</span> Qualification
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['qualification']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <span class="info-icon">⭐</span> Experience
                        </div>
                        <div class="info-value"><?php echo $doctor['experience_years']; ?> Years</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <span class="info-icon">🆔</span> License Number
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['license_number']); ?></div>
                    </div>
                    
                    <?php if ($doctor['hospital_affiliation']): ?>
                    <div class="info-item">
                        <div class="info-label">
                            <span class="info-icon">🏥</span> Hospital Affiliation
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($doctor['hospital_affiliation']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($doctor['qualification']): ?>
                <div class="qualifications-list">
                    <?php 
                    $quals = explode(',', $doctor['qualification']);
                    foreach ($quals as $qual): 
                    ?>
                        <span class="qualification-badge"><?php echo trim(htmlspecialchars($qual)); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Availability & Schedule -->
            <?php if ($doctor['available_days'] || $doctor['available_time']): ?>
            <div class="profile-section">
                <h2 class="section-title">
                    📅 Availability & Schedule
                </h2>
                
                <div class="availability-grid">
                    <?php if ($doctor['available_days']): ?>
                    <div class="availability-card">
                        <div class="availability-icon">📅</div>
                        <div class="availability-label">Available Days</div>
                        <div class="availability-value"><?php echo htmlspecialchars($doctor['available_days']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($doctor['available_time']): ?>
                    <div class="availability-card">
                        <div class="availability-icon">🕐</div>
                        <div class="availability-label">Available Time</div>
                        <div class="availability-value"><?php echo htmlspecialchars($doctor['available_time']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Consultation Fee -->
            <?php if ($doctor['consultation_fee']): ?>
            <div class="profile-section">
                <h2 class="section-title">
                    💰 Consultation Fee
                </h2>
                
                <div class="consultation-fee-box">
                    <div class="fee-label">Consultation Charges</div>
                    <div class="fee-amount">₹<?php echo number_format($doctor['consultation_fee'], 2); ?></div>
                    <div class="fee-note">Per consultation session</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Contact Information -->
            <div class="profile-section">
                <h2 class="section-title">
                    📞 Contact Information
                </h2>
                
                <div class="contact-card">
                    <div class="contact-item">
                        <div class="contact-icon-box">📧</div>
                        <div class="contact-details">
                            <div class="contact-label">Email Address</div>
                            <div class="contact-value"><?php echo htmlspecialchars($doctor['email']); ?></div>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon-box">📱</div>
                        <div class="contact-details">
                            <div class="contact-label">Phone Number</div>
                            <div class="contact-value"><?php echo htmlspecialchars($doctor['phone']); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($doctor['hospital_affiliation']): ?>
                    <div class="contact-item">
                        <div class="contact-icon-box">🏥</div>
                        <div class="contact-details">
                            <div class="contact-label">Hospital/Clinic</div>
                            <div class="contact-value"><?php echo htmlspecialchars($doctor['hospital_affiliation']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Book Appointment Section -->
            <div class="action-section">
                <h3 style="font-size: 24px; margin-bottom: 15px; color: #333;">
                    Ready to Book an Appointment?
                </h3>
                <p style="color: #666; margin-bottom: 25px; font-size: 16px;">
                    Schedule your consultation with Dr. <?php echo htmlspecialchars($doctor['first_name']); ?> today
                </p>
                
                <?php if (isset($_SESSION['user_name']) && $_SESSION['user_type'] === 'patient'): ?>
                    <a href="book_appointment.php?doctor_id=<?php echo $doctor['id']; ?>" class="book-appointment-btn" style="text-decoration: none;">
                        📅 Book Appointment Now
                    </a>
                <?php else: ?>
                    <a href="login.php" class="book-appointment-btn" style="text-decoration: none;">
                        🔐 Login to Book Appointment
                    </a>
                <?php endif; ?>
                
                <div style="margin-top: 20px;">
                    <a href="doctors.php" class="btn-secondary" style="display: inline-block; padding: 12px 30px; text-decoration: none;">
                        ← View Other Doctors
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Human Care</h3>
                    <p>Your health, our priority</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="doctors.php">Doctors</a></li>
                        <li><a href="education.php">Education</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>📞 +91 1234-567890</p>
                    <p>📧 info@humancare.com</p>
                    <p>📍 Rajkot, Gujarat, India</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Human Care. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="scripts/main.js"></script>
    <script>
        function bookAppointment(doctorId, doctorName) {
            alert('Booking appointment with Dr. ' + doctorName + '\n\nAppointment booking feature coming soon!');
            // Future: window.location.href = 'book_appointment.php?doctor_id=' + doctorId;
        }
    </script>
</body>
</html>
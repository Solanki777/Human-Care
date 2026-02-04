<?php 
session_start();

// Connect to doctors database
$conn = new mysqli("localhost", "root", "", "human_care_doctors");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters
$specialty_filter = isset($_GET['specialty']) ? $_GET['specialty'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query for verified doctors only (excluding deleted)
$query = "SELECT * FROM doctors WHERE is_verified = 1 AND verification_status = 'approved' AND (is_deleted = 0 OR is_deleted IS NULL)";

// Add specialty filter
if ($specialty_filter !== 'all') {
    $query .= " AND specialty = '" . $conn->real_escape_string($specialty_filter) . "'";
}

// Add search filter
if (!empty($search_query)) {
    $query .= " AND (first_name LIKE '%" . $conn->real_escape_string($search_query) . "%' 
                OR last_name LIKE '%" . $conn->real_escape_string($search_query) . "%' 
                OR specialty LIKE '%" . $conn->real_escape_string($search_query) . "%'
                OR qualification LIKE '%" . $conn->real_escape_string($search_query) . "%')";
}

$query .= " ORDER BY specialty, first_name";

$doctors_result = $conn->query($query);

// Get all unique specialties for filter dropdown (excluding deleted)
$specialties_query = "SELECT DISTINCT specialty FROM doctors WHERE is_verified = 1 AND verification_status = 'approved' AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY specialty";
$specialties_result = $conn->query($specialties_query);

// Get total verified doctors count (excluding deleted)
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE is_verified = 1 AND verification_status = 'approved' AND (is_deleted = 0 OR is_deleted IS NULL)")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Doctors - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
    <style>
        .doctors-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .doctors-count {
            background: linear-gradient(135deg, #e0e7ff 0%, #f3e7ff 100%);
            color: #667eea;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
        }

        .no-doctors {
            text-align: center;
            padding: 80px 20px;
            color: #999;
        }

        .no-doctors-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-doctors h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 10px;
        }

        .availability-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-top: 5px;
        }

        .doctor-meta {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #666;
        }

        .consultation-fee {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            margin: 10px 0;
        }

        .verified-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .filter-results {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-results strong {
            color: #667eea;
        }

        .clear-filters {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-left: 15px;
            font-size: 14px;
        }

        .clear-filters:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <!-- Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle">☰</button>

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
                    <span>Learning Center</span>
                </a></li>
            <li>
                <a href="book_appointment.php">
                    <span class="nav-icon">📅</span>
                    <span>Book Appointment</span>
                </a>
            </li>
            <?php if (isset($_SESSION['user_name'])): ?>
                <li>
                    <a href="patient_appointments.php">
                        <span class="nav-icon">📋</span>
                        <span>My Appointments</span>
                    </a>
                </li>
            <?php endif; ?>
            <li>
                <a href="contact.php">
                    <span class="nav-icon">💬</span>
                    <span>Contact & Support</span>
                </a>
            </li>
        </ul>
        <div class="user-box-sidebar">
            <?php if (isset($_SESSION['user_name'])): ?>
                <div class="user-name-sidebar">
                    👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'patient'): ?>
                    <a href="patient_appointments.php" class="login-btn-sidebar">My Dashboard</a>
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'doctor'): ?>
                    <a href="doctor_dashboard.php" class="login-btn-sidebar">My Dashboard</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn-sidebar">Logout</a>
            <?php else: ?>
                <a href="login.php" class="login-btn-sidebar">Login / Sign Up</a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>👨‍⚕️ Meet Our Expert Doctors</h1>
            <p>Experienced healthcare professionals dedicated to your wellbeing</p>
            <span class="doctors-count">
                <?php echo $total_doctors; ?> Verified Doctors Available
            </span>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content-section">
        <div class="container">
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <select class="filter-select" name="specialty" onchange="this.form.submit()">
                        <option value="all" <?php echo $specialty_filter === 'all' ? 'selected' : ''; ?>>All Specialties</option>
                        <?php while ($spec = $specialties_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($spec['specialty']); ?>" 
                                    <?php echo $specialty_filter === $spec['specialty'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec['specialty']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    
                    <input type="text" 
                           name="search" 
                           placeholder="Search by name or specialty..." 
                           class="search-input" 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    
                    <button type="submit" class="btn-primary">Search</button>
                    
                    <?php if ($specialty_filter !== 'all' || !empty($search_query)): ?>
                        <a href="doctors.php" class="btn-secondary">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Filter Results Info -->
            <?php if ($specialty_filter !== 'all' || !empty($search_query)): ?>
                <div class="filter-results">
                    Showing <strong><?php echo $doctors_result->num_rows; ?> doctors</strong>
                    <?php if ($specialty_filter !== 'all'): ?>
                        in <strong><?php echo htmlspecialchars($specialty_filter); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($search_query)): ?>
                        matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                    <?php endif; ?>
                    <a href="doctors.php" class="clear-filters">✕ Clear all filters</a>
                </div>
            <?php endif; ?>

            <!-- Doctors Grid -->
            <?php if ($doctors_result->num_rows > 0): ?>
                <div class="doctors-grid">
                    <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                        <div class="doctor-card" data-specialty="<?php echo htmlspecialchars($doctor['specialty']); ?>">
                            <div class="doctor-avatar">👨‍⚕️</div>
                            
                            <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                            
                            <p class="specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                            
                            <span class="verified-badge">
                                ✓ Verified Doctor
                            </span>
                            
                            <div class="doctor-meta">
                                <span class="meta-item">
                                    ⭐ <?php echo $doctor['experience_years']; ?> years experience
                                </span>
                            </div>
                            
                            <div class="qualifications">
                                <span class="badge"><?php echo htmlspecialchars($doctor['qualification']); ?></span>
                            </div>
                            
                            <?php if ($doctor['about']): ?>
                                <p class="doctor-description">
                                    <?php 
                                    $about = $doctor['about'];
                                    echo htmlspecialchars(strlen($about) > 100 ? substr($about, 0, 100) . '...' : $about); 
                                    ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($doctor['consultation_fee']): ?>
                                <div class="consultation-fee">
                                    Consultation: ₹<?php echo number_format($doctor['consultation_fee']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($doctor['available_days'] || $doctor['available_time']): ?>
                                <div style="margin: 10px 0; font-size: 13px; color: #666;">
                                    <?php if ($doctor['available_days']): ?>
                                        <div>📅 <?php echo htmlspecialchars($doctor['available_days']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($doctor['available_time']): ?>
                                        <div>🕐 <?php echo htmlspecialchars($doctor['available_time']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($doctor['hospital_affiliation']): ?>
                                <div style="margin: 10px 0; font-size: 12px; color: #999;">
                                    🏥 <?php echo htmlspecialchars($doctor['hospital_affiliation']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="doctor-actions">
                                <?php if (isset($_SESSION['user_name']) && $_SESSION['user_type'] === 'patient'): ?>
                                    <a href="book_appointment.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn-primary" style="text-decoration: none; display: block; text-align: center;">
                                        Book Appointment
                                    </a>
                                <?php else: ?>
                                    <a href="login.php" class="btn-primary" style="text-decoration: none; display: block; text-align: center;">Login to Book</a>
                                <?php endif; ?>
                                
                                <a href="doctor_profile.php?id=<?php echo $doctor['id']; ?>" class="btn-secondary" style="text-decoration: none; display: block;">
                                    View Profile
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-doctors">
                    <div class="no-doctors-icon">🔍</div>
                    <h3>No Doctors Found</h3>
                    <p>
                        <?php if (!empty($search_query)): ?>
                            No doctors match your search "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                        <?php elseif ($specialty_filter !== 'all'): ?>
                            No verified doctors available in <strong><?php echo htmlspecialchars($specialty_filter); ?></strong> specialty
                        <?php else: ?>
                            No verified doctors available at the moment
                        <?php endif; ?>
                    </p>
                    <a href="doctors.php" class="btn-primary" style="margin-top: 20px;">View All Doctors</a>
                </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="info-box">
                <h3>Need Help Choosing a Doctor?</h3>
                <p>Our support team can help you find the right specialist for your needs</p>
                <a href="contact.php" class="btn-primary">Contact Support</a>
            </div>
        </div>
    </section>

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

    <!-- JavaScript for Sidebar Toggle - INLINE TO ENSURE IT WORKS -->
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function () {

            // Get elements
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            // Toggle sidebar function
            function toggleSidebar() {
                if (sidebar && overlay) {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                }
            }

            // Menu button click
            if (menuToggle) {
                menuToggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleSidebar();
                    console.log('Menu toggle clicked!'); // Debug log
                });
            }

            // Overlay click to close
            if (overlay) {
                overlay.addEventListener('click', function () {
                    toggleSidebar();
                });
            }

            // Prevent sidebar clicks from bubbling
            if (sidebar) {
                sidebar.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }

            // Close sidebar when clicking outside
            document.addEventListener('click', function (event) {
                if (sidebar && menuToggle &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    !menuToggle.contains(event.target)) {
                    toggleSidebar();
                }
            });

            console.log('Sidebar script loaded successfully!'); // Debug log
        });
    </script>

    <script src="scripts/main.js"></script>
    <script>
        function bookAppointment(doctorId, doctorName) {
            // You can implement appointment booking functionality here
            alert('Booking appointment with Dr. ' + doctorName + '\n\nAppointment booking feature coming soon!');
            // Future: Redirect to appointment booking page
            // window.location.href = 'book_appointment.php?doctor_id=' + doctorId;
        }

        function viewDoctorProfile(doctorId) {
            // You can implement doctor profile view functionality here
            alert('Viewing doctor profile\n\nDetailed profile view coming soon!');
            // Future: Redirect to doctor profile page
            // window.location.href = 'doctor_profile.php?id=' + doctorId;
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
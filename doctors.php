<?php 
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Doctors - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
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
            <!-- <li><a href="hospitals.php">
                <span class="nav-icon">🗺️</span>
                <span>Find Hospitals</span>
            </a></li> -->
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

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>👨‍⚕️ Meet Our Expert Doctors</h1>
            <p>Experienced healthcare professionals dedicated to your wellbeing</p>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content-section">
        <div class="container">
            <!-- Filter Section -->
            <div class="filter-section">
                <select class="filter-select">
                    <option value="all">All Specialties</option>
                    <option value="cardiology">Cardiology</option>
                    <option value="pediatrics">Pediatrics</option>
                    <option value="orthopedics">Orthopedics</option>
                    <option value="dermatology">Dermatology</option>
                    <option value="neurology">Neurology</option>
                </select>
                <input type="text" placeholder="Search by doctor name..." class="search-input">
            </div>

            <!-- Doctors Grid -->
            <div class="doctors-grid">
                <div class="doctor-card">
                    <div class="doctor-avatar">👨‍⚕️</div>
                    <h3>Dr. Rajesh Kumar</h3>
                    <p class="specialty">Cardiologist</p>
                    <p class="experience">⭐ 15 years experience</p>
                    <div class="qualifications">
                        <span class="badge">MBBS, MD</span>
                        <span class="badge">Cardiology Specialist</span>
                    </div>
                    <p class="doctor-description">
                        Expert in heart diseases, cardiac surgery, and preventive cardiology
                    </p>
                    <div class="doctor-actions">
                        <?php if (isset($_SESSION['user_name'])): ?>
                            <button class="btn-primary">Book Appointment</button>
                        <?php else: ?>
                            <a href="login.php" class="btn-primary">Login to Book</a>
                        <?php endif; ?>
                        <button class="btn-secondary">View Profile</button>
                    </div>
                </div>

                <div class="doctor-card">
                    <div class="doctor-avatar">👩‍⚕️</div>
                    <h3>Dr. Sarah Patel</h3>
                    <p class="specialty">Pediatrician</p>
                    <p class="experience">⭐ 12 years experience</p>
                    <div class="qualifications">
                        <span class="badge">MBBS, DCH</span>
                        <span class="badge">Child Healthcare</span>
                    </div>
                    <p class="doctor-description">
                        Specialized in child health, vaccinations, and developmental care
                    </p>
                    <div class="doctor-actions">
                        <?php if (isset($_SESSION['user_name'])): ?>
                            <button class="btn-primary">Book Appointment</button>
                        <?php else: ?>
                            <a href="login.php" class="btn-primary">Login to Book</a>
                        <?php endif; ?>
                        <button class="btn-secondary">View Profile</button>
                    </div>
                </div>

                <div class="doctor-card">
                    <div class="doctor-avatar">👨‍⚕️</div>
                    <h3>Dr. Amit Shah</h3>
                    <p class="specialty">Orthopedic Surgeon</p>
                    <p class="experience">⭐ 18 years experience</p>
                    <div class="qualifications">
                        <span class="badge">MBBS, MS</span>
                        <span class="badge">Orthopedics</span>
                    </div>
                    <p class="doctor-description">
                        Expert in bone, joint, and spine surgeries with advanced techniques
                    </p>
                    <div class="doctor-actions">
                        <?php if (isset($_SESSION['user_name'])): ?>
                            <button class="btn-primary">Book Appointment</button>
                        <?php else: ?>
                            <a href="login.php" class="btn-primary">Login to Book</a>
                        <?php endif; ?>
                        <button class="btn-secondary">View Profile</button>
                    </div>
                </div>

                <div class="doctor-card">
                    <div class="doctor-avatar">👩‍⚕️</div>
                    <h3>Dr. Priya Sharma</h3>
                    <p class="specialty">Dermatologist</p>
                    <p class="experience">⭐ 10 years experience</p>
                    <div class="qualifications">
                        <span class="badge">MBBS, MD</span>
                        <span class="badge">Skin Specialist</span>
                    </div>
                    <p class="doctor-description">
                        Specialized in skin disorders, cosmetic procedures, and hair care
                    </p>
                    <div class="doctor-actions">
                        <?php if (isset($_SESSION['user_name'])): ?>
                            <button class="btn-primary">Book Appointment</button>
                        <?php else: ?>
                            <a href="login.php" class="btn-primary">Login to Book</a>
                        <?php endif; ?>
                        <button class="btn-secondary">View Profile</button>
                    </div>
                </div>

                <div class="doctor-card">
                    <div class="doctor-avatar">👨‍⚕️</div>
                    <h3>Dr. Karthik Reddy</h3>
                    <p class="specialty">Neurologist</p>
                    <p class="experience">⭐ 14 years experience</p>
                    <div class="qualifications">
                        <span class="badge">MBBS, DM</span>
                        <span class="badge">Neurology</span>
                    </div>
                    <p class="doctor-description">
                        Expert in brain and nervous system disorders, stroke care
                    </p>
                    <div class="doctor-actions">
                        <?php if (isset($_SESSION['user_name'])): ?>
                            <button class="btn-primary">Book Appointment</button>
                        <?php else: ?>
                            <a href="login.php" class="btn-primary">Login to Book</a>
                        <?php endif; ?>
                        <button class="btn-secondary">View Profile</button>
                    </div>
                </div>

                <div class="doctor-card">
                    <div class="doctor-avatar">👩‍⚕️</div>
                    <h3>Dr. Anjali Desai</h3>
                    <p class="specialty">Gynecologist</p>
                    <p class="experience">⭐ 16 years experience</p>
                    <div class="qualifications">
                        <span class="badge">MBBS, MD</span>
                        <span class="badge">Women's Health</span>
                    </div>
                    <p class="doctor-description">
                        Specialized in women's health, pregnancy care, and reproductive health
                    </p>
                    <div class="doctor-actions">
                        <?php if (isset($_SESSION['user_name'])): ?>
                            <button class="btn-primary">Book Appointment</button>
                        <?php else: ?>
                            <a href="login.php" class="btn-primary">Login to Book</a>
                        <?php endif; ?>
                        <button class="btn-secondary">View Profile</button>
                    </div>
                </div>
            </div>

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
                        <li><a href="hospitals.php">Hospitals</a></li>
                        <li><a href="doctors.php">Doctors</a></li>
                        <li><a href="education.php">Education</a></li>
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
</body>
</html>
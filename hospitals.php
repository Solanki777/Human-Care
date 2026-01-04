<?php 
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Hospitals - Human Care</title>
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
            <li><a href="hospitals.php" class="active">
                <span class="nav-icon">🗺️</span>
                <span>Find Hospitals</span>
            </a></li>
            <li><a href="doctors.php">
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
            <h1>🗺️ Find Hospitals Near You</h1>
            <p>Locate the nearest healthcare facilities in your area</p>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content-section">
        <div class="container">
            <!-- Search Box -->
            <div class="search-box">
                <input type="text" placeholder="Search hospitals by name or location..." id="searchInput">
                <button class="search-btn">🔍 Search</button>
            </div>

            <!-- Map Container -->
            <div class="map-container">
                <div class="map-placeholder">
                    <div style="font-size: 60px; margin-bottom: 15px;">📍</div>
                    <h3>Interactive Map</h3>
                    <p>Connect Google Maps API for live location</p>
                </div>
            </div>

            <!-- Hospital List -->
            <div class="hospitals-section">
                <h2>Nearby Hospitals</h2>
                <div class="hospital-grid">
                    <div class="hospital-card">
                        <div class="hospital-header">
                            <h3>🏥 Human Care Central Hospital</h3>
                            <span class="distance-badge">2.5 km</span>
                        </div>
                        <div class="hospital-details">
                            <p><strong>📍 Address:</strong> 123 Main Street, Rajkot</p>
                            <p><strong>☎️ Phone:</strong> +91 1234-567890</p>
                            <p><strong>⏰ Hours:</strong> 24/7 Emergency Services</p>
                            <p><strong>🏨 Facilities:</strong> ICU, Emergency, Surgery, Labs</p>
                        </div>
                        <div class="hospital-actions">
                            <button class="btn-primary">Get Directions</button>
                            <button class="btn-secondary">Call Now</button>
                        </div>
                    </div>

                    <div class="hospital-card">
                        <div class="hospital-header">
                            <h3>🏥 Human Care Emergency Center</h3>
                            <span class="distance-badge">4.2 km</span>
                        </div>
                        <div class="hospital-details">
                            <p><strong>📍 Address:</strong> 456 Park Avenue, Rajkot</p>
                            <p><strong>☎️ Phone:</strong> +91 1234-567891</p>
                            <p><strong>⏰ Hours:</strong> 24/7 Emergency Services</p>
                            <p><strong>🏨 Facilities:</strong> Emergency, Trauma Care, Ambulance</p>
                        </div>
                        <div class="hospital-actions">
                            <button class="btn-primary">Get Directions</button>
                            <button class="btn-secondary">Call Now</button>
                        </div>
                    </div>

                    <div class="hospital-card">
                        <div class="hospital-header">
                            <h3>🏥 Human Care Specialty Clinic</h3>
                            <span class="distance-badge">5.8 km</span>
                        </div>
                        <div class="hospital-details">
                            <p><strong>📍 Address:</strong> 789 Health Boulevard, Rajkot</p>
                            <p><strong>☎️ Phone:</strong> +91 1234-567892</p>
                            <p><strong>⏰ Hours:</strong> Mon-Sat: 9 AM - 8 PM</p>
                            <p><strong>🏨 Facilities:</strong> Cardiology, Orthopedics, Pediatrics</p>
                        </div>
                        <div class="hospital-actions">
                            <button class="btn-primary">Get Directions</button>
                            <button class="btn-secondary">Call Now</button>
                        </div>
                    </div>

                    <div class="hospital-card">
                        <div class="hospital-header">
                            <h3>🏥 City Medical Center</h3>
                            <span class="distance-badge">7.1 km</span>
                        </div>
                        <div class="hospital-details">
                            <p><strong>📍 Address:</strong> 321 City Center, Rajkot</p>
                            <p><strong>☎️ Phone:</strong> +91 1234-567893</p>
                            <p><strong>⏰ Hours:</strong> 24/7 All Services</p>
                            <p><strong>🏨 Facilities:</strong> Multi-specialty, Diagnostics, Pharmacy</p>
                        </div>
                        <div class="hospital-actions">
                            <button class="btn-primary">Get Directions</button>
                            <button class="btn-secondary">Call Now</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="emergency-box">
                <h3>🚨 Emergency? Call Immediately!</h3>
                <p>For medical emergencies, call our 24/7 helpline</p>
                <a href="tel:+911234567890" class="emergency-btn">📞 Emergency Hotline: +91 1234-567890</a>
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
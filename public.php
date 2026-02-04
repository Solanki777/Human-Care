<?php 
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Human Care - Healthcare for Everyone</title>
    <link rel="stylesheet" href="styles/public.css">
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
            <li><a href="#home" onclick="closeSidebarMobile()">
                <span class="nav-icon">🏠</span>
                <span>Home</span>
            </a></li>
            <!-- <li><a href="#hospitals" onclick="closeSidebarMobile()">
                <span class="nav-icon">🗺️</span>
                <span>Find Hospitals</span>
            </a></li> -->
            <li><a href="#doctors" onclick="closeSidebarMobile()">
                <span class="nav-icon">👨‍⚕️</span>
                <span>Our Doctors</span>
            </a></li>
            <li><a href="#learning" onclick="closeSidebarMobile()">
                <span class="nav-icon">📚</span>
                <span>Learning Center</span>
            </a></li>
            <li><a href="#help" onclick="closeSidebarMobile()">
                <span class="nav-icon">💬</span>
                <span>Help & Support</span>
            </a></li>
        </ul>

        <div class="user-box-sidebar">
            <?php if (isset($_SESSION['user_name'])): ?>
                <div class="user-name-sidebar">
                    👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
                <a href="dashboard.php" class="login-btn-sidebar">Go to Dashboard</a>
                <a href="logout.php" class="logout-btn-sidebar">Logout</a>
            <?php else: ?>
                <a href="login.php" class="login-btn-sidebar">Login / Sign Up</a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <h1>Welcome to HUMAN CARE</h1>
        <p>Your health, our priority. Quality healthcare accessible to everyone.</p>
        <?php if (!isset($_SESSION['user_name'])): ?>
            <a href="login.php" class="hero-btn">Get Started</a>
        <?php else: ?>
            <a href="dashboard.php" class="hero-btn">Go to Dashboard</a>
        <?php endif; ?>
    </section>

    <!-- Doctors Section -->
    <section class="section" id="doctors">
        <div class="section-header">
            <h2>👨‍⚕️ Meet Our Expert Doctors</h2>
            <p>Experienced healthcare professionals dedicated to your wellbeing</p>
        </div>
        <div class="doctors-grid">
            <div class="doctor-card">
                <div class="doctor-avatar">👨‍⚕️</div>
                <h3>Dr. Rajesh Kumar</h3>
                <p class="specialty">Cardiologist</p>
                <p class="experience">⭐ 15 years experience</p>
            </div>
            <div class="doctor-card">
                <div class="doctor-avatar">👩‍⚕️</div>
                <h3>Dr. Sarah Patel</h3>
                <p class="specialty">Pediatrician</p>
                <p class="experience">⭐ 12 years experience</p>
            </div>
            <div class="doctor-card">
                <div class="doctor-avatar">👨‍⚕️</div>
                <h3>Dr. Amit Shah</h3>
                <p class="specialty">Orthopedic Surgeon</p>
                <p class="experience">⭐ 18 years experience</p>
            </div>
            <div class="doctor-card">
                <div class="doctor-avatar">👩‍⚕️</div>
                <h3>Dr. Priya Sharma</h3>
                <p class="specialty">Dermatologist</p>
                <p class="experience">⭐ 10 years experience</p>
            </div>
        </div>
    </section>

    <!-- Learning Center Section -->
    <section class="section" id="learning">
        <div class="section-header">
            <h2>📚 Health Education Center</h2>
            <p>Learn about health, wellness, and medical topics</p>
        </div>
        <div class="learning-grid">
            <div class="learning-card">
                <div class="learning-icon">🥇</div>
                <h3>First Aid Basics</h3>
                <p>Essential first aid techniques for emergencies and how to respond in critical situations</p>
                <span class="course-count">12 Free Lessons</span>
            </div>
            <div class="learning-card">
                <div class="learning-icon">🥗</div>
                <h3>Nutrition & Diet</h3>
                <p>Learn about healthy eating, balanced diets, and meal planning for better health</p>
                <span class="course-count">18 Free Lessons</span>
            </div>
            <div class="learning-card">
                <div class="learning-icon">🧠</div>
                <h3>Mental Wellness</h3>
                <p>Stress management, mental health awareness, and emotional wellbeing strategies</p>
                <span class="course-count">15 Free Lessons</span>
            </div>
            <div class="learning-card">
                <div class="learning-icon">💪</div>
                <h3>Fitness & Exercise</h3>
                <p>Workout routines, fitness tips, and how to maintain an active lifestyle</p>
                <span class="course-count">20 Free Lessons</span>
            </div>
            <div class="learning-card">
                <div class="learning-icon">🦠</div>
                <h3>Disease Prevention</h3>
                <p>Understanding common diseases, prevention methods, and health screening importance</p>
                <span class="course-count">25 Free Lessons</span>
            </div>
            <div class="learning-card">
                <div class="learning-icon">👶</div>
                <h3>Child Care</h3>
                <p>Essential parenting information, child health, and developmental milestones</p>
                <span class="course-count">16 Free Lessons</span>
            </div>
        </div>
    </section>

    <!-- Find Hospitals Section -->
    <section class="section" id="hospitals">
        <div class="section-header">
            <h2>🗺️ Find Hospitals Near You</h2>
            <p>Locate the nearest healthcare facilities in your area</p>
        </div>
        <div class="map-container">
            <div class="map-placeholder">
                <div style="font-size: 60px; margin-bottom: 15px;">📍</div>
                Interactive Map - Connect Google Maps API
                <p style="font-size: 14px; color: #999; margin-top: 10px;">Login to access full features</p>
            </div>
            <div class="hospital-grid">
                <div class="hospital-card">
                    <h3>🏥 Human Care Central Hospital</h3>
                    <p>📍 123 Main Street, Rajkot</p>
                    <p>☎️ +91 1234-567890</p>
                    <p>⏰ 24/7 Emergency Services</p>
                    <p class="distance">📍 2.5 km away</p>
                </div>
                <div class="hospital-card">
                    <h3>🏥 Human Care Emergency Center</h3>
                    <p>📍 456 Park Avenue, Rajkot</p>
                    <p>☎️ +91 1234-567891</p>
                    <p>⏰ 24/7 Emergency Services</p>
                    <p class="distance">📍 4.2 km away</p>
                </div>
                <div class="hospital-card">
                    <h3>🏥 Human Care Specialty Clinic</h3>
                    <p>📍 789 Health Boulevard, Rajkot</p>
                    <p>☎️ +91 1234-567892</p>
                    <p>⏰ Mon-Sat: 9 AM - 8 PM</p>
                    <p class="distance">📍 5.8 km away</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Help & Support Section -->
    <section class="section" id="help">
        <div class="section-header">
            <h2>💬 Help & Support</h2>
            <p>We're here to help you 24/7</p>
        </div>
        <div class="help-section">
            <div class="help-grid">
                <div class="help-card">
                    <div class="help-icon">📞</div>
                    <h3>Emergency Hotline</h3>
                    <p>24/7 emergency medical assistance</p>
                    <button class="help-btn" onclick="alert('Emergency: +91 1234-567890')">Call Now</button>
                </div>
                <div class="help-card">
                    <div class="help-icon">💬</div>
                    <h3>Live Chat</h3>
                    <p>Chat with our support team instantly</p>
                    <button class="help-btn" onclick="alert('Chat feature - Login required')">Start Chat</button>
                </div>
                <div class="help-card">
                    <div class="help-icon">📧</div>
                    <h3>Email Support</h3>
                    <p>Send us your queries via email</p>
                    <button class="help-btn" onclick="window.location.href='mailto:support@humancare.com'">Email Us</button>
                </div>
                <div class="help-card">
                    <div class="help-icon">❓</div>
                    <h3>FAQ</h3>
                    <p>Find answers to common questions</p>
                    <button class="help-btn" onclick="alert('FAQ section coming soon!')">View FAQs</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 Human Care Hospital. All rights reserved.</p>
        <p>Your Health, Our Priority</p>
        <div class="social-links">
            <a href="#" title="Facebook">📘</a>
            <a href="#" title="Twitter">🐦</a>
            <a href="#" title="Instagram">📷</a>
            <a href="#" title="LinkedIn">💼</a>
        </div>
    </footer>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function closeSidebarMobile() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
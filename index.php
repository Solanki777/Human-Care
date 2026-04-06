<?php
session_start();
require_once 'config/config.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Human Care - Welcome</title>
    <link rel="stylesheet" href="styles/main.css">
</head>

<body>
    <?php $active_page = 'home'; ?>
    <?php include 'includes/public_sidebar.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to HUMAN CARE</h1>
            <p>Your health, our priority. Quality healthcare accessible to everyone.</p>
            <?php if (!isset($_SESSION['user_name'])): ?>
                <a href="login.php" class="hero-btn">Get Started</a>
            <?php else: ?>
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'patient'): ?>
                    <a href="patient_appointments.php" class="hero-btn">Go to Dashboard</a>
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'doctor'): ?>
                    <a href="doctor_dashboard.php" class="hero-btn">Go to Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="hero-btn">Get Started</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Quick Services Section -->
    <section class="quick-services">
        <div class="container">
            <h2>Our Services</h2>
            <div class="services-grid">
                <a href="doctors.php" class="service-card">
                    <div class="service-icon">👨‍⚕️</div>
                    <h3>Our Doctors</h3>
                    <p>Meet our expert healthcare professionals</p>
                </a>
                <a href="education.php" class="service-card">
                    <div class="service-icon">📚</div>
                    <h3>Health Education</h3>
                    <p>Learn about health, wellness, and prevention</p>
                </a>
                <a href="contact.php" class="service-card">
                    <div class="service-icon">📞</div>
                    <h3>24/7 Support</h3>
                    <p>Get help anytime, anywhere</p>
                </a>
                <a href="book_appointment.php" class="service-card">
                    <div class="service-icon">📅</div>
                    <h3>Book Appointment</h3>
                    <p>Book Your Appoitnment</p>
                </a>
               
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>About Human Care</h2>
                    <p>Human Care is dedicated to providing accessible, quality healthcare services to everyone. Our
                        mission is to make healthcare information and services available at your fingertips.</p>
                    <ul class="features-list">
                        <li>✅ Easy appointment booking</li>
                        <li>✅ 24/7 online consultation</li>
                        <li>✅ Access medical records anytime</li>
                        <li>✅ Find doctors & specialists</li>
                        <li>✅ Prescription management</li>
                        <li>✅ Health tracking & reminders</li>
                    </ul>
                </div>
                <div class="about-image">
                    <div class="stats-card">
                        <h3>1000+</h3>
                        <p>Patients Served</p>
                    </div>
                    <div class="stats-card">
                        <h3>50+</h3>
                        <p>Expert Doctors</p>
                    </div>
                    <div class="stats-card">
                        <h3>15+</h3>
                        <p>Years Experience</p>
                    </div>
                </div>
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

    

    <!-- Load main.js for other functionality -->
    <script src="scripts/main.js"></script>
</body>

</html>
<?php
session_start();

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Get user info from session
$userName = $_SESSION['user_name'];
// $userEmail = $_SESSION['email'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Human Care - Dashboard</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">❤️</div>
            HUMAN CARE
        </div>

        <!-- User profile -->
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($userName); ?></h3>
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
                <!-- <li class="nav-item">
                    <a class="nav-link" onclick="showSection('hospitals')">
                        <span class="nav-icon">🗺️</span>
                        <span>Find Hospitals</span>
                    </a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('doctors')">
                        <span class="nav-icon">👨‍⚕️</span>
                        <span>Our Doctors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('learning')">
                        <span class="nav-icon">📚</span>
                        <span>Learning Center</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('feedback')">
                        <span class="nav-icon">💬</span>
                        <span>Help & Feedback</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Logout Button -->
        <form method="post" action="logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <!-- Overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Dashboard Section -->
        <section id="dashboard" class="section active">
            <div class="hero-banner">
                <h2>Welcome back, <?php echo htmlspecialchars($userName); ?> 👋</h2>
                <p>Your health is our top priority. Access all your healthcare services in one place.</p>
            </div>

            <div class="cards-grid">
                <div class="card">
                    <div class="card-icon">📅</div>
                    <h3>Upcoming Appointments</h3>
                    <p>You have 2 appointments scheduled this week. Next appointment: Dr. Sarah on Oct 6</p>
                </div>
                <div class="card">
                    <div class="card-icon">📋</div>
                    <h3>Medical Records</h3>
                    <p>Access your complete medical history, test results, and prescriptions anytime</p>
                </div>
                <div class="card">
                    <div class="card-icon">💊</div>
                    <h3>Prescriptions</h3>
                    <p>3 active prescriptions. 1 refill reminder for next week</p>
                </div>
                <div class="card">
                    <div class="card-icon">📞</div>
                    <h3>24/7 Support</h3>
                    <p>Need help? Our medical team is available around the clock for consultations</p>
                </div>
            </div>
        </section>

        <!-- Other Sections (hospitals, doctors, etc.) -->
        <section id="hospitals" class="section hidden">
            <h2>Nearby Hospitals</h2>
            <p>Find hospitals near your current location...</p>
        </section>

        <section id="doctors" class="section hidden">
            <h2>Our Expert Doctors</h2>
            <p>Meet our certified professionals...</p>
        </section>

        <section id="learning" class="section hidden">
            <h2>Learning Center</h2>
            <p>Learn about diseases, treatments, and healthy living.</p>
        </section>

        <section id="feedback" class="section hidden">
            <h2>Help & Feedback</h2>
            <p>We're here to help! Contact our support team anytime.</p>
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
            
            // Close sidebar on mobile after selecting a section
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    toggleSidebar();
                }
            }
        });
    </script>
</body>
</html>
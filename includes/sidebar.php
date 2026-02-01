<?php
// Session is already handled in config.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Menu Toggle Button -->
<button class="menu-toggle" onclick="toggleSidebar()">☰</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">❤️</div>
        HUMAN CARE
    </div>

    <ul class="sidebar-nav">
        <li>
            <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                <span class="nav-icon">🏠</span>
                <span>Home</span>
            </a>
        </li>

        <li>
            <a href="doctors.php" class="<?= $currentPage === 'doctors.php' ? 'active' : '' ?>">
                <span class="nav-icon">👨‍⚕️</span>
                <span>Our Doctors</span>
            </a>
        </li>

        <li>
            <a href="education.php" class="<?= $currentPage === 'education.php' ? 'active' : '' ?>">
                <span class="nav-icon">📚</span>
                <span>Health Education</span>
            </a>
        </li>

        <li>
            <a href="book_appointment.php" class="<?= $currentPage === 'book_appointment.php' ? 'active' : '' ?>">
                <span class="nav-icon">📅</span>
                <span>Book Appointment</span>
            </a>
        </li>

        <?php if (isset($_SESSION['user_name'])): ?>
            <li>
                <a href="patient_appointments.php"
                   class="<?= $currentPage === 'patient_appointments.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span>
                    <span>My Appointments</span>
                </a>
            </li>
        <?php endif; ?>

        <li>
            <a href="contact.php" class="<?= $currentPage === 'contact.php' ? 'active' : '' ?>">
                <span class="nav-icon">💬</span>
                <span>Contact & Support</span>
            </a>
        </li>
    </ul>

    <div class="user-box-sidebar">
        <?php if (isset($_SESSION['user_name'])): ?>
            <div class="user-name-sidebar">
                👤 <?= htmlspecialchars($_SESSION['user_name']) ?>
            </div>
            <a href="patient_appointments.php" class="login-btn-sidebar">Dashboard</a>
            <a href="logout.php" class="logout-btn-sidebar">Logout</a>
        <?php else: ?>
            <a href="login.php" class="login-btn-sidebar">Login / Sign Up</a>
        <?php endif; ?>
    </div>
</aside>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

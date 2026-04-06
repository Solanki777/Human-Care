<?php
/**
 * Public Sidebar Include
 * ----------------------
 * Usage: require_once 'includes/sidebar.php';
 *
 * Optional — set these BEFORE including this file:
 *   $active_page  = 'home' | 'doctors' | 'education' | 'book' | 'appointments' | 'chats' | 'contact'
 *   $unreadCount  = (int) unread chat message count  (defaults to 0 if not set)
 *
 * Requires: $_SESSION must already be started (session_start() called before include).
 */

// Defaults
$active_page = $active_page ?? '';
$unreadCount = $unreadCount ?? 0;

// Helper: returns 'active' class string if the page matches
function sb_active(string $page, string $current): string {
    return $page === $current ? 'active' : '';
}
?>

<!-- ═══════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════════ -->

<!-- Menu Toggle Button -->
<button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">☰</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">❤️</div>
        HUMAN CARE
    </div>

    <ul class="sidebar-nav">
        <li>
            <a href="index.php" class="<?= sb_active('home', $active_page) ?>">
                <span class="nav-icon">🏠</span>
                <span>Home</span>
            </a>
        </li>
        <li>
            <a href="doctors.php" class="<?= sb_active('doctors', $active_page) ?>">
                <span class="nav-icon">👨‍⚕️</span>
                <span>Our Doctors</span>
            </a>
        </li>
        <li>
            <a href="education.php" class="<?= sb_active('education', $active_page) ?>">
                <span class="nav-icon">📚</span>
                <span>Learning Center</span>
            </a>
        </li>
        <li>
            <a href="book_appointment.php" class="<?= sb_active('book', $active_page) ?>">
                <span class="nav-icon">📅</span>
                <span>Book Appointment</span>
            </a>
        </li>

        <?php if (isset($_SESSION['user_name'])): ?>
            <li>
                <a href="patient_appointments.php" class="<?= sb_active('appointments', $active_page) ?>">
                    <span class="nav-icon">📋</span>
                    <span>My Appointments</span>
                </a>
            </li>
            <li>
                <a href="patient_chat.php" class="<?= sb_active('chats', $active_page) ?>">
                    <span class="nav-icon">💬</span>
                    <span>My Chats</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="unread-badge"><?= (int) $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endif; ?>

        <li>
            <a href="contact.php" class="<?= sb_active('contact', $active_page) ?>">
                <span class="nav-icon">📞</span>
                <span>Contact & Support</span>
            </a>
        </li>
    </ul>

    <!-- User Box -->
    <div class="user-box-sidebar">
        <?php if (isset($_SESSION['user_name'])): ?>
            <div class="user-name-sidebar">
                👤 <?= htmlspecialchars($_SESSION['user_name']) ?>
            </div>
            <?php if (isset($_SESSION['user_type'])): ?>
                <?php if ($_SESSION['user_type'] === 'patient'): ?>
                    <a href="patient_appointments.php" class="login-btn-sidebar">My Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'doctor'): ?>
                    <a href="doctor_dashboard.php" class="login-btn-sidebar">My Dashboard</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn-sidebar">Logout</a>
        <?php else: ?>
            <a href="login.php" class="login-btn-sidebar">Login / Sign Up</a>
        <?php endif; ?>
    </div>
</aside>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ═══════════════════════════════════════════
     SIDEBAR TOGGLE SCRIPT
     (safe to include multiple times — checks
      if already initialized via data attribute)
════════════════════════════════════════════ -->
<script>
(function () {
    // Guard: only initialise once per page
    if (document.body.dataset.sidebarInit) return;
    document.body.dataset.sidebarInit = '1';

    document.addEventListener('DOMContentLoaded', function () {
        var toggle  = document.getElementById('menuToggle');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        if (!toggle || !sidebar || !overlay) return;

        function openSidebar() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.classList.add('sidebar-open');
        }

        function closeSidebar() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        }

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            sidebar.classList.contains('active') ? closeSidebar() : openSidebar();
        });

        // Close on overlay click
        overlay.addEventListener('click', closeSidebar);

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSidebar();
        });

        // Close when clicking anywhere outside sidebar
        document.addEventListener('click', function (e) {
            if (sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                !toggle.contains(e.target)) {
                closeSidebar();
            }
        });

        // Prevent clicks inside sidebar from bubbling to document
        sidebar.addEventListener('click', function (e) { e.stopPropagation(); });
    });
}());
</script>
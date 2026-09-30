
<?php
/**
 * Human Care - Admin Sidebar
 *
 * Required:
 *   config/config.php must be loaded first.
 *
 * Optional:
 *   $active_page = 'dashboard' | 'doctors' | 'patients'
 *                 | 'appointments' | 'education' | 'ai' | 'nexora'
 */

// =====================================================
// ADMIN LOGIN CHECK
// =====================================================

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header("Location: admin_login.php");
    exit();
}

// =====================================================
// ADMIN INFORMATION
// =====================================================

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$active_page = $active_page ?? '';

// =====================================================
// PENDING PATIENT COUNT
// =====================================================

$pending_patients_count = 0;

$patients_conn_sidebar = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_PATIENTS
);

if (!$patients_conn_sidebar->connect_error) {
    $patients_conn_sidebar->set_charset("utf8mb4");

    $result = $patients_conn_sidebar->query(
        "SELECT COUNT(*) AS count
         FROM patients
         WHERE verification_status = 'pending'"
    );

    if ($result) {
        $pending_patients_count = (int) $result->fetch_assoc()['count'];
    }

    $patients_conn_sidebar->close();
}

// =====================================================
// PENDING DOCTOR COUNT
// =====================================================

$pending_doctors_count = 0;

$doctors_conn_sidebar = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_DOCTORS
);

if (!$doctors_conn_sidebar->connect_error) {
    $doctors_conn_sidebar->set_charset("utf8mb4");

    $result = $doctors_conn_sidebar->query(
        "SELECT COUNT(*) AS count
         FROM doctors
         WHERE verification_status = 'pending'"
    );

    if ($result) {
        $pending_doctors_count = (int) $result->fetch_assoc()['count'];
    }

    $doctors_conn_sidebar->close();
}

// =====================================================
// ACTIVE PAGE HELPER
// =====================================================

function admin_sb_active(string $page, string $current): string
{
    return $page === $current ? 'active' : '';
}

// =====================================================
// NAVIGATION ITEMS
// =====================================================

$nav_items = [
    [
        'id' => 'dashboard',
        'label' => 'Dashboard',
        'icon' => '📊',
        'url' => 'admin_dashboard.php'
    ],
    
    [
        'id' => 'doctors',
        'label' => 'Manage Doctors',
        'icon' => '👨‍⚕️',
        'url' => 'admin_doctors.php',
        'count' => $pending_doctors_count
    ],
    [
        'id' => 'patients',
        'label' => 'Manage Patients',
        'icon' => '👥',
        'url' => 'admin_patients.php',
        'count' => $pending_patients_count
    ],
    [
        'id' => 'appointments',
        'label' => 'Appointments',
        'icon' => '📅',
        'url' => 'admin_appointments.php'
    ],
    [
        'id' => 'education',
        'label' => 'Education',
        'icon' => '📚',
        'url' => 'admin_manage_education.php'
    ],
    [
        'id' => 'ai',
        'label' => 'AI Assistant',
        'icon' => '🤖',
        'url' => 'admin_ai.php'
    ],
    [
        'id' => 'nexora',
        'label' => 'Nexora Security',
        'icon' => '🛡️',
        'url' => 'security_dashboard.php'
    ]
];
?>

<!-- =====================================================
     ADMIN SIDEBAR
===================================================== -->

<!-- Menu Toggle Button -->
<button
    class="menu-toggle"
    id="menuToggle"
    aria-label="Toggle navigation"
    aria-controls="sidebar"
    aria-expanded="false"
    type="button"
>
    ☰
</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <span class="logo-icon">🏥</span>

            <div class="logo-text">
                <h2>Human Care</h2>
                <span>Admin Panel</span>
            </div>
        </div>
    </div>

    <!-- Admin Profile -->
    <div class="sidebar-profile">
        <div class="profile-avatar">🛡️</div>

        <div class="profile-info">
            <strong>
                <?= htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8') ?>
            </strong>
            <span>Administrator</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav" aria-label="Admin navigation">

        <div class="nav-heading">MAIN MENU</div>

        <?php foreach ($nav_items as $item): ?>
            <a
                href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                class="nav-link <?= admin_sb_active($item['id'], $active_page) ?>"
                <?= $active_page === $item['id'] ? 'aria-current="page"' : '' ?>
            >
                <span class="nav-icon">
                    <?= $item['icon'] ?>
                </span>

                <span class="nav-label">
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>

                <?php if (!empty($item['count'])): ?>
                    <span class="nav-badge">
                        <?= (int) $item['count'] ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">

        <a href="admin_logout.php" class="nav-link logout-link">
            <span class="nav-icon">🚪</span>
            <span class="nav-label">Logout</span>
        </a>

    </div>

</aside>

<!-- Sidebar Overlay -->
<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>

<script src="js/sidebar.js"></script>
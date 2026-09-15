
<?php
/**
 * Doctor Sidebar Component
 * ------------------------
 * Include this file in all doctor pages.
 *
 * Required variables before including:
 *   $doctor_id    = Current doctor's ID
 *   $active_page  = 'dashboard' | 'chat' | 'prescriptions' | 'ai' | 'education'
 *
 * Optional:
 *   $unreadCount  = Unread chat message count
 */

// =====================================================
// DOCTOR LOGIN CHECK
// =====================================================

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}


// =====================================================
// GET DOCTOR INFORMATION
// =====================================================

if (!isset($doctor)) {

    $doctors_conn_sidebar = new mysqli(
        DB_HOST,
        DB_USERNAME,
        DB_PASSWORD,
        DB_DOCTORS
    );

    if (!$doctors_conn_sidebar->connect_error) {

        $stmt = $doctors_conn_sidebar->prepare(
            "SELECT first_name, last_name, specialty
             FROM doctors
             WHERE id = ?"
        );

        if ($stmt) {

            $stmt->bind_param("i", $doctor_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $doctor = $result->fetch_assoc();

            $stmt->close();
        }

        $doctors_conn_sidebar->close();
    }
}


// =====================================================
// GET UNREAD CHAT COUNT
// =====================================================

if (!isset($unreadCount)) {

    try {

        require_once __DIR__ . '/../classes/msg.php';

        $chat_sidebar = new Chat();

        $unreadCount = $chat_sidebar->getUnreadCount(
            $doctor_id,
            'doctor'
        );

    } catch (Exception $e) {

        $unreadCount = 0;
    }
}


// =====================================================
// DEFAULT VALUES
// =====================================================

$active_page = $active_page ?? '';

$unreadCount = (int) ($unreadCount ?? 0);

$doctor_first_name = $doctor['first_name'] ?? 'Doctor';
$doctor_last_name  = $doctor['last_name'] ?? '';
$doctor_specialty  = $doctor['specialty'] ?? 'General Medicine';


// =====================================================
// ACTIVE PAGE HELPER
// =====================================================

function doctor_sb_active(string $page, string $current): string
{
    return $page === $current ? 'active' : '';
}

?>

<!-- =====================================================
     DOCTOR SIDEBAR
===================================================== -->

<!-- Menu Toggle Button -->
<button
    class="menu-toggle"
    id="menuToggle"
    aria-label="Toggle navigation"
    type="button"
>
    ☰
</button>


<!-- Sidebar -->
<aside class="sidebar" id="sidebar">

    <!-- =================================================
         LOGO
    ================================================== -->

    <div class="sidebar-logo">

        <div class="logo-icon">
            ❤️
        </div>

        HUMAN CARE

    </div>


    <!-- =================================================
         DOCTOR PROFILE
    ================================================== -->

    <div class="doctor-profile-sidebar">

        <div class="doctor-avatar-sidebar">
            👨‍⚕️
        </div>

        <div class="doctor-info-sidebar">

            <div class="doctor-name-sidebar">
                Dr.
                <?= htmlspecialchars(
                    trim($doctor_first_name . ' ' . $doctor_last_name)
                ) ?>
            </div>

            <span class="doctor-badge-sidebar">
                DOCTOR
            </span>

            <div class="doctor-specialty-sidebar">
                <?= htmlspecialchars($doctor_specialty) ?>
            </div>

        </div>

    </div>


    <!-- =================================================
         NAVIGATION
    ================================================== -->

    <ul class="sidebar-nav">

        <!-- Dashboard -->
        <li>

            <a
                href="doctor_dashboard.php"
                class="<?= doctor_sb_active('dashboard', $active_page) ?>"
            >

                <span class="nav-icon">
                    🏠
                </span>

                <span>
                    Dashboard
                </span>

            </a>

        </li>


        <!-- Patient Chats -->
        <li>

            <a
                href="doctor_msg.php"
                class="<?= doctor_sb_active('chat', $active_page) ?>"
            >

                <span class="nav-icon">
                    💬
                </span>

                <span>
                    Patient Chats
                </span>

                <?php if ($unreadCount > 0): ?>

                    <span class="unread-badge">
                        <?= $unreadCount ?>
                    </span>

                <?php endif; ?>

            </a>

        </li>


        <!-- Prescriptions -->
        <li>

            <a
                href="doctor_prescriptions_list.php"
                class="<?= doctor_sb_active('prescriptions', $active_page) ?>"
            >

                <span class="nav-icon">
                    💊
                </span>

                <span>
                    Prescriptions
                </span>

            </a>

        </li>


        <!-- AI Assistant -->
        <li>

            <a
                href="doctor_ai_assistant.php"
                class="<?= doctor_sb_active('ai', $active_page) ?>"
            >

                <span class="nav-icon">
                    🤖
                </span>

                <span>
                    AI Assistant
                </span>

            </a>

        </li>


        <!-- Learning Page -->
        <li>

            <a
                href="doctor_add_education.php"
                class="<?= doctor_sb_active('education', $active_page) ?>"
            >

                <span class="nav-icon">
                    📚
                </span>

                <span>
                    Edit Learning Page
                </span>

            </a>

        </li>


        <!-- Contact -->
        <li>

            <a
                href="doctor_contact.php"
                class="<?= doctor_sb_active('contact', $active_page) ?>"
            >

                <span class="nav-icon">
                    📞
                </span>

                <span>
                    Contact & Support
                </span>

            </a>

        </li>


        <!-- Bug Report -->
        <li>

            <a
                href="report_bug.php"
                class="bug-report-link"
            >

                <span class="nav-icon">
                    🐞
                </span>

                <span>
                    Report a Bug
                </span>

            </a>

        </li>

    </ul>


    <!-- =================================================
         USER BOX
    ================================================== -->

    <div class="user-box-sidebar">

        <div class="user-name-sidebar">

            👤 Dr.
            <?= htmlspecialchars(
                trim($doctor_first_name . ' ' . $doctor_last_name)
            ) ?>

        </div>


        <a
            href="doctor_dashboard.php"
            class="login-btn-sidebar"
        >
            My Dashboard
        </a>


        <a
            href="logout.php"
            class="logout-btn-sidebar"
        >
            Logout
        </a>

    </div>

</aside>


<!-- =====================================================
     SIDEBAR OVERLAY
===================================================== -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<!-- =====================================================
     SIDEBAR TOGGLE SCRIPT
===================================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');


    // Stop if sidebar elements do not exist
    if (!menuToggle || !sidebar || !overlay) {

        console.warn('Doctor sidebar elements not found.');

        return;
    }


    // ================================================
    // OPEN SIDEBAR
    // ================================================

    function openSidebar() {

        sidebar.classList.add('active');

        overlay.classList.add('active');

        document.body.classList.add('sidebar-open');

    }


    // ================================================
    // CLOSE SIDEBAR
    // ================================================

    function closeSidebar() {

        sidebar.classList.remove('active');

        overlay.classList.remove('active');

        document.body.classList.remove('sidebar-open');

    }


    // ================================================
    // TOGGLE SIDEBAR
    // ================================================

    function toggleSidebar() {

        if (sidebar.classList.contains('active')) {

            closeSidebar();

        } else {

            openSidebar();

        }

    }


    // ================================================
    // MENU BUTTON
    // ================================================

    menuToggle.addEventListener('click', function (e) {

        e.preventDefault();

        e.stopPropagation();

        toggleSidebar();

    });


    // ================================================
    // OVERLAY
    // ================================================

    overlay.addEventListener('click', function () {

        closeSidebar();

    });


    // ================================================
    // ESCAPE KEY
    // ================================================

    document.addEventListener('keydown', function (e) {

        if (e.key === 'Escape') {

            closeSidebar();

        }

    });


    // ================================================
    // CLICK OUTSIDE SIDEBAR
    // ================================================

    document.addEventListener('click', function (e) {

        if (
            sidebar.classList.contains('active') &&
            !sidebar.contains(e.target) &&
            !menuToggle.contains(e.target)
        ) {

            closeSidebar();

        }

    });


    // ================================================
    // PREVENT SIDEBAR CLICKS FROM CLOSING
    // ================================================

    sidebar.addEventListener('click', function (e) {

        e.stopPropagation();

    });

});

</script>


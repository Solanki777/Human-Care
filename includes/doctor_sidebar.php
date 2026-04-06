<?php
/**
 * Doctor Sidebar Component
 * Include this file in all doctor pages
 * 
 * Required variables before including:
 * - $doctor_id (int) - Current doctor's ID
 * - $active_page (string) - Current page identifier for active menu highlighting
 * 
 * Optional variables:
 * - $unreadCount (int) - Unread chat messages count (defaults to 0)
 */

// Ensure doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

// Get doctor information if not already loaded
if (!isset($doctor)) {
    $doctors_conn_sidebar = new mysqli("localhost", "root", "", "human_care_doctors");
    if (!$doctors_conn_sidebar->connect_error) {
        $stmt = $doctors_conn_sidebar->prepare("SELECT first_name, last_name, specialty FROM doctors WHERE id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $doctor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $doctors_conn_sidebar->close();
    }
}

// Get unread count if not already loaded
if (!isset($unreadCount)) {
    try {
        require_once 'classes/Chat.php';
        $chat_sidebar = new Chat();
        $unreadCount = $chat_sidebar->getUnreadCount($doctor_id, 'doctor');
    } catch (Exception $e) {
        $unreadCount = 0;
    }
}

// Default values
$doctor_first_name = $doctor['first_name'] ?? 'Doctor';
$doctor_last_name = $doctor['last_name'] ?? '';
$doctor_specialty = $doctor['specialty'] ?? 'General Medicine';
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="logo">
        <div class="logo-icon">❤️</div>
        HUMAN CARE
    </div>

    <!-- Doctor Profile -->
    <div class="user-profile">
        <div class="user-avatar">👨‍⚕️</div>
        <div class="user-info">
            <h3>Dr. <?php echo htmlspecialchars($doctor_first_name . ' ' . $doctor_last_name); ?></h3>
            <span class="doctor-badge">DOCTOR</span>
            <p class="specialty-tag"><?php echo htmlspecialchars($doctor_specialty); ?></p>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav>
        <ul class="nav-menu">
            <li class="nav-item">
                <a class="nav-link <?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>" href="doctor_dashboard.php">
                    <span class="nav-icon">🏠</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($active_page === 'chat') ? 'active' : ''; ?>" href="doctor_chat.php">
                    <span class="nav-icon">💬</span>
                    <span>Patient Chats</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($active_page === 'prescriptions') ? 'active' : ''; ?>" href="doctor_prescriptions_list.php">
                    <span class="nav-icon">💊</span>
                    <span>Prescriptions</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($active_page === 'education') ? 'active' : ''; ?>" href="doctor_add_education.php">
                    <span class="nav-icon">📚</span>
                    <span>Edit Learning Page</span>
                </a>
            </li>

        </ul>
    </nav>

    <!-- Logout Button -->
    <form method="post" action="logout.php">
        <button class="logout-btn" type="submit">🚪 Logout</button>
    </form>
</aside>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<style>
    /* Doctor-specific sidebar styles */
    .doctor-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-top: 5px;
        display: inline-block;
    }

    .specialty-tag {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.9);
        margin-top: 5px;
    }

    .badge {
        background: #ff4757;
        color: white;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }
</style>
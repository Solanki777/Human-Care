<?php
require_once 'config/config.php';
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = Database::getConnection('admin');

/* Handle approve / reject / cancel */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = (int) $_POST['appointment_id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'approved',
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $_SESSION['admin_id'], $appointment_id);
        $stmt->execute();
        // ===== CREATE CHAT ROOM AFTER APPROVAL =====

        // Get appointment details
        $info_stmt = $conn->prepare("
        SELECT patient_id, doctor_id, patient_name, doctor_name
        FROM appointments
        WHERE id = ?
    ");
        $info_stmt->bind_param("i", $appointment_id);
        $info_stmt->execute();
        $appt = $info_stmt->get_result()->fetch_assoc();
        $info_stmt->close();

        // Create chat room
        $chat_stmt = $conn->prepare("
        INSERT INTO chat_rooms
        (appointment_id, patient_id, doctor_id, patient_name, doctor_name, status)
        VALUES (?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE status = 'active'
    ");
        $chat_stmt->bind_param(
            "iiiss",
            $appointment_id,
            $appt['patient_id'],
            $appt['doctor_id'],
            $appt['patient_name'],
            $appt['doctor_name']
        );
        $chat_stmt->execute();
        $chat_stmt->close();


        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO appointment_history (appointment_id, action, performed_by, performed_by_type, old_status, new_status, created_at) VALUES (?, 'approved', ?, 'admin', 'pending', 'approved', NOW())");
        $log_stmt->bind_param("ii", $appointment_id, $_SESSION['admin_id']);
        $log_stmt->execute();
        $log_stmt->close();
    }

    if ($action === 'reject') {
        $reason = trim($_POST['rejection_reason']);

        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'rejected',
                rejection_reason = ?,
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $reason, $_SESSION['admin_id'], $appointment_id);
        $stmt->execute();

        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO appointment_history (appointment_id, action, performed_by, performed_by_type, old_status, new_status, notes, created_at) VALUES (?, 'rejected', ?, 'admin', 'pending', 'rejected', ?, NOW())");
        $log_stmt->bind_param("iis", $appointment_id, $_SESSION['admin_id'], $reason);
        $log_stmt->execute();
        $log_stmt->close();
    }

    // NEW: Cancel/Deny an already approved appointment
    if ($action === 'cancel') {
        $cancellation_reason = trim($_POST['cancellation_reason']);

        // Get current appointment details before cancelling
        $check_stmt = $conn->prepare("SELECT status FROM appointments WHERE id = ?");
        $check_stmt->bind_param("i", $appointment_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $current = $result->fetch_assoc();
        $old_status = $current['status'];
        $check_stmt->close();

        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'cancelled',
                rejection_reason = ?,
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $cancellation_reason, $_SESSION['admin_id'], $appointment_id);
        $stmt->execute();

        // Log the cancellation action
        $log_stmt = $conn->prepare("INSERT INTO appointment_history (appointment_id, action, performed_by, performed_by_type, old_status, new_status, notes, created_at) VALUES (?, 'cancelled_by_admin', ?, 'admin', ?, 'cancelled', ?, NOW())");
        $log_stmt->bind_param("iiss", $appointment_id, $_SESSION['admin_id'], $old_status, $cancellation_reason);
        $log_stmt->execute();
        $log_stmt->close();

        // Create notifications for patient and doctor
        $notify_stmt = $conn->prepare("
            SELECT patient_id, doctor_id, patient_name, doctor_name, appointment_date, appointment_time 
            FROM appointments WHERE id = ?
        ");
        $notify_stmt->bind_param("i", $appointment_id);
        $notify_stmt->execute();
        $appt_data = $notify_stmt->get_result()->fetch_assoc();
        $notify_stmt->close();

        // Notification for patient
        $patient_msg = "Your appointment with Dr. " . $appt_data['doctor_name'] . " on " .
            date('M d, Y', strtotime($appt_data['appointment_date'])) . " at " .
            date('h:i A', strtotime($appt_data['appointment_time'])) .
            " has been cancelled by admin. Reason: " . $cancellation_reason;

        $notif_stmt = $conn->prepare("INSERT INTO appointment_notifications (appointment_id, recipient_type, recipient_id, notification_type, message) VALUES (?, 'patient', ?, 'cancelled', ?)");
        $notif_stmt->bind_param("iis", $appointment_id, $appt_data['patient_id'], $patient_msg);
        $notif_stmt->execute();

        // Notification for doctor
        $doctor_msg = "Appointment with " . $appt_data['patient_name'] . " on " .
            date('M d, Y', strtotime($appt_data['appointment_date'])) . " at " .
            date('h:i A', strtotime($appt_data['appointment_time'])) .
            " has been cancelled by admin. Reason: " . $cancellation_reason;

        $notif_stmt->bind_param("iis", $appointment_id, $appt_data['doctor_id'], $doctor_msg);
        $notif_stmt->execute();
        $notif_stmt->close();
    }
    // NEW: Mark approved appointment as completed
    if ($action === 'complete') {

        // Ensure only approved appointments can be completed
        $check_stmt = $conn->prepare("SELECT status FROM appointments WHERE id = ?");
        $check_stmt->bind_param("i", $appointment_id);
        $check_stmt->execute();
        $current = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($current && $current['status'] === 'approved') {

            // Update appointment status
            $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'completed',
                completed_at = NOW()
            WHERE id = ?
        ");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $stmt->close();

            // Log history
            $log_stmt = $conn->prepare("
            INSERT INTO appointment_history 
            (appointment_id, action, performed_by, performed_by_type, old_status, new_status, created_at)
            VALUES (?, 'completed', ?, 'admin', 'approved', 'completed', NOW())
        ");
            $log_stmt->bind_param("ii", $appointment_id, $_SESSION['admin_id']);
            $log_stmt->execute();
            $log_stmt->close();

            // Close chat room if exists
            $chat_stmt = $conn->prepare("
            UPDATE chat_rooms 
            SET status = 'closed'
            WHERE appointment_id = ?
        ");
            $chat_stmt->bind_param("i", $appointment_id);
            $chat_stmt->execute();
            $chat_stmt->close();
        }
    }

}

// Fetch appointments with filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$allowed_filters = ['all', 'pending', 'approved', 'rejected', 'cancelled', 'completed'];

if (!in_array($filter, $allowed_filters)) {
    $filter = 'pending';
}

if ($filter === 'all') {
    $result = $conn->query("
        SELECT * FROM appointments
        ORDER BY 
            CASE 
                WHEN status = 'pending' THEN 1
                WHEN status = 'approved' THEN 2
                WHEN status = 'cancelled' THEN 3
                WHEN status = 'rejected' THEN 4
                WHEN status = 'completed' THEN 5
            END,
            created_at DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT * FROM appointments
        WHERE status = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("s", $filter);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Get counts for badges
$pending_count = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'approved'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'rejected'")->fetch_assoc()['count'];
$cancelled_count = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled'")->fetch_assoc()['count'];
$completed_count = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")->fetch_assoc()['count'];
$total_count = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Appointments - Human Care</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .admin-badge {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .pending-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .nav-link.admin-nav {
            background: rgba(30, 60, 114, 0.1);
        }

        .nav-link.admin-nav:hover {
            background: rgba(30, 60, 114, 0.2);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            color: #666;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-tab:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-color: #1e3c72;
        }

        .count-badge {
            background: rgba(255, 255, 255, 0.3);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }

        .filter-tab.active .count-badge {
            background: rgba(255, 255, 255, 0.3);
        }

        .appointments-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }

        .appointments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .appointments-table th {
            background: #f3f4f6;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e5e7eb;
        }

        .appointments-table td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .appointments-table tr:hover {
            background: #f9fafb;
        }

        .patient-info,
        .doctor-info {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .appointment-datetime {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .date-badge,
        .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
        }

        .date-badge {
            background: #dbeafe;
            color: #1e40af;
        }

        .time-badge {
            background: #fce7f3;
            color: #9f1239;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-cancelled {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .approve-form,
        .reject-form,
        .cancel-form {
            margin: 0;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s;
            width: 100%;
            margin-top: 8px;
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s;
            width: 100%;
            margin-top: 8px;
        }

        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .rejection-reason,
        .cancellation-reason {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            resize: vertical;
            min-height: 60px;
            margin-bottom: 5px;
            font-family: inherit;
        }

        .rejection-reason:focus,
        .cancellation-reason:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .no-appointments {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-appointments-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-left: 4px solid #3b82f6;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #3b82f6;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .reason-display {
            background: #fee2e2;
            padding: 10px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 13px;
            color: #991b1b;
            border-left: 3px solid #ef4444;
        }

        .reason-display strong {
            display: block;
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .filter-tabs {
                flex-direction: column;
            }

            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }

            .appointments-table {
                font-size: 12px;
            }

            .appointments-table th,
            .appointments-table td {
                padding: 10px;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">🛡️</div>
            ADMIN PANEL
        </div>

        <!-- Admin Profile -->
        <div class="user-profile">
            <div class="user-avatar">👨‍💼</div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h3>
                <span class="admin-badge">ADMINISTRATOR</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_dashboard.php">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_doctors.php">
                        <span class="nav-icon">👨‍⚕️</span>
                        <span>Manage Doctors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_patients.php">
                        <span class="nav-icon">👥</span>
                        <span>Manage Patients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active admin-nav" href="admin_appointments.php">
                        <span class="nav-icon">📅</span>
                        <span>Appointments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_manage_education.php">
                        <span class="nav-icon">📚 </span>
                        <span>Approve Education</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Logout Button -->
        <form method="post" action="admin_logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="hero-banner" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
            <h2>📅 Appointment Management</h2>
            <p>Review and manage all appointment requests</p>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                📋 All Appointments
                <span class="count-badge"><?php echo $total_count; ?></span>
            </a>
            <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                ⏳ Pending
                <span class="count-badge"><?php echo $pending_count; ?></span>
            </a>
            <a href="?filter=approved" class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                ✅ Approved
                <span class="count-badge"><?php echo $approved_count; ?></span>
            </a>
            <a href="?filter=cancelled" class="filter-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                🚫 Cancelled
                <span class="count-badge"><?php echo $cancelled_count; ?></span>
            </a>
            <a href="?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                ❌ Rejected
                <span class="count-badge"><?php echo $rejected_count; ?></span>
            </a>
            <a href="?filter=completed" class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                ✔️ Completed
                <span class="count-badge"><?php echo $completed_count; ?></span>
            </a>
        </div>

        <!-- Appointments Container -->
        <div class="appointments-container">
            <h3 style="font-size: 20px; margin-bottom: 20px; color: #333;">
                <?php
                echo match ($filter) {
                    'all' => '📋 All Appointments',
                    'pending' => '⏳ Pending Appointment Requests',
                    'approved' => '✅ Approved Appointments',
                    'cancelled' => '🚫 Cancelled Appointments',
                    'rejected' => '❌ Rejected Appointments',
                    'completed' => '✔️ Completed Appointments',
                    default => 'Appointments'
                };
                ?>
            </h3>

            <?php if ($result->num_rows === 0): ?>
                <div class="no-appointments">
                    <div class="no-appointments-icon">
                        <?php
                        echo match ($filter) {
                            'pending' => '✅',
                            'approved' => '📭',
                            'cancelled' => '📭',
                            'rejected' => '📭',
                            'completed' => '📭',
                            default => '📭'
                        };
                        ?>
                    </div>
                    <h3 style="font-size: 18px; color: #666; margin-bottom: 10px;">
                        <?php echo $filter === 'pending' ? 'All Clear!' : 'No Appointments Found'; ?>
                    </h3>
                    <p>
                        <?php
                        echo match ($filter) {
                            'pending' => 'No pending appointments at the moment.',
                            'approved' => 'No approved appointments.',
                            'cancelled' => 'No cancelled appointments.',
                            'rejected' => 'No rejected appointments.',
                            'completed' => 'No completed appointments.',
                            'all' => 'No appointments in the system.',
                            default => 'No appointments found.'
                        };
                        ?>
                    </p>
                </div>
            <?php else: ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Patient Details</th>
                            <th>Doctor Details</th>
                            <th>Appointment Date & Time</th>
                            <th>Status</th>
                            <th style="width: 250px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="patient-info">👤 <?= htmlspecialchars($row['patient_name']) ?></div>
                                    <div style="font-size: 12px; color: #666;">📧 <?= htmlspecialchars($row['patient_email']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">📱 <?= htmlspecialchars($row['patient_phone']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="doctor-info">👨‍⚕️ Dr. <?= htmlspecialchars($row['doctor_name']) ?></div>
                                    <div style="font-size: 12px; color: #667eea; font-weight: 600;">
                                        <?= htmlspecialchars($row['doctor_specialty']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="appointment-datetime">
                                        <span class="date-badge">📅
                                            <?= date('M d, Y', strtotime($row['appointment_date'])) ?></span>
                                        <span class="time-badge">🕐
                                            <?= date('h:i A', strtotime($row['appointment_time'])) ?></span>
                                    </div>
                                    <div style="margin-top: 8px; font-size: 12px; color: #666;">
                                        <strong>Reason:</strong> <?= htmlspecialchars($row['reason_for_visit']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $row['status'] ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>

                                    <?php if (($row['status'] === 'rejected' || $row['status'] === 'cancelled') && $row['rejection_reason']): ?>
                                        <div class="reason-display">
                                            <strong>Reason:</strong>
                                            <?= htmlspecialchars($row['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <!-- Approve -->
                                            <form method="POST" class="approve-form">
                                                <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-approve">
                                                    ✅ Approve
                                                </button>
                                            </form>

                                            <!-- Reject -->
                                            <form method="POST" class="reject-form">
                                                <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <textarea name="rejection_reason" class="rejection-reason" required
                                                    placeholder="Enter reason for rejection..."></textarea>
                                                <button type="submit" class="btn-reject">
                                                    ❌ Reject
                                                </button>
                                            </form>
                                        <?php elseif ($row['status'] === 'approved'): ?>

                                            <!-- Mark as Completed -->
                                            <form method="POST">
                                                <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <button type="submit" class="btn-approve"
                                                    onclick="return confirm('Mark this appointment as COMPLETED? This action cannot be undone.')">
                                                    ✔️ Mark Completed
                                                </button>
                                            </form>

                                            <!-- Cancel Approved Appointment -->
                                            <form method="POST" class="cancel-form">
                                                <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <textarea name="cancellation_reason" class="cancellation-reason" required
                                                    placeholder="Enter reason for cancellation..."></textarea>
                                                <button type="submit" class="btn-cancel">
                                                    🚫 Cancel Appointment
                                                </button>
                                            </form>

                                            <!-- Cancel Approved Appointment -->
                                            <form method="POST" class="cancel-form">
                                                <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <textarea name="cancellation_reason" class="cancellation-reason" required
                                                    placeholder="Enter reason for cancellation (will notify patient & doctor)..."></textarea>
                                                <button type="submit" class="btn-cancel"
                                                    onclick="return confirm('Are you sure you want to cancel this approved appointment? Both patient and doctor will be notified.')">
                                                    🚫 Cancel Appointment
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div style="color: #999; font-size: 13px; text-align: center; padding: 10px;">
                                                No actions available
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
    </script>
</body>

</html>
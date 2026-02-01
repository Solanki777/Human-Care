<?php
require_once 'config/config.php';
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = Database::getConnection('admin');

/* Handle approve / reject */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = (int)$_POST['appointment_id'];
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
    }
}

/* Fetch pending appointments */
$result = $conn->query("
    SELECT * FROM appointments
    WHERE status = 'pending'
    ORDER BY created_at DESC
");

// Get counts for sidebar badges
$patients_conn = new mysqli("localhost", "root", "", "human_care_patients");
$pending_patients = $patients_conn->query("SELECT COUNT(*) as count FROM patients WHERE verification_status = 'pending'")->fetch_assoc()['count'];
$patients_conn->close();

$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
$pending_doctors = $doctors_conn->query("SELECT COUNT(*) as count FROM doctors WHERE verification_status = 'pending'")->fetch_assoc()['count'];
$doctors_conn->close();
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

        .appointments-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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

        .patient-info, .doctor-info {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .appointment-datetime {
            display: flex;
            gap: 15px;
        }

        .date-badge, .time-badge {
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

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .approve-form, .reject-form {
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

        .rejection-reason {
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

        .rejection-reason:focus {
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
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
                    <a class="nav-link active admin-nav" onclick="showSection('dashboard')">
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
                    <a class="nav-link admin-nav" href="admin_appointments.php">
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
            <p>Review and manage pending appointment requests</p>
        </div>

        <!-- Statistics Summary -->
        <div class="stats-summary">
            <div class="stat-box">
                <div class="stat-label">Pending Appointments</div>
                <div class="stat-number"><?php echo $result->num_rows; ?></div>
            </div>
        </div>

        <!-- Appointments Container -->
        <div class="appointments-container">
            <h3 style="font-size: 20px; margin-bottom: 20px; color: #333;">⏳ Pending Appointment Requests</h3>

            <?php if ($result->num_rows === 0): ?>
                <div class="no-appointments">
                    <div class="no-appointments-icon">✅</div>
                    <h3 style="font-size: 18px; color: #666; margin-bottom: 10px;">All Clear!</h3>
                    <p>No pending appointments at the moment.</p>
                </div>
            <?php else: ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Patient Details</th>
                            <th>Doctor Details</th>
                            <th>Appointment Date & Time</th>
                            <th style="width: 250px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="patient-info">👤 <?= htmlspecialchars($row['patient_name']) ?></div>
                            </td>
                            <td>
                                <div class="doctor-info">👨‍⚕️ <?= htmlspecialchars($row['doctor_name']) ?></div>
                            </td>
                            <td>
                                <div class="appointment-datetime">
                                    <span class="date-badge">📅 <?= date('M d, Y', strtotime($row['appointment_date'])) ?></span>
                                    <span class="time-badge">🕐 <?= date('h:i A', strtotime($row['appointment_time'])) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
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
                                        <textarea 
                                            name="rejection_reason" 
                                            class="rejection-reason" 
                                            required 
                                            placeholder="Enter reason for rejection..."
                                        ></textarea>
                                        <button type="submit" class="btn-reject">
                                            ❌ Reject
                                        </button>
                                    </form>
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
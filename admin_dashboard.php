<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Get counts from databases
// Patients count
$patients_conn = new mysqli("localhost", "root", "", "human_care_patients");
$total_patients = $patients_conn->query("SELECT COUNT(*) as count FROM patients")->fetch_assoc()['count'];
$pending_patients = $patients_conn->query("SELECT COUNT(*) as count FROM patients WHERE verification_status = 'pending'")->fetch_assoc()['count'];
$patients_conn->close();

// Doctors count
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
$total_doctors = $doctors_conn->query("SELECT COUNT(*) as count FROM doctors")->fetch_assoc()['count'];
$pending_doctors = $doctors_conn->query("SELECT COUNT(*) as count FROM doctors WHERE verification_status = 'pending'")->fetch_assoc()['count'];
$total_appointments = $doctors_conn->query("SELECT COUNT(*) as count FROM doctor_appointments")->fetch_assoc()['count'];
$doctors_conn->close();

// Recent activity
$admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
$recent_logs = $admin_conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Human Care</title>
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

        .stat-card.admin {
            border-left: 4px solid #1e3c72;
        }

        .stat-card.warning {
            border-left: 4px solid #f59e0b;
        }

        .stat-card.success {
            border-left: 4px solid #10b981;
        }

        .stat-card.info {
            border-left: 4px solid #3b82f6;
        }

        .pending-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .action-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .action-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .action-desc {
            font-size: 13px;
            color: #666;
        }

        .activity-log {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .log-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-action {
            font-weight: 600;
            color: #333;
        }

        .log-time {
            font-size: 12px;
            color: #999;
        }

        .nav-link.admin-nav {
            background: rgba(30, 60, 114, 0.1);
        }

        .nav-link.admin-nav:hover {
            background: rgba(30, 60, 114, 0.2);
        }
    </style>
</head>
<body>
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
                        <?php if ($pending_doctors > 0): ?>
                            <span class="pending-badge"><?php echo $pending_doctors; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_patients.php">
                        <span class="nav-icon">👥</span>
                        <span>Manage Patients</span>
                        <?php if ($pending_patients > 0): ?>
                            <span class="pending-badge"><?php echo $pending_patients; ?></span>
                        <?php endif; ?>
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
        <section id="dashboard" class="section active">
            <div class="hero-banner" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?> 🛡️</h2>
                <p>Complete administrative control of Human Care Hospital System</p>
            </div>

            <!-- Statistics Cards -->
            <div class="cards-grid">
                <div class="card stat-card info">
                    <div class="card-icon">👥</div>
                    <h3>Total Patients</h3>
                    <p style="font-size: 32px; font-weight: bold; color: #3b82f6; margin: 10px 0;"><?php echo $total_patients; ?></p>
                    <?php if ($pending_patients > 0): ?>
                        <span class="pending-badge"><?php echo $pending_patients; ?> Pending</span>
                    <?php endif; ?>
                </div>

                <div class="card stat-card success">
                    <div class="card-icon">👨‍⚕️</div>
                    <h3>Total Doctors</h3>
                    <p style="font-size: 32px; font-weight: bold; color: #10b981; margin: 10px 0;"><?php echo $total_doctors; ?></p>
                    <?php if ($pending_doctors > 0): ?>
                        <span class="pending-badge"><?php echo $pending_doctors; ?> Pending</span>
                    <?php endif; ?>
                </div>

                <div class="card stat-card warning">
                    <div class="card-icon">📅</div>
                    <h3>Total Appointments</h3>
                    <p style="font-size: 32px; font-weight: bold; color: #f59e0b; margin: 10px 0;"><?php echo $total_appointments; ?></p>
                </div>

                <div class="card stat-card admin">
                    <div class="card-icon">⏰</div>
                    <h3>Pending Verifications</h3>
                    <p style="font-size: 32px; font-weight: bold; color: #1e3c72; margin: 10px 0;"><?php echo $pending_doctors + $pending_patients; ?></p>
                </div>
            </div>

            <!-- Quick Actions -->
            <h3 style="font-size: 24px; margin: 30px 0 20px; color: #333;">⚡ Quick Actions</h3>
            <div class="quick-actions">
                <a href="admin_doctors.php" class="action-card">
                    <div class="action-icon">✅</div>
                    <div class="action-title">Verify Doctors</div>
                    <div class="action-desc">Approve or reject doctor applications</div>
                </a>

                <a href="admin_patients.php" class="action-card">
                    <div class="action-icon">👥</div>
                    <div class="action-title">Manage Patients</div>
                    <div class="action-desc">View and manage patient records</div>
                </a>

                <a href="admin_appointments.php" class="action-card">
                    <div class="action-icon">📅</div>
                    <div class="action-title">View Appointments</div>
                    <div class="action-desc">Monitor all appointments</div>
                </a>

                <a href="admin_settings.php" class="action-card">
                    <div class="action-icon">⚙️</div>
                    <div class="action-title">System Settings</div>
                    <div class="action-desc">Configure system parameters</div>
                </a>

              

                <a href="index.php" target="_blank" class="action-card">
                    <div class="action-icon">🌐</div>
                    <div class="action-title">View Website</div>
                    <div class="action-desc">Preview public website</div>
                </a>
            </div>

            <!-- Recent Activity -->
            <h3 style="font-size: 24px; margin: 30px 0 20px; color: #333;">📋 Recent Activity</h3>
            <div class="activity-log">
                <?php if ($recent_logs->num_rows > 0): ?>
                    <?php while ($log = $recent_logs->fetch_assoc()): ?>
                        <div class="log-item">
                            <div>
                                <div class="log-action"><?php echo htmlspecialchars($log['action']); ?></div>
                                <div style="font-size: 13px; color: #666;"><?php echo htmlspecialchars($log['description']); ?></div>
                            </div>
                            <div class="log-time"><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #999; padding: 20px;">No recent activity</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
                section.classList.add('hidden');
            });
            document.getElementById(sectionId)?.classList.remove('hidden');
            document.getElementById(sectionId)?.classList.add('active');
        }
    </script>
</body>
</html>
<?php $admin_conn->close(); ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';

// Check admin login before outputting anything
if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header("Location: admin_login.php");
    exit();
}

// Active sidebar page
$active_page = 'dashboard';

// Database connections
$patients_conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_PATIENTS
);

$doctors_conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_DOCTORS
);

$admin_conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_ADMIN
);

$patients_conn->set_charset("utf8mb4");
$doctors_conn->set_charset("utf8mb4");
$admin_conn->set_charset("utf8mb4");

// Patients
$total_patients = $patients_conn
    ->query("SELECT COUNT(*) AS count FROM patients")
    ->fetch_assoc()['count'];

$pending_patients = $patients_conn
    ->query("SELECT COUNT(*) AS count FROM patients WHERE verification_status = 'pending'")
    ->fetch_assoc()['count'];

// Doctors and appointments
$total_doctors = $doctors_conn
    ->query("SELECT COUNT(*) AS count FROM doctors")
    ->fetch_assoc()['count'];

$pending_doctors = $doctors_conn
    ->query("SELECT COUNT(*) AS count FROM doctors WHERE verification_status = 'pending'")
    ->fetch_assoc()['count'];

$total_appointments = $doctors_conn
    ->query("SELECT COUNT(*) AS count FROM doctor_appointments")
    ->fetch_assoc()['count'];

// Recent activity
$recent_logs = $admin_conn->query(
    "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5"
);

// Pending education
$pending_education = $admin_conn
    ->query("SELECT COUNT(*) AS count FROM educational_content WHERE status = 'pending'")
    ->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/dashboard.css">

</head>

<body>
     <?php
    require_once __DIR__ . '/includes/admin_sidebar.php';
    ?>


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
                    <p style="font-size: 32px; font-weight: bold; color: #3b82f6; margin: 10px 0;">
                        <?php echo $total_patients; ?></p>
                    <?php if ($pending_patients > 0): ?>
                        <span class="pending-badge"><?php echo $pending_patients; ?> Pending</span>
                    <?php endif; ?>
                </div>

                <div class="card stat-card success">
                    <div class="card-icon">👨‍⚕️</div>
                    <h3>Total Doctors</h3>
                    <p style="font-size: 32px; font-weight: bold; color: #10b981; margin: 10px 0;">
                        <?php echo $total_doctors; ?></p>
                    <?php if ($pending_doctors > 0): ?>
                        <span class="pending-badge"><?php echo $pending_doctors; ?> Pending</span>
                    <?php endif; ?>
                </div>

                <div class="card stat-card warning">
                    <div class="card-icon">📅</div>
                    <h3>Total Appointments</h3>
                    <p style="font-size: 32px; font-weight: bold; color: #f59e0b; margin: 10px 0;">
                        <?php echo $total_appointments; ?></p>
                </div>

                <div class="card stat-card admin">
                    <div class="card-icon">📚</div>
                    <h3>Pending Education</h3>
                    <p style="font-size: 32px; font-weight: bold; color: #1e3c72; margin: 10px 0;">
                        <?php echo $pending_education; ?></p>
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

                <a href="admin_manage_education.php" class="action-card">
                    <div class="action-icon">📚</div>
                    <div class="action-title">Approve Education</div>
                    <div class="action-desc">Review educational content from doctors</div>
                </a>





                <a href="index.php" target="_blank" class="action-card">
                    <div class="action-icon">🌐</div>
                    <div class="action-title">View Website</div>
                    <div class="action-desc">Preview public website</div>
                </a>
            </div>

            <!-- Pending Education Review (Quick Action Section) -->
            <?php if ($pending_education > 0): ?>
                <?php
                $pending_list = $admin_conn->query("SELECT * FROM educational_content WHERE status = 'pending' ORDER BY created_at DESC LIMIT 3");
                ?>
                <h3 style="font-size: 24px; margin: 30px 0 20px; color: #333;">📚 Pending Education Reviews</h3>
                <div class="activity-log" style="border-left: 4px solid #f59e0b;">
                    <?php while ($edu = $pending_list->fetch_assoc()): ?>
                        <div class="log-item">
                            <div>
                                <div class="log-action"><?php echo $edu['icon']; ?>
                                    <?php echo htmlspecialchars($edu['title']); ?></div>
                                <div style="font-size: 13px; color: #666;">By Dr.
                                    <?php echo htmlspecialchars($edu['doctor_name']); ?>
                                    (<?php echo htmlspecialchars($edu['category']); ?>)</div>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <a href="admin_manage_education.php" class="pending-badge"
                                    style="background: #3b82f6; color: white; text-decoration: none; padding: 5px 15px;">Review
                                    Now</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <h3 style="font-size: 24px; margin: 30px 0 20px; color: #333;">📋 Recent Activity</h3>
            <div class="activity-log">
                <?php if ($recent_logs->num_rows > 0): ?>
                    <?php while ($log = $recent_logs->fetch_assoc()): ?>
                        <div class="log-item">
                            <div>
                                <div class="log-action"><?php echo htmlspecialchars($log['action']); ?></div>
                                <div style="font-size: 13px; color: #666;"><?php echo htmlspecialchars($log['description']); ?>
                                </div>
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

    <script src="js/dashboard.js"></script>
    <script src="js/sidebar.js"></script>
</body>
</html>

<?php
$patients_conn->close();
$doctors_conn->close();
$admin_conn->close();
?>
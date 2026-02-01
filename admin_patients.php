<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "human_care_patients");

// Handle patient actions (verify, suspend, delete)
if (isset($_POST['action'])) {
    $patient_id = $_POST['patient_id'];
    $action = $_POST['action'];
    
    if ($action === 'verify') {
        $stmt = $conn->prepare("UPDATE patients SET is_verified = 1, verification_status = 'approved', verified_by = ?, verified_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $_SESSION['admin_id'], $patient_id);
        $stmt->execute();
        
        // Get patient details for email
        $patient_stmt = $conn->prepare("SELECT email, first_name, last_name FROM patients WHERE id = ?");
        $patient_stmt->bind_param("i", $patient_id);
        $patient_stmt->execute();
        $patient_info = $patient_stmt->get_result()->fetch_assoc();
        $patient_stmt->close();
        
        // Send approval email
        $to = $patient_info['email'];
        $subject = "Account Verified - Human Care Hospital";
        $patient_name = $patient_info['first_name'] . ' ' . $patient_info['last_name'];
        
        $email_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Account Verified!</h1>
                </div>
                <div class='content'>
                    <p>Dear $patient_name,</p>
                    
                    <p>Great news! Your patient account has been verified by our admin team.</p>
                    
                    <p>You now have full access to all features of Human Care Hospital platform.</p>
                    
                    <p style='text-align: center;'>
                        <a href='http://localhost/vscode/login.php' class='button'>Login to Dashboard</a>
                    </p>
                    
                    <p><strong>What you can do now:</strong></p>
                    <ul>
                        <li>Book appointments with doctors</li>
                        <li>Access your medical records</li>
                        <li>Manage prescriptions</li>
                        <li>Track your health metrics</li>
                    </ul>
                    
                    <p>Best regards,<br>
                    <strong>Human Care Hospital Team</strong></p>
                </div>
                <div class='footer'>
                    <p>© 2025 Human Care Hospital. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Human Care Hospital <noreply@humancare.com>" . "\r\n";
        
        mail($to, $subject, $email_message, $headers);
        $message = "Patient verified successfully! Email notification sent.";
        
    } elseif ($action === 'suspend') {
        $stmt = $conn->prepare("UPDATE patients SET is_verified = 0, verification_status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $message = "Patient account suspended!";
        
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $message = "Patient account deleted permanently!";
    }
    
    // Log activity
    $admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
    $log_stmt = $admin_conn->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?, ?, ?)");
    $log_action = "patient_$action";
    $log_desc = "Patient ID $patient_id - action: $action";
    $log_stmt->bind_param("iss", $_SESSION['admin_id'], $log_action, $log_desc);
    $log_stmt->execute();
    $admin_conn->close();
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where_clause = "WHERE 1=1";
if (!empty($search)) {
    $where_clause .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}

if ($filter === 'verified') {
    $where_clause .= " AND is_verified = 1";
} elseif ($filter === 'pending') {
    $where_clause .= " AND verification_status = 'pending'";
} elseif ($filter === 'suspended') {
    $where_clause .= " AND is_verified = 0 AND verification_status = 'rejected'";
}

// Get all patients
$all_patients = $conn->query("SELECT * FROM patients $where_clause ORDER BY registered_date DESC");

// Get counts
$total_patients = $conn->query("SELECT COUNT(*) as count FROM patients")->fetch_assoc()['count'];
$verified_patients = $conn->query("SELECT COUNT(*) as count FROM patients WHERE is_verified = 1")->fetch_assoc()['count'];
$pending_patients = $conn->query("SELECT COUNT(*) as count FROM patients WHERE verification_status = 'pending'")->fetch_assoc()['count'];
$suspended_patients = $conn->query("SELECT COUNT(*) as count FROM patients WHERE is_verified = 0 AND verification_status = 'rejected'")->fetch_assoc()['count'];

// Get doctor pending count for sidebar badge
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
$pending_doctors = $doctors_conn->query("SELECT COUNT(*) as count FROM doctors WHERE verification_status = 'pending'")->fetch_assoc()['count'];
$doctors_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - Admin Panel</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-card.total .stat-number { color: #3b82f6; }
        .stat-card.verified .stat-number { color: #10b981; }
        .stat-card.pending .stat-number { color: #f59e0b; }
        .stat-card.suspended .stat-number { color: #ef4444; }

        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .search-filter-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .search-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto auto;
            gap: 15px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn, .clear-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .search-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .clear-btn {
            background: #f3f4f6;
            color: #333;
        }

        .clear-btn:hover {
            background: #e5e7eb;
        }

        .patient-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .patient-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .patient-email {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
        }

        .detail-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-verify {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-suspend {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .btn-suspend:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-view {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }

        .no-results-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .no-results h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .no-results p {
            color: #666;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
        }

        .modal-body {
            margin-bottom: 25px;
            color: #666;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

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

        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }

            .patient-details {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
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
                    <a class="nav-link admin-nav" href="admin_dashboard.php">
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
                    <a class="nav-link admin-nav active" href="admin_patients.php">
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
            <h2>👥 Patient Management</h2>
            <p>Manage patient accounts and verify new registrations</p>
        </div>

        <?php if (isset($message)): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #10b981;">
                ✅ <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card total">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $total_patients; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card verified">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $verified_patients; ?></div>
                <div class="stat-label">Verified Patients</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $pending_patients; ?></div>
                <div class="stat-label">Pending Verification</div>
            </div>
            <div class="stat-card suspended">
                <div class="stat-icon">🚫</div>
                <div class="stat-number"><?php echo $suspended_patients; ?></div>
                <div class="stat-label">Suspended Accounts</div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter-section">
            <form class="search-form" method="GET" action="">
                <div class="form-group">
                    <label>🔍 Search Patients</label>
                    <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label>📊 Filter by Status</label>
                    <select name="filter">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Patients</option>
                        <option value="verified" <?php echo $filter === 'verified' ? 'selected' : ''; ?>>Verified Only</option>
                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                        <option value="suspended" <?php echo $filter === 'suspended' ? 'selected' : ''; ?>>Suspended Only</option>
                    </select>
                </div>
                <button type="submit" class="search-btn">Search</button>
                <a href="admin_patients.php" class="clear-btn">Clear</a>
            </form>
        </div>

        <!-- Patients List -->
        <?php if ($all_patients->num_rows > 0): ?>
            <?php while ($patient = $all_patients->fetch_assoc()): ?>
                <div class="patient-card">
                    <div class="patient-header">
                        <div class="patient-info">
                            <div class="patient-name">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </div>
                            <div class="patient-email">
                                📧 <?php echo htmlspecialchars($patient['email']); ?>
                            </div>
                            <?php if ($patient['is_verified']): ?>
                                <span class="status-badge status-verified">✓ Verified</span>
                            <?php elseif ($patient['verification_status'] === 'pending'): ?>
                                <span class="status-badge status-pending">⏳ Pending</span>
                            <?php else: ?>
                                <span class="status-badge status-suspended">🚫 Suspended</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="patient-details">
                        <div class="detail-item">
                            <span class="detail-label">📞 Phone:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($patient['phone']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">🎂 Date of Birth:</span>
                            <span class="detail-value"><?php echo date('M d, Y', strtotime($patient['dob'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">👤 Gender:</span>
                            <span class="detail-value"><?php echo ucfirst($patient['gender']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">🩸 Blood Group:</span>
                            <span class="detail-value"><?php echo $patient['blood_group'] ?? 'Not specified'; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">📅 Registered:</span>
                            <span class="detail-value"><?php echo date('M d, Y', strtotime($patient['registered_date'])); ?></span>
                        </div>
                        <?php if ($patient['emergency_contact']): ?>
                        <div class="detail-item">
                            <span class="detail-label">🚨 Emergency Contact:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($patient['emergency_contact']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($patient['address']): ?>
                        <div style="margin: 15px 0; padding: 15px; background: #f9fafb; border-radius: 8px;">
                            <strong style="color: #666;">📍 Address:</strong><br>
                            <span style="color: #333;"><?php echo htmlspecialchars($patient['address']); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <?php if (!$patient['is_verified']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                                <input type="hidden" name="action" value="verify">
                                <button type="submit" class="btn btn-verify">✓ Verify Patient</button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($patient['is_verified']): ?>
                            <button class="btn btn-suspend" onclick="confirmAction(<?php echo $patient['id']; ?>, 'suspend', '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>')">
                                🚫 Suspend Account
                            </button>
                        <?php endif; ?>
                        
                        <a href="admin_patient_profile.php?id=<?php echo $patient['id']; ?>" class="btn btn-view">
                            👁️ View Full Profile
                        </a>
                        
                        <button class="btn btn-delete" onclick="confirmAction(<?php echo $patient['id']; ?>, 'delete', '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>')">
                            🗑️ Delete
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <h3>No Patients Found</h3>
                <p>No patients match your search criteria.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">⚠️ Confirm Action</div>
            <div class="modal-body" id="modalMessage"></div>
            <div class="modal-actions">
                <form method="POST" id="confirmForm">
                    <input type="hidden" name="patient_id" id="modal_patient_id">
                    <input type="hidden" name="action" id="modal_action">
                    <button type="submit" class="btn btn-delete">Confirm</button>
                </form>
                <button class="btn" onclick="closeModal()" style="background: #f3f4f6; color: #333;">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        function confirmAction(patientId, action, patientName) {
            const modal = document.getElementById('confirmModal');
            const modalMessage = document.getElementById('modalMessage');
            
            let message = '';
            if (action === 'suspend') {
                message = `Are you sure you want to suspend the account of <strong>${patientName}</strong>? They will not be able to login until reactivated.`;
            } else if (action === 'delete') {
                message = `Are you sure you want to permanently delete <strong>${patientName}</strong>? This action cannot be undone and will remove all patient data.`;
            }
            
            modalMessage.innerHTML = message;
            document.getElementById('modal_patient_id').value = patientId;
            document.getElementById('modal_action').value = action;
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
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
                        <a href='http://localhost/humancare/login.php' class='button'>Login to Dashboard</a>
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
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-number.total { color: #3b82f6; }
        .stat-number.verified { color: #10b981; }
        .stat-number.pending { color: #f59e0b; }
        .stat-number.suspended { color: #ef4444; }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .search-filter-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
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
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .search-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .clear-btn {
            padding: 12px 20px;
            background: #f3f4f6;
            color: #333;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
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
            flex-wrap: wrap;
            gap: 15px;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .patient-email {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
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
            margin: 20px 0;
            padding: 20px;
            background: #f9fafb;
            border-radius: 10px;
        }

        .detail-item {
            font-size: 14px;
        }

        .detail-label {
            color: #666;
            font-weight: 600;
            display: block;
            margin-bottom: 3px;
        }

        .detail-value {
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }

        .btn-verify {
            background: #10b981;
            color: white;
        }

        .btn-verify:hover {
            background: #059669;
        }

        .btn-suspend {
            background: #f59e0b;
            color: white;
        }

        .btn-suspend:hover {
            background: #d97706;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .btn-view {
            background: #3b82f6;
            color: white;
        }

        .btn-view:hover {
            background: #2563eb;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-results-icon {
            font-size: 60px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
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
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .modal-body {
            margin-bottom: 20px;
            color: #666;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
            
            .patient-header {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">🛡️</div>
            ADMIN PANEL
        </div>
        <div class="user-profile">
            <div class="user-avatar">👨‍💼</div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h3>
            </div>
        </div>
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_doctors.php">
                        <span class="nav-icon">👨‍⚕️</span>
                        <span>Manage Doctors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="admin_patients.php">
                        <span class="nav-icon">👥</span>
                        <span>Manage Patients</span>
                    </a>
                </li>
            </ul>
        </nav>
        <form method="post" action="admin_logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <main class="main-content">
        <h1 style="font-size: 32px; margin-bottom: 10px;">👥 Manage Patients</h1>
        <p style="color: #666; margin-bottom: 30px;">View and manage all patient registrations</p>

        <?php if (isset($message)): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number total"><?php echo $total_patients; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number verified"><?php echo $verified_patients; ?></div>
                <div class="stat-label">Verified</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-number pending"><?php echo $pending_patients; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚫</div>
                <div class="stat-number suspended"><?php echo $suspended_patients; ?></div>
                <div class="stat-label">Suspended</div>
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
                        
                        <button class="btn btn-view" onclick="alert('Patient details view - Feature coming soon!')">
                            👁️ View Full Profile
                        </button>
                        
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
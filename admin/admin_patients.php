<?php

session_start();

require_once __DIR__ . '/../config/config.php';



if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}
$active_page = 'patients';

$conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_PATIENTS
);

// Handle patient actions (verify, suspend, delete)
if (isset($_POST['action'])) {
    if (!csrf_validate()) { die('Invalid CSRF token'); }
    $patient_id = intval($_POST['patient_id']);
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
    $admin_conn = new mysqli("sql205.infinityfree.com", "if0_42370337", "6yFxYkbKGy", "if0_42370337_human_care_admin");
    $log_stmt = $admin_conn->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?, ?, ?)");
    $log_action = "patient_$action";
    $log_desc = "Patient ID $patient_id - action: $action";
    $log_stmt->bind_param("iss", $_SESSION['admin_id'], $log_action, $log_desc);
    $log_stmt->execute();
    $admin_conn->close();
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$allowed_filters = ['all', 'verified', 'pending', 'suspended'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters, true)
    ? $_GET['filter']
    : 'all';

// Build safe query with prepared statements
$where_clause = "WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    // Match first name, last name, full name, email, or mobile number.
    // Remove common phone-number formatting from the search term so users
    // can search using digits even if the stored number contains separators.
    $searchParam = '%' . $search . '%';
    $phoneSearch = preg_replace('/[\\s()+.-]/', '', $search);
    $phoneSearchParam = '%' . $phoneSearch . '%';

    $where_clause .= " AND (
        first_name LIKE ?
        OR last_name LIKE ?
        OR CONCAT_WS(' ', first_name, last_name) LIKE ?
        OR email LIKE ?
        OR phone LIKE ?
        OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ?
    )";

    $params = array_merge($params, [
        $searchParam,
        $searchParam,
        $searchParam,
        $searchParam,
        $searchParam,
        $phoneSearchParam
    ]);
    $types .= 'ssssss';
}

if ($filter === 'verified') {
    $where_clause .= " AND is_verified = 1";
} elseif ($filter === 'pending') {
    $where_clause .= " AND verification_status = 'pending'";
} elseif ($filter === 'suspended') {
    $where_clause .= " AND is_verified = 0 AND verification_status = 'rejected'";
}

// Get all patients with prepared statement
$stmt_patients = $conn->prepare("SELECT * FROM patients $where_clause ORDER BY registered_date DESC");
if (!empty($params)) {
    $stmt_patients->bind_param($types, ...$params);
}
$stmt_patients->execute();
$all_patients = $stmt_patients->get_result();
$stmt_patients->close();

// Get counts
$total_patients = $conn->query("SELECT COUNT(*) as count FROM patients")->fetch_assoc()['count'];
$verified_patients = $conn->query("SELECT COUNT(*) as count FROM patients WHERE is_verified = 1")->fetch_assoc()['count'];
$pending_patients = $conn->query("SELECT COUNT(*) as count FROM patients WHERE verification_status = 'pending'")->fetch_assoc()['count'];
$suspended_patients = $conn->query("SELECT COUNT(*) as count FROM patients WHERE is_verified = 0 AND verification_status = 'rejected'")->fetch_assoc()['count'];

// Get doctor pending count for sidebar badge
$doctors_conn = new mysqli(
                DB_HOST,
                DB_USERNAME,
                DB_PASSWORD,
                DB_DOCTORS
            );
$pending_doctors = $doctors_conn->query("SELECT COUNT(*) as count FROM doctors WHERE verification_status = 'pending'")->fetch_assoc()['count'];
$doctors_conn->close();

$admin_conn = new mysqli(
                DB_HOST,
                DB_USERNAME,
                DB_PASSWORD,
                DB_ADMIN
            );
$pending_education = $admin_conn->query("SELECT COUNT(*) as count FROM educational_content WHERE status = 'pending'")->fetch_assoc()['count'];
$admin_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - Admin Panel</title>
    
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/admin_doctors.css">
    <link rel="stylesheet" href="styles/admin_patient.css">

   
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="hero-banner" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
            <h2>👥 Patient Management</h2>
            <p>Manage patient accounts and verify new registrations</p>
        </div>

        <?php if (isset($message)): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #10b981;">
                ✅ <?php echo htmlspecialchars($message); ?>
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
                    <select name="filter" onchange="this.form.submit()">
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
                                <?php echo csrf_field(); ?>
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
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="patient_id" id="modal_patient_id">
                    <input type="hidden" name="action" id="modal_action">
                    <button type="submit" class="btn btn-delete">Confirm</button>
                </form>
                <button class="btn" onclick="closeModal()" style="background: #f3f4f6; color: #333;">Cancel</button>
            </div>
        </div>
    </div>

    <script>


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
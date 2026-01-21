<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "human_care_doctors");

// Handle approval/rejection
if (isset($_POST['action'])) {
    $doctor_id = $_POST['doctor_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Get doctor details for email
        $doctor_stmt = $conn->prepare("SELECT email, first_name, last_name FROM doctors WHERE id = ?");
        $doctor_stmt->bind_param("i", $doctor_id);
        $doctor_stmt->execute();
        $doctor_info = $doctor_stmt->get_result()->fetch_assoc();
        $doctor_stmt->close();
        
        // Update doctor status
        $stmt = $conn->prepare("UPDATE doctors SET is_verified = 1, verification_status = 'approved', verified_by = ?, verified_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $_SESSION['admin_id'], $doctor_id);
        $stmt->execute();
        
        // Send approval email
        $to = $doctor_info['email'];
        $subject = "Account Approved - Human Care Hospital";
        $doctor_name = $doctor_info['first_name'] . ' ' . $doctor_info['last_name'];
        
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
                .credentials { background: white; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Account Approved!</h1>
                </div>
                <div class='content'>
                    <p>Dear Dr. $doctor_name,</p>
                    
                    <p>Congratulations! Your doctor account has been approved by our admin team.</p>
                    
                    <p>You can now login to your dashboard and start providing medical services through our platform.</p>
                    
                    <div class='credentials'>
                        <strong>Your Login Credentials:</strong><br>
                        Email: $to<br>
                        Password: (The password you set during registration)
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='http://localhost/humancare/login.php' class='button'>Login Now</a>
                    </p>
                    
                    <p><strong>Next Steps:</strong></p>
                    <ul>
                        <li>Login to your doctor dashboard</li>
                        <li>Complete your profile information</li>
                        <li>Set your availability schedule</li>
                        <li>Start accepting patient appointments</li>
                    </ul>
                    
                    <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                    
                    <p>Best regards,<br>
                    <strong>Human Care Hospital Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>© 2025 Human Care Hospital. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Human Care Hospital <noreply@humancare.com>" . "\r\n";
        
        // Send email
        if (mail($to, $subject, $email_message, $headers)) {
            $message = "Doctor approved successfully! Email notification sent to " . $to;
        } else {
            $message = "Doctor approved successfully! (Email notification failed to send)";
        }
        
    } elseif ($action === 'reject') {
        $reason = $_POST['reason'] ?? 'Not specified';
        
        // Get doctor details for email
        $doctor_stmt = $conn->prepare("SELECT email, first_name, last_name FROM doctors WHERE id = ?");
        $doctor_stmt->bind_param("i", $doctor_id);
        $doctor_stmt->execute();
        $doctor_info = $doctor_stmt->get_result()->fetch_assoc();
        $doctor_stmt->close();
        
        // Update doctor status
        $stmt = $conn->prepare("UPDATE doctors SET is_verified = 0, verification_status = 'rejected', rejection_reason = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $reason, $_SESSION['admin_id'], $doctor_id);
        $stmt->execute();
        
        // Send rejection email
        $to = $doctor_info['email'];
        $subject = "Account Verification Update - Human Care Hospital";
        $doctor_name = $doctor_info['first_name'] . ' ' . $doctor_info['last_name'];
        
        $email_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .reason-box { background: white; padding: 15px; border-left: 4px solid #ff6b6b; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Account Verification Update</h1>
                </div>
                <div class='content'>
                    <p>Dear Dr. $doctor_name,</p>
                    
                    <p>Thank you for your interest in joining Human Care Hospital.</p>
                    
                    <p>After careful review, we regret to inform you that your doctor account application has not been approved at this time.</p>
                    
                    <div class='reason-box'>
                        <strong>Reason:</strong><br>
                        $reason
                    </div>
                    
                    <p><strong>What you can do:</strong></p>
                    <ul>
                        <li>Review the rejection reason carefully</li>
                        <li>Contact our support team for clarification</li>
                        <li>Reapply with updated/corrected information</li>
                    </ul>
                    
                    <p>If you believe this decision was made in error or if you have additional documentation to support your application, please contact our support team at support@humancare.com</p>
                    
                    <p>Best regards,<br>
                    <strong>Human Care Hospital Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
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
        $message = "Doctor rejected! Email notification sent.";
        
    } elseif ($action === 'delete') {
        // Get doctor details before deletion
        $doctor_stmt = $conn->prepare("SELECT first_name, last_name, email, specialty FROM doctors WHERE id = ?");
        $doctor_stmt->bind_param("i", $doctor_id);
        $doctor_stmt->execute();
        $doctor_info = $doctor_stmt->get_result()->fetch_assoc();
        $doctor_stmt->close();
        
        // Soft delete - mark as deleted instead of actually deleting
        $stmt = $conn->prepare("UPDATE doctors SET is_deleted = 1, deleted_by = ?, deleted_at = NOW(), is_verified = 0, verification_status = 'deleted' WHERE id = ?");
        $stmt->bind_param("ii", $_SESSION['admin_id'], $doctor_id);
        $stmt->execute();
        
        // Cancel all pending appointments
        $cancel_stmt = $conn->prepare("UPDATE doctor_appointments SET status = 'cancelled', admin_notes = 'Doctor has left the hospital' WHERE doctor_id = ? AND status = 'pending'");
        $cancel_stmt->bind_param("i", $doctor_id);
        $cancel_stmt->execute();
        
        // Send notification email to doctor
        $to = $doctor_info['email'];
        $subject = "Account Deactivated - Human Care Hospital";
        $doctor_name = $doctor_info['first_name'] . ' ' . $doctor_info['last_name'];
        
        $email_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #333; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Account Deactivated</h1>
                </div>
                <div class='content'>
                    <p>Dear Dr. $doctor_name,</p>
                    
                    <p>Your doctor account with Human Care Hospital has been deactivated.</p>
                    
                    <p>Your profile has been removed from our public listing and you will no longer be able to access the doctor dashboard.</p>
                    
                    <p>If you have any questions or concerns regarding this action, please contact our administration team.</p>
                    
                    <p>Thank you for your service at Human Care Hospital.</p>
                    
                    <p>Best regards,<br>
                    <strong>Human Care Hospital Administration</strong></p>
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
        
        $message = "Doctor account deleted successfully! Dr. " . $doctor_name . " (" . $doctor_info['specialty'] . ") has been removed from the system.";
    }
    
    // Log activity
    $admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
    $log_stmt = $admin_conn->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?, ?, ?)");
    $log_action = "doctor_$action";
    $log_desc = "Doctor ID $doctor_id was $action" . "d";
    $log_stmt->bind_param("iss", $_SESSION['admin_id'], $log_action, $log_desc);
    $log_stmt->execute();
    $admin_conn->close();
}

// Get all doctors
$all_doctors = $conn->query("SELECT * FROM doctors WHERE is_deleted = 0 OR is_deleted IS NULL ORDER BY registered_date DESC");
$pending_doctors = $conn->query("SELECT * FROM doctors WHERE verification_status = 'pending' AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY registered_date DESC");
$approved_doctors = $conn->query("SELECT * FROM doctors WHERE verification_status = 'approved' AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY registered_date DESC");
$rejected_doctors = $conn->query("SELECT * FROM doctors WHERE verification_status = 'rejected' AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY registered_date DESC");
$deleted_doctors = $conn->query("SELECT * FROM doctors WHERE is_deleted = 1 ORDER BY deleted_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Admin Panel</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 24px;
            background: #f3f4f6;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .tab.active {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }

        .doctor-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .doctor-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .doctor-info {
            flex: 1;
        }

        .doctor-name {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .doctor-specialty {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
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

        .doctor-details {
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
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-approve {
            background: #10b981;
            color: white;
        }

        .btn-approve:hover {
            background: #059669;
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
        }

        .btn-view {
            background: #3b82f6;
            color: white;
        }

        .btn-view:hover {
            background: #2563eb;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
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
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            margin-bottom: 15px;
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
                    <a class="nav-link active" href="admin_doctors.php">
                        <span class="nav-icon">👨‍⚕️</span>
                        <span>Manage Doctors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_patients.php">
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
        <h1 style="font-size: 32px; margin-bottom: 10px;">👨‍⚕️ Manage Doctors</h1>
        <p style="color: #666; margin-bottom: 30px;">Verify and manage doctor registrations</p>

        <?php if (isset($message)): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('pending')">
                Pending (<?php echo $pending_doctors->num_rows; ?>)
            </button>
            <button class="tab" onclick="showTab('approved')">
                Approved (<?php echo $approved_doctors->num_rows; ?>)
            </button>
            <button class="tab" onclick="showTab('rejected')">
                Rejected (<?php echo $rejected_doctors->num_rows; ?>)
            </button>
            <button class="tab" onclick="showTab('deleted')">
                Deleted (<?php echo $deleted_doctors->num_rows; ?>)
            </button>
            <button class="tab" onclick="showTab('all')">
                All Doctors (<?php echo $all_doctors->num_rows; ?>)
            </button>
        </div>

        <!-- Pending Doctors -->
        <div id="pending" class="tab-content active">
            <?php if ($pending_doctors->num_rows > 0): ?>
                <?php $pending_doctors->data_seek(0); ?>
                <?php while ($doctor = $pending_doctors->fetch_assoc()): ?>
                    <div class="doctor-card">
                        <div class="doctor-header">
                            <div class="doctor-info">
                                <div class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                                <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                                <span class="status-badge status-pending">⏳ Pending Verification</span>
                            </div>
                        </div>
                        <div class="doctor-details">
                            <div class="detail-item">
                                <span class="detail-label">📧 Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($doctor['email']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">📞 Phone:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($doctor['phone']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">🎓 Qualification:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($doctor['qualification']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">⭐ Experience:</span>
                                <span class="detail-value"><?php echo $doctor['experience_years']; ?> years</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">🆔 License:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($doctor['license_number']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">💰 Fee:</span>
                                <span class="detail-value">₹<?php echo number_format($doctor['consultation_fee']); ?></span>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-approve">✓ Approve & Send Email</button>
                            </form>
                            <button class="btn btn-reject" onclick="showRejectModal(<?php echo $doctor['id']; ?>)">✗ Reject</button>
                            <a href="admin_edit_doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-view">✏️ Edit Details</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #999;">No pending doctor verifications</p>
            <?php endif; ?>
        </div>

        <!-- Approved Doctors -->
        <div id="approved" class="tab-content">
            <?php if ($approved_doctors->num_rows > 0): ?>
                <?php $approved_doctors->data_seek(0); ?>
                <?php while ($doctor = $approved_doctors->fetch_assoc()): ?>
                    <div class="doctor-card">
                        <div class="doctor-header">
                            <div class="doctor-info">
                                <div class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                                <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                                <span class="status-badge status-approved">✓ Approved</span>
                            </div>
                        </div>
                        <div class="doctor-details">
                            <div class="detail-item">
                                <span class="detail-label">📧 Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($doctor['email']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">🎓 Qualification:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($doctor['qualification']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">⭐ Experience:</span>
                                <span class="detail-value"><?php echo $doctor['experience_years']; ?> years</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">✓ Verified:</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($doctor['verified_at'])); ?></span>
                            </div>
                        </div>
                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                            <a href="admin_edit_doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-view">✏️ Edit Details</a>
                            <button class="btn btn-delete" onclick="confirmDelete(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>')">
                                🗑️ Delete Doctor
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #999;">No approved doctors</p>
            <?php endif; ?>
        </div>

        <!-- Rejected Doctors -->
        <div id="rejected" class="tab-content">
            <?php if ($rejected_doctors->num_rows > 0): ?>
                <?php $rejected_doctors->data_seek(0); ?>
                <?php while ($doctor = $rejected_doctors->fetch_assoc()): ?>
                    <div class="doctor-card">
                        <div class="doctor-header">
                            <div class="doctor-info">
                                <div class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                                <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                                <span class="status-badge status-rejected">✗ Rejected</span>
                            </div>
                        </div>
                        <?php if ($doctor['rejection_reason']): ?>
                            <p style="color: #991b1b; margin: 10px 0;"><strong>Reason:</strong> <?php echo htmlspecialchars($doctor['rejection_reason']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #999;">No rejected doctors</p>
            <?php endif; ?>
        </div>

        <!-- All Doctors -->
        <div id="all" class="tab-content">
            <?php if ($all_doctors->num_rows > 0): ?>
                <?php $all_doctors->data_seek(0); ?>
                <?php while ($doctor = $all_doctors->fetch_assoc()): ?>
                    <div class="doctor-card">
                        <div class="doctor-header">
                            <div class="doctor-info">
                                <div class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                                <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                                <span class="status-badge status-<?php echo $doctor['verification_status']; ?>">
                                    <?php echo ucfirst($doctor['verification_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Deleted Doctors -->
        <div id="deleted" class="tab-content">
            <?php if ($deleted_doctors->num_rows > 0): ?>
                <?php while ($doctor = $deleted_doctors->fetch_assoc()): ?>
                    <div class="doctor-card" style="opacity: 0.7; border-left: 4px solid #999;">
                        <div class="doctor-header">
                            <div class="doctor-info">
                                <div class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                                <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                                <span class="status-badge" style="background: #f3f4f6; color: #666;">🗑️ Deleted</span>
                            </div>
                        </div>
                        <div class="doctor-details">
                            <div class="detail-item">
                                <span class="detail-label">📧 Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($doctor['email']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">🗑️ Deleted On:</span>
                                <span class="detail-value"><?php echo date('M d, Y h:i A', strtotime($doctor['deleted_at'])); ?></span>
                            </div>
                        </div>
                        <p style="color: #666; margin-top: 10px; font-size: 13px;">
                            ℹ️ This doctor has been removed from the system and is no longer visible to patients.
                        </p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #999;">No deleted doctors</p>
            <?php endif; ?>
        </div>
    </main>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Reject Doctor Application</div>
            <form method="POST">
                <input type="hidden" name="doctor_id" id="reject_doctor_id">
                <input type="hidden" name="action" value="reject">
                <label>Reason for rejection:</label>
                <textarea name="reason" rows="4" required placeholder="Enter reason..."></textarea>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-reject">Reject & Send Email</button>
                    <button type="button" class="btn" onclick="closeRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }

        function showRejectModal(doctorId) {
            document.getElementById('reject_doctor_id').value = doctorId;
            document.getElementById('rejectModal').classList.add('active');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
        }

        function confirmDelete(doctorId, doctorName) {
            document.getElementById('delete_doctor_id').value = doctorId;
            document.getElementById('deleteMessage').innerHTML = 
                `Are you sure you want to delete <strong>Dr. ${doctorName}</strong>?`;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
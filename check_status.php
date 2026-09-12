<?php

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/database.php';

try {

    $conn = Database::getConnection('doctors');

} catch (Exception $e) {

    error_log("Check status database connection failed: " . $e->getMessage());

    die("Database connection failed.");
}

$status_message = "";
$doctor_info = null;
$show_status = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT first_name, last_name, email, specialty, verification_status, rejection_reason, registered_date, verified_at FROM doctors WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $doctor_info = $result->fetch_assoc();
            $show_status = true;
        } else {
            $status_message = "No registration found with this email address.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Verification Status - Human Care</title>
    <link rel="stylesheet" href="styles/check_status.css">
    
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Check Verification Status</h1>
            <p>Enter your email to check your doctor registration status</p>
        </div>

        <div class="content">
            <?php if ($status_message): ?>
                <div class="error-message"><?php echo $status_message; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Your Registered Email</label>
                    <input type="email" id="email" name="email" placeholder="doctor@example.com" required>
                </div>

                <button type="submit" class="check-btn">Check Status</button>
            </form>

            <?php if ($show_status && $doctor_info): ?>
                <?php
                $status = $doctor_info['verification_status'];
                $status_class = "status-" . $status;
                ?>

                <div class="status-card <?php echo $status_class; ?>">
                    <?php if ($status === 'pending'): ?>
                        <div class="status-icon">⏳</div>
                        <div class="status-title">Verification Pending</div>
                        <div class="message-box">
                            <p><strong>Dear Dr. <?php echo htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']); ?>,</strong></p>
                            <p>Your doctor registration application is currently under review by our admin team.</p>
                            <p><strong>What happens next?</strong></p>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li>Admin will verify your credentials and license</li>
                                <li>You will receive an email notification once approved</li>
                                <li>After approval, you can login with your credentials</li>
                            </ul>
                            <p><strong>Expected time:</strong> 24-48 hours</p>
                        </div>

                    <?php elseif ($status === 'approved'): ?>
                        <div class="status-icon">✅</div>
                        <div class="status-title">Account Approved!</div>
                        <div class="message-box">
                            <p><strong>Congratulations Dr. <?php echo htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']); ?>!</strong></p>
                            <p>Your doctor account has been approved and verified. You can now login to your dashboard.</p>
                            <p><strong>Approved on:</strong> <?php echo date('F d, Y', strtotime($doctor_info['verified_at'])); ?></p>
                        </div>
                        <div class="action-buttons">
                            <a href="login.php" class="action-btn btn-primary">Login Now</a>
                            <a href="index.php" class="action-btn btn-secondary">Go to Home</a>
                        </div>

                    <?php elseif ($status === 'rejected'): ?>
                        <div class="status-icon">❌</div>
                        <div class="status-title">Application Rejected</div>
                        <div class="message-box">
                            <p><strong>Dear Dr. <?php echo htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']); ?>,</strong></p>
                            <p>Unfortunately, your doctor registration application has been rejected.</p>
                            <p><strong>Reason:</strong></p>
                            <p style="background: white; padding: 10px; border-radius: 5px; margin: 10px 0;">
                                <?php echo htmlspecialchars($doctor_info['rejection_reason'] ?? 'Not specified'); ?>
                            </p>
                            <p><strong>What you can do:</strong></p>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li>Contact support for clarification</li>
                                <li>Reapply with corrected information</li>
                                <li>Provide additional documentation if needed</li>
                            </ul>
                        </div>
                        <div class="action-buttons">
                            <a href="contact.php" class="action-btn btn-primary">Contact Support</a>
                            <a href="register.php" class="action-btn btn-secondary">Register Again</a>
                        </div>
                    <?php endif; ?>

                    <div class="status-info">
                        <div class="info-item">
                            <span class="info-label">Doctor Name:</span>
                            <span class="info-value">Dr. <?php echo htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($doctor_info['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Specialty:</span>
                            <span class="info-value"><?php echo htmlspecialchars($doctor_info['specialty']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Registered On:</span>
                            <span class="info-value"><?php echo date('F d, Y', strtotime($doctor_info['registered_date'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Current Status:</span>
                            <span class="info-value"><?php echo ucfirst($status); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="back-link">
                <a href="login.php">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
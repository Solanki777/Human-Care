<?php
session_start();

$conn = new mysqli("localhost", "root", "", "human_care_doctors");

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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .check-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .check-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .status-card {
            margin-top: 30px;
            padding: 30px;
            border-radius: 15px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-pending {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
        }

        .status-approved {
            background: #d1fae5;
            border-left: 4px solid #10b981;
        }

        .status-rejected {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
        }

        .status-icon {
            font-size: 50px;
            text-align: center;
            margin-bottom: 20px;
        }

        .status-title {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
        }

        .status-pending .status-title {
            color: #92400e;
        }

        .status-approved .status-title {
            color: #065f46;
        }

        .status-rejected .status-title {
            color: #991b1b;
        }

        .status-info {
            margin-top: 20px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .info-label {
            font-weight: 600;
            color: #666;
        }

        .info-value {
            color: #333;
            font-weight: 500;
        }

        .message-box {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .action-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }
    </style>
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
                <a href="index.php">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
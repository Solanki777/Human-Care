<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../classes/msg.php';


/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| User & Database
|--------------------------------------------------------------------------
*/

$userId = $_SESSION['user_id'];

$conn = Database::getConnection('admin');

$chat = new Chat();


/*
|--------------------------------------------------------------------------
| Get Appointments
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        a.*,
        cr.id AS chat_room_id,
        cr.patient_unread_count
    FROM appointments a
    LEFT JOIN chat_rooms cr
        ON cr.appointment_id = a.id
    WHERE a.patient_id = ?
    ORDER BY a.created_at DESC
");

if (!$stmt) {
    die("Database query preparation failed: " . $conn->error);
}


$stmt->bind_param("i", $userId);

$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Get Total Unread Messages
|--------------------------------------------------------------------------
*/

$unreadCount = $chat->getUnreadCount(
    $userId,
    'patient'
);

?>
<!DOCTYPE html>
<html>

<head>
    <title>My Appointments - Human Care</title>
    <!-- Common CSS -->
    <link rel="stylesheet" href="styles/main.css">

    <link rel="stylesheet" href="styles/patient_appointment.css">

    <!-- Sidebar CSS -->
    <link rel="stylesheet" href="styles/sidebar.css">

    
    <script src="scripts/main.js"></script>
  
    
</head>

<body>
    <!-- Menu Toggle Button -->
    <?php $active_page = 'appointments'; ?>
    <?php include 'includes/public_sidebar.php'; ?>

    <div style="max-width:900px; margin:auto; padding: 30px 20px;">
        <div class="page-title">
            <h2>📋 My Appointments</h2>
            <p>View and manage all your appointments</p>
        </div>

        <?php if ($result->num_rows === 0): ?>
            <div class="no-appointments">
                <div class="no-appointments-icon">📅</div>
                <h3 style="font-size: 20px; color: #666; margin-bottom: 10px;">No Appointments Yet</h3>
                <p style="color: #999; margin-bottom: 20px;">You haven't booked any appointments.</p>
                <a href="book_appointment.php" style="display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    Book Your First Appointment
                </a>
            </div>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="appointment-card status-<?= $row['status'] ?>">
                    <div class="appointment-header">
                        <div>
                            <div class="doctor-name">👨‍⚕️ Dr. <?= htmlspecialchars($row['doctor_name']) ?></div>
                            <span class="specialty-tag"><?= htmlspecialchars($row['doctor_specialty']) ?></span>
                        </div>
                        <span class="status-badge status-<?= $row['status'] ?>">
                            <?php 
                                echo match($row['status']) {
                                    'approved' => '✅ Confirmed',
                                    'pending' => '⏳ Pending',
                                    'rejected' => '❌ Rejected',
                                    'cancelled' => '🚫 Cancelled',
                                    'completed' => '✔️ Completed',
                                    default => ucfirst($row['status'])
                                };
                            ?>
                        </span>
                    </div>

                    <div class="appointment-details">
                        <div class="detail-item">
                            <span class="detail-icon">📅</span>
                            <div>
                                <div class="detail-label">Date</div>
                                <div><?= date('l, F j, Y', strtotime($row['appointment_date'])) ?></div>
                            </div>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">🕐</span>
                            <div>
                                <div class="detail-label">Time</div>
                                <div><?= date('h:i A', strtotime($row['appointment_time'])) ?></div>
                            </div>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">💼</span>
                            <div>
                                <div class="detail-label">Type</div>
                                <div><?= ucfirst($row['consultation_type']) ?></div>
                            </div>
                        </div>
                        <div class="detail-item">
                            <span class="detail-icon">📝</span>
                            <div>
                                <div class="detail-label">Booked On</div>
                                <div><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="reason-box">
                        <strong>🩺 Reason for Visit:</strong>
                        <p><?= htmlspecialchars($row['reason_for_visit']) ?></p>
                    </div>

                    <?php if ($row['status'] === 'approved'): ?>
                    
                        <div class="appointment-actions">
                            <button onclick="openChatForAppointment(<?= $row['id'] ?>)" class="chat-btn" style="border:none; cursor:pointer;">
                                <span class="chat-btn-icon">💬</span>
                                <span>Chat with Doctor</span>
                                <?php if ($row['patient_unread_count'] > 0): ?>
                                    <span class="unread-badge"><?= $row['patient_unread_count'] ?></span>
                                <?php endif; ?>
                            </button>
                        </div>

                        <div class="cancellation-alert" style="background: #d1fae5; border-left-color: #10b981;">
                            <strong style="color: #065f46;">✅ Appointment Confirmed</strong>
                            <p style="color: #065f46;">Your appointment has been confirmed! You can now chat with your doctor using the button above. Please arrive 10 minutes early and bring your ID and any previous medical records.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($row['status'] === 'pending'): ?>
                        <div class="cancellation-alert">
                            <strong>⏳ Awaiting Admin Approval</strong>
                            <p>Your appointment request is currently under review by our admin team. You will be notified via email once it's approved. Chat will be enabled after approval.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($row['status'] === 'cancelled' && $row['rejection_reason']): ?>
                        <div class="cancellation-alert cancelled">
                            <strong>🚫 Appointment Cancelled by Admin</strong>
                            <p><strong>Reason:</strong> <?= htmlspecialchars($row['rejection_reason']) ?></p>
                            <p style="margin-top: 8px;">We apologize for the inconvenience. Please feel free to book another appointment or contact our support team for assistance.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($row['status'] === 'rejected' && $row['rejection_reason']): ?>
                        <div class="cancellation-alert rejected">
                            <strong>❌ Appointment Request Rejected</strong>
                            <p><strong>Reason:</strong> <?= htmlspecialchars($row['rejection_reason']) ?></p>
                            <p style="margin-top: 8px;">You may try booking a different time slot or choose another doctor. If you have questions, please contact our support team.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($row['status'] === 'completed'): ?>
                        <?php if ($row['chat_room_id']): ?>
                            <div class="appointment-actions">
                                <button onclick="openChatForAppointment(<?= $row['id'] ?>)" class="chat-btn" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border:none; cursor:pointer;">
                                    <span class="chat-btn-icon">💬</span>
                                    <span>View Chat History</span>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="cancellation-alert" style="background: #dbeafe; border-left-color: #3b82f6;">
                            <strong style="color: #1e40af;">✔️ Consultation Completed</strong>
                            <p style="color: #1e40af;">This appointment has been completed. Thank you for choosing Human Care. We hope you're feeling better!</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <script src="js/patent_appointment.js"></script>
    

    
</body>
    

</html>
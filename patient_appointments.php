<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'classes/Chat.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$conn = Database::getConnection('admin');
$chat = new Chat();

// Get appointments with chat room info
$stmt = $conn->prepare("
    SELECT 
        a.*,
        cr.id as chat_room_id,
        cr.patient_unread_count
    FROM appointments a
    LEFT JOIN chat_rooms cr ON cr.appointment_id = a.id
    WHERE a.patient_id = ?
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

// Get total unread messages
$unreadCount = $chat->getUnreadCount($userId, 'patient');
?>
<!DOCTYPE html>
<html>

<head>
    <title>My Appointments - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
    <script src="scripts/main.js"></script>
    <style>
        .appointment-card {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-left: 5px solid #667eea;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .appointment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .appointment-card.status-approved {
            border-left-color: #10b981;
        }

        .appointment-card.status-rejected {
            border-left-color: #ef4444;
        }

        .appointment-card.status-cancelled {
            border-left-color: #8b5cf6;
        }

        .appointment-card.status-pending {
            border-left-color: #f59e0b;
        }

        .appointment-card.status-completed {
            border-left-color: #3b82f6;
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .doctor-name {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-cancelled {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }

        .detail-icon {
            font-size: 18px;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
        }

        .reason-box {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .reason-box strong {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }

        .reason-box p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .cancellation-alert {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .cancellation-alert.cancelled {
            background: #f3e8ff;
            border-left-color: #8b5cf6;
        }

        .cancellation-alert.rejected {
            background: #fee2e2;
            border-left-color: #ef4444;
        }

        .cancellation-alert strong {
            display: block;
            margin-bottom: 8px;
            color: #991b1b;
            font-size: 14px;
        }

        .cancellation-alert.cancelled strong {
            color: #6b21a8;
        }

        .cancellation-alert p {
            color: #92400e;
            font-size: 14px;
            margin: 0;
            line-height: 1.6;
        }

        .cancellation-alert.cancelled p {
            color: #6b21a8;
        }

        .cancellation-alert.rejected p {
            color: #991b1b;
        }

        /* Chat Button Styles */
        .appointment-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .chat-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
        }

        .chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .chat-btn-icon {
            font-size: 18px;
        }

        .unread-badge {
            background: #ff4757;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
        }

        .no-appointments {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .no-appointments-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .page-title {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .specialty-tag {
            display: inline-block;
            padding: 4px 12px;
            background: #e0e7ff;
            color: #667eea;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .appointment-details {
                grid-template-columns: 1fr;
            }

            .appointment-actions {
                flex-direction: column;
            }

            .chat-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <!-- Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle">☰</button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">❤️</div>
            HUMAN CARE
        </div>

        <ul class="sidebar-nav">
            <li><a href="index.php">
                    <span class="nav-icon">🏠</span>
                    <span>Home</span>
                </a></li>
            <li><a href="doctors.php">
                    <span class="nav-icon">👨‍⚕️</span>
                    <span>Our Doctors</span>
                </a></li>
            <li><a href="education.php">
                    <span class="nav-icon">📚</span>
                    <span>Learning Center</span>
                </a></li>
            <li>
                <a href="book_appointment.php">
                    <span class="nav-icon">📅</span>
                    <span>Book Appointment</span>
                </a>
            </li>
            <?php if (isset($_SESSION['user_name'])): ?>
                <li>
                    <a href="patient_appointments.php" class="active">
                        <span class="nav-icon">📋</span>
                        <span>My Appointments</span>
                    </a>
                </li>
                <li>
                    <a href="patient_chat.php">
                        <span class="nav-icon">💬</span>
                        <span>My Chats</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="unread-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>
            <li>
                <a href="contact.php">
                    <span class="nav-icon">📞</span>
                    <span>Contact & Support</span>
                </a>
            </li>
        </ul>
        <div class="user-box-sidebar">
            <?php if (isset($_SESSION['user_name'])): ?>
                <div class="user-name-sidebar">
                    👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
                <a href="patient_appointments.php" class="login-btn-sidebar">My Dashboard</a>
                <a href="logout.php" class="logout-btn-sidebar">Logout</a>
            <?php else: ?>
                <a href="login.php" class="login-btn-sidebar">Login / Sign Up</a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

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
                            <a href="patient_chat.php?room_id=<?= $row['chat_room_id'] ?>" class="chat-btn">
                                <span class="chat-btn-icon">💬</span>
                                <span>Chat with Doctor</span>
                                <?php if ($row['patient_unread_count'] > 0): ?>
                                    <span class="unread-badge"><?= $row['patient_unread_count'] ?></span>
                                <?php endif; ?>
                            </a>
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
                                <a href="patient_chat.php?room_id=<?= $row['chat_room_id'] ?>" class="chat-btn" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                    <span class="chat-btn-icon">💬</span>
                                    <span>View Chat History</span>
                                </a>
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

    <!-- JavaScript for Sidebar Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                if (sidebar && overlay) {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                }
            }

            if (menuToggle) {
                menuToggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            if (overlay) {
                overlay.addEventListener('click', function () {
                    toggleSidebar();
                });
            }

            if (sidebar) {
                sidebar.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }

            document.addEventListener('click', function (event) {
                if (sidebar && menuToggle &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    !menuToggle.contains(event.target)) {
                    toggleSidebar();
                }
            });
        });
    </script>

</body>

</html>
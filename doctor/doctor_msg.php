<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}
$active_page = 'chat'; // Change based on page

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/msg.php';

$doctorId = $_SESSION['user_id'];
$doctor_id = $doctorId;

$doctorName = $_SESSION['user_name'];
$chat = new Chat();

// Get selected chat room ID from URL
$selectedRoomId = isset($_GET['room_id']) ? intval($_GET['room_id']) : null;

// Get every patient this doctor has an approved appointment with - this
// includes patients who haven't messaged yet (no chat room), so the
// doctor can start a conversation from this page too, not only reply
// to patients who already messaged first.
$contacts = $chat->getApprovedContacts($doctorId, 'doctor');

// Get total unread count
$unreadCount = $chat->getUnreadCount($doctorId, 'doctor');

// If a room is selected, verify access and get details
$selectedRoom = null;
if ($selectedRoomId) {
    if ($chat->hasAccess($selectedRoomId, $doctorId, 'doctor')) {
        $selectedRoom = $chat->getChatRoom($selectedRoomId);
        // Mark messages as read when opening chat
        $chat->markAsRead($selectedRoomId, 'doctor');
    } else {
        $selectedRoomId = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Chats - Human Care</title>

    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/msg.css">
    
</head>
<body>

    <?php include 'includes/doctor_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="chat-page-container">
        <div class="page-header">
            <h1>💬 Patient Chats</h1>
            <p>Communicate with your patients securely</p>
        </div>

        <div class="chat-container">
            <!-- Chat List Sidebar -->
            <div class="chat-list-container" id="chatListContainer">
                <div class="chat-list-header">
                    <h3>Patients</h3>
                    <?php if ($unreadCount > 0): ?>
                        <span class="total-unread-badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </div>

                <div class="chat-search">
                    <input type="text" placeholder="🔍 Search patients..." id="chatSearch">
                </div>

                <div class="chat-list" id="chatList">
                    <?php if (empty($contacts)): ?>
                        <div class="chat-list-empty">
                            <p>No patient conversations yet</p>
                            <small>Patients with approved appointments will appear here</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="chat-list-item <?php echo ($contact['chat_room_id'] && $selectedRoomId == $contact['chat_room_id']) ? 'active' : ''; ?>"
                                onclick="openChat(<?php echo $contact['chat_room_id'] ? (int)$contact['chat_room_id'] : 'null'; ?>, <?php echo (int)$contact['appointment_id']; ?>)">
                                <div class="chat-avatar">👤</div>
                                <div class="chat-preview">
                                    <div class="chat-preview-header">
                                        <h4><?php echo htmlspecialchars($contact['contact_name']); ?></h4>
                                        <span class="chat-time">
                                            <?php
                                            if (!empty($contact['last_message_time'])) {
                                                echo date('M j, g:i A', strtotime($contact['last_message_time']));
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <p class="last-message">
                                        <?php
                                        if (!empty($contact['last_message'])) {
                                            echo htmlspecialchars(substr($contact['last_message'], 0, 50)) . '...';
                                        } else {
                                            echo 'Tap to start chatting';
                                        }
                                        ?>
                                    </p>
                                </div>
                                <?php if ($contact['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $contact['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Window -->
            <div class="chat-window-container" id="chatWindow">
                <?php if (!$selectedRoom): ?>
                    <div class="chat-window">
                        <div class="chat-empty-state">
                            <div class="empty-icon">💬</div>
                            <h3>Select a Patient</h3>
                            <p>Choose a patient from the list to start chatting</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="active-chat">
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="chat-header-left">
                                <button class="back-btn" onclick="closeChat()">←</button>
                                <div class="chat-avatar">👤</div>
                                <div class="chat-info">
                                    <h4><?php echo htmlspecialchars($selectedRoom['patient_name']); ?></h4>
                                    <span class="chat-status" id="typingStatus">Online</span>
                                </div>
                            </div>
                            <div class="chat-header-right">
                                <button class="chat-action-btn" onclick="refreshMessages()" title="Refresh">🔄</button>
                            </div>
                        </div>

                        <!-- Messages Container -->
                        <div class="messages-container" id="messagesContainer">
                            <div class="messages-loading" id="messagesLoading">
                                <div class="spinner"></div>
                            </div>
                        </div>

                        <!-- Typing Indicator -->
                        <div class="typing-indicator" id="typingIndicator" style="display: none;">
                            <div class="typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            <span>Patient is typing...</span>
                        </div>

                        <!-- Message Input -->
                        <div class="message-input-container">
                            <form id="messageForm">
                                <textarea id="messageInput" placeholder="Type your message to patient..." rows="1"
                                    required></textarea>
                                <button type="submit" class="send-btn">
                                    <span>Send</span>
                                    <span>📤</span>
                                </button>
                            </form>
                            <div class="message-hint">Press Enter to send, Shift+Enter for new line</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<script>
    window.chatConfig = {
        chatRoomId: <?php echo $selectedRoomId ? (int)$selectedRoomId : 'null'; ?>,
        userId: <?php echo (int)$doctorId; ?>,
        userType: 'doctor'
    };
</script>

<script src="js/sidebar.js"></script>
<script src="js/msg.js"></script>
</body>

</html>
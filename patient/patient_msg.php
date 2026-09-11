<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/msg.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

// Check if patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$chat = new Chat();

// Get selected chat room ID from URL
$selectedRoomId = isset($_GET['room_id']) ? intval($_GET['room_id']) : null;

// Get every doctor this patient has an approved appointment with - this
// includes conversations that haven't been started yet (no chat room),
// so the patient can begin chatting with any approved doctor straight
// from this page, not only doctors who already have a room.
$contacts = $chat->getApprovedContacts($userId, 'patient');

// Get total unread count
$unreadCount = $chat->getUnreadCount($userId, 'patient');

// If a room is selected, verify access and get details
$selectedRoom = null;
if ($selectedRoomId) {
    if ($chat->hasAccess($selectedRoomId, $userId, 'patient')) {
        $selectedRoom = $chat->getChatRoom($selectedRoomId);
        // Mark messages as read when opening chat
        $chat->markAsRead($selectedRoomId, 'patient');
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
    <title>My Chats - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/msg.css">
    <link rel="stylesheet" href="styles/patient_msg.css">
    <link rel="stylesheet" href="styles/sidebar.css">
    
</head>

<body>
    <!-- Menu Toggle Button -->
    <?php $active_page = 'chats'; ?>
    <?php include 'includes/public_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="chat-page-container">
        <div class="page-header">
            <h1>💬 My Chats</h1>
            <p>Chat with your doctors about your appointments</p>
        </div>

        <div class="chat-container">
            <!-- Chat List Sidebar -->
            <div class="chat-list-container" id="chatListContainer">
                <div class="chat-list-header">
                    <h3>Conversations</h3>
                    <?php if ($unreadCount > 0): ?>
                        <span class="total-unread-badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </div>

                <div class="chat-search">
                    <input type="text" placeholder="🔍 Search chats..." id="chatSearch">
                </div>

                <div class="chat-list" id="chatList">
                    <?php if (empty($contacts)): ?>
                        <div class="chat-list-empty">
                            <p>No conversations yet</p>
                            <small>Book an approved appointment to start chatting with your doctor</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="chat-list-item <?php echo ($contact['chat_room_id'] && $selectedRoomId == $contact['chat_room_id']) ? 'active' : ''; ?>"
                                onclick="openChat(<?php echo $contact['chat_room_id'] ? (int)$contact['chat_room_id'] : 'null'; ?>, <?php echo (int)$contact['appointment_id']; ?>)">
                                <div class="chat-avatar">👨‍⚕️</div>
                                <div class="chat-preview">
                                    <div class="chat-preview-header">
                                        <h4>Dr. <?php echo htmlspecialchars($contact['contact_name']); ?></h4>
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
                                        } elseif (!empty($contact['contact_meta'])) {
                                            echo htmlspecialchars($contact['contact_meta']);
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
                            <h3>Select a Conversation</h3>
                            <p>Choose a doctor from the list to start chatting</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="active-chat">
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="chat-header-left">
                                <button class="back-btn" onclick="closeChat()">←</button>
                                <div class="chat-avatar">👨‍⚕️</div>
                                <div class="chat-info">
                                    <h4>Dr. <?php echo htmlspecialchars($selectedRoom['doctor_name']); ?></h4>
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
                            <span>Doctor is typing...</span>
                        </div>

                        <!-- Message Input -->
                        <div class="message-input-container">
                            <form id="messageForm">
                                <textarea id="messageInput" placeholder="Type your message here..." rows="1"
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
    <script src="js/patient_msg.js"></script>
    
</body>

</html>
<?php
session_start();

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

require_once 'classes/Chat.php';

$doctorId = $_SESSION['user_id'];
$doctorName = $_SESSION['user_name'];
$chat = new Chat();

// Get selected chat room ID from URL
$selectedRoomId = isset($_GET['room_id']) ? intval($_GET['room_id']) : null;

// Get all chat rooms for this doctor
$chatRooms = $chat->getUserChatRooms($doctorId, 'doctor');

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
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/chat.css">
    <style>
        body {
            background: #f5f7fa;
        }
        .chat-page-container {
            max-width: 1400px;
            margin: 0 auto;
            margin-left: 0;
            padding: 30px 20px;
            padding-top: 80px;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        .page-header p {
            color: #666;
            font-size: 14px;
        }
        .unread-badge {
            background: #ff4757;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <!-- Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle">☰</button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">👨‍⚕️</div>
            DOCTOR PORTAL
        </div>

        <div class="user-profile">
            <div class="user-avatar">👨‍⚕️</div>
            <div class="user-info">
                <h3>Dr. <?php echo htmlspecialchars($doctorName); ?></h3>
                <p>Medical Professional</p>
            </div>
        </div>

        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link" href="doctor_dashboard.php">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="doctor_chat.php">
                        <span class="nav-icon">💬</span>
                        <span>Patient Chats</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="unread-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="doctor_profile.php">
                        <span class="nav-icon">👤</span>
                        <span>My Profile</span>
                    </a>
                </li>
            </ul>
        </nav>

        <form method="post" action="logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

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
                    <?php if (empty($chatRooms)): ?>
                        <div class="chat-list-empty">
                            <p>No patient conversations yet</p>
                            <small>Chats will appear here when patients with approved appointments message you</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chatRooms as $room): ?>
                            <div class="chat-list-item <?php echo $selectedRoomId == $room['id'] ? 'active' : ''; ?>" 
                                 onclick="openChat(<?php echo $room['id']; ?>)">
                                <div class="chat-avatar">👤</div>
                                <div class="chat-preview">
                                    <div class="chat-preview-header">
                                        <h4><?php echo htmlspecialchars($room['patient_name']); ?></h4>
                                        <span class="chat-time">
                                            <?php 
                                            if ($room['last_message_time']) {
                                                echo date('M j, g:i A', strtotime($room['last_message_time']));
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <p class="last-message">
                                        <?php echo $room['last_message'] ? htmlspecialchars(substr($room['last_message'], 0, 50)) . '...' : 'No messages yet'; ?>
                                    </p>
                                </div>
                                <?php if ($room['doctor_unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $room['doctor_unread_count']; ?></span>
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
                                <textarea 
                                    id="messageInput" 
                                    placeholder="Type your message to patient..." 
                                    rows="1"
                                    required
                                ></textarea>
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
        const chatRoomId = <?php echo $selectedRoomId ? $selectedRoomId : 'null'; ?>;
        const userId = <?php echo $doctorId; ?>;
        const userType = 'doctor';
        let lastMessageId = 0;
        let pollingInterval = null;
        let typingTimeout = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            if (chatRoomId) {
                loadMessages();
                startPolling();
                setupMessageInput();
            }
            setupSidebar();
        });

        // Load messages
        function loadMessages() {
            fetch(`chat_api.php?action=get_messages&chat_room_id=${chatRoomId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMessages(data.messages);
                        if (data.messages.length > 0) {
                            lastMessageId = data.messages[data.messages.length - 1].id;
                        }
                    }
                })
                .catch(error => console.error('Error loading messages:', error));
        }

        // Display messages
        function displayMessages(messages) {
            const container = document.getElementById('messagesContainer');
            const loading = document.getElementById('messagesLoading');
            
            if (loading) loading.style.display = 'none';

            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="no-messages">
                        <p>No messages yet</p>
                        <small>Start the conversation by sending a message</small>
                    </div>
                `;
                return;
            }

            let html = '';
            messages.forEach(msg => {
                const isMine = msg.sender_type === userType;
                const time = new Date(msg.created_at).toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit' 
                });

                html += `
                    <div class="message ${isMine ? 'message-mine' : 'message-theirs'}">
                        <div class="message-content">
                            <p>${escapeHtml(msg.message)}</p>
                            <span class="message-time">${time}</span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            scrollToBottom();
        }

        // Poll for new messages
        function startPolling() {
            pollingInterval = setInterval(() => {
                fetch(`chat_api.php?action=get_recent_messages&chat_room_id=${chatRoomId}&after_message_id=${lastMessageId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.messages.length > 0) {
                            appendMessages(data.messages);
                            lastMessageId = data.messages[data.messages.length - 1].id;
                        }
                        
                        // Update typing indicator
                        const typingIndicator = document.getElementById('typingIndicator');
                        if (data.isTyping) {
                            typingIndicator.style.display = 'flex';
                        } else {
                            typingIndicator.style.display = 'none';
                        }
                    })
                    .catch(error => console.error('Error polling messages:', error));
            }, 2000); // Poll every 2 seconds
        }

        // Append new messages
        function appendMessages(messages) {
            const container = document.getElementById('messagesContainer');
            
            messages.forEach(msg => {
                const isMine = msg.sender_type === userType;
                const time = new Date(msg.created_at).toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit' 
                });

                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${isMine ? 'message-mine' : 'message-theirs'}`;
                messageDiv.innerHTML = `
                    <div class="message-content">
                        <p>${escapeHtml(msg.message)}</p>
                        <span class="message-time">${time}</span>
                    </div>
                `;
                
                container.appendChild(messageDiv);
            });

            scrollToBottom();
        }

        // Setup message input
        function setupMessageInput() {
            const form = document.getElementById('messageForm');
            const input = document.getElementById('messageInput');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                sendMessage();
            });

            // Handle Enter key
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // Typing indicator
            input.addEventListener('input', function() {
                updateTypingStatus(true);
                
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    updateTypingStatus(false);
                }, 3000);
            });

            // Auto-resize textarea
            input.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }

        // Send message
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();

            if (!message) return;

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('chat_room_id', chatRoomId);
            formData.append('message', message);

            fetch('chat_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    input.style.height = 'auto';
                    updateTypingStatus(false);
                    
                    // Immediately fetch the new message
                    setTimeout(() => {
                        fetch(`chat_api.php?action=get_recent_messages&chat_room_id=${chatRoomId}&after_message_id=${lastMessageId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.messages.length > 0) {
                                    appendMessages(data.messages);
                                    lastMessageId = data.messages[data.messages.length - 1].id;
                                }
                            });
                    }, 100);
                } else {
                    alert('Failed to send message. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                alert('Error sending message. Please try again.');
            });
        }

        // Update typing status
        function updateTypingStatus(isTyping) {
            const formData = new FormData();
            formData.append('action', 'update_typing');
            formData.append('chat_room_id', chatRoomId);
            formData.append('is_typing', isTyping ? '1' : '0');

            fetch('chat_api.php', {
                method: 'POST',
                body: formData
            }).catch(error => console.error('Error updating typing status:', error));
        }

        // Utility functions
        function scrollToBottom() {
            const container = document.getElementById('messagesContainer');
            container.scrollTop = container.scrollHeight;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function refreshMessages() {
            loadMessages();
        }

        function openChat(roomId) {
            window.location.href = `doctor_chat.php?room_id=${roomId}`;
        }

        function closeChat() {
            const container = document.getElementById('chatListContainer');
            container.classList.remove('hidden');
        }

        // Setup sidebar
        function setupSidebar() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

        // Search chats
        document.getElementById('chatSearch')?.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.chat-list-item');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(search) ? 'flex' : 'none';
            });
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            if (chatRoomId) {
                updateTypingStatus(false);
            }
        });
    </script>
</body>
</html>
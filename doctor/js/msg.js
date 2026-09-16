const chatConfig = window.chatConfig || {};

const chatRoomId = chatConfig.chatRoomId ?? null;
const userId = chatConfig.userId;
const userType = chatConfig.userType;

let lastMessageId = 0;
let pollingInterval = null;
let typingTimeout = null;


// ================================
// INITIALIZE
// ================================

document.addEventListener('DOMContentLoaded', function () {

    if (chatRoomId) {
        loadMessages();
        startPolling();
        setupMessageInput();
    }

    setupChatSearch();
});


// ================================
// LOAD MESSAGES
// ================================

function loadMessages() {

    fetch(`msg_api.php?action=get_messages&chat_room_id=${chatRoomId}`)
        .then(response => response.json())
        .then(data => {

            if (data.success) {

                displayMessages(data.messages);

                if (data.messages.length > 0) {
                    lastMessageId =
                        data.messages[data.messages.length - 1].id;
                }
            }

        })
        .catch(error => {
            console.error('Error loading messages:', error);
        });
}


// ================================
// DISPLAY MESSAGES
// ================================

function displayMessages(messages) {

    const container =
        document.getElementById('messagesContainer');

    const loading =
        document.getElementById('messagesLoading');

    if (!container) return;

    if (loading) {
        loading.style.display = 'none';
    }

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

        const isMine =
            msg.sender_id == userId &&
            msg.sender_type.toLowerCase().trim() ===
            userType.toLowerCase().trim();

        const time =
            new Date(msg.created_at).toLocaleTimeString('en-US', {
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


// ================================
// POLLING
// ================================

function startPolling() {

    pollingInterval = setInterval(() => {

        fetch(
            `msg_api.php?action=get_recent_messages&chat_room_id=${chatRoomId}&after_message_id=${lastMessageId}`
        )
            .then(response => response.json())
            .then(data => {

                if (data.success && data.messages.length > 0) {

                    appendMessages(data.messages);

                    lastMessageId =
                        data.messages[data.messages.length - 1].id;
                }

                const typingIndicator =
                    document.getElementById('typingIndicator');

                if (typingIndicator) {

                    typingIndicator.style.display =
                        data.isTyping ? 'flex' : 'none';
                }

            })
            .catch(error => {
                console.error('Error polling messages:', error);
            });

    }, 2000);
}


// ================================
// APPEND NEW MESSAGES
// ================================

function appendMessages(messages) {

    const container =
        document.getElementById('messagesContainer');

    if (!container) return;

    messages.forEach(msg => {

        const isMine =
            msg.sender_id == userId &&
            msg.sender_type.toLowerCase().trim() ===
            userType.toLowerCase().trim();

        const time =
            new Date(msg.created_at).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit'
            });

        const messageDiv =
            document.createElement('div');

        messageDiv.className =
            `message ${isMine ? 'message-mine' : 'message-theirs'}`;

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


// ================================
// MESSAGE INPUT
// ================================

function setupMessageInput() {

    const form =
        document.getElementById('messageForm');

    const input =
        document.getElementById('messageInput');

    if (!form || !input) return;


    form.addEventListener('submit', function (e) {

        e.preventDefault();

        sendMessage();
    });


    input.addEventListener('keydown', function (e) {

        if (e.key === 'Enter' && !e.shiftKey) {

            e.preventDefault();

            sendMessage();
        }
    });


    input.addEventListener('input', function () {

        updateTypingStatus(true);

        clearTimeout(typingTimeout);

        typingTimeout = setTimeout(() => {

            updateTypingStatus(false);

        }, 3000);
    });


    input.addEventListener('input', function () {

        this.style.height = 'auto';

        this.style.height =
            this.scrollHeight + 'px';
    });
}


// ================================
// SEND MESSAGE
// ================================

function sendMessage() {

    const input =
        document.getElementById('messageInput');

    if (!input) return;

    const message =
        input.value.trim();

    if (!message) return;


    const formData =
        new FormData();

    formData.append(
        'action',
        'send_message'
    );

    formData.append(
        'chat_room_id',
        chatRoomId
    );

    formData.append(
        'message',
        message
    );


    fetch('msg_api.php', {
        method: 'POST',
        body: formData
    })

        .then(response => response.json())

        .then(data => {

            if (data.success) {

                input.value = '';

                input.style.height = 'auto';

                updateTypingStatus(false);


                setTimeout(() => {

                    fetch(
                        `msg_api.php?action=get_recent_messages&chat_room_id=${chatRoomId}&after_message_id=${lastMessageId}`
                    )

                        .then(response => response.json())

                        .then(data => {

                            if (
                                data.success &&
                                data.messages.length > 0
                            ) {

                                appendMessages(data.messages);

                                lastMessageId =
                                    data.messages[data.messages.length - 1].id;
                            }

                        });

                }, 100);

            } else {

                alert(
                    data.error ||
                    'Failed to send message. Please try again.'
                );
            }

        })

        .catch(error => {

            console.error(
                'Error sending message:',
                error
            );

            alert(
                'Error sending message. Please try again.'
            );
        });
}


// ================================
// TYPING STATUS
// ================================

function updateTypingStatus(isTyping) {

    if (!chatRoomId) return;

    const formData =
        new FormData();

    formData.append(
        'action',
        'update_typing'
    );

    formData.append(
        'chat_room_id',
        chatRoomId
    );

    formData.append(
        'is_typing',
        isTyping ? '1' : '0'
    );


    fetch('msg_api.php', {
        method: 'POST',
        body: formData
    })

        .catch(error => {

            console.error(
                'Error updating typing status:',
                error
            );
        });
}


// ================================
// UTILITY
// ================================

function scrollToBottom() {

    const container =
        document.getElementById('messagesContainer');

    if (!container) return;

    container.scrollTop =
        container.scrollHeight;
}


function escapeHtml(text) {

    const div =
        document.createElement('div');

    div.textContent = text;

    return div.innerHTML;
}


function refreshMessages() {

    loadMessages();
}


// ================================
// OPEN CHAT
// ================================

function openChat(roomId, appointmentId) {

    if (roomId) {

        window.location.href =
            `doctor_msg.php?room_id=${roomId}`;

        return;
    }


    const formData =
        new FormData();

    formData.append(
        'action',
        'get_or_create_room'
    );

    formData.append(
        'appointment_id',
        appointmentId
    );


    fetch('msg_api.php', {
        method: 'POST',
        body: formData
    })

        .then(response => response.json())

        .then(data => {

            if (
                data.success &&
                data.chatRoom
            ) {

                window.location.href =
                    `doctor_msg.php?room_id=${data.chatRoom.id}`;

            } else {

                alert(
                    data.error ||
                    'Unable to open chat. Please try again.'
                );
            }

        })

        .catch(error => {

            console.error(
                'Error opening chat:',
                error
            );

            alert(
                'Error opening chat. Please try again.'
            );
        });
}


// ================================
// CLOSE CHAT
// ================================

function closeChat() {

    const container =
        document.getElementById('chatListContainer');

    if (container) {
        container.classList.remove('hidden');
    }
}


// ================================
// SEARCH
// ================================

function setupChatSearch() {

    const searchInput =
        document.getElementById('chatSearch');

    if (!searchInput) return;


    searchInput.addEventListener(
        'input',
        function (e) {

            const search =
                e.target.value.toLowerCase();

            const items =
                document.querySelectorAll(
                    '.chat-list-item'
                );


            items.forEach(item => {

                const text =
                    item.textContent.toLowerCase();

                item.style.display =
                    text.includes(search)
                        ? 'flex'
                        : 'none';
            });
        }
    );
}


// ================================
// CLEANUP
// ================================

window.addEventListener(
    'beforeunload',
    function () {

        if (pollingInterval) {

            clearInterval(
                pollingInterval
            );
        }

        if (chatRoomId) {

            updateTypingStatus(false);
        }
    }
);
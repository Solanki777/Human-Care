(function () {
    'use strict';

    // =========================================================
    // CHAT CONFIG
    // =========================================================

    const chatConfig = window.chatConfig || {};

    const chatRoomId = chatConfig.chatRoomId ?? null;
    const userId = chatConfig.userId;
    const userType = chatConfig.userType;

    let lastMessageId = 0;
    let pollingInterval = null;
    let typingTimeout = null;


    // =========================================================
    // INITIALIZATION
    // =========================================================

    document.addEventListener('DOMContentLoaded', function () {

        // Only initialize chat functionality if chat elements exist
        const chatPage = document.querySelector('.chat-page-container');

        if (!chatPage) {
            return;
        }

        if (chatRoomId) {
            loadMessages();
            startPolling();
            setupMessageInput();
        }

        setupChatSearch();
    });


    // =========================================================
    // LOAD MESSAGES
    // =========================================================

    async function loadMessages() {

        if (!chatRoomId) {
            return;
        }

        const messagesContainer =
            document.getElementById('messagesContainer');

        const messagesLoading =
            document.getElementById('messagesLoading');

        if (!messagesContainer) {
            return;
        }

        if (messagesLoading) {
            messagesLoading.style.display = 'flex';
        }

        try {

            const response = await fetch(
                `msg_api.php?action=get_messages&chat_room_id=${encodeURIComponent(chatRoomId)}`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {

                displayMessages(data.messages || []);

            } else {

                showChatError(
                    data.message || 'Unable to load messages.'
                );
            }

        } catch (error) {

            console.error('Error loading messages:', error);

            showChatError(
                'Unable to load messages. Please try again.'
            );

        } finally {

            if (messagesLoading) {
                messagesLoading.style.display = 'none';
            }
        }
    }


    // =========================================================
    // DISPLAY MESSAGES
    // =========================================================

    function displayMessages(messages) {

        const container =
            document.getElementById('messagesContainer');

        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (!messages || messages.length === 0) {

            container.innerHTML = `
                <div class="no-messages">
                    <p>No messages yet</p>
                    <small>Start the conversation.</small>
                </div>
            `;

            lastMessageId = 0;
            return;
        }

        messages.forEach(function (message) {

            appendMessageElement(container, message);

            if (message.id) {
                lastMessageId =
                    Math.max(lastMessageId, Number(message.id));
            }
        });

        scrollToBottom();
    }


    // =========================================================
    // APPEND MESSAGE
    // =========================================================

    function appendMessageElement(container, message) {

        const messageId = Number(message.id || 0);

        const senderId = Number(
            message.sender_id ??
            message.user_id ??
            0
        );

        const isMine = senderId === Number(userId);

        const messageClass =
            isMine ? 'message-mine' : 'message-theirs';

        const messageText = escapeHtml(
            message.message ??
            message.content ??
            ''
        );

        const senderName = escapeHtml(
            message.sender_name ??
            message.sender ??
            ''
        );

        const messageTime = formatMessageTime(
            message.created_at ??
            message.sent_at ??
            ''
        );

        const messageElement = document.createElement('div');

        messageElement.className =
            `message ${messageClass}`;

        if (messageId) {
            messageElement.dataset.messageId = messageId;
        }

        messageElement.innerHTML = `
            <div class="message-content">
                ${!isMine && senderName
                    ? `<small>${senderName}</small>`
                    : ''
                }

                <p>${messageText}</p>

                <span class="message-time">
                    ${escapeHtml(messageTime)}
                </span>
            </div>
        `;

        container.appendChild(messageElement);
    }


    // =========================================================
    // POLLING
    // =========================================================

    function startPolling() {

        stopPolling();

        if (!chatRoomId) {
            return;
        }

        pollingInterval = setInterval(function () {

            loadNewMessages();

            updateTypingIndicator();

        }, 2000);
    }


    function stopPolling() {

        if (pollingInterval) {

            clearInterval(pollingInterval);

            pollingInterval = null;
        }
    }


    // =========================================================
    // LOAD NEW MESSAGES
    // =========================================================

    async function loadNewMessages() {

        if (!chatRoomId) {
            return;
        }

        try {

            const response = await fetch(
                `msg_api.php?action=get_messages&chat_room_id=${encodeURIComponent(chatRoomId)}`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data.success || !Array.isArray(data.messages)) {
                return;
            }

            const newMessages =
                data.messages.filter(function (message) {

                    return Number(message.id || 0) > lastMessageId;
                });

            if (newMessages.length === 0) {
                return;
            }

            const container =
                document.getElementById('messagesContainer');

            if (!container) {
                return;
            }

            const noMessages =
                container.querySelector('.no-messages');

            if (noMessages) {
                noMessages.remove();
            }

            newMessages.forEach(function (message) {

                appendMessageElement(container, message);

                if (message.id) {
                    lastMessageId =
                        Math.max(
                            lastMessageId,
                            Number(message.id)
                        );
                }
            });

            scrollToBottom();

        } catch (error) {

            // Do not let polling errors interfere with the page/sidebar.
            console.error('Message polling error:', error);
        }
    }


    // =========================================================
    // MESSAGE INPUT
    // =========================================================

    function setupMessageInput() {

        const form =
            document.getElementById('messageForm');

        const input =
            document.getElementById('messageInput');

        if (!form || !input) {
            return;
        }

        form.addEventListener('submit', function (event) {

            event.preventDefault();

            sendMessage();
        });


        // Enter = send
        // Shift + Enter = new line

        input.addEventListener('keydown', function (event) {

            if (
                event.key === 'Enter' &&
                !event.shiftKey
            ) {

                event.preventDefault();

                sendMessage();
            }
        });


        input.addEventListener('input', function () {

            updateTypingStatus(true);

            clearTimeout(typingTimeout);

            typingTimeout = setTimeout(function () {

                updateTypingStatus(false);

            }, 1500);


            // Auto resize

            input.style.height = 'auto';

            input.style.height =
                Math.min(input.scrollHeight, 150) + 'px';
        });
    }


    // =========================================================
    // SEND MESSAGE
    // =========================================================

    async function sendMessage() {

        if (!chatRoomId) {
            return;
        }

        const input =
            document.getElementById('messageInput');

        const button =
            document.querySelector(
                '#messageForm button[type="submit"], .send-btn'
            );

        if (!input) {
            return;
        }

        const message = input.value.trim();

        if (!message) {
            return;
        }

        if (button) {
            button.disabled = true;
        }

        try {

            const formData = new FormData();

            formData.append(
                'chat_room_id',
                chatRoomId
            );

            formData.append(
                'message',
                message
            );


            const response = await fetch(
                'msg_api.php?action=send_message',
                {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {

                input.value = '';

                input.style.height = 'auto';

                updateTypingStatus(false);

                if (data.message) {

                    const container =
                        document.getElementById(
                            'messagesContainer'
                        );

                    if (container) {

                        const noMessages =
                            container.querySelector(
                                '.no-messages'
                            );

                        if (noMessages) {
                            noMessages.remove();
                        }

                        appendMessageElement(
                            container,
                            data.message
                        );

                        if (data.message.id) {

                            lastMessageId =
                                Math.max(
                                    lastMessageId,
                                    Number(data.message.id)
                                );
                        }

                        scrollToBottom();
                    }

                } else {

                    await loadMessages();
                }

            } else {

                showChatError(
                    data.message ||
                    'Unable to send message.'
                );
            }

        } catch (error) {

            console.error('Send message error:', error);

            showChatError(
                'Unable to send message. Please try again.'
            );

        } finally {

            if (button) {
                button.disabled = false;
            }

            input.focus();
        }
    }


    // =========================================================
    // TYPING STATUS
    // =========================================================

    async function updateTypingStatus(isTyping) {

        if (!chatRoomId) {
            return;
        }

        try {

            const formData = new FormData();

            formData.append(
                'chat_room_id',
                chatRoomId
            );

            formData.append(
                'is_typing',
                isTyping ? '1' : '0'
            );

            await fetch(
                'msg_api.php?action=typing',
                {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    keepalive: true
                }
            );

        } catch (error) {

            // Typing status must never break chat/sidebar.
            console.error(
                'Typing status error:',
                error
            );
        }
    }


    // =========================================================
    // TYPING INDICATOR
    // =========================================================

    async function updateTypingIndicator() {

        if (!chatRoomId) {
            return;
        }

        const indicator =
            document.getElementById('typingIndicator');

        if (!indicator) {
            return;
        }

        try {

            const response = await fetch(
                `msg_api.php?action=get_typing&chat_room_id=${encodeURIComponent(chatRoomId)}`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (
                data.success &&
                data.is_typing
            ) {

                indicator.style.display = 'flex';

            } else {

                indicator.style.display = 'none';
            }

        } catch (error) {

            console.error(
                'Typing indicator error:',
                error
            );
        }
    }


    // =========================================================
    // SCROLL
    // =========================================================

    function scrollToBottom() {

        const container =
            document.getElementById(
                'messagesContainer'
            );

        if (!container) {
            return;
        }

        requestAnimationFrame(function () {

            container.scrollTop =
                container.scrollHeight;
        });
    }


    // =========================================================
    // REFRESH
    // =========================================================

    function refreshMessages() {

        if (!chatRoomId) {
            return;
        }

        lastMessageId = 0;

        loadMessages();
    }


    // =========================================================
    // OPEN CHAT
    // =========================================================

    function openChat(roomId, appointmentId) {

        if (!roomId && !appointmentId) {
            return;
        }

        let url = 'doctor_msg.php';

        if (roomId) {

            url +=
                '?room_id=' +
                encodeURIComponent(roomId);

        } else if (appointmentId) {

            url +=
                '?appointment_id=' +
                encodeURIComponent(appointmentId);
        }

        window.location.href = url;
    }


    // =========================================================
    // CLOSE CHAT
    // =========================================================

    function closeChat() {

        stopPolling();

        const chatList =
            document.getElementById(
                'chatListContainer'
            );

        const chatWindow =
            document.getElementById(
                'chatWindow'
            );

        if (chatList) {

            chatList.classList.remove('hidden');
        }

        if (chatWindow) {

            chatWindow.classList.remove('active');
        }

        // On mobile, return to chat list.
        if (window.innerWidth <= 768) {

            if (chatList) {
                chatList.style.display = '';
            }

            if (chatWindow) {
                chatWindow.style.display = 'none';
            }
        }
    }


    // =========================================================
    // CHAT SEARCH
    // =========================================================

    function setupChatSearch() {

        const searchInput =
            document.getElementById('chatSearch');

        const chatList =
            document.getElementById('chatList');

        if (!searchInput || !chatList) {
            return;
        }

        searchInput.addEventListener(
            'input',
            function () {

                const searchTerm =
                    searchInput.value
                        .toLowerCase()
                        .trim();

                const items =
                    chatList.querySelectorAll(
                        '.chat-list-item'
                    );

                items.forEach(function (item) {

                    const text =
                        item.textContent
                            .toLowerCase();

                    item.style.display =
                        text.includes(searchTerm)
                            ? ''
                            : 'none';
                });
            }
        );
    }


    // =========================================================
    // HTML ESCAPING
    // =========================================================

    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value == null
                ? ''
                : String(value);

        return div.innerHTML;
    }


    // =========================================================
    // TIME FORMAT
    // =========================================================

    function formatMessageTime(value) {

        if (!value) {
            return '';
        }

        const date =
            new Date(
                String(value).replace(' ', 'T')
            );

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleTimeString(
            [],
            {
                hour: '2-digit',
                minute: '2-digit'
            }
        );
    }


    // =========================================================
    // ERROR MESSAGE
    // =========================================================

    function showChatError(message) {

        const container =
            document.getElementById(
                'messagesContainer'
            );

        if (!container) {
            return;
        }

        const existing =
            container.querySelector(
                '.chat-error-message'
            );

        if (existing) {
            existing.remove();
        }

        const errorElement =
            document.createElement('div');

        errorElement.className =
            'error-message chat-error-message';

        errorElement.textContent = message;

        container.appendChild(errorElement);
    }


    // =========================================================
    // CLEANUP
    // =========================================================

    window.addEventListener(
        'beforeunload',
        function () {

            stopPolling();

            if (chatRoomId) {
                updateTypingStatus(false);
            }
        }
    );


    // =========================================================
    // EXPOSE ONLY REQUIRED HTML HANDLERS
    // =========================================================
    //
    // Your doctor_msg.php uses inline:
    //
    // onclick="openChat(...)"
    // onclick="closeChat()"
    // onclick="refreshMessages()"
    //
    // Therefore expose ONLY these functions.
    //

    window.openChat = openChat;
    window.closeChat = closeChat;
    window.refreshMessages = refreshMessages;
    window.sendMessage = sendMessage;

})();
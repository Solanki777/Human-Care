(function () {
    'use strict';

    const chatWindow = document.getElementById('chatWindow');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const typingRow = document.getElementById('typingRow');
    const initialTimestamp = document.getElementById('initialTimestamp');

    if (!chatWindow || !chatInput || !sendBtn || !typingRow) {
        return;
    }

    const CHAT_API_URL = 'api/doctor_ai_msg.php';
    const conversationHistory = [];

    /* ============================================
       HELPERS
       ============================================ */

    function formatTime(d) {
        return d.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function scrollBottom() {
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }


    /* ============================================
       MARKDOWN RENDERING
       ============================================ */

    function renderMarkdown(text) {
        const div = document.createElement('div');
        div.textContent = text;

        let s = div.innerHTML;

        /* ✅ / ❌ notice boxes */
        s = s.replace(/(✅|❌)[^\n]*/g, function (m) {
            const ok = m.startsWith('✅');

            const c = ok ? '#065f46' : '#991b1b';
            const bg = ok ? '#d1fae5' : '#fee2e2';
            const bc = ok ? '#10b981' : '#ef4444';

            return `
                <div style="
                    margin-top:10px;
                    padding:10px 14px;
                    border-radius:8px;
                    background:${bg};
                    color:${c};
                    border-left:3px solid ${bc};
                    font-size:13.5px;
                ">
                    ${m}
                </div>
            `;
        });

        /* Navigation links */
        s = s.replace(/([\w_]+\.php)/g, function (match) {
            const pages = [
                'doctor_dashboard.php',
                'doctor_prescriptions_list.php',
                'doctor_chat.php',
                'doctor_profile.php',
                'doctor_add_education.php'
            ];

            if (pages.includes(match)) {
                return `
                    <a href="${match}"
                       style="
                           color:#667eea;
                           font-weight:600;
                           text-decoration:underline;
                       ">
                        ${match}
                    </a>
                `;
            }

            return match;
        });

        /* Bold */
        s = s.replace(
            /\*\*(.+?)\*\*/g,
            '<strong>$1</strong>'
        );

        /* Italic */
        s = s.replace(
            /\*(.+?)\*/g,
            '<em>$1</em>'
        );

        /* New lines */
        s = s.replace(/\n/g, '<br>');

        return s;
    }


    /* ============================================
       INITIAL TIMESTAMP
       ============================================ */

    if (initialTimestamp) {
        initialTimestamp.textContent =
            formatTime(new Date());
    }


    /* ============================================
       RENDER MESSAGE
       ============================================ */

    function appendMessage(text, sender, isError) {

        const row = document.createElement('div');

        row.className =
            'message-row ' + sender;


        /* Avatar */
        const avatar = document.createElement('div');

        avatar.className =
            'avatar ' +
            (sender === 'user'
                ? 'user-avatar'
                : 'ai-avatar');

        avatar.textContent =
            sender === 'user'
                ? '👨‍⚕️'
                : '🤖';


        /* Bubble group */
        const group = document.createElement('div');

        group.className = 'bubble-group';


        /* Message bubble */
        const bubble = document.createElement('div');

        bubble.className =
            'bubble' +
            (isError
                ? ' error-bubble'
                : '');


        if (sender === 'ai' && !isError) {
            bubble.innerHTML =
                renderMarkdown(text);
        } else {
            bubble.textContent = text;
        }


        /* Timestamp */
        const time = document.createElement('div');

        time.className =
            'msg-timestamp';

        time.textContent =
            formatTime(new Date());


        /* Build message */
        group.appendChild(bubble);
        group.appendChild(time);

        row.appendChild(avatar);
        row.appendChild(group);


        /* Insert before typing indicator */
        chatWindow.insertBefore(
            row,
            typingRow
        );

        scrollBottom();
    }


    /* ============================================
       TYPING INDICATOR
       ============================================ */

    function showTyping(show) {

        typingRow.style.display =
            show ? 'flex' : 'none';

        if (show) {
            scrollBottom();
        }
    }


    /* ============================================
       SENDING STATE
       ============================================ */

    function setSending(isSending) {

        sendBtn.disabled =
            isSending;

        chatInput.disabled =
            isSending;
    }


    /* ============================================
       QUICK ACTION BUTTONS
       ============================================ */

    document
        .querySelectorAll('.quick-btn')
        .forEach(function (btn) {

            btn.addEventListener(
                'click',
                function () {

                    chatInput.value =
                        btn.dataset.text ||
                        btn.textContent.trim();

                    chatInput.focus();

                    autoResize();
                }
            );
        });


    /* ============================================
       TEXTAREA AUTO RESIZE
       ============================================ */

    function autoResize() {

        chatInput.style.height =
            'auto';

        chatInput.style.height =
            Math.min(
                chatInput.scrollHeight,
                120
            ) + 'px';
    }

    chatInput.addEventListener(
        'input',
        autoResize
    );


    /* ============================================
       ENTER TO SEND
       ============================================ */

    chatInput.addEventListener(
        'keydown',
        function (e) {

            if (
                e.key === 'Enter' &&
                !e.shiftKey
            ) {
                e.preventDefault();
                sendMessage();
            }
        }
    );


    /* Send button */
    sendBtn.addEventListener(
        'click',
        sendMessage
    );


    /* ============================================
       SEND MESSAGE
       ============================================ */

    async function sendMessage() {

        const text =
            chatInput.value.trim();

        if (!text) {
            return;
        }


        /* Display user's message immediately */
        appendMessage(
            text,
            'user'
        );


        /* Clear input */
        chatInput.value = '';

        autoResize();


        /* Save conversation history */
        conversationHistory.push({
            role: 'user',
            content: text
        });


        /* Keep last 20 messages */
        if (
            conversationHistory.length > 20
        ) {
            conversationHistory.splice(
                0,
                2
            );
        }


        setSending(true);
        showTyping(true);


        try {

            const historyToSend =
                conversationHistory
                    .slice(0, -1)
                    .map(function (t) {

                        return {
                            role:
                                t.role === 'ai'
                                    ? 'assistant'
                                    : t.role,

                            content:
                                t.content
                        };
                    });


            const response =
                await fetch(
                    CHAT_API_URL,
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json'
                        },

                        body: JSON.stringify({
                            message: text,
                            history: historyToSend
                        })
                    }
                );


            showTyping(false);


            if (!response.ok) {

                const err =
                    await response.text();

                throw new Error(
                    'HTTP ' +
                    response.status +
                    ' — ' +
                    err
                );
            }


            const data =
                await response.json();


            if (
                data &&
                typeof data.reply === 'string' &&
                data.reply.length > 0
            ) {

                appendMessage(
                    data.reply,
                    'ai'
                );

                conversationHistory.push({
                    role: 'assistant',
                    content: data.reply
                });

            } else {

                appendMessage(
                    'Sorry, the AI service is currently unavailable.',
                    'ai',
                    true
                );

                conversationHistory.pop();
            }


        } catch (err) {

            console.error(
                'Doctor AI error:',
                err
            );

            showTyping(false);

            appendMessage(
                'Sorry, something went wrong: ' +
                err.message,
                'ai',
                true
            );

            conversationHistory.pop();


        } finally {

            setSending(false);

            chatInput.focus();
        }
    }


    /* ============================================
       INITIAL SCROLL
       ============================================ */

    scrollBottom();

})();
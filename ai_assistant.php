<?php
/* =====================================================================
   ai_assistant.php
   MediMate AI Assistant page for Human Care Hospital Management System
   - Matches the visual theme used in patient_appointment.php
   - PHP handles auth/session + sidebar include only.
   - Chat logic is pure JS (fetch to api/chat.php). No backend built yet.
   ===================================================================== */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/session.php';

// ---------------------------------------------------------------------
// Auth guard — same pattern as patient_appointment.php
// ---------------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
    <script src="scripts/main.js"></script>

    <style>
        /* =================================================================
           PAGE TITLE / HEADER
           (mirrors .page-title styling from patient_appointment.php)
        ================================================================= */
        .ai-page-title {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            text-align: center;
        }

        .ai-page-title h2 {
            font-size: 28px;
            margin: 0 0 8px 0;
            color: #333;
        }

        .ai-page-title p {
            color: #666;
            margin: 0;
            font-size: 15px;
        }

        .ai-welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 22px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .ai-welcome-banner .wave-text {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 4px 0;
        }

        .ai-welcome-banner .ask-text {
            font-size: 15px;
            margin: 0;
            opacity: 0.95;
        }

        /* =================================================================
           CHAT CONTAINER
        ================================================================= */
        .chat-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: 65vh;
            min-height: 420px;
        }

        .chat-window {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #f9fafb;
            scroll-behavior: smooth;
        }

        /* Custom scrollbar */
        .chat-window::-webkit-scrollbar {
            width: 8px;
        }

        .chat-window::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-window::-webkit-scrollbar-thumb {
            background: #c7d2fe;
            border-radius: 10px;
        }

        /* ---------------- Message Row & Bubbles ---------------- */
        .message-row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            max-width: 80%;
            opacity: 0;
            transform: translateY(12px);
            animation: messageIn 0.35s ease forwards;
        }

        @keyframes messageIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-row.user {
            align-self: flex-end;
            flex-direction: row-reverse;
            margin-left: auto;
        }

        .message-row.ai {
            align-self: flex-start;
            margin-right: auto;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .avatar.ai-avatar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .avatar.user-avatar {
            background: #e0e7ff;
            color: #667eea;
        }

        .bubble-group {
            display: flex;
            flex-direction: column;
        }

        .message-row.user .bubble-group {
            align-items: flex-end;
        }

        .message-row.ai .bubble-group {
            align-items: flex-start;
        }

        .bubble {
            padding: 13px 18px;
            border-radius: 16px;
            font-size: 14.5px;
            line-height: 1.6;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .message-row.ai .bubble {
            background: white;
            color: #333;
            border: 1px solid #eef0f5;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }

        .message-row.user .bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .bubble.error-bubble {
            background: #fee2e2 !important;
            color: #991b1b !important;
            border: 1px solid #fecaca !important;
        }

        .msg-timestamp {
            font-size: 11.5px;
            color: #9aa0ab;
            margin-top: 5px;
            padding: 0 4px;
        }

        /* ---------------- Suggestion Cards ---------------- */
        .suggestions-wrap {
            align-self: flex-start;
            max-width: 100%;
            width: 100%;
            margin-top: -4px;
        }

        .suggestions-label {
            font-size: 13px;
            color: #888;
            margin: 4px 0 10px 46px;
            font-weight: 600;
        }

        .suggestions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-left: 46px;
        }

        .suggestion-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13.5px;
            font-weight: 600;
            color: #444;
            cursor: pointer;
            text-align: left;
            transition: all 0.25s;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        }

        .suggestion-card:hover {
            border-color: #667eea;
            background: #f5f6ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.18);
        }

        /* ---------------- Typing Indicator ---------------- */
        .typing-row {
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            transform: translateY(10px);
            animation: messageIn 0.3s ease forwards;
        }

        .typing-bubble {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            border: 1px solid #eef0f5;
            padding: 12px 18px;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }

        .typing-text {
            font-size: 13px;
            color: #888;
            font-style: italic;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dots span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #667eea;
            animation: bounceDot 1.2s infinite ease-in-out;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.15s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes bounceDot {

            0%,
            80%,
            100% {
                transform: translateY(0);
                opacity: 0.5;
            }

            40% {
                transform: translateY(-6px);
                opacity: 1;
            }
        }

        /* =================================================================
           INPUT AREA
        ================================================================= */
        .chat-input-area {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            padding: 16px 20px;
            background: white;
            border-top: 1px solid #eef0f5;
        }

        .chat-textarea {
            flex: 1;
            resize: none;
            border: 1.5px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 14.5px;
            font-family: inherit;
            line-height: 1.5;
            max-height: 120px;
            min-height: 46px;
            outline: none;
            transition: border-color 0.25s, box-shadow 0.25s;
        }

        .chat-textarea:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
        }

        .send-btn {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            font-size: 18px;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.35);
            transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
        }

        .send-btn:hover:not(:disabled) {
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 5px 14px rgba(102, 126, 234, 0.45);
        }

        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* =================================================================
           FOOTER NOTE
        ================================================================= */
        .ai-footer-note {
            text-align: center;
            font-size: 12.5px;
            color: #92400e;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 18px;
        }

        /* =================================================================
           RESPONSIVE
        ================================================================= */
        @media (max-width: 768px) {
            .chat-wrapper {
                height: 72vh;
            }

            .message-row {
                max-width: 92%;
            }

            .suggestions-grid {
                grid-template-columns: 1fr;
                margin-left: 0;
            }

            .suggestions-label {
                margin-left: 0;
            }

            .ai-page-title h2 {
                font-size: 23px;
            }
        }

        @media (max-width: 480px) {
            .chat-window {
                padding: 16px;
            }

            .chat-input-area {
                padding: 12px;
            }

            .avatar {
                width: 30px;
                height: 30px;
                font-size: 15px;
            }
        }
    </style>
</head>

<body>

    <!-- ============================================================
         Shared sidebar (same include used across the patient pages)
    ============================================================ -->
    <?php $active_page = 'ai'; ?>
    <?php include 'includes/public_sidebar.php'; ?>

    <div style="max-width:900px; margin:auto; padding: 30px 20px;">

        <!-- ============================================================
             HEADER
        ============================================================ -->
        <div class="ai-page-title">
            <h2>🤖 MediMate AI Assistant</h2>
            <p>Your intelligent hospital assistant powered by AI</p>
        </div>

        <div class="ai-welcome-banner">
            <p class="wave-text">Hello 👋</p>
            <p class="ask-text">How can I help you today?</p>
        </div>

        <!-- ============================================================
             CHAT CONTAINER
        ============================================================ -->
        <div class="chat-wrapper">

            <!-- Scrollable message area -->
            <div class="chat-window" id="chatWindow">

                <!-- Initial AI greeting message -->
                <div class="message-row ai">
                    <div class="avatar ai-avatar">🤖</div>
                    <div class="bubble-group">
                        <div class="bubble">
                            Hello! I'm MediMate AI. I can answer your healthcare questions and assist you with hospital
                            services. How can I help you today?
                        </div>
                        <div class="msg-timestamp" id="initialTimestamp"></div>
                    </div>
                </div>

                <!-- Suggested question cards -->
                <div class="suggestions-wrap" id="suggestionsWrap">
                    <div class="suggestions-label">Try asking about:</div>
                    <div class="suggestions-grid">
                        <button type="button" class="suggestion-card" data-text="I'd like to book an appointment">📅
                            Book an Appointment</button>
                        <button type="button" class="suggestion-card" data-text="Can you help me find a doctor?">👨‍⚕️
                            Find a Doctor</button>
                        <button type="button" class="suggestion-card" data-text="I need information about a medicine">💊
                            Medicine Information</button>
                        <button type="button" class="suggestion-card" data-text="Can you help explain my symptoms?">🩺
                            Explain My Symptoms</button>
                        <button type="button" class="suggestion-card"
                            data-text="What hospital services do you offer?">📄 Hospital Services</button>
                        <button type="button" class="suggestion-card" data-text="I need an emergency contact number">🏥
                            Emergency Contact</button>
                    </div>
                </div>

                <!-- Typing indicator (hidden by default, toggled via JS) -->
                <div class="typing-row" id="typingRow" style="display:none;">
                    <div class="avatar ai-avatar">🤖</div>
                    <div class="typing-bubble">
                        <span class="typing-text">MediMate AI is typing</span>
                        <div class="typing-dots">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ============================================================
                 INPUT AREA
            ============================================================ -->
            <div class="chat-input-area">
                <textarea id="chatInput" class="chat-textarea" placeholder="Type your message..." rows="1"></textarea>
                <button type="button" id="sendBtn" class="send-btn" aria-label="Send message">
                    ✈️
                </button>
            </div>

        </div>

        <!-- ============================================================
             FOOTER NOTE
        ============================================================ -->
        <div class="ai-footer-note">
            ⚠ MediMate AI provides informational assistance only and should not replace professional medical advice.
        </div>

    </div>

    <!-- ================================================================
         JAVASCRIPT — Chat behaviour
         Sends { message } to api/chat.php, expects { reply } back.
         Backend (api/chat.php) is not implemented yet — fetch errors or
         non-OK responses fall back to a friendly unavailable message.
    ================================================================ -->
    <script>
        (function () {
            'use strict';

            const chatWindow = document.getElementById('chatWindow');
            const chatInput = document.getElementById('chatInput');
            const sendBtn = document.getElementById('sendBtn');
            const typingRow = document.getElementById('typingRow');
            const suggestionsWrap = document.getElementById('suggestionsWrap');

            const CHAT_API_URL = 'api/medimate_chat.php';

            // Stores full conversation: { role: 'user'|'assistant', content: '...' }
            const conversationHistory = [];

            /* ---------- Helpers ---------- */

            function formatTimestamp(date) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function scrollToBottom() {
                chatWindow.scrollTop = chatWindow.scrollHeight;
            }

            function renderMarkdown(text) {
                const div = document.createElement('div');
                div.textContent = text;
                let escaped = div.innerHTML;

                // ✅ / ❌ notice boxes
                escaped = escaped.replace(/(✅|❌)[^\n]*/g, function (match) {
                    const isSuccess = match.startsWith('✅');
                    const color = isSuccess ? '#065f46' : '#991b1b';
                    const bg = isSuccess ? '#d1fae5' : '#fee2e2';
                    const border = isSuccess ? '#10b981' : '#ef4444';
                    return '<div style="margin-top:10px;padding:10px 14px;border-radius:8px;background:' + bg + ';color:' + color + ';border-left:3px solid ' + border + ';font-size:13.5px;">' + match + '</div>';
                });

                escaped = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                escaped = escaped.replace(/\*(.+?)\*/g, '<em>$1</em>');
                escaped = escaped.replace(/\n/g, '<br>');

                return escaped;
            }

            // Set timestamp on initial greeting
            const initialTs = document.getElementById('initialTimestamp');
            if (initialTs) initialTs.textContent = formatTimestamp(new Date());

            /* ---------- Render a message bubble ---------- */

            function appendMessage(text, sender, isError) {
                const row = document.createElement('div');
                row.className = 'message-row ' + sender;

                const avatar = document.createElement('div');
                avatar.className = 'avatar ' + (sender === 'user' ? 'user-avatar' : 'ai-avatar');
                avatar.textContent = sender === 'user' ? '🙂' : '🤖';

                const group = document.createElement('div');
                group.className = 'bubble-group';

                const bubble = document.createElement('div');
                bubble.className = 'bubble' + (isError ? ' error-bubble' : '');

                if (sender === 'ai' && !isError) {
                    bubble.innerHTML = renderMarkdown(text);
                } else {
                    bubble.textContent = text;
                }

                const time = document.createElement('div');
                time.className = 'msg-timestamp';
                time.textContent = formatTimestamp(new Date());

                group.appendChild(bubble);
                group.appendChild(time);
                row.appendChild(avatar);
                row.appendChild(group);

                chatWindow.insertBefore(row, typingRow);
                scrollToBottom();
            }

            function showTyping(show) {
                typingRow.style.display = show ? 'flex' : 'none';
                if (show) scrollToBottom();
            }

            function setSending(isSending) {
                sendBtn.disabled = isSending;
                chatInput.disabled = isSending;
            }

            /* ---------- Suggestion cards ---------- */

            suggestionsWrap.addEventListener('click', function (e) {
                const card = e.target.closest('.suggestion-card');
                if (!card) return;
                chatInput.value = card.dataset.text || card.textContent.trim();
                chatInput.focus();
                autoResizeTextarea();
            });

            /* ---------- Textarea auto-resize ---------- */

            function autoResizeTextarea() {
                chatInput.style.height = 'auto';
                chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
            }

            chatInput.addEventListener('input', autoResizeTextarea);

            /* ---------- Enter / Shift+Enter ---------- */

            chatInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            sendBtn.addEventListener('click', sendMessage);

            /* ---------- Send message ---------- */

            async function sendMessage() {
                const text = chatInput.value.trim();
                if (!text) return;

                // Show user bubble
                appendMessage(text, 'user');
                chatInput.value = '';
                autoResizeTextarea();

                // Add to history as 'user'
                conversationHistory.push({ role: 'user', content: text });

                // Keep last 20 turns max
                if (conversationHistory.length > 20) {
                    conversationHistory.splice(0, 2);
                }

                setSending(true);
                showTyping(true);

                try {
                    // Build history to send (exclude current message, already in 'message' field)
                    // Convert any legacy 'ai' role to 'assistant' for the backend
                    const historyToSend = conversationHistory.slice(0, -1).map(function (turn) {
                        return {
                            role: turn.role === 'ai' ? 'assistant' : turn.role,
                            content: turn.content
                        };
                    });

                    const response = await fetch(CHAT_API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            message: text,
                            history: historyToSend
                        })
                    });

                    showTyping(false);

                    if (!response.ok) {
                        const errText = await response.text();
                        throw new Error('HTTP ' + response.status + ' — ' + errText);
                    }

                    const data = await response.json();

                    if (data && typeof data.reply === 'string' && data.reply.length > 0) {
                        appendMessage(data.reply, 'ai');
                        // Store as 'assistant' so backend understands it
                        conversationHistory.push({ role: 'assistant', content: data.reply });
                    } else {
                        appendMessage('Sorry, the AI service is currently unavailable.', 'ai', true);
                        // Remove failed user turn
                        conversationHistory.pop();
                    }

                } catch (err) {
                    console.error('MediMate error:', err);
                    showTyping(false);
                    appendMessage('Sorry, something went wrong: ' + err.message, 'ai', true);
                    // Remove failed user turn from history
                    conversationHistory.pop();
                } finally {
                    setSending(false);
                    chatInput.focus();
                }
            }

            /* ---------- Initial scroll ---------- */
            scrollToBottom();

        })();
    </script>

</body>

</html>
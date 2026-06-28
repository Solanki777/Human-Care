<?php
/**
 * doctor_ai_assistant.php
 * MediMate Doctor AI Assistant Page
 */

require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

Auth::require('doctor');

$doctor_id = Auth::id();

// Load doctor info for sidebar
$doctors_conn = Database::getConnection('doctors');
$stmt = $doctors_conn->prepare("
    SELECT id, first_name, last_name, specialty
    FROM doctors WHERE id = ? LIMIT 1
");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></title>
    <link rel="stylesheet" href="styles/main.css">
    <style>
        .ai-page-title {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            text-align: center;
        }
        .ai-page-title h2 { font-size: 28px; margin: 0 0 8px 0; color: #333; }
        .ai-page-title p  { color: #666; margin: 0; font-size: 15px; }

        .ai-welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 22px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(102,126,234,0.3);
        }
        .ai-welcome-banner .wave-text { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
        .ai-welcome-banner .ask-text  { font-size: 15px; margin: 0; opacity: .95; }

        /* Quick action buttons */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .quick-btn {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px 12px;
            font-size: 13.5px;
            font-weight: 600;
            color: #444;
            cursor: pointer;
            text-align: center;
            transition: all 0.25s;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .quick-btn:hover {
            border-color: #667eea;
            background: #f5f6ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102,126,234,0.18);
        }
        .quick-btn .btn-icon { font-size: 22px; display: block; margin-bottom: 6px; }

        /* Chat container */
        .chat-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: 60vh;
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
        .chat-window::-webkit-scrollbar { width: 8px; }
        .chat-window::-webkit-scrollbar-track { background: transparent; }
        .chat-window::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 10px; }

        .message-row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            max-width: 82%;
            opacity: 0;
            transform: translateY(12px);
            animation: msgIn 0.35s ease forwards;
        }
        @keyframes msgIn { to { opacity:1; transform:translateY(0); } }
        .message-row.user  { align-self: flex-end;  flex-direction: row-reverse; margin-left: auto; }
        .message-row.ai    { align-self: flex-start; margin-right: auto; }

        .avatar {
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .avatar.ai-avatar   { background: linear-gradient(135deg,#667eea,#764ba2); color: white; }
        .avatar.user-avatar { background: #e0e7ff; color: #667eea; }

        .bubble-group { display: flex; flex-direction: column; }
        .message-row.user .bubble-group { align-items: flex-end; }
        .message-row.ai   .bubble-group { align-items: flex-start; }

        .bubble {
            padding: 13px 18px; border-radius: 16px;
            font-size: 14.5px; line-height: 1.6;
            word-wrap: break-word; white-space: pre-wrap;
        }
        .message-row.ai .bubble {
            background: white; color: #333;
            border: 1px solid #eef0f5; border-bottom-left-radius: 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .message-row.user .bubble {
            background: linear-gradient(135deg,#667eea,#764ba2); color: white;
            border-bottom-right-radius: 4px;
            box-shadow: 0 2px 8px rgba(102,126,234,0.3);
        }
        .bubble.error-bubble { background:#fee2e2!important; color:#991b1b!important; border:1px solid #fecaca!important; }
        .msg-timestamp { font-size: 11.5px; color: #9aa0ab; margin-top: 5px; padding: 0 4px; }

        /* Typing indicator */
        .typing-row {
            display: flex; align-items: center; gap: 10px;
            opacity: 0; transform: translateY(10px);
            animation: msgIn 0.3s ease forwards;
        }
        .typing-bubble {
            display: flex; align-items: center; gap: 8px;
            background: white; border: 1px solid #eef0f5;
            padding: 12px 18px; border-radius: 16px;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .typing-text { font-size: 13px; color: #888; font-style: italic; }
        .typing-dots { display: flex; gap: 4px; }
        .typing-dots span {
            width: 7px; height: 7px; border-radius: 50%;
            background: #667eea;
            animation: dot 1.2s infinite ease-in-out;
        }
        .typing-dots span:nth-child(2) { animation-delay: .15s; }
        .typing-dots span:nth-child(3) { animation-delay: .3s; }
        @keyframes dot { 0%,80%,100%{transform:translateY(0);opacity:.5;} 40%{transform:translateY(-6px);opacity:1;} }

        /* Input area */
        .chat-input-area {
            display: flex; align-items: flex-end; gap: 12px;
            padding: 16px 20px; background: white;
            border-top: 1px solid #eef0f5;
        }
        .chat-textarea {
            flex: 1; resize: none;
            border: 1.5px solid #e5e7eb; border-radius: 14px;
            padding: 12px 16px; font-size: 14.5px; font-family: inherit;
            line-height: 1.5; max-height: 120px; min-height: 46px;
            outline: none; transition: border-color .25s, box-shadow .25s;
        }
        .chat-textarea:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
        }
        .send-btn {
            width: 46px; height: 46px; border-radius: 50%; border: none;
            background: linear-gradient(135deg,#667eea,#764ba2); color: white;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; flex-shrink: 0; font-size: 18px;
            box-shadow: 0 3px 10px rgba(102,126,234,0.35);
            transition: transform .2s, box-shadow .2s, opacity .2s;
        }
        .send-btn:hover:not(:disabled) { transform: translateY(-2px) scale(1.04); box-shadow: 0 5px 14px rgba(102,126,234,0.45); }
        .send-btn:disabled { opacity: .5; cursor: not-allowed; }

        .ai-footer-note {
            text-align: center; font-size: 12.5px; color: #92400e;
            background: #fef3c7; border-left: 4px solid #f59e0b;
            border-radius: 8px; padding: 12px 16px; margin-top: 18px;
        }

        @media(max-width:768px) {
            .chat-wrapper { height: 65vh; }
            .message-row { max-width: 92%; }
            .quick-actions { grid-template-columns: repeat(2,1fr); }
        }
    </style>
</head>
<body>

<?php $active_page = 'ai'; ?>
<?php include 'includes/doctor_sidebar.php'; ?>

<div style="max-width:900px; margin:auto; padding:30px 20px;">

    <!-- Header -->
    <div class="ai-page-title">
        <h2>🤖 MediMate Doctor AI</h2>
        <p>Your intelligent clinical assistant — appointments, patients, prescriptions & more</p>
    </div>

    <div class="ai-welcome-banner">
        <p class="wave-text">Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 17) ? 'Afternoon' : 'Evening'); ?>, Dr. <?php echo htmlspecialchars($doctor['last_name']); ?> 👋</p>
        <p class="ask-text">How can I assist you today?</p>
    </div>

    <!-- Quick action buttons -->
    <div class="quick-actions">
        <button class="quick-btn" data-text="Show me today's appointments">
            <span class="btn-icon">📅</span>Today's Schedule
        </button>
        <button class="quick-btn" data-text="Show my upcoming appointments for the next 7 days">
            <span class="btn-icon">🗓️</span>Upcoming Appointments
        </button>
        <button class="quick-btn" data-text="Show my recent prescriptions">
            <span class="btn-icon">💊</span>Prescriptions
        </button>
        <button class="quick-btn" data-text="What medicines are available in the system?">
            <span class="btn-icon">🏥</span>Medicine List
        </button>
        <button class="quick-btn" data-text="Show my profile and availability">
            <span class="btn-icon">👨‍⚕️</span>My Profile
        </button>
        <button class="quick-btn" data-text="Give me a summary of today's patient cases">
            <span class="btn-icon">📋</span>Patient Summary
        </button>
    </div>

    <!-- Chat container -->
    <div class="chat-wrapper">
        <div class="chat-window" id="chatWindow">

            <!-- Initial greeting -->
            <div class="message-row ai">
                <div class="avatar ai-avatar">🤖</div>
                <div class="bubble-group">
                    <div class="bubble">
                        Hello Dr. <?php echo htmlspecialchars($doctor['last_name']); ?>! I'm your MediMate AI assistant. I can help you manage appointments, view patient details, check prescriptions, and answer clinical questions. How can I help you today?
                    </div>
                    <div class="msg-timestamp" id="initialTimestamp"></div>
                </div>
            </div>

            <!-- Typing indicator -->
            <div class="typing-row" id="typingRow" style="display:none;">
                <div class="avatar ai-avatar">🤖</div>
                <div class="typing-bubble">
                    <span class="typing-text">MediMate AI is thinking</span>
                    <div class="typing-dots"><span></span><span></span><span></span></div>
                </div>
            </div>

        </div>

        <!-- Input area -->
        <div class="chat-input-area">
            <textarea id="chatInput" class="chat-textarea" placeholder="Ask about appointments, patients, medicines..." rows="1"></textarea>
            <button type="button" id="sendBtn" class="send-btn" aria-label="Send">✈️</button>
        </div>
    </div>

    <div class="ai-footer-note">
        ⚠ MediMate AI provides informational assistance only. Clinical decisions should always be based on your professional judgment.
    </div>

</div>

<script>
(function () {
    'use strict';

    const chatWindow  = document.getElementById('chatWindow');
    const chatInput   = document.getElementById('chatInput');
    const sendBtn     = document.getElementById('sendBtn');
    const typingRow   = document.getElementById('typingRow');

    const CHAT_API_URL = 'api/doctor_ai_chat.php';
    const conversationHistory = [];

    /* --- Helpers --- */
    function formatTime(d) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    function scrollBottom() { chatWindow.scrollTop = chatWindow.scrollHeight; }

    function renderMarkdown(text) {
        const div = document.createElement('div');
        div.textContent = text;
        let s = div.innerHTML;

        // ✅ / ❌ notice boxes
        s = s.replace(/(✅|❌)[^\n]*/g, function(m) {
            const ok = m.startsWith('✅');
            const c  = ok ? '#065f46' : '#991b1b';
            const bg = ok ? '#d1fae5' : '#fee2e2';
            const bc = ok ? '#10b981' : '#ef4444';
            return '<div style="margin-top:10px;padding:10px 14px;border-radius:8px;background:' + bg + ';color:' + c + ';border-left:3px solid ' + bc + ';font-size:13.5px;">' + m + '</div>';
        });

        // Navigation links — wrap page filenames in clickable anchors
        s = s.replace(/([\w_]+\.php)/g, function(match) {
            const pages = ['doctor_dashboard.php','doctor_prescriptions_list.php','doctor_chat.php','doctor_profile.php','doctor_add_education.php'];
            if (pages.includes(match)) {
                return '<a href="' + match + '" style="color:#667eea;font-weight:600;text-decoration:underline;">' + match + '</a>';
            }
            return match;
        });

        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
        s = s.replace(/\n/g, '<br>');
        return s;
    }

    document.getElementById('initialTimestamp').textContent = formatTime(new Date());

    /* --- Render message --- */
    function appendMessage(text, sender, isError) {
        const row    = document.createElement('div');
        row.className = 'message-row ' + sender;

        const avatar = document.createElement('div');
        avatar.className = 'avatar ' + (sender === 'user' ? 'user-avatar' : 'ai-avatar');
        avatar.textContent = sender === 'user' ? '👨‍⚕️' : '🤖';

        const group  = document.createElement('div');
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
        time.textContent = formatTime(new Date());

        group.appendChild(bubble);
        group.appendChild(time);
        row.appendChild(avatar);
        row.appendChild(group);
        chatWindow.insertBefore(row, typingRow);
        scrollBottom();
    }

    function showTyping(show) {
        typingRow.style.display = show ? 'flex' : 'none';
        if (show) scrollBottom();
    }
    function setSending(b) { sendBtn.disabled = b; chatInput.disabled = b; }

    /* --- Quick action buttons --- */
    document.querySelectorAll('.quick-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            chatInput.value = btn.dataset.text || btn.textContent.trim();
            chatInput.focus();
            autoResize();
        });
    });

    /* --- Textarea auto-resize --- */
    function autoResize() {
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
    }
    chatInput.addEventListener('input', autoResize);

    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    sendBtn.addEventListener('click', sendMessage);

    /* --- Send --- */
    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        appendMessage(text, 'user');
        chatInput.value = '';
        autoResize();

        conversationHistory.push({ role: 'user', content: text });
        if (conversationHistory.length > 20) conversationHistory.splice(0, 2);

        setSending(true);
        showTyping(true);

        try {
            const historyToSend = conversationHistory.slice(0, -1).map(function(t) {
                return { role: t.role === 'ai' ? 'assistant' : t.role, content: t.content };
            });

            const response = await fetch(CHAT_API_URL, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ message: text, history: historyToSend })
            });

            showTyping(false);

            if (!response.ok) {
                const err = await response.text();
                throw new Error('HTTP ' + response.status + ' — ' + err);
            }

            const data = await response.json();

            if (data && typeof data.reply === 'string' && data.reply.length > 0) {
                appendMessage(data.reply, 'ai');
                conversationHistory.push({ role: 'assistant', content: data.reply });
            } else {
                appendMessage('Sorry, the AI service is currently unavailable.', 'ai', true);
                conversationHistory.pop();
            }

        } catch (err) {
            console.error('Doctor AI error:', err);
            showTyping(false);
            appendMessage('Sorry, something went wrong: ' + err.message, 'ai', true);
            conversationHistory.pop();
        } finally {
            setSending(false);
            chatInput.focus();
        }
    }

    scrollBottom();
})();
</script>

</body>
</html>
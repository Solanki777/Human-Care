<?php
/**
 * admin_ai_assistant.php
 * MediMate Admin AI Assistant Page
 */
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$hour       = (int)date('H');
$greeting   = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Admin Panel | Human Care</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .admin-main { margin-left:260px; padding:30px; min-height:100vh; background:#f5f7fb; }

        .page-header {
            background: linear-gradient(135deg,#1e3c72,#2a5298);
            color: white; padding: 30px; border-radius: 14px;
            margin-bottom: 25px; display:flex; align-items:center; gap:20px;
        }
        .page-header .hdr-icon { font-size:52px; }
        .page-header h2 { margin:0 0 6px; font-size:26px; }
        .page-header p  { margin:0; opacity:.9; font-size:14px; }

        .welcome-banner {
            background: white; border-radius: 12px;
            padding: 20px 25px; margin-bottom: 22px;
            border-left: 5px solid #2a5298;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            display: flex; align-items: center; justify-content: space-between;
        }
        .welcome-banner .wt { font-size:18px; font-weight:700; color:#1e3c72; }
        .welcome-banner .ws { font-size:13px; color:#666; margin-top:3px; }

        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px,1fr));
            gap: 12px; margin-bottom: 22px;
        }
        .qbtn {
            background: white; border: 2px solid #e5e7eb;
            border-radius: 10px; padding: 14px 10px;
            font-size: 13px; font-weight: 600; color: #333;
            cursor: pointer; text-align: center;
            transition: all .2s; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .qbtn:hover { border-color:#2a5298; background:#f0f4ff; transform:translateY(-2px); }
        .qbtn .qi  { font-size:22px; display:block; margin-bottom:6px; }

        /* Chat */
        .chat-wrapper {
            background: white; border-radius: 14px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            display: flex; flex-direction: column;
            overflow: hidden; height: 58vh; min-height: 420px;
        }
        .chat-window {
            flex:1; overflow-y:auto; padding:25px;
            display:flex; flex-direction:column; gap:16px;
            background:#f9fafb; scroll-behavior:smooth;
        }
        .chat-window::-webkit-scrollbar { width:8px; }
        .chat-window::-webkit-scrollbar-thumb { background:#bfdbfe; border-radius:10px; }

        .message-row {
            display:flex; align-items:flex-end; gap:10px; max-width:82%;
            opacity:0; transform:translateY(12px);
            animation:msgIn .35s ease forwards;
        }
        @keyframes msgIn { to{ opacity:1; transform:translateY(0); } }
        .message-row.user { align-self:flex-end; flex-direction:row-reverse; margin-left:auto; }
        .message-row.ai   { align-self:flex-start; margin-right:auto; }

        .avatar {
            width:36px; height:36px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:18px; flex-shrink:0;
        }
        .avatar.ai-av   { background:linear-gradient(135deg,#1e3c72,#2a5298); color:white; }
        .avatar.user-av { background:#dbeafe; color:#1e3c72; }

        .bubble-group { display:flex; flex-direction:column; }
        .message-row.user .bubble-group { align-items:flex-end; }
        .message-row.ai   .bubble-group { align-items:flex-start; }

        .bubble {
            padding:13px 18px; border-radius:16px;
            font-size:14.5px; line-height:1.6;
            word-wrap:break-word; white-space:pre-wrap;
        }
        .message-row.ai .bubble {
            background:white; color:#333;
            border:1px solid #eef0f5; border-bottom-left-radius:4px;
            box-shadow:0 2px 6px rgba(0,0,0,0.04);
        }
        .message-row.user .bubble {
            background:linear-gradient(135deg,#1e3c72,#2a5298); color:white;
            border-bottom-right-radius:4px;
        }
        .bubble.error-bubble { background:#fee2e2!important; color:#991b1b!important; border:1px solid #fecaca!important; }
        .msg-ts { font-size:11.5px; color:#9aa0ab; margin-top:5px; padding:0 4px; }

        .typing-row {
            display:flex; align-items:center; gap:10px;
            opacity:0; transform:translateY(10px);
            animation:msgIn .3s ease forwards;
        }
        .typing-bubble {
            display:flex; align-items:center; gap:8px;
            background:white; border:1px solid #eef0f5;
            padding:12px 18px; border-radius:16px;
            border-bottom-left-radius:4px;
        }
        .typing-text { font-size:13px; color:#888; font-style:italic; }
        .typing-dots { display:flex; gap:4px; }
        .typing-dots span {
            width:7px; height:7px; border-radius:50%; background:#2a5298;
            animation:dot 1.2s infinite ease-in-out;
        }
        .typing-dots span:nth-child(2) { animation-delay:.15s; }
        .typing-dots span:nth-child(3) { animation-delay:.3s; }
        @keyframes dot { 0%,80%,100%{transform:translateY(0);opacity:.5;} 40%{transform:translateY(-6px);opacity:1;} }

        .chat-input-area {
            display:flex; align-items:flex-end; gap:12px;
            padding:16px 20px; background:white; border-top:1px solid #eef0f5;
        }
        .chat-textarea {
            flex:1; resize:none; border:1.5px solid #e5e7eb; border-radius:14px;
            padding:12px 16px; font-size:14.5px; font-family:inherit;
            line-height:1.5; max-height:120px; min-height:46px;
            outline:none; transition:border-color .25s, box-shadow .25s;
        }
        .chat-textarea:focus { border-color:#2a5298; box-shadow:0 0 0 3px rgba(42,82,152,0.12); }
        .send-btn {
            width:46px; height:46px; border-radius:50%; border:none;
            background:linear-gradient(135deg,#1e3c72,#2a5298); color:white;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; flex-shrink:0; font-size:18px;
            box-shadow:0 3px 10px rgba(30,60,114,0.35);
            transition:transform .2s, opacity .2s;
        }
        .send-btn:hover:not(:disabled) { transform:translateY(-2px) scale(1.04); }
        .send-btn:disabled { opacity:.5; cursor:not-allowed; }

        .footer-note {
            text-align:center; font-size:12.5px; color:#92400e;
            background:#fef3c7; border-left:4px solid #f59e0b;
            border-radius:8px; padding:12px 16px; margin-top:18px;
        }

        @media(max-width:768px) {
            .admin-main { margin-left:0; padding:15px; }
            .chat-wrapper { height:65vh; }
            .quick-actions { grid-template-columns:repeat(2,1fr); }
            .page-header { flex-direction:column; text-align:center; }
        }
    </style>
</head>
<body>

<?php
$active_page = 'ai';
require_once 'config/config.php';
include 'includes/admin_sidebar.php';
?>

<div class="admin-main">

    <!-- Header -->
    <div class="page-header">
        <div class="hdr-icon">🤖</div>
        <div>
            <h2>MediMate Admin AI</h2>
            <p>Complete hospital management at your fingertips — approve doctors, manage appointments, view analytics and more</p>
        </div>
    </div>

    <!-- Welcome -->
    <div class="welcome-banner">
        <div>
            <div class="wt"><?php echo $greeting; ?>, <?php echo htmlspecialchars($admin_name); ?>! 👋</div>
            <div class="ws">Ask me anything about the hospital system. I can take actions on your behalf after confirmation.</div>
        </div>
        <div style="font-size:40px;">🛡️</div>
    </div>

    <!-- Quick actions -->
    <div class="quick-actions">
        <button class="qbtn" data-text="Show me the hospital statistics summary">
            <span class="qi">📊</span>Hospital Stats
        </button>
        <button class="qbtn" data-text="Show all pending doctor applications">
            <span class="qi">👨‍⚕️</span>Pending Doctors
        </button>
        <button class="qbtn" data-text="Show all pending appointments that need approval">
            <span class="qi">📅</span>Pending Appointments
        </button>
        <button class="qbtn" data-text="Show recent patient registrations">
            <span class="qi">👥</span>Recent Patients
        </button>
        <button class="qbtn" data-text="Show recent activity log">
            <span class="qi">📋</span>Activity Log
        </button>
        <button class="qbtn" data-text="Show all approved doctors list">
            <span class="qi">✅</span>Approved Doctors
        </button>
    </div>

    <!-- Chat -->
    <div class="chat-wrapper">
        <div class="chat-window" id="chatWindow">

            <div class="message-row ai">
                <div class="avatar ai-av">🤖</div>
                <div class="bubble-group">
                    <div class="bubble">
                        Hello, <?php echo htmlspecialchars($admin_name); ?>! I'm your MediMate Admin AI. I have full visibility into the hospital system and can help you manage doctors, appointments, and patients. I can also take actions like approving or rejecting requests — but I'll always ask for your confirmation first. How can I help you today?
                    </div>
                    <div class="msg-ts" id="initialTs"></div>
                </div>
            </div>

            <div class="typing-row" id="typingRow" style="display:none;">
                <div class="avatar ai-av">🤖</div>
                <div class="typing-bubble">
                    <span class="typing-text">MediMate AI is thinking</span>
                    <div class="typing-dots"><span></span><span></span><span></span></div>
                </div>
            </div>

        </div>

        <div class="chat-input-area">
            <textarea id="chatInput" class="chat-textarea" placeholder="Ask about stats, pending requests, appointments..." rows="1"></textarea>
            <button type="button" id="sendBtn" class="send-btn" aria-label="Send">✈️</button>
        </div>
    </div>

    <div class="footer-note">
        ⚠ MediMate Admin AI can take real actions on the database. Always review before confirming.
    </div>

</div>

<script>
(function () {
    'use strict';

    const chatWindow = document.getElementById('chatWindow');
    const chatInput  = document.getElementById('chatInput');
    const sendBtn    = document.getElementById('sendBtn');
    const typingRow  = document.getElementById('typingRow');

    const CHAT_API_URL = 'api/admin_ai_chat.php';
    const conversationHistory = [];

    function formatTime(d) { return d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }); }
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
            return '<div style="margin-top:10px;padding:10px 14px;border-radius:8px;background:'+bg+';color:'+c+';border-left:3px solid '+bc+';font-size:13.5px;">'+m+'</div>';
        });

        // Clickable page links
        const pages = ['admin_dashboard.php','admin_doctors.php','admin_patients.php',
                       'admin_appointments.php','admin_manage_education.php','admin_ai_assistant.php'];
        s = s.replace(/([\w_]+\.php)/g, function(match) {
            if (pages.includes(match)) {
                return '<a href="'+match+'" style="color:#2a5298;font-weight:600;text-decoration:underline;">'+match+'</a>';
            }
            return match;
        });

        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
        s = s.replace(/\n/g, '<br>');
        return s;
    }

    document.getElementById('initialTs').textContent = formatTime(new Date());

    function appendMessage(text, sender, isError) {
        const row = document.createElement('div');
        row.className = 'message-row ' + sender;

        const avatar = document.createElement('div');
        avatar.className = 'avatar ' + (sender === 'user' ? 'user-av' : 'ai-av');
        avatar.textContent = sender === 'user' ? '👨‍💼' : '🤖';

        const group  = document.createElement('div');
        group.className = 'bubble-group';

        const bubble = document.createElement('div');
        bubble.className = 'bubble' + (isError ? ' error-bubble' : '');
        if (sender === 'ai' && !isError) bubble.innerHTML = renderMarkdown(text);
        else bubble.textContent = text;

        const ts = document.createElement('div');
        ts.className = 'msg-ts';
        ts.textContent = formatTime(new Date());

        group.appendChild(bubble);
        group.appendChild(ts);
        row.appendChild(avatar);
        row.appendChild(group);
        chatWindow.insertBefore(row, typingRow);
        scrollBottom();
    }

    function showTyping(v) { typingRow.style.display = v ? 'flex' : 'none'; if (v) scrollBottom(); }
    function setSending(v) { sendBtn.disabled = v; chatInput.disabled = v; }

    document.querySelectorAll('.qbtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            chatInput.value = btn.dataset.text || '';
            chatInput.focus(); autoResize();
        });
    });

    function autoResize() {
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
    }
    chatInput.addEventListener('input', autoResize);
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    sendBtn.addEventListener('click', sendMessage);

    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        appendMessage(text, 'user');
        chatInput.value = ''; autoResize();

        conversationHistory.push({ role: 'user', content: text });
        if (conversationHistory.length > 20) conversationHistory.splice(0, 2);

        setSending(true); showTyping(true);

        try {
            const historyToSend = conversationHistory.slice(0, -1).map(function(t) {
                return { role: t.role === 'ai' ? 'assistant' : t.role, content: t.content };
            });

            const response = await fetch(CHAT_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text, history: historyToSend })
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
            console.error('Admin AI error:', err);
            showTyping(false);
            appendMessage('Something went wrong: ' + err.message, 'ai', true);
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
<?php
/* =====================================================================
   ai_assistant.php
   MediMate AI Assistant page for Human Care Hospital Management System
   - Matches the visual theme used in patient_appointment.php
   - PHP handles auth/session + sidebar include only.
   - Chat logic is pure JS (fetch to api/chat.php). No backend built yet.
   ===================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

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
    <link rel="stylesheet" href="styles/ai_assis.css">
    <link rel="stylesheet" href="styles/sidebar.css">

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
    
    
    <script src="js/main.js"></script>
    <script src="js/ai_assis.js"></script>

</body>

</html>
<?php
/**
 * doctor_ai_assistant.php
 * MediMate Doctor AI Assistant Page
 */

require_once __DIR__ .'/../config/config.php';
require_once __DIR__ .'/../classes/Database.php';
require_once __DIR__ .'/../classes/Auth.php';

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
    <title>AI Assistant - Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
    </title>

    <link rel="stylesheet" href="styles/dashboard.css">   
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/doc_ai.css">
    
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
            <p class="wave-text">Good
                <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 17) ? 'Afternoon' : 'Evening'); ?>, Dr.
                <?php echo htmlspecialchars($doctor['last_name']); ?> 👋
            </p>
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
                            Hello Dr. <?php echo htmlspecialchars($doctor['last_name']); ?>! I'm your MediMate AI
                            assistant. I can help you manage appointments, view patient details, check prescriptions,
                            and answer clinical questions. How can I help you today?
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
                <textarea id="chatInput" class="chat-textarea"
                    placeholder="Ask about appointments, patients, medicines..." rows="1"></textarea>
                <button type="button" id="sendBtn" class="send-btn" aria-label="Send">✈️</button>
            </div>
        </div>

        <div class="ai-footer-note">
            ⚠ MediMate AI provides informational assistance only. Clinical decisions should always be based on your
            professional judgment.
        </div>

    </div>
    <script src="js/doc_ai.js"></script>

    
</body>

</html>
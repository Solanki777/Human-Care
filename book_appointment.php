<?php
require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'classes/Database.php';
require_once 'classes/Validator.php';
require_once 'classes/AppointmentService.php';

Auth::require('patient');

$success = "";
$error = "";
$doctors = [];

// ------------------------------------------------------------------ //
// Load patient info (display only — read once for the form)
// ------------------------------------------------------------------ //
$patient_id = Auth::id();
$patients_conn = Database::getConnection('patients');
$stmt = $patients_conn->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ------------------------------------------------------------------ //
// Load approved doctors for the dropdown
// ------------------------------------------------------------------ //
$doctors_conn = Database::getConnection('doctors');
$doctors_result = $doctors_conn->query("
    SELECT id, first_name, last_name, specialty, consultation_fee, available_days, available_time
    FROM doctors
    WHERE is_verified = 1 AND verification_status = 'approved' AND is_deleted = 0
    ORDER BY specialty, last_name
");

if ($doctors_result) {
    $doctors = $doctors_result->fetch_all(MYSQLI_ASSOC);
}

// ------------------------------------------------------------------ //
// Handle form submission
// ------------------------------------------------------------------ //
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate form fields
    $validator = new Validator();
    $valid = $validator->validate($_POST, [
        'doctor_id' => 'required|numeric',
        'appointment_date' => 'required|date|futureDate',
        'appointment_time' => 'required',
        'reason' => 'required|min:10|max:500',
        'consultation_type' => 'required',
    ]);

    if (!$valid) {
        $error = $validator->firstError();
    } else {

        // Sanitize inputs
        $doctor_id = intval($_POST['doctor_id']);
        $appointment_date = Validator::sanitize($_POST['appointment_date']);
        $appointment_time = Validator::sanitize($_POST['appointment_time']);
        $consultation_type = Validator::sanitize($_POST['consultation_type']);
        $reason = Validator::sanitize($_POST['reason']);
        $symptoms = Validator::sanitize($_POST['symptoms'] ?? '');

        // Delegate all booking logic to the service
        $result = AppointmentService::createAppointment(
            $patient_id,
            $doctor_id,
            $appointment_date,
            $appointment_time,
            $consultation_type,
            $reason,
            $symptoms
        );

        if ($result['success']) {
            $success = $result['message'];
            $_POST = []; // Clear form on success
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="styles/main.css">
    <style>
        .booking-container {
            max-width: 800px;
            margin: 100px auto 50px;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .booking-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .booking-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .booking-header p {
            color: #666;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .required {
            color: #ef4444;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .doctor-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            display: none;
        }

        .doctor-info.active {
            display: block;
        }

        .consultation-types {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .consultation-type {
            position: relative;
        }

        .consultation-type input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .consultation-type label {
            display: block;
            padding: 15px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .consultation-type input[type="radio"]:checked+label {
            background: #e0e7ff;
            border-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <?php $active_page = 'book'; ?>

    <?php include 'includes/public_sidebar.php'; ?>

    <div class="booking-container">
        <div class="booking-header">
            <h1>📅 Book an Appointment</h1>
            <p>Schedule your consultation with our expert doctors</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <!-- Patient Information -->
            <div class="form-section">
                <div class="section-title">
                    👤 Patient Information
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text"
                            value="<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>"
                            disabled>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($patient['email']); ?>" disabled>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" value="<?php echo htmlspecialchars($patient['phone']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="text"
                            value="<?php echo (new DateTime($patient['dob']))->diff(new DateTime())->y; ?> years"
                            disabled>
                    </div>
                </div>
            </div>

            <!-- Doctor Selection -->
            <div class="form-section">
                <div class="section-title">
                    👨‍⚕️ Select Doctor
                </div>

                <div class="form-group">
                    <label>Choose Doctor <span class="required">*</span></label>
                    <select name="doctor_id" id="doctorSelect" required onchange="showDoctorInfo(this.value)">
                        <option value="">-- Select a Doctor --</option>
                        <?php
                        $specialties = [];
                        foreach ($doctors as $doctor) {
                            if (!in_array($doctor['specialty'], $specialties)) {
                                if (!empty($specialties))
                                    echo '</optgroup>';
                                echo '<optgroup label="' . htmlspecialchars($doctor['specialty']) . '">';
                                $specialties[] = $doctor['specialty'];
                            }
                            echo '<option value="' . $doctor['id'] . '"'
                                . ' data-specialty="' . htmlspecialchars($doctor['specialty']) . '"'
                                . ' data-fee="' . $doctor['consultation_fee'] . '"'
                                . ' data-days="' . htmlspecialchars($doctor['available_days']) . '"'
                                . ' data-time="' . htmlspecialchars($doctor['available_time']) . '">';
                            echo 'Dr. ' . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']);
                            if ($doctor['consultation_fee']) {
                                echo ' - ₹' . number_format($doctor['consultation_fee']);
                            }
                            echo '</option>';
                        }
                        if (!empty($specialties))
                            echo '</optgroup>';
                        ?>
                    </select>
                    <div class="form-hint">Select the doctor you want to consult</div>
                </div>

                <div id="doctorInfo" class="doctor-info"></div>
            </div>

            <!-- Appointment Details -->
            <div class="form-section">
                <div class="section-title">
                    📅 Appointment Date &amp; Time
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Preferred Date <span class="required">*</span></label>
                        <input type="date" name="appointment_date" required
                            min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                            max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                            value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? ''); ?>">
                        <div class="form-hint">Select a date within the next 30 days</div>
                    </div>

                    <div class="form-group">
                        <label>Preferred Time <span class="required">*</span></label>
                        <select name="appointment_time" required>
                            <option value="">-- Select Time --</option>
                            <option value="09:00:00">9:00 AM</option>
                            <option value="09:30:00">9:30 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="10:30:00">10:30 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="11:30:00">11:30 AM</option>
                            <option value="12:00:00">12:00 PM</option>
                            <option value="14:00:00">2:00 PM</option>
                            <option value="14:30:00">2:30 PM</option>
                            <option value="15:00:00">3:00 PM</option>
                            <option value="15:30:00">3:30 PM</option>
                            <option value="16:00:00">4:00 PM</option>
                            <option value="16:30:00">4:30 PM</option>
                            <option value="17:00:00">5:00 PM</option>
                        </select>
                        <div class="form-hint">Select your preferred time slot</div>
                    </div>
                </div>
            </div>

            <!-- Consultation Type -->
            <div class="form-section">
                <div class="section-title">
                    💼 Consultation Type
                </div>

                <div class="consultation-types">
                    <div class="consultation-type">
                        <input type="radio" id="in-person" name="consultation_type" value="in-person" checked>
                        <label for="in-person">
                            <div style="font-size: 24px; margin-bottom: 5px;">🏥</div>
                            <div>In-Person</div>
                            <div style="font-size: 11px; color: #999; margin-top: 5px;">Visit hospital</div>
                        </label>
                    </div>

                    <div class="consultation-type">
                        <input type="radio" id="online" name="consultation_type" value="online">
                        <label for="online">
                            <div style="font-size: 24px; margin-bottom: 5px;">💻</div>
                            <div>Online</div>
                            <div style="font-size: 11px; color: #999; margin-top: 5px;">Video call</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Reason for Visit -->
            <div class="form-section">
                <div class="section-title">
                    📝 Reason for Visit
                </div>

                <div class="form-group">
                    <label>Chief Complaint <span class="required">*</span></label>
                    <textarea name="reason" required placeholder="Please describe your main health concern..."
                        maxlength="500"><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                    <div class="form-hint">Minimum 10 characters, maximum 500 characters</div>
                </div>

                <div class="form-group">
                    <label>Additional Symptoms (Optional)</label>
                    <textarea name="symptoms" placeholder="Any other symptoms or information you'd like to share..."
                        maxlength="1000"><?php echo htmlspecialchars($_POST['symptoms'] ?? ''); ?></textarea>
                    <div class="form-hint">This helps the doctor prepare for your consultation</div>
                </div>
            </div>

            <!-- Important Notice -->
            <div
                style="background: #fef3c7; padding: 15px; border-radius: 10px; border-left: 4px solid #f59e0b; margin-bottom: 25px;">
                <strong>⏳ Please Note:</strong>
                <p style="margin: 10px 0 0 0; font-size: 14px; color: #92400e;">
                    Your appointment request will be reviewed by our admin team. You will receive a confirmation email
                    once approved. This typically takes less than 24 hours.
                </p>
            </div>

            <button type="submit" class="submit-btn">
                📅 Submit Appointment Request
            </button>
        </form>
    </div>

    <!-- ================================================================
     UPDATED JAVASCRIPT — Replace the existing <script> block in
     ai_assistant.php with this one.

     Changes vs original:
       - Maintains a `conversationHistory` array so Claude has
         multi-turn context (needed for booking flows).
       - Passes { message, history } to api/chat.php.
       - Renders Markdown-style bold (**text**) from the AI reply.
       - Action result notices (✅ / ❌) are rendered in a styled box.
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

            // In-memory conversation history for multi-turn context
            const conversationHistory = [];

            /* ---------- Helpers ---------- */

            function formatTimestamp(date) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function scrollToBottom() {
                chatWindow.scrollTop = chatWindow.scrollHeight;
            }

            /**
             * Minimal Markdown renderer for AI replies.
             * Supports: **bold**, *italic*, line-breaks, ✅/❌ notice blocks.
             */
            function renderMarkdown(text) {
                // Escape HTML first
                const div = document.createElement('div');
                div.textContent = text;
                let escaped = div.innerHTML;

                // Action result notice lines (lines starting with ✅ or ❌)
                escaped = escaped.replace(
                    /(✅|❌)[^\n]*/g,
                    (match) => {
                        const isSuccess = match.startsWith('✅');
                        const color = isSuccess ? '#065f46' : '#991b1b';
                        const bg = isSuccess ? '#d1fae5' : '#fee2e2';
                        const border = isSuccess ? '#10b981' : '#ef4444';
                        return `<div style="margin-top:10px;padding:10px 14px;border-radius:8px;background:${bg};color:${color};border-left:3px solid ${border};font-size:13.5px;">${match}</div>`;
                    }
                );

                // **bold**
                escaped = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                // *italic*
                escaped = escaped.replace(/\*(.+?)\*/g, '<em>$1</em>');
                // newlines → <br>
                escaped = escaped.replace(/\n/g, '<br>');

                return escaped;
            }

            // Set timestamp on the initial greeting
            document.getElementById('initialTimestamp').textContent = formatTimestamp(new Date());

            /* ---------- Message rendering ---------- */

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

            /* ---------- Send + fetch ---------- */

            async function sendMessage() {
                const text = chatInput.value.trim();
                if (!text) return;

                appendMessage(text, 'user');
                chatInput.value = '';
                autoResizeTextarea();

                // Add to history BEFORE the API call
                conversationHistory.push({ role: 'user', content: text });

                // Keep history to last 20 turns to avoid huge payloads
                if (conversationHistory.length > 20) conversationHistory.splice(0, 2);

                setSending(true);
                showTyping(true);

                try {
                    const response = await fetch(CHAT_API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            message: text,
                            history: conversationHistory.slice(0, -1) // all but current
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
                        // Add assistant reply to history
                        conversationHistory.push({ role: 'assistant', content: data.reply });
                    } else {
                        appendMessage('Sorry, the AI service is currently unavailable.', 'ai', true);
                    }

                } catch (err) {
                    console.error(err);
                    showTyping(false);
                    appendMessage('Sorry, something went wrong: ' + err.message, 'ai', true);
                    // Remove the failed user turn from history
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
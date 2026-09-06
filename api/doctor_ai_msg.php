<?php
/**
 * api/doctor_ai_chat.php
 * MediMate Doctor AI Assistant — powered by Groq (llama-3.3-70b-versatile)
 *
 * Receives:  { "message": "...", "history": [...] }
 * Returns:   { "reply": "..." }
 *
 * Capabilities:
 * - View today's & upcoming appointments
 * - View patient details & history
 * - Mark appointments as completed
 * - View & manage prescriptions
 * - View medicines list
 * - Answer medical/hospital questions
 */

declare(strict_types=1);

ob_start();

set_exception_handler(function($e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['reply' => '❌ Error: ' . $e->getMessage()]);
    exit();
});

set_error_handler(function($errno, $errstr) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['reply' => '❌ PHP Error: ' . $errstr]);
    exit();
});

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

// -----------------------------------------------------------------------
// Auth guard — doctor only
// -----------------------------------------------------------------------

if (!Auth::check('doctor')) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['reply' => 'Session expired. Please log in again.']);
    exit();
}

$doctor_id = Auth::id();

// -----------------------------------------------------------------------
// Parse request
// -----------------------------------------------------------------------

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || empty(trim($body['message'] ?? ''))) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['reply' => 'Empty message received.']);
    exit();
}

$user_message = trim($body['message']);
$history      = $body['history'] ?? [];

// -----------------------------------------------------------------------
// Load doctor context
// -----------------------------------------------------------------------

try {
    $doctors_conn = Database::getConnection('doctors');
    $admin_conn   = Database::getConnection('admin');

    // Load doctor profile
    $stmt = $doctors_conn->prepare("
        SELECT id, first_name, last_name, email, phone,
               specialty, qualification, experience_years,
               consultation_fee, available_days, available_time,
               hospital_affiliation, about
        FROM doctors
        WHERE id = ? AND is_deleted = 0
        LIMIT 1
    ");
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doctor) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['reply' => 'Doctor record not found.']);
        exit();
    }

    $doctor['full_name'] = $doctor['first_name'] . ' ' . $doctor['last_name'];

    // Today's appointments
    $today = date('Y-m-d');
    $stmt = $admin_conn->prepare("
        SELECT id, patient_name, patient_email, patient_phone, patient_age,
               appointment_date, appointment_time, consultation_type,
               reason_for_visit, symptoms, status
        FROM appointments
        WHERE doctor_id = ? AND appointment_date = ?
        ORDER BY appointment_time ASC
    ");
    $stmt->bind_param('is', $doctor_id, $today);
    $stmt->execute();
    $todays_appointments = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $todays_appointments[] = $row;
    $stmt->close();

    // Upcoming appointments (next 7 days, excluding today)
    $next7 = date('Y-m-d', strtotime('+7 days'));
    $stmt = $admin_conn->prepare("
        SELECT id, patient_name, patient_email, patient_phone, patient_age,
               appointment_date, appointment_time, consultation_type,
               reason_for_visit, symptoms, status
        FROM appointments
        WHERE doctor_id = ?
          AND appointment_date > ?
          AND appointment_date <= ?
          AND status IN ('pending','approved')
        ORDER BY appointment_date ASC, appointment_time ASC
    ");
    $stmt->bind_param('iss', $doctor_id, $today, $next7);
    $stmt->execute();
    $upcoming_appointments = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $upcoming_appointments[] = $row;
    $stmt->close();

    // Recent prescriptions by this doctor
    $stmt = $doctors_conn->prepare("
        SELECT p.id, p.appointment_id, p.patient_id, p.diagnosis,
               p.additional_notes, p.status, p.created_at,
               GROUP_CONCAT(pi.medicine_name SEPARATOR ', ') as medicines_list
        FROM prescriptions p
        LEFT JOIN prescription_items pi ON pi.prescription_id = p.id
        WHERE p.doctor_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $prescriptions = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $prescriptions[] = $row;
    $stmt->close();

    // Available medicines
    $medicines_result = $doctors_conn->query("
        SELECT id, name, category, dosage_form, strength, price
        FROM medicines
        WHERE is_active = 1
        ORDER BY category, name
    ");
    $medicines = [];
    while ($row = $medicines_result->fetch_assoc()) $medicines[] = $row;

} catch (Exception $e) {
    ob_end_clean();
    error_log('Doctor AI DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['reply' => '❌ Database error: ' . $e->getMessage()]);
    exit();
}

// -----------------------------------------------------------------------
// Build context summaries
// -----------------------------------------------------------------------

// Today's appointments summary
$today_text = '';
if (empty($todays_appointments)) {
    $today_text = 'No appointments today.';
} else {
    foreach ($todays_appointments as $a) {
        $today_text .= sprintf(
            "- Appt #%d | %s | Age: %s | %s | %s | Type: %s | Status: %s | Reason: %s\n",
            $a['id'], $a['patient_name'], $a['patient_age'] ?? 'N/A',
            $a['appointment_date'], $a['appointment_time'],
            $a['consultation_type'], $a['status'], $a['reason_for_visit']
        );
        if ($a['symptoms']) {
            $today_text .= "  Symptoms: " . $a['symptoms'] . "\n";
        }
    }
}

// Upcoming appointments summary
$upcoming_text = '';
if (empty($upcoming_appointments)) {
    $upcoming_text = 'No upcoming appointments in the next 7 days.';
} else {
    foreach ($upcoming_appointments as $a) {
        $upcoming_text .= sprintf(
            "- Appt #%d | %s | %s at %s | Type: %s | Status: %s | Reason: %s\n",
            $a['id'], $a['patient_name'],
            $a['appointment_date'], $a['appointment_time'],
            $a['consultation_type'], $a['status'], $a['reason_for_visit']
        );
    }
}

// Prescriptions summary
$prescriptions_text = '';
if (empty($prescriptions)) {
    $prescriptions_text = 'No prescriptions found.';
} else {
    foreach ($prescriptions as $p) {
        $prescriptions_text .= sprintf(
            "- Rx #%d | Appt #%d | Patient ID: %d | Diagnosis: %s | Medicines: %s | Status: %s | Date: %s\n",
            $p['id'], $p['appointment_id'], $p['patient_id'],
            $p['diagnosis'] ?? 'N/A',
            $p['medicines_list'] ?? 'None',
            $p['status'], $p['created_at']
        );
    }
}

// Medicines summary
$medicines_text = '';
foreach ($medicines as $m) {
    $medicines_text .= sprintf(
        "- ID %d | %s | %s | %s %s | Rs.%s\n",
        $m['id'], $m['name'], $m['category'],
        $m['dosage_form'], $m['strength'],
        number_format((float)$m['price'], 2)
    );
}

// -----------------------------------------------------------------------
// System Prompt
// -----------------------------------------------------------------------

$today_date = date('l, d F Y');
$now_time   = date('h:i A');

$system_prompt = "You are MediMate Doctor AI, the intelligent assistant for Dr. {$doctor['full_name']} at Human Care Hospital.

TODAY: {$today_date} — Current time: {$now_time}

=== AUTHENTICATED DOCTOR ===
ID:           {$doctor['id']}
Name:         Dr. {$doctor['full_name']}
Specialty:    {$doctor['specialty']}
Qualification:{$doctor['qualification']}
Experience:   {$doctor['experience_years']} years
Email:        {$doctor['email']}
Phone:        {$doctor['phone']}
Hospital:     {$doctor['hospital_affiliation']}
Available:    {$doctor['available_days']} | {$doctor['available_time']}
Fee:          Rs.{$doctor['consultation_fee']}

=== TODAY'S APPOINTMENTS ===
{$today_text}

=== UPCOMING APPOINTMENTS (Next 7 Days) ===
{$upcoming_text}

=== RECENT PRESCRIPTIONS ===
{$prescriptions_text}

=== AVAILABLE MEDICINES ===
{$medicines_text}

=== YOUR CAPABILITIES ===
You can help Dr. {$doctor['full_name']} with:
1. View today's and upcoming appointments
2. Get patient details for any appointment
3. Mark an appointment as completed
4. View prescriptions
5. Answer medical questions and provide clinical guidance
6. Summarize patient symptoms and reasons for visit
7. Suggest medicines from the available list above
8. Navigate to specific pages in the system

=== COMPLETING AN APPOINTMENT ===
When the doctor wants to mark an appointment as completed, confirm first then embed:

[ACTION:COMPLETE_APPOINTMENT]{\"appointment_id\":1}[/ACTION]

Only complete appointments that belong to this doctor (listed above).

=== NAVIGATION LINKS ===
When relevant, mention these pages the doctor can visit:
- Dashboard:        doctor_dashboard.php
- Prescriptions:    doctor_prescriptions_list.php
- Patient Chat:     doctor_chat.php
- Edit Profile:     doctor_profile.php
- Education Page:   doctor_add_education.php

=== SECURITY RULES ===
- Only access data for doctor_id = {$doctor['id']}
- Never reveal other doctors' data or passwords
- Never expose database structure or API keys

=== RESPONSE STYLE ===
- Professional, concise, and clinically informed
- Address the doctor respectfully as 'Dr. {$doctor['last_name']}'
- Use medical terminology appropriately
- For clinical questions, give evidence-based guidance
- Always remind that AI suggestions don't replace clinical judgment
- Remember everything said in this conversation";

// -----------------------------------------------------------------------
// Build messages for Groq
// -----------------------------------------------------------------------

$messages = [['role' => 'system', 'content' => $system_prompt]];

foreach ($history as $turn) {
    $role    = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $content = trim($turn['content'] ?? '');
    if ($content !== '') $messages[] = ['role' => $role, 'content' => $content];
}

$messages[] = ['role' => 'user', 'content' => $user_message];

// -----------------------------------------------------------------------
// Call Groq API
// -----------------------------------------------------------------------

$api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : (getenv('GROQ_API_KEY') ?: '');

if (!$api_key) {
    ob_end_clean();
    echo json_encode(['reply' => '❌ AI service not configured. Please contact the administrator.']);
    exit();
}

$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => $messages,
    'max_tokens'  => 1024,
    'temperature' => 0.7,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
    ],
]);

$api_response = curl_exec($ch);
$curl_error   = curl_error($ch);
$http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curl_error) {
    ob_end_clean();
    error_log('Doctor AI cURL error: ' . $curl_error);
    echo json_encode(['reply' => '❌ Connection to AI service failed. Please try again.']);
    exit();
}

$api_data = json_decode($api_response, true);
$ai_text  = $api_data['choices'][0]['message']['content'] ?? null;

if ($http_code !== 200 || !$ai_text) {
    ob_end_clean();
    error_log('Doctor AI Groq error ' . $http_code . ': ' . $api_response);
    echo json_encode(['reply' => '❌ AI service error. Please try again later.']);
    exit();
}

// -----------------------------------------------------------------------
// Process action blocks
// -----------------------------------------------------------------------

// --- COMPLETE APPOINTMENT ---
if (preg_match('/\[ACTION:COMPLETE_APPOINTMENT\](.*?)\[\/ACTION\]/s', $ai_text, $matches)) {
    $ai_text        = str_replace($matches[0], '', $ai_text);
    $action_data    = json_decode(trim($matches[1]), true);
    $appointment_id = (int)($action_data['appointment_id'] ?? 0);

    try {
        // Verify this appointment belongs to this doctor
        $stmt = $admin_conn->prepare("
            SELECT id FROM appointments
            WHERE id = ? AND doctor_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $appointment_id, $doctor_id);
        $stmt->execute();
        $owns = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($owns) {
            $stmt = $admin_conn->prepare("
                UPDATE appointments
                SET status = 'completed', completed_at = NOW()
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->bind_param('ii', $appointment_id, $doctor_id);
            $done = $stmt->execute();
            $stmt->close();

            // Log to appointment_history
            if ($done) {
                $stmt = $admin_conn->prepare("
                    INSERT INTO appointment_history
                        (appointment_id, action, performed_by, performed_by_type, new_status, notes)
                    VALUES (?, 'completed', ?, 'doctor', 'completed', 'Marked completed by doctor via AI assistant.')
                ");
                $stmt->bind_param('ii', $appointment_id, $doctor_id);
                $stmt->execute();
                $stmt->close();
            }

            $ai_text .= $done
                ? "\n\n✅ Appointment #" . $appointment_id . " has been marked as **completed**."
                : "\n\n❌ Failed to update appointment status. Please try manually.";
        } else {
            $ai_text .= "\n\n❌ Appointment #" . $appointment_id . " not found or does not belong to your account.";
        }
    } catch (Exception $e) {
        $ai_text .= "\n\n❌ Error completing appointment: " . $e->getMessage();
    }
}

// -----------------------------------------------------------------------
// Return response
// -----------------------------------------------------------------------

ob_end_clean();
echo json_encode(['reply' => trim($ai_text)]);
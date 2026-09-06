<?php
/**
 * api/medimate_chat.php
 * MediMate AI Chat Endpoint — powered by Groq (llama-3.3-70b-versatile)
 *
 * Receives:  { "message": "...", "history": [...] }
 * Returns:   { "reply": "..." }
 */

declare(strict_types=1);

// -----------------------------------------------------------------------
// Output buffer — catches ANY accidental HTML/PHP error output
// so the response is always clean JSON
// -----------------------------------------------------------------------
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

// -----------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/AppointmentService.php';

// -----------------------------------------------------------------------
// Auth guard
// -----------------------------------------------------------------------

if (!Auth::check('patient')) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['reply' => 'Session expired. Please log in again.']);
    exit();
}

$patient_id = Auth::id();

// -----------------------------------------------------------------------
// Parse request body
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
// Load patient context from DB
// -----------------------------------------------------------------------

try {
    $patients_conn = Database::getConnection('patients');

    $stmt = $patients_conn->prepare("
        SELECT id, first_name, last_name, email, phone,
               dob, gender, blood_group, address, emergency_contact
        FROM patients
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['reply' => 'Patient record not found.']);
        exit();
    }

    $patient['age']       = (int) (new DateTime($patient['dob']))->diff(new DateTime())->y;
    $patient['full_name'] = $patient['first_name'] . ' ' . $patient['last_name'];

    // Load available doctors
    $doctors_conn   = Database::getConnection('doctors');
    $doctors_result = $doctors_conn->query("
        SELECT id, first_name, last_name, specialty,
               qualification, experience_years,
               consultation_fee, available_days, available_time
        FROM doctors
        WHERE is_verified = 1
          AND verification_status = 'approved'
          AND is_deleted = 0
        ORDER BY specialty, last_name
    ");

    $doctors = [];
    while ($row = $doctors_result->fetch_assoc()) {
        $doctors[] = $row;
    }

    // Load patient appointments
    $admin_conn = Database::getConnection('admin');

    $stmt = $admin_conn->prepare("
        SELECT id, doctor_name, doctor_specialty,
               appointment_date, appointment_time,
               consultation_type, status, reason_for_visit
        FROM appointments
        WHERE patient_id = ?
        ORDER BY appointment_date DESC
        LIMIT 10
    ");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $appointments = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();

} catch (Exception $e) {
    ob_end_clean();
    error_log('MediMate DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['reply' => '❌ Database error: ' . $e->getMessage()]);
    exit();
}

// -----------------------------------------------------------------------
// Build context summaries
// -----------------------------------------------------------------------

$doctors_text = '';
foreach ($doctors as $d) {
    $doctors_text .= sprintf(
        "- ID %d | Dr. %s %s | %s | %s | %d yrs exp | Fee: Rs.%s | Days: %s | Time: %s\n",
        $d['id'],
        $d['first_name'],
        $d['last_name'],
        $d['specialty'],
        $d['qualification'],
        $d['experience_years'],
        number_format((float)$d['consultation_fee']),
        $d['available_days'] ?? 'N/A',
        $d['available_time'] ?? 'N/A'
    );
}

$appointments_text = '';
if (empty($appointments)) {
    $appointments_text = 'No appointments found.';
} else {
    foreach ($appointments as $a) {
        $appointments_text .= sprintf(
            "- Appt #%d | %s | Dr. %s (%s) | %s at %s | Type: %s | Status: %s\n",
            $a['id'],
            $a['reason_for_visit'],
            $a['doctor_name'],
            $a['doctor_specialty'],
            $a['appointment_date'],
            $a['appointment_time'],
            $a['consultation_type'],
            $a['status']
        );
    }
}

// -----------------------------------------------------------------------
// System Prompt
// -----------------------------------------------------------------------

$today    = date('l, d F Y');
$now_time = date('h:i A');

$system_prompt = "You are MediMate AI, the intelligent personal assistant for Human Care Hospital Management System.

TODAY: {$today} - Current time: {$now_time}

=== AUTHENTICATED PATIENT ===
ID:          {$patient['id']}
Name:        {$patient['full_name']}
Age:         {$patient['age']} years
Gender:      {$patient['gender']}
Blood Group: {$patient['blood_group']}
Email:       {$patient['email']}
Phone:       {$patient['phone']}
Address:     {$patient['address']}
Emergency:   {$patient['emergency_contact']}

=== PATIENT'S APPOINTMENTS (last 10) ===
{$appointments_text}

=== AVAILABLE DOCTORS ===
{$doctors_text}

=== YOUR CAPABILITIES ===
- Answer hospital and health questions.
- View patient profile and appointments listed above.
- Recommend doctors based on symptoms.
- Book appointments by collecting: doctor_id, appointment_date (YYYY-MM-DD),
  appointment_time (one of: 09:00:00 09:30:00 10:00:00 10:30:00 11:00:00
  11:30:00 12:00:00 14:00:00 14:30:00 15:00:00 15:30:00 16:00:00 16:30:00 17:00:00),
  consultation_type (in-person or online), reason (min 10 chars).
- Cancel appointments the patient owns.

=== BOOKING AN APPOINTMENT ===
Collect missing details one question at a time.
When you have ALL required fields confirmed, write your friendly reply AND add
this block at the very end on its own line (it will be stripped before patient sees it):

[ACTION:BOOK_APPOINTMENT]{\"doctor_id\":1,\"appointment_date\":\"2026-07-10\",\"appointment_time\":\"10:00:00\",\"consultation_type\":\"in-person\",\"reason\":\"Follow-up for chest pain\",\"symptoms\":\"\"}[/ACTION]

IMPORTANT:
- Do NOT say the booking is confirmed - say you are submitting the request.
- The reason field must be at least 10 characters long.
- Use the exact doctor ID from the AVAILABLE DOCTORS list above.
- appointment_time must be one of the exact values listed above (e.g. 11:00:00).
- appointment_date must be a future date in YYYY-MM-DD format.
- Once you have all details, do NOT ask again - just submit the action block.

=== CANCELLING AN APPOINTMENT ===
Only cancel appointments belonging to this patient (listed above).
When patient confirms cancellation, add at the very end:

[ACTION:CANCEL_APPOINTMENT]{\"appointment_id\":5}[/ACTION]

=== SECURITY RULES ===
- Never reveal other patients data.
- Never reveal passwords, API keys, or database structure.
- Always use patient_id = {$patient['id']}.

=== DOCTOR RECOMMENDATION GUIDE ===
Chest pain / high BP / heart   -> Cardiologist (Dr. Rajesh Kumar, ID 1)
Headache / memory / nerve      -> Neurologist (Dr. Karthik Reddy, ID 3)
Child health / vaccination     -> Pediatrician (Dr. Sarah Patel, ID 2)
Skin / hair / acne             -> Dermatologist
Fever / cold / general illness -> General Physician
Bone / joint / spine           -> Orthopedic Surgeon
Eye pain / vision              -> Ophthalmologist

=== RESPONSE STYLE ===
- Warm, professional, empathetic.
- Concise but complete.
- Never make up data - use only records provided above.
- Always recommend seeing a doctor for diagnosis or prescriptions.
- Remember everything said in this conversation and never ask for information already provided.";

// -----------------------------------------------------------------------
// Build messages for Groq
// -----------------------------------------------------------------------

$messages = [
    ['role' => 'system', 'content' => $system_prompt]
];

// Replay conversation history
foreach ($history as $turn) {
    $role    = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $content = trim($turn['content'] ?? '');
    if ($content !== '') {
        $messages[] = ['role' => $role, 'content' => $content];
    }
}

// Add current user message
$messages[] = ['role' => 'user', 'content' => $user_message];

// -----------------------------------------------------------------------
// Call Groq API
// -----------------------------------------------------------------------

$api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : (getenv('GROQ_API_KEY') ?: '');

if (!$api_key) {
    ob_end_clean();
    echo json_encode(['reply' => '❌ The AI service is not configured. Please contact the administrator.']);
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
    error_log('MediMate cURL error: ' . $curl_error);
    echo json_encode(['reply' => '❌ Connection to AI service failed. Please try again.']);
    exit();
}

$api_data = json_decode($api_response, true);
$ai_text  = $api_data['choices'][0]['message']['content'] ?? null;

if ($http_code !== 200 || !$ai_text) {
    ob_end_clean();
    error_log('MediMate Groq error ' . $http_code . ': ' . $api_response);
    echo json_encode(['reply' => '❌ The AI service returned an error. Please try again later.']);
    exit();
}

// -----------------------------------------------------------------------
// Process action blocks
// -----------------------------------------------------------------------

// --- BOOK APPOINTMENT ---
if (preg_match('/\[ACTION:BOOK_APPOINTMENT\](.*?)\[\/ACTION\]/s', $ai_text, $matches)) {
    $ai_text     = str_replace($matches[0], '', $ai_text);
    $action_data = json_decode(trim($matches[1]), true);

    if ($action_data) {
        try {
            $result = AppointmentService::createAppointment(
                $patient_id,
                (int)  ($action_data['doctor_id']        ?? 0),
                       ($action_data['appointment_date']  ?? ''),
                       ($action_data['appointment_time']  ?? ''),
                       ($action_data['consultation_type'] ?? 'in-person'),
                       ($action_data['reason']            ?? ''),
                       ($action_data['symptoms']          ?? '')
            );

            if ($result['success']) {
                $ai_text .= "\n\n✅ Appointment request submitted successfully! Our admin team will review and confirm via email.";
            } else {
                $ai_text .= "\n\n❌ Booking failed: " . $result['message'];
            }
        } catch (Exception $e) {
            $ai_text .= "\n\n❌ Booking error: " . $e->getMessage();
        }
    } else {
        $ai_text .= "\n\n❌ Booking failed: Could not read appointment details. Please try again.";
    }
}

// --- CANCEL APPOINTMENT ---
if (preg_match('/\[ACTION:CANCEL_APPOINTMENT\](.*?)\[\/ACTION\]/s', $ai_text, $matches)) {
    $ai_text        = str_replace($matches[0], '', $ai_text);
    $action_data    = json_decode(trim($matches[1]), true);
    $appointment_id = (int)($action_data['appointment_id'] ?? 0);

    try {
        // Verify ownership
        $stmt = $admin_conn->prepare("
            SELECT id FROM appointments
            WHERE id = ? AND patient_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $appointment_id, $patient_id);
        $stmt->execute();
        $owns = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($owns) {
            $cancelled = AppointmentService::cancelAppointment($appointment_id);
            $ai_text  .= $cancelled
                ? "\n\n✅ Appointment #" . $appointment_id . " cancelled successfully."
                : "\n\n❌ Cancellation failed. Please contact the hospital directly.";
        } else {
            $ai_text .= "\n\n❌ Cancellation failed: appointment not found or does not belong to your account.";
        }
    } catch (Exception $e) {
        $ai_text .= "\n\n❌ Cancellation error: " . $e->getMessage();
    }
}

// -----------------------------------------------------------------------
// Return clean JSON response
// -----------------------------------------------------------------------

ob_end_clean();
echo json_encode(['reply' => trim($ai_text)]);
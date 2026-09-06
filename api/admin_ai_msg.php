<?php
/**
 * api/admin_ai_chat.php
 * MediMate Admin AI Assistant — powered by Groq (llama-3.3-70b-versatile)
 *
 * Receives:  { "message": "...", "history": [...] }
 * Returns:   { "reply": "..." }
 *
 * Actions supported:
 *   APPROVE_DOCTOR     — verify a doctor
 *   REJECT_DOCTOR      — reject a doctor with reason
 *   APPROVE_APPOINTMENT — approve a pending appointment
 *   REJECT_APPOINTMENT  — reject with reason
 *   CANCEL_APPOINTMENT  — cancel an appointment
 *   COMPLETE_APPOINTMENT— mark completed
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
// Auth — admin only
// -----------------------------------------------------------------------
if (!Auth::check('admin')) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['reply' => 'Session expired. Please log in again.']);
    exit();
}

$admin_id   = $_SESSION['admin_id']   ?? 1;
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

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
// Load system context from all 3 databases
// -----------------------------------------------------------------------
try {
    $admin_conn    = Database::getConnection('admin');
    $doctors_conn  = Database::getConnection('doctors');
    $patients_conn = Database::getConnection('patients');

    // --- Stats ---
    $total_patients   = $patients_conn->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc()['c'];
    $pending_patients = $patients_conn->query("SELECT COUNT(*) as c FROM patients WHERE verification_status='pending'")->fetch_assoc()['c'];

    $total_doctors    = $doctors_conn->query("SELECT COUNT(*) as c FROM doctors WHERE is_deleted=0")->fetch_assoc()['c'];
    $pending_doctors  = $doctors_conn->query("SELECT COUNT(*) as c FROM doctors WHERE verification_status='pending' AND is_deleted=0")->fetch_assoc()['c'];
    $approved_doctors = $doctors_conn->query("SELECT COUNT(*) as c FROM doctors WHERE verification_status='approved' AND is_deleted=0")->fetch_assoc()['c'];

    $total_appts    = $admin_conn->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc()['c'];
    $pending_appts  = $admin_conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='pending'")->fetch_assoc()['c'];
    $approved_appts = $admin_conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='approved'")->fetch_assoc()['c'];
    $completed_appts= $admin_conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='completed'")->fetch_assoc()['c'];

    // --- Pending doctors ---
    $res = $doctors_conn->query("
        SELECT id, first_name, last_name, email, specialty, qualification, experience_years, license_number, registered_date
        FROM doctors WHERE verification_status='pending' AND is_deleted=0 ORDER BY registered_date DESC LIMIT 10
    ");
    $pending_doctors_list = [];
    while ($r = $res->fetch_assoc()) $pending_doctors_list[] = $r;

    // --- All approved doctors ---
    $res = $doctors_conn->query("
        SELECT id, first_name, last_name, specialty, email, phone, experience_years, consultation_fee, available_days, available_time
        FROM doctors WHERE verification_status='approved' AND is_deleted=0 ORDER BY specialty
    ");
    $approved_doctors_list = [];
    while ($r = $res->fetch_assoc()) $approved_doctors_list[] = $r;

    // --- Pending appointments ---
    $res = $admin_conn->query("
        SELECT id, patient_name, patient_email, patient_age,
               doctor_name, doctor_specialty,
               appointment_date, appointment_time, consultation_type,
               reason_for_visit, symptoms, status, created_at
        FROM appointments WHERE status='pending'
        ORDER BY appointment_date ASC, appointment_time ASC LIMIT 20
    ");
    $pending_appts_list = [];
    while ($r = $res->fetch_assoc()) $pending_appts_list[] = $r;

    // --- Recent appointments (all statuses) ---
    $res = $admin_conn->query("
        SELECT id, patient_name, doctor_name, doctor_specialty,
               appointment_date, appointment_time, consultation_type, status
        FROM appointments ORDER BY created_at DESC LIMIT 15
    ");
    $recent_appts_list = [];
    while ($r = $res->fetch_assoc()) $recent_appts_list[] = $r;

    // --- Recent patients ---
    $res = $patients_conn->query("
        SELECT id, first_name, last_name, email, phone, gender, blood_group, verification_status, registered_date
        FROM patients ORDER BY registered_date DESC LIMIT 10
    ");
    $recent_patients = [];
    while ($r = $res->fetch_assoc()) $recent_patients[] = $r;

    // --- Recent activity logs ---
    $res = $admin_conn->query("
        SELECT action, description, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 8
    ");
    $activity_logs = [];
    while ($r = $res->fetch_assoc()) $activity_logs[] = $r;

} catch (Exception $e) {
    ob_end_clean();
    error_log('Admin AI DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['reply' => '❌ Database error: ' . $e->getMessage()]);
    exit();
}

// -----------------------------------------------------------------------
// Build context summaries
// -----------------------------------------------------------------------

// Pending doctors
$pending_doctors_text = empty($pending_doctors_list) ? 'No pending doctor applications.' : '';
foreach ($pending_doctors_list as $d) {
    $pending_doctors_text .= sprintf(
        "- ID %d | Dr. %s %s | %s | %s | %d yrs | License: %s | Applied: %s\n",
        $d['id'], $d['first_name'], $d['last_name'],
        $d['specialty'], $d['qualification'], $d['experience_years'],
        $d['license_number'], date('d M Y', strtotime($d['registered_date']))
    );
}

// Approved doctors
$approved_doctors_text = '';
foreach ($approved_doctors_list as $d) {
    $approved_doctors_text .= sprintf(
        "- ID %d | Dr. %s %s | %s | Fee: Rs.%s | Days: %s | Time: %s\n",
        $d['id'], $d['first_name'], $d['last_name'],
        $d['specialty'], number_format((float)$d['consultation_fee']),
        $d['available_days'] ?? 'N/A', $d['available_time'] ?? 'N/A'
    );
}

// Pending appointments
$pending_appts_text = empty($pending_appts_list) ? 'No pending appointments.' : '';
foreach ($pending_appts_list as $a) {
    $pending_appts_text .= sprintf(
        "- Appt #%d | %s (Age:%s) → Dr. %s (%s) | %s at %s | %s | Reason: %s\n",
        $a['id'], $a['patient_name'], $a['patient_age'] ?? 'N/A',
        $a['doctor_name'], $a['doctor_specialty'],
        $a['appointment_date'], $a['appointment_time'],
        $a['consultation_type'], $a['reason_for_visit']
    );
}

// Recent appointments
$recent_appts_text = '';
foreach ($recent_appts_list as $a) {
    $recent_appts_text .= sprintf(
        "- Appt #%d | %s → Dr. %s | %s at %s | Status: %s\n",
        $a['id'], $a['patient_name'], $a['doctor_name'],
        $a['appointment_date'], $a['appointment_time'], $a['status']
    );
}

// Recent patients
$patients_text = '';
foreach ($recent_patients as $p) {
    $patients_text .= sprintf(
        "- ID %d | %s %s | %s | %s | Blood: %s | Status: %s | Joined: %s\n",
        $p['id'], $p['first_name'], $p['last_name'],
        $p['email'], $p['gender'], $p['blood_group'] ?? 'N/A',
        $p['verification_status'], date('d M Y', strtotime($p['registered_date']))
    );
}

// Activity logs
$activity_text = '';
foreach ($activity_logs as $log) {
    $activity_text .= sprintf("- [%s] %s: %s\n",
        date('d M H:i', strtotime($log['created_at'])),
        $log['action'], $log['description']
    );
}

// -----------------------------------------------------------------------
// System Prompt
// -----------------------------------------------------------------------

$today    = date('l, d F Y');
$now_time = date('h:i A');

$system_prompt = "You are MediMate Admin AI, the intelligent administrative assistant for Human Care Hospital Management System.

TODAY: {$today} — Current time: {$now_time}

=== AUTHENTICATED ADMIN ===
ID:   {$admin_id}
Name: {$admin_name}
Role: Administrator

=== HOSPITAL STATISTICS ===
Patients:     Total={$total_patients} | Pending verification={$pending_patients}
Doctors:      Total={$total_doctors} | Approved={$approved_doctors} | Pending={$pending_doctors}
Appointments: Total={$total_appts} | Pending={$pending_appts} | Approved={$approved_appts} | Completed={$completed_appts}

=== PENDING DOCTOR APPLICATIONS ===
{$pending_doctors_text}

=== APPROVED DOCTORS ===
{$approved_doctors_text}

=== PENDING APPOINTMENTS ===
{$pending_appts_text}

=== RECENT APPOINTMENTS ===
{$recent_appts_text}

=== RECENT PATIENTS ===
{$patients_text}

=== RECENT ACTIVITY LOG ===
{$activity_text}

=== YOUR CAPABILITIES ===
You can help the admin with:
1. View hospital statistics and summaries
2. View pending doctor applications and approve/reject them
3. View pending appointments and approve/reject/cancel/complete them
4. View patient records and details
5. Answer hospital management questions
6. Provide insights from the data above
7. Navigate to admin pages

=== APPROVING A DOCTOR ===
When admin confirms approval of a doctor, embed at end of reply:
[ACTION:APPROVE_DOCTOR]{\"doctor_id\":1}[/ACTION]

=== REJECTING A DOCTOR ===
When admin confirms rejection, embed at end of reply:
[ACTION:REJECT_DOCTOR]{\"doctor_id\":1,\"reason\":\"License verification failed\"}[/ACTION]

=== APPROVING AN APPOINTMENT ===
When admin confirms approval, embed at end of reply:
[ACTION:APPROVE_APPOINTMENT]{\"appointment_id\":1}[/ACTION]

=== REJECTING AN APPOINTMENT ===
[ACTION:REJECT_APPOINTMENT]{\"appointment_id\":1,\"reason\":\"No slots available\"}[/ACTION]

=== CANCELLING AN APPOINTMENT ===
[ACTION:CANCEL_APPOINTMENT]{\"appointment_id\":1,\"reason\":\"Cancelled by admin\"}[/ACTION]

=== COMPLETING AN APPOINTMENT ===
[ACTION:COMPLETE_APPOINTMENT]{\"appointment_id\":1}[/ACTION]

IMPORTANT: Always confirm with the admin before performing any action. Ask 'Are you sure you want to approve/reject...?' and only embed the action block after they confirm.

=== NAVIGATION LINKS ===
- Dashboard:         admin_dashboard.php
- Manage Doctors:    admin_doctors.php
- Manage Patients:   admin_patients.php
- Appointments:      admin_appointments.php
- Approve Education: admin_manage_education.php
- AI Assistant:      admin_ai_assistant.php

=== SECURITY RULES ===
- Never reveal passwords, API keys, or database structure
- Only perform actions on data visible in the context above
- Always confirm before destructive actions

=== RESPONSE STYLE ===
- Professional, concise, and data-driven
- Use tables or lists when showing multiple records
- Always confirm actions before executing them
- Address admin as '{$admin_name}'
- Remember everything in this conversation";

// -----------------------------------------------------------------------
// Build Groq messages
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
    echo json_encode(['reply' => '❌ AI service not configured. Please add GROQ_API_KEY to config.php.']);
    exit();
}

$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => $messages,
    'max_tokens'  => 1024,
    'temperature' => 0.5,
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
    error_log('Admin AI cURL error: ' . $curl_error);
    echo json_encode(['reply' => '❌ Connection to AI failed. Please try again.']);
    exit();
}

$api_data = json_decode($api_response, true);
$ai_text  = $api_data['choices'][0]['message']['content'] ?? null;

if ($http_code !== 200 || !$ai_text) {
    ob_end_clean();
    error_log('Admin AI Groq error ' . $http_code . ': ' . $api_response);
    echo json_encode(['reply' => '❌ AI service error. Please try again later.']);
    exit();
}

// -----------------------------------------------------------------------
// Process action blocks
// -----------------------------------------------------------------------

// Helper: log activity
function logActivity(object $conn, int $admin_id, string $action, string $desc): void {
    $stmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?,?,?)");
    $stmt->bind_param('iss', $admin_id, $action, $desc);
    $stmt->execute();
    $stmt->close();
}

// --- APPROVE DOCTOR ---
if (preg_match('/\[ACTION:APPROVE_DOCTOR\](.*?)\[\/ACTION\]/s', $ai_text, $m)) {
    $ai_text = str_replace($m[0], '', $ai_text);
    $data    = json_decode(trim($m[1]), true);
    $did     = (int)($data['doctor_id'] ?? 0);
    try {
        $stmt = $doctors_conn->prepare("UPDATE doctors SET is_verified=1, verification_status='approved', verified_by=?, verified_at=NOW() WHERE id=?");
        $stmt->bind_param('ii', $admin_id, $did);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            logActivity($admin_conn, $admin_id, 'doctor_approve', "Doctor ID {$did} approved by admin via AI.");
            $ai_text .= "\n\n✅ Doctor #" . $did . " has been **approved** successfully.";
        } else {
            $ai_text .= "\n\n❌ Failed to approve Doctor #" . $did . ".";
        }
    } catch (Exception $e) { $ai_text .= "\n\n❌ Error: " . $e->getMessage(); }
}

// --- REJECT DOCTOR ---
if (preg_match('/\[ACTION:REJECT_DOCTOR\](.*?)\[\/ACTION\]/s', $ai_text, $m)) {
    $ai_text = str_replace($m[0], '', $ai_text);
    $data    = json_decode(trim($m[1]), true);
    $did     = (int)($data['doctor_id'] ?? 0);
    $reason  = $data['reason'] ?? 'Application rejected by admin.';
    try {
        $stmt = $doctors_conn->prepare("UPDATE doctors SET is_verified=0, verification_status='rejected', rejection_reason=?, verified_by=?, verified_at=NOW() WHERE id=?");
        $stmt->bind_param('sii', $reason, $admin_id, $did);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            logActivity($admin_conn, $admin_id, 'doctor_reject', "Doctor ID {$did} rejected. Reason: {$reason}");
            $ai_text .= "\n\n✅ Doctor #" . $did . " has been **rejected**. Reason: " . $reason;
        } else {
            $ai_text .= "\n\n❌ Failed to reject Doctor #" . $did . ".";
        }
    } catch (Exception $e) { $ai_text .= "\n\n❌ Error: " . $e->getMessage(); }
}

// --- APPROVE APPOINTMENT ---
if (preg_match('/\[ACTION:APPROVE_APPOINTMENT\](.*?)\[\/ACTION\]/s', $ai_text, $m)) {
    $ai_text = str_replace($m[0], '', $ai_text);
    $data    = json_decode(trim($m[1]), true);
    $aid     = (int)($data['appointment_id'] ?? 0);
    try {
        $stmt = $admin_conn->prepare("UPDATE appointments SET status='approved', verified_by=?, verified_at=NOW() WHERE id=?");
        $stmt->bind_param('ii', $admin_id, $aid);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            $stmt = $admin_conn->prepare("INSERT INTO appointment_history (appointment_id,action,performed_by,performed_by_type,new_status,notes) VALUES (?,'approved',?,'admin','approved','Approved by admin via AI assistant.')");
            $stmt->bind_param('ii', $aid, $admin_id); $stmt->execute(); $stmt->close();
            logActivity($admin_conn, $admin_id, 'appointment_approve', "Appointment #{$aid} approved via AI.");
            $ai_text .= "\n\n✅ Appointment #" . $aid . " has been **approved** successfully.";
        } else {
            $ai_text .= "\n\n❌ Failed to approve Appointment #" . $aid . ".";
        }
    } catch (Exception $e) { $ai_text .= "\n\n❌ Error: " . $e->getMessage(); }
}

// --- REJECT APPOINTMENT ---
if (preg_match('/\[ACTION:REJECT_APPOINTMENT\](.*?)\[\/ACTION\]/s', $ai_text, $m)) {
    $ai_text = str_replace($m[0], '', $ai_text);
    $data    = json_decode(trim($m[1]), true);
    $aid     = (int)($data['appointment_id'] ?? 0);
    $reason  = $data['reason'] ?? 'Rejected by admin.';
    try {
        $stmt = $admin_conn->prepare("UPDATE appointments SET status='rejected', rejection_reason=?, verified_by=?, verified_at=NOW() WHERE id=?");
        $stmt->bind_param('sii', $reason, $admin_id, $aid);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            $stmt = $admin_conn->prepare("INSERT INTO appointment_history (appointment_id,action,performed_by,performed_by_type,new_status,notes) VALUES (?,'rejected',?,'admin','rejected',?)");
            $stmt->bind_param('iis', $aid, $admin_id, $reason); $stmt->execute(); $stmt->close();
            logActivity($admin_conn, $admin_id, 'appointment_reject', "Appointment #{$aid} rejected. Reason: {$reason}");
            $ai_text .= "\n\n✅ Appointment #" . $aid . " has been **rejected**. Reason: " . $reason;
        }
    } catch (Exception $e) { $ai_text .= "\n\n❌ Error: " . $e->getMessage(); }
}

// --- CANCEL APPOINTMENT ---
if (preg_match('/\[ACTION:CANCEL_APPOINTMENT\](.*?)\[\/ACTION\]/s', $ai_text, $m)) {
    $ai_text = str_replace($m[0], '', $ai_text);
    $data    = json_decode(trim($m[1]), true);
    $aid     = (int)($data['appointment_id'] ?? 0);
    $reason  = $data['reason'] ?? 'Cancelled by admin.';
    try {
        $stmt = $admin_conn->prepare("UPDATE appointments SET status='cancelled', rejection_reason=? WHERE id=?");
        $stmt->bind_param('si', $reason, $aid);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            logActivity($admin_conn, $admin_id, 'appointment_cancel', "Appointment #{$aid} cancelled via AI.");
            $ai_text .= "\n\n✅ Appointment #" . $aid . " has been **cancelled**.";
        }
    } catch (Exception $e) { $ai_text .= "\n\n❌ Error: " . $e->getMessage(); }
}

// --- COMPLETE APPOINTMENT ---
if (preg_match('/\[ACTION:COMPLETE_APPOINTMENT\](.*?)\[\/ACTION\]/s', $ai_text, $m)) {
    $ai_text = str_replace($m[0], '', $ai_text);
    $data    = json_decode(trim($m[1]), true);
    $aid     = (int)($data['appointment_id'] ?? 0);
    try {
        $stmt = $admin_conn->prepare("UPDATE appointments SET status='completed', completed_at=NOW() WHERE id=?");
        $stmt->bind_param('i', $aid);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) {
            logActivity($admin_conn, $admin_id, 'appointment_complete', "Appointment #{$aid} completed via AI.");
            $ai_text .= "\n\n✅ Appointment #" . $aid . " has been marked as **completed**.";
        }
    } catch (Exception $e) { $ai_text .= "\n\n❌ Error: " . $e->getMessage(); }
}

// -----------------------------------------------------------------------
// Return
// -----------------------------------------------------------------------
ob_end_clean();
echo json_encode(['reply' => trim($ai_text)]);
<?php
declare(strict_types=1);

session_start();
/* =====================================================================
   api/chat.php
   MediMate AI — PHP bridge between the JS frontend and the FastAPI
   Gemini backend.

   Flow:
     Browser (JS fetch)  →  chat.php  →  FastAPI /chat  →  Gemini
   ===================================================================== */


// -----------------------------------------------------------------
// 1. Force JSON-only responses for every exit path
// -----------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

// Prevent browsers from caching AI responses
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// -----------------------------------------------------------------
// 2. CORS — tighten in production by replacing * with your domain
// -----------------------------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// -----------------------------------------------------------------
// 3. Method guard — only POST is accepted
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
    exit();
}

// -----------------------------------------------------------------
// 4. Parse and validate request body
// -----------------------------------------------------------------
$rawBody = file_get_contents('php://input');

if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode(['error' => 'Request body is empty.']);
    exit();
}

$payload = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

$message = trim($payload['message'] ?? '');

if ($message === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Field "message" is required and cannot be empty.']);
    exit();
}

// -----------------------------------------------------------------
// 5. Forward request to FastAPI backend via cURL
// -----------------------------------------------------------------
const FASTAPI_URL = 'http://127.0.0.1:8000/chat';

$userId = $_SESSION['user_id'] ?? null;


$outgoingPayload = json_encode([
    'message' => $message,
    'user_id' => $userId
]);

error_log("SESSION USER ID = " . ($userId ?? "NULL"));

$ch = curl_init(FASTAPI_URL);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,       // Return response as string
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $outgoingPayload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($outgoingPayload),
    ],
    CURLOPT_TIMEOUT        => 30,         // 30 s total timeout
    CURLOPT_CONNECTTIMEOUT => 5,          // 5 s connect timeout
    CURLOPT_FAILONERROR    => false,      // We handle HTTP errors ourselves
]);

$rawResponse = curl_exec($ch);
$curlError   = curl_error($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// -----------------------------------------------------------------
// 6. Handle cURL-level failures (FastAPI unreachable / timeout)
// -----------------------------------------------------------------
if ($rawResponse === false || $curlError !== '') {
    // Log internally — never expose raw cURL errors to the client
    error_log('[MediMate AI] cURL error: ' . $curlError);

    http_response_code(503);
    echo json_encode(['reply' => 'Sorry, the AI service is currently unavailable.']);
    exit();
}

// -----------------------------------------------------------------
// 7. Handle non-200 HTTP responses from FastAPI
// -----------------------------------------------------------------
if ($httpCode < 200 || $httpCode >= 300) {
    error_log('[MediMate AI] FastAPI returned HTTP ' . $httpCode . ': ' . $rawResponse);

    http_response_code(502);
    echo json_encode(['reply' => 'Sorry, the AI service is currently unavailable.']);
    exit();
}

// -----------------------------------------------------------------
// 8. Decode FastAPI response
// -----------------------------------------------------------------
$apiResponse = json_decode($rawResponse, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($apiResponse['reply'])) {
    error_log('[MediMate AI] Unexpected FastAPI response: ' . $rawResponse);

    http_response_code(502);
    echo json_encode(['reply' => 'Sorry, the AI service returned an unexpected response.']);
    exit();
}

// -----------------------------------------------------------------
// 9. Return reply to the browser
// -----------------------------------------------------------------
http_response_code(200);
echo json_encode(['reply' => $apiResponse['reply']]);
exit();
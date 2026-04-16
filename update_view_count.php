<?php
/**
 * update_view_count.php
 * Called via AJAX from education.php when a user opens a content modal.
 * Only increments views on content that is currently 'approved'.
 */
session_start();

// Only accept POST from a logged-in user
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$content_id = isset($_POST['content_id']) ? intval($_POST['content_id']) : 0;
if ($content_id <= 0) {
    http_response_code(400);
    exit('Invalid ID');
}

$conn = new mysqli("localhost", "root", "", "human_care_admin");
if ($conn->connect_error) {
    http_response_code(500);
    exit('DB error');
}

// Only count views on approved content
$stmt = $conn->prepare("UPDATE educational_content SET views = views + 1 WHERE id = ? AND status = 'approved'");
$stmt->bind_param("i", $content_id);
$stmt->execute();
$stmt->close();
$conn->close();

echo 'ok';
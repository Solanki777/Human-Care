<?php
// update_view_count.php - Updates view count for educational content

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content_id'])) {
    $content_id = intval($_POST['content_id']);
    
    $admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
    
    if ($admin_conn->connect_error) {
        http_response_code(500);
        exit();
    }
    
    $stmt = $admin_conn->prepare("UPDATE educational_content SET views = views + 1 WHERE id = ?");
    $stmt->bind_param("i", $content_id);
    $stmt->execute();
    $stmt->close();
    $admin_conn->close();
    
    http_response_code(200);
    echo json_encode(['success' => true]);
}
?>
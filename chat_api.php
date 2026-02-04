<?php
/**
 * Chat API Endpoint
 * Location: chat_api.php
 * Handles all AJAX requests for the chat system
 */

session_start();
require_once 'classes/Chat.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

// Only patients and doctors can use chat
if (!in_array($userType, ['patient', 'doctor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

try {
    $chat = new Chat();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        
        // Get user's chat rooms
        case 'get_chat_rooms':
            $chatRooms = $chat->getUserChatRooms($userId, $userType);
            echo json_encode(['success' => true, 'chatRooms' => $chatRooms]);
            break;
        
        // Get or create chat room for appointment
        case 'get_or_create_room':
            $appointmentId = intval($_POST['appointment_id'] ?? 0);
            if (!$appointmentId) {
                throw new Exception('Appointment ID required');
            }
            
            $chatRoom = $chat->getOrCreateChatRoom($appointmentId);
            echo json_encode(['success' => true, 'chatRoom' => $chatRoom]);
            break;
        
        // Get messages for a chat room
        case 'get_messages':
            $chatRoomId = intval($_GET['chat_room_id'] ?? 0);
            if (!$chatRoomId) {
                throw new Exception('Chat room ID required');
            }
            
            // Check access
            if (!$chat->hasAccess($chatRoomId, $userId, $userType)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            
            $messages = $chat->getMessages($chatRoomId);
            
            // Mark messages as read
            $chat->markAsRead($chatRoomId, $userType);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
        
        // Get recent messages (polling)
        case 'get_recent_messages':
            $chatRoomId = intval($_GET['chat_room_id'] ?? 0);
            $afterMessageId = intval($_GET['after_message_id'] ?? 0);
            
            if (!$chatRoomId) {
                throw new Exception('Chat room ID required');
            }
            
            // Check access
            if (!$chat->hasAccess($chatRoomId, $userId, $userType)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            
            $messages = $chat->getRecentMessages($chatRoomId, $afterMessageId);
            $isTyping = $chat->getTypingStatus($chatRoomId, $userType);
            
            // Mark new messages as read
            if (!empty($messages)) {
                $chat->markAsRead($chatRoomId, $userType);
            }
            
            echo json_encode([
                'success' => true, 
                'messages' => $messages,
                'isTyping' => $isTyping
            ]);
            break;
        
        // Send a message
        case 'send_message':
            $chatRoomId = intval($_POST['chat_room_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            
            if (!$chatRoomId || empty($message)) {
                throw new Exception('Chat room ID and message required');
            }
            
            // Check access
            if (!$chat->hasAccess($chatRoomId, $userId, $userType)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            
            $messageId = $chat->sendMessage($chatRoomId, $userId, $userType, $message);
            
            echo json_encode([
                'success' => true, 
                'messageId' => $messageId,
                'message' => 'Message sent'
            ]);
            break;
        
        // Update typing status
        case 'update_typing':
            $chatRoomId = intval($_POST['chat_room_id'] ?? 0);
            $isTyping = filter_var($_POST['is_typing'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (!$chatRoomId) {
                throw new Exception('Chat room ID required');
            }
            
            // Check access
            if (!$chat->hasAccess($chatRoomId, $userId, $userType)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            
            $chat->updateTypingStatus($chatRoomId, $userId, $userType, $isTyping);
            
            echo json_encode(['success' => true]);
            break;
        
        // Get unread count
        case 'get_unread_count':
            $unreadCount = $chat->getUnreadCount($userId, $userType);
            echo json_encode(['success' => true, 'unreadCount' => $unreadCount]);
            break;
        
        // Mark messages as read
        case 'mark_as_read':
            $chatRoomId = intval($_POST['chat_room_id'] ?? 0);
            
            if (!$chatRoomId) {
                throw new Exception('Chat room ID required');
            }
            
            // Check access
            if (!$chat->hasAccess($chatRoomId, $userId, $userType)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            
            $chat->markAsRead($chatRoomId, $userType);
            
            echo json_encode(['success' => true]);
            break;
        
        // Delete a message
        case 'delete_message':
            $messageId = intval($_POST['message_id'] ?? 0);
            
            if (!$messageId) {
                throw new Exception('Message ID required');
            }
            
            $chat->deleteMessage($messageId, $userId, $userType);
            
            echo json_encode(['success' => true, 'message' => 'Message deleted']);
            break;
        
        // Archive chat room
        case 'archive_room':
            $chatRoomId = intval($_POST['chat_room_id'] ?? 0);
            
            if (!$chatRoomId) {
                throw new Exception('Chat room ID required');
            }
            
            // Check access
            if (!$chat->hasAccess($chatRoomId, $userId, $userType)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            
            $chat->archiveChatRoom($chatRoomId);
            
            echo json_encode(['success' => true, 'message' => 'Chat archived']);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
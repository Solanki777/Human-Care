<?php
/**
 * Chat Class - Handles all chat operations
 * Location: classes/Chat.php
 */

class Chat {
    private $adminConn;
    private $doctorsConn;
    
    public function __construct() {
        // Connect to databases
        $this->adminConn = new mysqli("localhost", "root", "", "human_care_admin");
        $this->doctorsConn = new mysqli("localhost", "root", "", "human_care_doctors");
        
        if ($this->adminConn->connect_error || $this->doctorsConn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        $this->adminConn->set_charset("utf8mb4");
        $this->doctorsConn->set_charset("utf8mb4");
    }
    
    /**
     * Create or get chat room for an appointment
     */
    public function getOrCreateChatRoom($appointmentId) {
        // Check if chat room already exists
        $stmt = $this->adminConn->prepare("SELECT * FROM chat_rooms WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        // Get appointment details
        $stmt = $this->doctorsConn->prepare("
            SELECT 
                da.id, 
                da.patient_id, 
                da.doctor_id,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                CONCAT(d.first_name, ' ', d.last_name) as doctor_name
            FROM doctor_appointments da
            JOIN human_care_patients.patients p ON da.patient_id = p.id
            JOIN doctors d ON da.doctor_id = d.id
            WHERE da.id = ?
        ");
        $stmt->bind_param("i", $appointmentId);
        $stmt->execute();
        $appointment = $stmt->get_result()->fetch_assoc();
        
        if (!$appointment) {
            throw new Exception("Appointment not found");
        }
        
        // Create new chat room
        $stmt = $this->adminConn->prepare("
            INSERT INTO chat_rooms (appointment_id, patient_id, doctor_id, patient_name, doctor_name) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiss", 
            $appointment['id'],
            $appointment['patient_id'],
            $appointment['doctor_id'],
            $appointment['patient_name'],
            $appointment['doctor_name']
        );
        $stmt->execute();
        
        $chatRoomId = $this->adminConn->insert_id;
        
        // Update appointment to enable chat
        $stmt = $this->doctorsConn->prepare("UPDATE doctor_appointments SET chat_enabled = TRUE, chat_room_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $chatRoomId, $appointmentId);
        $stmt->execute();
        
        // Return the newly created chat room
        return $this->getChatRoom($chatRoomId);
    }
    
    /**
     * Get chat room by ID
     */
    public function getChatRoom($chatRoomId) {
        $stmt = $this->adminConn->prepare("SELECT * FROM chat_rooms WHERE id = ?");
        $stmt->bind_param("i", $chatRoomId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get all chat rooms for a user
     */
    public function getUserChatRooms($userId, $userType) {
        $column = $userType === 'patient' ? 'patient_id' : 'doctor_id';
        
        $stmt = $this->adminConn->prepare("
            SELECT * FROM chat_rooms 
            WHERE $column = ? AND status = 'active' 
            ORDER BY updated_at DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Send a message
     */
    public function sendMessage($chatRoomId, $senderId, $senderType, $message, $messageType = 'text', $fileUrl = null, $fileName = null) {
        // Insert message
        $stmt = $this->adminConn->prepare("
            INSERT INTO chat_messages (chat_room_id, sender_id, sender_type, message, message_type, file_url, file_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisssss", $chatRoomId, $senderId, $senderType, $message, $messageType, $fileUrl, $fileName);
        $stmt->execute();
        
        // Update chat room last message and unread count
        $unreadColumn = $senderType === 'patient' ? 'doctor_unread_count' : 'patient_unread_count';
        
        $stmt = $this->adminConn->prepare("
            UPDATE chat_rooms 
            SET last_message = ?, 
                last_message_time = NOW(), 
                $unreadColumn = $unreadColumn + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("si", $message, $chatRoomId);
        $stmt->execute();
        
        return $this->adminConn->insert_id;
    }
    
    /**
     * Get messages for a chat room
     */
    public function getMessages($chatRoomId, $limit = 100, $offset = 0) {
        $stmt = $this->adminConn->prepare("
            SELECT * FROM chat_messages 
            WHERE chat_room_id = ? AND is_deleted = FALSE 
            ORDER BY created_at ASC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iii", $chatRoomId, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get recent messages (for real-time updates)
     */
    public function getRecentMessages($chatRoomId, $afterMessageId = 0) {
        $stmt = $this->adminConn->prepare("
            SELECT * FROM chat_messages 
            WHERE chat_room_id = ? AND id > ? AND is_deleted = FALSE 
            ORDER BY created_at ASC
        ");
        $stmt->bind_param("ii", $chatRoomId, $afterMessageId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Mark messages as read
     */
    public function markAsRead($chatRoomId, $userType) {
        // Mark all messages from the other user as read
        $senderType = $userType === 'patient' ? 'doctor' : 'patient';
        
        $stmt = $this->adminConn->prepare("
            UPDATE chat_messages 
            SET is_read = TRUE, read_at = NOW() 
            WHERE chat_room_id = ? AND sender_type = ? AND is_read = FALSE
        ");
        $stmt->bind_param("is", $chatRoomId, $senderType);
        $stmt->execute();
        
        // Reset unread count
        $unreadColumn = $userType === 'patient' ? 'patient_unread_count' : 'doctor_unread_count';
        
        $stmt = $this->adminConn->prepare("UPDATE chat_rooms SET $unreadColumn = 0 WHERE id = ?");
        $stmt->bind_param("i", $chatRoomId);
        $stmt->execute();
    }
    
    /**
     * Get unread count for user
     */
    public function getUnreadCount($userId, $userType) {
        $column = $userType === 'patient' ? 'patient_id' : 'doctor_id';
        $unreadColumn = $userType === 'patient' ? 'patient_unread_count' : 'doctor_unread_count';
        
        $stmt = $this->adminConn->prepare("
            SELECT SUM($unreadColumn) as total_unread 
            FROM chat_rooms 
            WHERE $column = ? AND status = 'active'
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['total_unread'] ?? 0;
    }
    
    /**
     * Update typing status
     */
    public function updateTypingStatus($chatRoomId, $userId, $userType, $isTyping = true) {
        $stmt = $this->adminConn->prepare("
            INSERT INTO chat_typing (chat_room_id, user_id, user_type, is_typing) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE is_typing = ?, updated_at = NOW()
        ");
        $stmt->bind_param("iisii", $chatRoomId, $userId, $userType, $isTyping, $isTyping);
        $stmt->execute();
    }
    
    /**
     * Get typing status
     */
    public function getTypingStatus($chatRoomId, $userType) {
        $otherUserType = $userType === 'patient' ? 'doctor' : 'patient';
        
        $stmt = $this->adminConn->prepare("
            SELECT * FROM chat_typing 
            WHERE chat_room_id = ? AND user_type = ? AND is_typing = TRUE 
            AND updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
        ");
        $stmt->bind_param("is", $chatRoomId, $otherUserType);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Delete a message
     */
    public function deleteMessage($messageId, $userId, $userType) {
        // Verify the message belongs to the user
        $stmt = $this->adminConn->prepare("
            UPDATE chat_messages 
            SET is_deleted = TRUE 
            WHERE id = ? AND sender_id = ? AND sender_type = ?
        ");
        $stmt->bind_param("iis", $messageId, $userId, $userType);
        return $stmt->execute();
    }
    
    /**
     * Archive a chat room
     */
    public function archiveChatRoom($chatRoomId) {
        $stmt = $this->adminConn->prepare("UPDATE chat_rooms SET status = 'archived' WHERE id = ?");
        $stmt->bind_param("i", $chatRoomId);
        return $stmt->execute();
    }
    
    /**
     * Check if user has access to chat room
     */
    public function hasAccess($chatRoomId, $userId, $userType) {
        $column = $userType === 'patient' ? 'patient_id' : 'doctor_id';
        
        $stmt = $this->adminConn->prepare("SELECT id FROM chat_rooms WHERE id = ? AND $column = ?");
        $stmt->bind_param("ii", $chatRoomId, $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Close database connections
     */
    public function __destruct() {
        if ($this->adminConn) {
            $this->adminConn->close();
        }
        if ($this->doctorsConn) {
            $this->doctorsConn->close();
        }
    }
}
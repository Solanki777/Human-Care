<?php
/**
 * Chat Class - Handles all chat operations
 * Location: classes/Chat.php
 */

class Chat {
    private $adminConn;
    
    /**
     * Validate that userType is one of the allowed values.
     * Prevents SQL injection via dynamic column names.
     */
    private function validateUserType(string $userType): string {
        if (!in_array($userType, ['patient', 'doctor'], true)) {
            throw new Exception("Invalid user type");
        }
        return $userType;
    }

    public function __construct() {
        // Connect to the admin database - this is where both the unified
        // "appointments" table and all chat_* tables live.
        $this->adminConn = new mysqli("sql205.infinityfree.com", "if0_42370337", "6yFxYkbKGy", "if0_42370337_human_care_admin");
        
        if ($this->adminConn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        $this->adminConn->set_charset("utf8mb4");
    }
    
    /**
     * Create or get chat room for an appointment.
     * Also verifies that the requesting user actually owns the appointment
     * and that the appointment has been approved, so this can be safely
     * called directly from the API layer.
     */
    public function getOrCreateChatRoom($appointmentId, $userId = null, $userType = null) {
        // Check if chat room already exists
        $stmt = $this->adminConn->prepare("SELECT * FROM chat_rooms WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        // Get appointment details from the real, unified appointments table
        // (chat_rooms and appointments both live in the admin database)
        $stmt = $this->adminConn->prepare("
            SELECT id, patient_id, doctor_id, patient_name, doctor_name, status
            FROM appointments
            WHERE id = ?
        ");
        $stmt->bind_param("i", $appointmentId);
        $stmt->execute();
        $appointment = $stmt->get_result()->fetch_assoc();
        
        if (!$appointment) {
            throw new Exception("Appointment not found");
        }
        
        if ($appointment['status'] !== 'approved') {
            throw new Exception("Chat is only available for approved appointments");
        }
        
        // If the caller told us who's asking, verify they actually own this appointment
        if ($userType !== null) {
            $this->validateUserType($userType);
            $ownerId = $userType === 'patient' ? $appointment['patient_id'] : $appointment['doctor_id'];
            if ((int)$ownerId !== (int)$userId) {
                throw new Exception("Access denied");
            }
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
        
        // Return the newly created chat room
        return $this->getChatRoom($chatRoomId);
    }

    /**
     * Get every person (doctor or patient) this user has an approved
     * appointment with, whether or not a chat room has been started yet.
     * Used to populate the chat page contact list so a conversation can
     * be started straight from the chat screen, on either side.
     */
    public function getApprovedContacts($userId, $userType) {
        $this->validateUserType($userType);

        if ($userType === 'patient') {
            $sql = "
                SELECT
                    a.id AS appointment_id,
                    a.doctor_id AS contact_id,
                    a.doctor_name AS contact_name,
                    a.doctor_specialty AS contact_meta,
                    cr.id AS chat_room_id,
                    cr.last_message,
                    cr.last_message_time,
                    cr.patient_unread_count AS unread_count
                FROM appointments a
                LEFT JOIN chat_rooms cr ON cr.appointment_id = a.id
                WHERE a.patient_id = ? AND a.status = 'approved'
                ORDER BY (cr.last_message_time IS NULL), cr.last_message_time DESC, a.doctor_name ASC
            ";
        } else {
            $sql = "
                SELECT
                    a.id AS appointment_id,
                    a.patient_id AS contact_id,
                    a.patient_name AS contact_name,
                    NULL AS contact_meta,
                    cr.id AS chat_room_id,
                    cr.last_message,
                    cr.last_message_time,
                    cr.doctor_unread_count AS unread_count
                FROM appointments a
                LEFT JOIN chat_rooms cr ON cr.appointment_id = a.id
                WHERE a.doctor_id = ? AND a.status = 'approved'
                ORDER BY (cr.last_message_time IS NULL), cr.last_message_time DESC, a.patient_name ASC
            ";
        }

        $stmt = $this->adminConn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // A patient/doctor pair can have multiple approved appointments over
        // time; only keep one contact entry per distinct other-party, and
        // prefer the one that already has a chat room / the most recent one.
        $contacts = [];
        foreach ($rows as $row) {
            $key = $row['contact_id'];
            if (!isset($contacts[$key]) || (!$contacts[$key]['chat_room_id'] && $row['chat_room_id'])) {
                $contacts[$key] = $row;
            }
        }

        return array_values($contacts);
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
        $this->validateUserType($userType);
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
        $this->validateUserType($senderType);
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
        $this->validateUserType($userType);
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
        $this->validateUserType($userType);
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
        $this->validateUserType($userType);
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
    }
}
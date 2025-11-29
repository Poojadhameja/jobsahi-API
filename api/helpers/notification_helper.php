<?php
// notification_helper.php - Helper functions for sending notifications
// Using FCM v1 API (new method)
// 
// ⚠️ IMPORTANT: Notification system is ONLY for STUDENTS
// - Push notifications are sent only to students
// - FCM tokens are stored only for students
// - All notification methods target students only
//
require_once __DIR__ . '/firebase_helper_v1.php';

class NotificationHelper {
    
    /**
     * Get all active FCM tokens for a user
     * ⚠️ Note: Currently only students have FCM tokens stored
     * @param int $user_id - User ID (should be a student)
     * @return array - Array of FCM tokens
     */
    public static function getUserFCMTokens($user_id) {
        global $conn;
        
        $sql = "SELECT fcm_token FROM fcm_tokens WHERE user_id = ? AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tokens = [];
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['fcm_token'])) {
                $tokens[] = $row['fcm_token'];
            }
        }
        $stmt->close();
        
        return $tokens;
    }
    
    /**
     * Get all active FCM tokens for all students
     * ✅ This method ONLY fetches tokens for students (role = 'student')
     * @return array - Array of FCM tokens (students only)
     */
    public static function getAllStudentFCMTokens() {
        global $conn;
        
        // ✅ Query: Only fetch tokens for students
        $sql = "SELECT DISTINCT ft.fcm_token 
                FROM fcm_tokens ft
                INNER JOIN users u ON ft.user_id = u.id
                WHERE u.role = 'student' 
                AND ft.is_active = 1
                AND u.status = 'active'
                AND u.is_verified = 1";
        
        $result = $conn->query($sql);
        $tokens = [];
        
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['fcm_token'])) {
                $tokens[] = $row['fcm_token'];
            }
        }
        
        return $tokens;
    }
    
    /**
     * Send notification to a student when they are shortlisted
     * ✅ This method sends notification ONLY to students
     * @param int $student_user_id - User ID of the student (must be a student)
     * @param string $job_title - Title of the job
     * @param int $job_id - Job ID
     * @return array - Result of notification send
     */
    public static function notifyShortlisted($student_user_id, $job_title, $job_id) {
        $tokens = self::getUserFCMTokens($student_user_id);
        
        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No FCM tokens found for user'
            ];
        }
        
        $title = "🎉 Congratulations! You've been shortlisted";
        $body = "You have been shortlisted for the position: " . $job_title;
        
        $data = [
            'type' => 'shortlisted',
            'job_id' => (string)$job_id,
            'job_title' => $job_title
        ];
        
        // Save notification to database
        self::saveNotificationToDB($student_user_id, $body, 'shortlisted', $job_id);
        
        return FirebaseHelperV1::sendToMultipleDevices($tokens, $title, $body, $data);
    }
    
    /**
     * Send notification to all students when a new job is posted
     * ✅ This method sends notification ONLY to all active students
     * Uses getAllStudentFCMTokens() which fetches only students' tokens
     * @param string $job_title - Title of the job
     * @param int $job_id - Job ID
     * @param string $location - Job location
     * @return array - Result of notification send
     */
    public static function notifyNewJobPosted($job_title, $job_id, $location = '') {
        // ✅ Get tokens only for students
        $tokens = self::getAllStudentFCMTokens();
        
        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No student FCM tokens found'
            ];
        }
        
        $title = "🆕 New Job Posted!";
        $body = "A new job has been posted: " . $job_title;
        if (!empty($location)) {
            $body .= " - " . $location;
        }
        
        $data = [
            'type' => 'new_job',
            'job_id' => (string)$job_id,
            'job_title' => $job_title,
            'location' => $location
        ];
        
        // Save notification to database for all students
        // Note: This might be heavy, so you can optimize by using a topic instead
        self::saveNotificationToAllStudents($body, 'new_job', $job_id);
        
        // Send in batches if too many tokens (FCM limit is 1000 per request)
        if (count($tokens) > 1000) {
            $batches = array_chunk($tokens, 1000);
            $results = [];
            foreach ($batches as $batch) {
                $results[] = FirebaseHelper::sendToMultipleDevices($batch, $title, $body, $data);
            }
            return [
                'success' => true,
                'message' => 'Notifications sent in batches',
                'batches' => count($batches),
                'results' => $results
            ];
        }
        
        return FirebaseHelperV1::sendToMultipleDevices($tokens, $title, $body, $data);
    }
    
    /**
     * Save notification to database for a specific user
     * @param int $user_id
     * @param string $message
     * @param string $type
     * @param int $reference_id - Optional reference ID (e.g., job_id, application_id)
     */
    private static function saveNotificationToDB($user_id, $message, $type, $reference_id = null) {
        global $conn;
        
        try {
            $sql = "INSERT INTO notifications (user_id, message, type, is_read, created_at, received_role) 
                    VALUES (?, ?, ?, 0, NOW(), 
                    (SELECT role FROM users WHERE id = ?))";
            $stmt = $conn->prepare($sql);
            $role = 'student'; // Default
            $stmt->bind_param("isss", $user_id, $message, $type, $user_id);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error saving notification to DB: " . $e->getMessage());
        }
    }
    
    /**
     * Save notification to database for all students
     * ✅ This method saves notifications ONLY for students (role = 'student')
     * @param string $message
     * @param string $type
     * @param int $reference_id
     */
    private static function saveNotificationToAllStudents($message, $type, $reference_id = null) {
        global $conn;
        
        try {
            // ✅ Insert notification ONLY for active students
            $sql = "INSERT INTO notifications (user_id, message, type, is_read, created_at, received_role)
                    SELECT id, ?, ?, 0, NOW(), 'student'
                    FROM users
                    WHERE role = 'student' AND status = 'active' AND is_verified = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $message, $type);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error saving notifications to DB: " . $e->getMessage());
        }
    }
}


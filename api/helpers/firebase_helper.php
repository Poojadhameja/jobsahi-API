<?php
// firebase_helper.php - Firebase Cloud Messaging (FCM) Helper
// This helper sends push notifications using Firebase Cloud Messaging

class FirebaseHelper {
    // Firebase Server Key - Replace with your actual FCM Server Key
    // Get it from: Firebase Console > Project Settings > Cloud Messaging > Server Key
    private static $FCM_SERVER_KEY = 'YOUR_FIREBASE_SERVER_KEY_HERE';
    private static $FCM_URL = 'https://fcm.googleapis.com/fcm/send';

    /**
     * Send notification to a single device
     * @param string $fcm_token - FCM token of the device
     * @param string $title - Notification title
     * @param string $body - Notification body/message
     * @param array $data - Additional data payload (optional)
     * @return array - Response with status and message
     */
    public static function sendToDevice($fcm_token, $title, $body, $data = []) {
        if (empty(self::$FCM_SERVER_KEY) || self::$FCM_SERVER_KEY === 'YOUR_FIREBASE_SERVER_KEY_HERE') {
            error_log("Firebase Server Key not configured");
            return [
                'success' => false,
                'message' => 'Firebase Server Key not configured'
            ];
        }

        if (empty($fcm_token)) {
            return [
                'success' => false,
                'message' => 'FCM token is required'
            ];
        }

        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => 1
        ];

        $payload = [
            'to' => $fcm_token,
            'notification' => $notification,
            'data' => array_merge([
                'title' => $title,
                'body' => $body,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ], $data),
            'priority' => 'high'
        ];

        return self::sendRequest($payload);
    }

    /**
     * Send notification to multiple devices
     * @param array $fcm_tokens - Array of FCM tokens
     * @param string $title - Notification title
     * @param string $body - Notification body/message
     * @param array $data - Additional data payload (optional)
     * @return array - Response with status and message
     */
    public static function sendToMultipleDevices($fcm_tokens, $title, $body, $data = []) {
        if (empty(self::$FCM_SERVER_KEY) || self::$FCM_SERVER_KEY === 'YOUR_FIREBASE_SERVER_KEY_HERE') {
            error_log("Firebase Server Key not configured");
            return [
                'success' => false,
                'message' => 'Firebase Server Key not configured'
            ];
        }

        if (empty($fcm_tokens) || !is_array($fcm_tokens)) {
            return [
                'success' => false,
                'message' => 'FCM tokens array is required'
            ];
        }

        // Remove empty tokens
        $fcm_tokens = array_filter($fcm_tokens);

        if (empty($fcm_tokens)) {
            return [
                'success' => false,
                'message' => 'No valid FCM tokens provided'
            ];
        }

        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => 1
        ];

        $payload = [
            'registration_ids' => array_values($fcm_tokens), // Use registration_ids for multiple
            'notification' => $notification,
            'data' => array_merge([
                'title' => $title,
                'body' => $body,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ], $data),
            'priority' => 'high'
        ];

        return self::sendRequest($payload);
    }

    /**
     * Send notification to a topic (for broadcasting to all users)
     * @param string $topic - Topic name (e.g., 'all_students')
     * @param string $title - Notification title
     * @param string $body - Notification body/message
     * @param array $data - Additional data payload (optional)
     * @return array - Response with status and message
     */
    public static function sendToTopic($topic, $title, $body, $data = []) {
        if (empty(self::$FCM_SERVER_KEY) || self::$FCM_SERVER_KEY === 'YOUR_FIREBASE_SERVER_KEY_HERE') {
            error_log("Firebase Server Key not configured");
            return [
                'success' => false,
                'message' => 'Firebase Server Key not configured'
            ];
        }

        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => 1
        ];

        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => $notification,
            'data' => array_merge([
                'title' => $title,
                'body' => $body,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ], $data),
            'priority' => 'high'
        ];

        return self::sendRequest($payload);
    }

    /**
     * Send HTTP request to FCM
     * @param array $payload - FCM payload
     * @return array - Response with status and message
     */
    private static function sendRequest($payload) {
        $headers = [
            'Authorization: key=' . self::$FCM_SERVER_KEY,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$FCM_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("FCM cURL Error: " . $error);
            return [
                'success' => false,
                'message' => 'cURL Error: ' . $error
            ];
        }

        $response_data = json_decode($response, true);

        if ($http_code === 200 && isset($response_data['success']) && $response_data['success'] > 0) {
            return [
                'success' => true,
                'message' => 'Notification sent successfully',
                'response' => $response_data
            ];
        } else {
            error_log("FCM Error Response: " . $response);
            return [
                'success' => false,
                'message' => 'Failed to send notification',
                'response' => $response_data,
                'http_code' => $http_code
            ];
        }
    }

    /**
     * Get FCM Server Key (for configuration)
     * @return string
     */
    public static function getServerKey() {
        return self::$FCM_SERVER_KEY;
    }

    /**
     * Set FCM Server Key (for configuration)
     * @param string $server_key
     */
    public static function setServerKey($server_key) {
        self::$FCM_SERVER_KEY = $server_key;
    }
}


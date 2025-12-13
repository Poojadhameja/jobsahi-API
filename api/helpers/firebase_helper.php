<?php
// firebase_helper.php - Firebase Cloud Messaging (FCM) Helper
// This helper sends push notifications using Firebase Cloud Messaging

// Load environment variables from .env file
require_once __DIR__ . '/../config/env_loader.php';

class FirebaseHelper {
    // Firebase Server Key - Load from environment variable
    // Get it from: Firebase Console > Project Settings > Cloud Messaging > Server Key
    // Set in .env file as FCM_SERVER_KEY
    private static function getFCMServerKey() {
        // Try to get from environment variable
        $key = getenv('FCM_SERVER_KEY');
        if ($key === false || empty($key) || $key === 'YOUR_FIREBASE_SERVER_KEY_HERE') {
            // Try $_ENV as fallback
            $key = isset($_ENV['FCM_SERVER_KEY']) ? $_ENV['FCM_SERVER_KEY'] : 'YOUR_FIREBASE_SERVER_KEY_HERE';
        }
        return $key;
    }
    
    private static function getFCMUrl() {
        $url = getenv('FCM_URL');
        if ($url === false || empty($url)) {
            $url = isset($_ENV['FCM_URL']) ? $_ENV['FCM_URL'] : 'https://fcm.googleapis.com/fcm/send';
        }
        return $url;
    }
    
    private static $FCM_URL = null; // Will be resolved dynamically

    /**
     * Send notification to a single device
     * @param string $fcm_token - FCM token of the device
     * @param string $title - Notification title
     * @param string $body - Notification body/message
     * @param array $data - Additional data payload (optional)
     * @return array - Response with status and message
     */
    public static function sendToDevice($fcm_token, $title, $body, $data = []) {
        $serverKey = self::getFCMServerKey();
        if (empty($serverKey) || $serverKey === 'YOUR_FIREBASE_SERVER_KEY_HERE') {
            error_log("Firebase Server Key not configured. Please set FCM_SERVER_KEY in .env file");
            return [
                'success' => false,
                'message' => 'Firebase Server Key not configured. Please set FCM_SERVER_KEY in .env file'
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

        return self::sendRequest($payload, $serverKey);
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
        $serverKey = self::getFCMServerKey();
        if (empty($serverKey) || $serverKey === 'YOUR_FIREBASE_SERVER_KEY_HERE') {
            error_log("Firebase Server Key not configured. Please set FCM_SERVER_KEY in .env file");
            return [
                'success' => false,
                'message' => 'Firebase Server Key not configured. Please set FCM_SERVER_KEY in .env file'
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

        return self::sendRequest($payload, $serverKey);
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
        $serverKey = self::getFCMServerKey();
        if (empty($serverKey) || $serverKey === 'YOUR_FIREBASE_SERVER_KEY_HERE') {
            error_log("Firebase Server Key not configured. Please set FCM_SERVER_KEY in .env file");
            return [
                'success' => false,
                'message' => 'Firebase Server Key not configured. Please set FCM_SERVER_KEY in .env file'
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

        return self::sendRequest($payload, $serverKey);
    }

    /**
     * Send HTTP request to FCM
     * @param array $payload - FCM payload
     * @param string $serverKey - FCM Server Key
     * @return array - Response with status and message
     */
    private static function sendRequest($payload, $serverKey = null) {
        if ($serverKey === null) {
            $serverKey = self::getFCMServerKey();
        }
        
        $headers = [
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json'
        ];

        $fcmUrl = self::getFCMUrl();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fcmUrl);
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
        return self::getFCMServerKey();
    }

    /**
     * Set FCM Server Key (for configuration)
     * Note: This method sets the environment variable temporarily
     * @param string $server_key
     */
    public static function setServerKey($server_key) {
        putenv("FCM_SERVER_KEY=$server_key");
        $_ENV['FCM_SERVER_KEY'] = $server_key;
    }
}


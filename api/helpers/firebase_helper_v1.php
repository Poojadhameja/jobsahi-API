<?php
// firebase_helper_v1.php - Firebase Cloud Messaging (FCM) v1 API Helper
// Uses FCM v1 API with Service Account JSON (Not Legacy Server Key)
//
// ⚠️ IMPORTANT: This helper is used to send notifications to STUDENTS only
// - FCM tokens are stored only for students
// - All notification methods target students
//
class FirebaseHelperV1 {
    // Path to Service Account JSON file
    // Download from: Firebase Console > Project Settings > Service Accounts > Generate new private key
    // Try multiple possible paths
    private static function resolveServiceAccountPath() {
        $possiblePaths = [
            // From api/helpers (default)
            __DIR__ . '/../../config/firebase-service-account.json',
            // From api
            dirname(__DIR__) . '/config/firebase-service-account.json',
            // From root
            dirname(dirname(__DIR__)) . '/config/firebase-service-account.json',
            // Absolute path (if DOCUMENT_ROOT is set)
            isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/config/firebase-service-account.json' : null,
            // Alternative locations
            __DIR__ . '/../../../config/firebase-service-account.json',
        ];
        
        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                return realpath($path);
            }
        }
        
        // Return default path if none found (for error message)
        return __DIR__ . '/../../config/firebase-service-account.json';
    }
    
    private static $SERVICE_ACCOUNT_PATH = null; // Will be resolved dynamically
    
    // FCM v1 API endpoint
    private static $FCM_V1_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    
    // Cached access token (expires in 1 hour)
    private static $cached_access_token = null;
    private static $token_expires_at = 0;
    
    /**
     * Get access token from Google OAuth2 using Service Account
     * @return string|false - Access token or false on error
     */
    private static function getAccessToken() {
        // Check if cached token is still valid (with 5 min buffer)
        if (self::$cached_access_token && time() < (self::$token_expires_at - 300)) {
            return self::$cached_access_token;
        }
        
        // Resolve service account path
        $serviceAccountPath = self::resolveServiceAccountPath();
        
        if (!file_exists($serviceAccountPath)) {
            error_log("Firebase Service Account JSON file not found at: " . $serviceAccountPath);
            error_log("Current working directory: " . getcwd());
            error_log("__DIR__ value: " . __DIR__);
            error_log("Document root: " . (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'Not set'));
            return false;
        }
        
        $fileContent = file_get_contents($serviceAccountPath);
        if ($fileContent === false) {
            error_log("Failed to read Service Account JSON file from: " . $serviceAccountPath);
            return false;
        }
        
        $serviceAccount = json_decode($fileContent, true);
        
        if (!$serviceAccount) {
            $jsonError = json_last_error_msg();
            error_log("Failed to parse Service Account JSON file. JSON Error: " . $jsonError);
            error_log("File size: " . strlen($fileContent) . " bytes");
            return false;
        }
        
        // Validate required fields
        $requiredFields = ['project_id', 'private_key', 'client_email'];
        foreach ($requiredFields as $field) {
            if (!isset($serviceAccount[$field]) || empty($serviceAccount[$field])) {
                error_log("Service Account JSON missing or empty required field: " . $field);
                return false;
            }
        }
        
        // Generate JWT for OAuth2
        $jwt = self::generateJWT($serviceAccount);
        
        if (!$jwt) {
            error_log("Failed to generate JWT token from Service Account");
            return false;
        }
        
        // Exchange JWT for access token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $data = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("cURL error while getting access token: " . $curl_error);
            return false;
        }
        
        if ($http_code !== 200) {
            error_log("Failed to get access token. HTTP Code: $http_code");
            error_log("Response: " . $response);
            return false;
        }
        
        $tokenData = json_decode($response, true);
        
        if (isset($tokenData['access_token'])) {
            self::$cached_access_token = $tokenData['access_token'];
            self::$token_expires_at = time() + ($tokenData['expires_in'] ?? 3600);
            return self::$cached_access_token;
        }
        
        error_log("Access token not found in response: " . $response);
        return false;
    }
    
    /**
     * Generate JWT for OAuth2
     * @param array $serviceAccount - Service Account JSON data
     * @return string|false - JWT token or false on error
     */
    private static function generateJWT($serviceAccount) {
        $now = time();
        $exp = $now + 3600; // 1 hour expiry
        
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        $payload = [
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $exp,
            'iat' => $now
        ];
        
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        $signatureInput = $headerEncoded . '.' . $payloadEncoded;
        
        // Load private key
        $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
        if (!$privateKey) {
            $openssl_error = openssl_error_string();
            error_log("Failed to load private key from Service Account");
            error_log("OpenSSL Error: " . ($openssl_error ? $openssl_error : "Unknown error"));
            error_log("Private key length: " . strlen($serviceAccount['private_key']));
            error_log("Private key preview: " . substr($serviceAccount['private_key'], 0, 50) . "...");
            return false;
        }
        
        // Sign
        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $openssl_error = openssl_error_string();
            error_log("Failed to sign JWT");
            error_log("OpenSSL Error: " . ($openssl_error ? $openssl_error : "Unknown error"));
            // Note: openssl_free_key() is deprecated in PHP 8.0+, resources are automatically freed
            return false;
        }
        
        // Note: openssl_free_key() is deprecated in PHP 8.0+, resources are automatically freed
        
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Get project ID from Service Account
     * @return string|false
     */
    private static function getProjectId() {
        $serviceAccountPath = self::resolveServiceAccountPath();
        
        if (!file_exists($serviceAccountPath)) {
            return false;
        }
        
        $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
        return $serviceAccount['project_id'] ?? false;
    }
    
    /**
     * Send notification to a single device (FCM v1 API)
     * @param string $fcm_token - FCM token of the device
     * @param string $title - Notification title
     * @param string $body - Notification body/message
     * @param array $data - Additional data payload (optional)
     * @return array - Response with status and message
     */
    public static function sendToDevice($fcm_token, $title, $body, $data = []) {
        if (empty($fcm_token)) {
            error_log("FCM v1 sendToDevice - Empty FCM token provided");
            return [
                'success' => false,
                'message' => 'FCM token is required'
            ];
        }
        
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            error_log("FCM v1 sendToDevice - Failed to get access token");
            return [
                'success' => false,
                'message' => 'Failed to get access token. Check Service Account configuration.'
            ];
        }
        
        $projectId = self::getProjectId();
        if (!$projectId) {
            error_log("FCM v1 sendToDevice - Failed to get project ID");
            return [
                'success' => false,
                'message' => 'Failed to get project ID from Service Account'
            ];
        }
        
        // FCM v1 API message format
        $message = [
            'message' => [
                'token' => $fcm_token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => array_map('strval', array_merge([
                    'title' => $title,
                    'body' => $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ], $data)),
                'android' => [
                    'priority' => 'high'
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10'
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1
                        ]
                    ]
                ]
            ]
        ];
        
        $url = sprintf(self::$FCM_V1_URL, $projectId);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("FCM v1 cURL Error: " . $error);
            return [
                'success' => false,
                'message' => 'cURL Error: ' . $error
            ];
        }
        
        $response_data = json_decode($response, true);
        
        if ($http_code === 200 && isset($response_data['name'])) {
            error_log("FCM v1 Success - Notification sent to token: " . substr($fcm_token, 0, 20) . "...");
            return [
                'success' => true,
                'message' => 'Notification sent successfully',
                'response' => $response_data
            ];
        } else {
            error_log("FCM v1 Error - HTTP Code: $http_code");
            error_log("FCM v1 Error Response: " . $response);
            if (isset($response_data['error'])) {
                error_log("FCM v1 Error Details: " . json_encode($response_data['error']));
            }
            return [
                'success' => false,
                'message' => 'Failed to send notification',
                'response' => $response_data,
                'http_code' => $http_code
            ];
        }
    }
    
    /**
     * Send notification to multiple devices (FCM v1 API)
     * Note: FCM v1 doesn't support batch sending directly, so we send individually
     * @param array $fcm_tokens - Array of FCM tokens
     * @param string $title - Notification title
     * @param string $body - Notification body/message
     * @param array $data - Additional data payload (optional)
     * @return array - Response with status and message
     */
    public static function sendToMultipleDevices($fcm_tokens, $title, $body, $data = []) {
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
        
        $results = [];
        $success_count = 0;
        $fail_count = 0;
        
        // Send to each device individually
        foreach ($fcm_tokens as $token) {
            $result = self::sendToDevice($token, $title, $body, $data);
            $results[] = $result;
            
            if ($result['success']) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }
        
        return [
            'success' => $success_count > 0,
            'message' => "Sent to $success_count devices, failed: $fail_count",
            'success_count' => $success_count,
            'fail_count' => $fail_count,
            'results' => $results
        ];
    }
    
    /**
     * Set Service Account JSON file path
     * @param string $path - Path to Service Account JSON file
     */
    public static function setServiceAccountPath($path) {
        self::$SERVICE_ACCOUNT_PATH = $path;
        // Clear cached token when path changes
        self::$cached_access_token = null;
        self::$token_expires_at = 0;
    }
    
    /**
     * Get Service Account JSON file path (public method for testing)
     * @return string
     */
    public static function getServiceAccountPath() {
        return self::resolveServiceAccountPath();
    }
}


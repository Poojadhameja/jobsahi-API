<?php
// jwt_helper.php - JWT Helper Functions
class JWTHelper {
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    public static function generateJWT($payload, $secret = null, $algorithm = 'HS256') {
        if ($secret === null) {
            $secret = JWT_SECRET;
        }
        
        $header = json_encode(['typ' => 'JWT', 'alg' => $algorithm]);
        $payload = json_encode($payload);
        
        $headerEncoded = self::base64UrlEncode($header);
        $payloadEncoded = self::base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    public static function validateJWT($jwt, $secret = null) {
        if ($secret === null) {
            $secret = JWT_SECRET;
        }
        
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        
        // Expiration check removed - tokens never expire based on time
        // if (isset($payload['exp']) && $payload['exp'] < time()) {
        //     return false;
        // }
        
        return $payload;
    }
    
    public static function getJWTFromHeader() {
        $authHeader = null;
        
        // Method 1: Try getallheaders() first (works on most servers)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ($headers && isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
            } elseif ($headers && isset($headers['authorization'])) {
                // Case-insensitive check
                $authHeader = $headers['authorization'];
            }
        }
        
        // Method 2: Try $_SERVER['HTTP_AUTHORIZATION'] (for Apache/FastCGI)
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        // Method 3: Try REDIRECT_HTTP_AUTHORIZATION (for Apache with mod_rewrite)
        if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        
        // Method 4: Try apache_request_headers() if available
        if (!$authHeader && function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if ($apacheHeaders) {
                foreach ($apacheHeaders as $key => $value) {
                    if (strtolower($key) === 'authorization') {
                        $authHeader = $value;
                        break;
                    }
                }
            }
        }
        
        // Method 5: Try reading from php://input headers (last resort)
        if (!$authHeader) {
            // Check all $_SERVER keys for Authorization
            foreach ($_SERVER as $key => $value) {
                if (strtoupper($key) === 'HTTP_AUTHORIZATION' || 
                    strtoupper($key) === 'REDIRECT_HTTP_AUTHORIZATION') {
                    $authHeader = $value;
                    break;
                }
            }
        }
        
        // Debug logging (remove in production if needed)
        if (!$authHeader) {
            error_log("JWT Helper: No Authorization header found. Available headers: " . json_encode(array_keys($_SERVER)));
        }
        
        if (!$authHeader) {
            return null;
        }
        
        // Extract Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
            if (empty($token)) {
                error_log("JWT Helper: Bearer token found but empty");
                return null;
            }
            return $token;
        }
        
        // If no Bearer prefix, try to use the header value directly (some clients send token without Bearer)
        $token = trim($authHeader);
        if (!empty($token) && strlen($token) > 20) { // Basic validation - JWT tokens are usually long
            error_log("JWT Helper: Using token without Bearer prefix");
            return $token;
        }
        
        error_log("JWT Helper: Could not extract token from header: " . substr($authHeader, 0, 50));
        return null;
    }
    
    public static function verifyJWT($jwt, $secret = null) {
        return self::validateJWT($jwt, $secret);
    }
    
    // ✅ Blacklist token functions
    public static function blacklistToken($jwt, $user_id = null) {
        global $conn;
        
        if (!$jwt) {
            return false;
        }
        
        // Create hash of the token for storage
        $token_hash = hash('sha256', $jwt);
        
        // Get token expiration from payload
        $payload = self::validateJWT($jwt, JWT_SECRET);
        $expires_at = isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : null;
        
        // Check if token is already blacklisted
        $check_sql = "SELECT id FROM blacklisted_tokens WHERE token_hash = ?";
        if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($check_stmt, "s", $token_hash);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($result) > 0) {
                mysqli_stmt_close($check_stmt);
                return true; // Already blacklisted
            }
            mysqli_stmt_close($check_stmt);
        }
        
        // Insert into blacklist
        $insert_sql = "INSERT INTO blacklisted_tokens (token_hash, user_id, expires_at) VALUES (?, ?, ?)";
        if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
            mysqli_stmt_bind_param($insert_stmt, "sis", $token_hash, $user_id, $expires_at);
            $success = mysqli_stmt_execute($insert_stmt);
            mysqli_stmt_close($insert_stmt);
            return $success;
        }
        
        return false;
    }
    
    public static function isTokenBlacklisted($jwt) {
        global $conn;
        
        if (!$jwt) {
            return true; // No token = considered blacklisted
        }
        
        $token_hash = hash('sha256', $jwt);
        
        $sql = "SELECT id FROM blacklisted_tokens WHERE token_hash = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $token_hash);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $is_blacklisted = mysqli_num_rows($result) > 0;
            mysqli_stmt_close($stmt);
            return $is_blacklisted;
        }
        
        return false;
    }
    
    // Clean up expired blacklisted tokens (can be called periodically)
    public static function cleanupExpiredBlacklistedTokens() {
        global $conn;
        
        $sql = "DELETE FROM blacklisted_tokens WHERE expires_at IS NOT NULL AND expires_at < NOW()";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_execute($stmt);
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            return $affected_rows;
        }
        
        return 0;
    }
}
?>
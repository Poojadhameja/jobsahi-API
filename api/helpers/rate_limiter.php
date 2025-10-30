<?php
// rate_limiter.php - Simple Rate Limiting Helper (File-based)

class RateLimiter {
    private static $enabled = true;
    private static $cache_dir = null;
    
    /**
     * Initialize cache directory
     */
    private static function initCacheDir() {
        if (self::$cache_dir === null) {
            self::$cache_dir = __DIR__ . '/../cache/rate_limits/';
            if (!is_dir(self::$cache_dir)) {
                mkdir(self::$cache_dir, 0755, true);
            }
        }
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get rate limit data from file
     */
    private static function getRateLimitData($endpoint, $ip) {
        self::initCacheDir();
        $filename = self::$cache_dir . md5("{$endpoint}:{$ip}") . '.json';
        
        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true);
            if ($data && isset($data['expires']) && $data['expires'] > time()) {
                return $data;
            }
        }
        
        return null;
    }
    
    /**
     * Save rate limit data to file
     */
    private static function saveRateLimitData($endpoint, $ip, $count, $expires) {
        self::initCacheDir();
        $filename = self::$cache_dir . md5("{$endpoint}:{$ip}") . '.json';
        
        $data = [
            'count' => $count,
            'expires' => $expires,
            'created' => time()
        ];
        
        file_put_contents($filename, json_encode($data));
    }
    
    /**
     * Check rate limit for an endpoint
     */
    public static function check($endpoint, $max_requests = 100, $window_seconds = 3600) {
        if (!self::$enabled) {
            return true; // Allow if rate limiting is disabled
        }
        
        $ip = self::getClientIP();
        $data = self::getRateLimitData($endpoint, $ip);
        $current_time = time();
        
        if ($data === null) {
            // First request
            self::saveRateLimitData($endpoint, $ip, 1, $current_time + $window_seconds);
            return true;
        }
        
        if ($data['count'] >= $max_requests) {
            // Rate limit exceeded
            return false;
        }
        
        // Increment counter
        self::saveRateLimitData($endpoint, $ip, $data['count'] + 1, $data['expires']);
        return true;
    }
    
    /**
     * Get remaining requests for an endpoint
     */
    public static function getRemaining($endpoint, $max_requests = 100) {
        if (!self::$enabled) {
            return $max_requests;
        }
        
        $ip = self::getClientIP();
        $data = self::getRateLimitData($endpoint, $ip);
        
        if ($data === null) {
            return $max_requests;
        }
        
        return max(0, $max_requests - $data['count']);
    }
    
    /**
     * Get reset time for rate limit
     */
    public static function getResetTime($endpoint, $window_seconds = 3600) {
        if (!self::$enabled) {
            return time() + $window_seconds;
        }
        
        $ip = self::getClientIP();
        $data = self::getRateLimitData($endpoint, $ip);
        
        if ($data === null) {
            return time() + $window_seconds;
        }
        
        return $data['expires'];
    }
    
    /**
     * Apply rate limiting to current request
     */
    public static function apply($endpoint, $max_requests = 100, $window_seconds = 3600) {
        if (!self::check($endpoint, $max_requests, $window_seconds)) {
            $reset_time = self::getResetTime($endpoint, $window_seconds);
            
            http_response_code(429);
            echo json_encode([
                "status" => false,
                "message" => "Rate limit exceeded. Please try again later.",
                "code" => "RATE_LIMIT_EXCEEDED",
                "reset_time" => date('Y-m-d H:i:s', $reset_time),
                "timestamp" => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        
        // Add rate limit headers
        $remaining = self::getRemaining($endpoint, $max_requests);
        $reset_time = self::getResetTime($endpoint, $window_seconds);
        
        header("X-RateLimit-Limit: {$max_requests}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$reset_time}");
    }
    
    /**
     * Clear rate limit for an IP (admin function)
     */
    public static function clear($endpoint, $ip = null) {
        if (!self::$enabled) {
            return true;
        }
        
        if ($ip === null) {
            $ip = self::getClientIP();
        }
        
        self::initCacheDir();
        $filename = self::$cache_dir . md5("{$endpoint}:{$ip}") . '.json';
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return false;
    }
    
    /**
     * Clean up expired rate limit files
     */
    public static function cleanup() {
        if (!self::$enabled) {
            return;
        }
        
        self::initCacheDir();
        $files = glob(self::$cache_dir . '*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['expires']) && $data['expires'] < time()) {
                unlink($file);
            }
        }
    }
    
    /**
     * Disable rate limiting (for testing)
     */
    public static function disable() {
        self::$enabled = false;
    }
    
    /**
     * Enable rate limiting
     */
    public static function enable() {
        self::$enabled = true;
    }
}

// Clean up expired rate limits periodically (1% chance)
if (rand(1, 100) === 1) {
    RateLimiter::cleanup();
}
?>

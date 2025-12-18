<?php
// oauth_helper.php - OAuth Helper Functions for Google and LinkedIn

require_once __DIR__ . '/../config/oauth_config.php';

class OAuthHelper {
    
    /**
     * Generate Google OAuth URL
     */
    public static function getGoogleAuthUrl($state = null) {
        if (!$state) {
            $state = bin2hex(random_bytes(16)); // Generate random state for CSRF protection
        }
        
        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        ];
        
        return GOOGLE_AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Exchange Google authorization code for access token and get user info
     */
    public static function getGoogleUserInfo($code) {
        // Exchange code for access token
        $tokenData = [
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init(GOOGLE_TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return ['error' => 'CURL Error: ' . $curlError, 'response' => $response];
        }
        
        if ($httpCode !== 200) {
            $errorResponse = json_decode($response, true);
            $errorMessage = isset($errorResponse['error_description']) ? $errorResponse['error_description'] : (isset($errorResponse['error']) ? $errorResponse['error'] : $response);
            return ['error' => 'Failed to exchange code for token (HTTP ' . $httpCode . '): ' . $errorMessage, 'response' => $errorResponse, 'full_response' => $response];
        }
        
        $tokenResponse = json_decode($response, true);
        if (!isset($tokenResponse['access_token'])) {
            return ['error' => 'No access token received', 'response' => $tokenResponse, 'raw_response' => $response, 'http_code' => $httpCode];
        }
        
        // Debug: Log token received (remove in production)
        error_log("Google OAuth: Access token received successfully");
        
        // Get user info using access token
        // Try OpenID Connect userinfo endpoint first, fallback to OAuth2 endpoint
        $userinfoUrls = [
            'https://openidconnect.googleapis.com/v1/userinfo',
            GOOGLE_USERINFO_URL
        ];
        
        $userInfo = null;
        $lastError = null;
        
        foreach ($userinfoUrls as $userinfoUrl) {
            $ch = curl_init($userinfoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $tokenResponse['access_token']
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $userResponse = curl_exec($ch);
            $userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $userCurlError = curl_error($ch);
            curl_close($ch);
            
            if ($userCurlError) {
                $lastError = 'CURL Error getting user info from ' . $userinfoUrl . ': ' . $userCurlError;
                continue;
            }
            
            if ($userHttpCode === 200 && !empty($userResponse)) {
                $userInfo = json_decode($userResponse, true);
                if ($userInfo && isset($userInfo['email'])) {
                    break; // Success, exit loop
                }
            } else {
                $errorResponse = json_decode($userResponse, true);
                $lastError = 'Failed to get user info from ' . $userinfoUrl . ' (HTTP ' . $userHttpCode . '): ' . ($errorResponse['error'] ?? $userResponse);
            }
        }
        
        if (!$userInfo || !isset($userInfo['email'])) {
            return [
                'error' => 'Failed to get user info from all endpoints',
                'last_error' => $lastError,
                'response' => $userResponse ?? null,
                'http_code' => $userHttpCode ?? null,
                'access_token_received' => isset($tokenResponse['access_token'])
            ];
        }
        
        return [
            'success' => true,
            'id' => $userInfo['sub'] ?? $userInfo['id'] ?? null,
            'email' => $userInfo['email'] ?? null,
            'name' => $userInfo['name'] ?? ($userInfo['given_name'] . ' ' . $userInfo['family_name'] ?? '') ?? null,
            'first_name' => $userInfo['given_name'] ?? null,
            'last_name' => $userInfo['family_name'] ?? null,
            'picture' => $userInfo['picture'] ?? null,
            'verified_email' => $userInfo['email_verified'] ?? $userInfo['verified_email'] ?? false
        ];
    }
    
    /**
     * Get Google user info directly from access token (for Flutter Android/iOS)
     */
    public static function getGoogleUserInfoFromAccessToken($accessToken) {
        // Validate access token
        if (empty($accessToken)) {
            return [
                'error' => 'Access token is empty',
                'http_code' => null
            ];
        }
        
        // Log access token preview for debugging (first 20 chars only)
        error_log("Google OAuth: Attempting to get user info with access token: " . substr($accessToken, 0, 20) . "...");
        
        // Get user info using access token directly
        $userinfoUrls = [
            'https://openidconnect.googleapis.com/v1/userinfo',
            GOOGLE_USERINFO_URL
        ];
        
        $userInfo = null;
        $lastError = null;
        $lastResponse = null;
        $lastHttpCode = null;
        
        foreach ($userinfoUrls as $userinfoUrl) {
            error_log("Google OAuth: Trying userinfo endpoint: " . $userinfoUrl);
            
            $ch = curl_init($userinfoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Use SSL verification in production
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $userResponse = curl_exec($ch);
            $userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $userCurlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            
            if ($userCurlError) {
                $lastError = 'CURL Error getting user info from ' . $userinfoUrl . ' (Error ' . $curlErrno . '): ' . $userCurlError;
                error_log("Google OAuth CURL Error: " . $lastError);
                continue;
            }
            
            $lastResponse = $userResponse;
            $lastHttpCode = $userHttpCode;
            
            if ($userHttpCode === 200 && !empty($userResponse)) {
                $userInfo = json_decode($userResponse, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $lastError = 'Invalid JSON response from ' . $userinfoUrl . ': ' . json_last_error_msg();
                    error_log("Google OAuth JSON Error: " . $lastError);
                    continue;
                }
                
                if ($userInfo && isset($userInfo['email'])) {
                    error_log("Google OAuth: Successfully retrieved user info for: " . $userInfo['email']);
                    break; // Success, exit loop
                } else {
                    $lastError = 'User info response missing email field';
                    error_log("Google OAuth: Response missing email - " . json_encode($userInfo));
                }
            } else {
                $errorResponse = json_decode($userResponse, true);
                $errorMessage = 'Failed to get user info from ' . $userinfoUrl . ' (HTTP ' . $userHttpCode . ')';
                
                if ($errorResponse && isset($errorResponse['error'])) {
                    $errorMessage .= ': ' . $errorResponse['error'];
                    if (isset($errorResponse['error_description'])) {
                        $errorMessage .= ' - ' . $errorResponse['error_description'];
                    }
                } else {
                    $errorMessage .= ': ' . substr($userResponse, 0, 200);
                }
                
                $lastError = $errorMessage;
                error_log("Google OAuth API Error: " . $lastError);
                
                // If we get 401 (Unauthorized), the token is invalid/expired
                if ($userHttpCode === 401) {
                    error_log("Google OAuth: Access token is invalid or expired");
                    break; // Don't try other endpoints if token is invalid
                }
            }
        }
        
        if (!$userInfo || !isset($userInfo['email'])) {
            error_log("Google OAuth: Failed to get user info from all endpoints. Last error: " . $lastError);
            return [
                'error' => 'Failed to get user info from all endpoints',
                'last_error' => $lastError,
                'response' => $lastResponse,
                'http_code' => $lastHttpCode,
                'access_token_preview' => substr($accessToken, 0, 20) . '...'
            ];
        }
        
        return [
            'success' => true,
            'id' => $userInfo['sub'] ?? $userInfo['id'] ?? null,
            'email' => $userInfo['email'] ?? null,
            'name' => $userInfo['name'] ?? ($userInfo['given_name'] . ' ' . $userInfo['family_name'] ?? '') ?? null,
            'first_name' => $userInfo['given_name'] ?? null,
            'last_name' => $userInfo['family_name'] ?? null,
            'picture' => $userInfo['picture'] ?? null,
            'verified_email' => $userInfo['email_verified'] ?? $userInfo['verified_email'] ?? false
        ];
    }
    
    /**
     * Generate LinkedIn OAuth URL
     */
    public static function getLinkedInAuthUrl($state = null) {
        if (!$state) {
            $state = bin2hex(random_bytes(16)); // Generate random state for CSRF protection
        }
        
        $params = [
            'response_type' => 'code',
            'client_id' => LINKEDIN_CLIENT_ID,
            'redirect_uri' => LINKEDIN_REDIRECT_URI,
            'state' => $state,
            'scope' => 'openid profile email'
        ];
        
        return LINKEDIN_AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Exchange LinkedIn authorization code for access token and get user info
     */
    public static function getLinkedInUserInfo($code) {
        // Exchange code for access token
        $tokenData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => LINKEDIN_REDIRECT_URI,
            'client_id' => LINKEDIN_CLIENT_ID,
            'client_secret' => LINKEDIN_CLIENT_SECRET
        ];
        
        $ch = curl_init(LINKEDIN_TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return ['error' => 'CURL Error: ' . $curlError, 'response' => $response];
        }
        
        if ($httpCode !== 200) {
            $errorResponse = json_decode($response, true);
            $errorMessage = isset($errorResponse['error_description']) ? $errorResponse['error_description'] : (isset($errorResponse['error']) ? $errorResponse['error'] : $response);
            return [
                'error' => 'Failed to exchange code for token (HTTP ' . $httpCode . '): ' . $errorMessage,
                'response' => $errorResponse,
                'full_response' => $response,
                'request_data' => [
                    'redirect_uri' => LINKEDIN_REDIRECT_URI,
                    'client_id' => substr(LINKEDIN_CLIENT_ID, 0, 10) . '...'
                ]
            ];
        }
        
        $tokenResponse = json_decode($response, true);
        if (!isset($tokenResponse['access_token'])) {
            return [
                'error' => 'No access token received',
                'response' => $tokenResponse,
                'raw_response' => $response,
                'http_code' => $httpCode
            ];
        }
        
        // Get user info using access token
        // LinkedIn OpenID Connect userinfo endpoint
        $userinfoUrls = [
            'https://api.linkedin.com/v2/userinfo', // OpenID Connect endpoint
        ];
        
        $userInfo = null;
        $lastError = null;
        
        foreach ($userinfoUrls as $userinfoUrl) {
            $ch = curl_init($userinfoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $tokenResponse['access_token']
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $userResponse = curl_exec($ch);
            $userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $userCurlError = curl_error($ch);
            curl_close($ch);
            
            if ($userCurlError) {
                $lastError = 'CURL Error getting user info from ' . $userinfoUrl . ': ' . $userCurlError;
                continue;
            }
            
            if ($userHttpCode === 200 && !empty($userResponse)) {
                $userInfo = json_decode($userResponse, true);
                if ($userInfo && (isset($userInfo['email']) || isset($userInfo['sub']))) {
                    break; // Success, exit loop
                }
            } else {
                $errorResponse = json_decode($userResponse, true);
                $lastError = 'Failed to get user info from ' . $userinfoUrl . ' (HTTP ' . $userHttpCode . '): ' . ($errorResponse['message'] ?? $errorResponse['error'] ?? $userResponse);
            }
        }
        
        if (!$userInfo || (!isset($userInfo['email']) && !isset($userInfo['sub']))) {
            return [
                'error' => 'Failed to get user info from all endpoints',
                'last_error' => $lastError,
                'response' => $userResponse ?? null,
                'http_code' => $userHttpCode ?? null,
                'access_token_received' => isset($tokenResponse['access_token'])
            ];
        }
        
        return [
            'success' => true,
            'id' => $userInfo['sub'] ?? null, // LinkedIn uses 'sub' for user ID
            'email' => $userInfo['email'] ?? null,
            'name' => $userInfo['name'] ?? ($userInfo['given_name'] . ' ' . $userInfo['family_name'] ?? ''),
            'first_name' => $userInfo['given_name'] ?? null,
            'last_name' => $userInfo['family_name'] ?? null,
            'picture' => $userInfo['picture'] ?? null,
            'email_verified' => $userInfo['email_verified'] ?? false
        ];
    }
}
?>


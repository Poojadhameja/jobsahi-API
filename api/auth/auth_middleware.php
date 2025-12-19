<?php
// auth_middleware.php - JWT Authentication Middleware
require_once __DIR__ . '/../db.php'; // Add this line to include the config file
require_once __DIR__ . '/../jwt_token/jwt_helper.php'; // Include JWT helper

function authenticateJWT($required_role = null) {
    $jwt = JWTHelper::getJWTFromHeader();
    
    if (!$jwt) {
        http_response_code(401);
        echo json_encode(array("message" => "No token provided", "status" => false));
        exit;
    }
    
    // ✅ Check if token is blacklisted
    if (JWTHelper::isTokenBlacklisted($jwt)) {
        http_response_code(401);
        echo json_encode(array("message" => "Token has been revoked", "status" => false));
        exit;
    }
    
    $payload = JWTHelper::validateJWT($jwt, JWT_SECRET);
    
    if (!$payload) {
        http_response_code(401);
        echo json_encode(array("message" => "Invalid or expired token", "status" => false));
        exit;
    }
    
    // ✅ Check role(s) if specified
    if ($required_role) {
        $userRole = $payload['role'] ?? null;

        // If required_role is a string, convert to array
        $allowedRoles = is_array($required_role) ? $required_role : [$required_role];

        if (!in_array($userRole, $allowedRoles)) {
            http_response_code(403);
            echo json_encode(array("message" => "Insufficient permissions", "status" => false));
            exit;
        }
    }
    
    return $payload; // Return user data from token
}

function getCurrentUser() {
    $jwt = JWTHelper::getJWTFromHeader();
    if ($jwt) {
        return JWTHelper::validateJWT($jwt, JWT_SECRET);
    }
    return null;
}

// Optional authentication - returns user data if token is valid, null otherwise (doesn't exit)
function authenticateJWTOptional($allowed_roles = null) {
    $jwt = JWTHelper::getJWTFromHeader();
    
    if (!$jwt) {
        return null; // No token provided - allow public access
    }
    
    // Check if token is blacklisted
    if (JWTHelper::isTokenBlacklisted($jwt)) {
        return null; // Token revoked - treat as public
    }
    
    $payload = JWTHelper::validateJWT($jwt, JWT_SECRET);
    
    if (!$payload) {
        return null; // Invalid token - allow public access
    }
    
    // Check role(s) if specified
    if ($allowed_roles) {
        $userRole = $payload['role'] ?? null;
        $allowedRoles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
        
        if (!in_array($userRole, $allowedRoles)) {
            return null; // Role not allowed - treat as public
        }
    }
    
    return $payload; // Return user data from token
}
?>

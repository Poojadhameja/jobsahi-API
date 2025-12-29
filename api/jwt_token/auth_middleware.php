<?php
// auth_middleware.php - JWT Authentication Middleware
require_once __DIR__ . '/../db.php'; // Add this line to include the config file

function authenticateJWT($required_role = null) {
    $jwt = JWTHelper::getJWTFromHeader();
    
    if (!$jwt) {
        http_response_code(401);
        echo json_encode(array("message" => "No token provided", "status" => false));
        exit;
    }
    
    $payload = JWTHelper::validateJWT($jwt, JWT_SECRET);
    
    if (!$payload) {
        http_response_code(401);
        echo json_encode(array("message" => "Invalid or expired token", "status" => false));
        exit;
    }
    
    // Check role if specified
    if ($required_role) {
        $user_role = $payload['role'] ?? null;
        
        // Handle both array and string role requirements
        if (is_array($required_role)) {
            // If required_role is an array, check if user's role is in the array
            if (!in_array($user_role, $required_role)) {
                http_response_code(403);
                echo json_encode(array("message" => "Insufficient permissions. Required role: " . implode(', ', $required_role), "status" => false));
                exit;
            }
        } else {
            // If required_role is a string, check exact match
            if ($user_role !== $required_role) {
                http_response_code(403);
                echo json_encode(array("message" => "Insufficient permissions. Required role: $required_role", "status" => false));
                exit;
            }
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
?>
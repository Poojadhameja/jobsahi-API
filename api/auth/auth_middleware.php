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
?>

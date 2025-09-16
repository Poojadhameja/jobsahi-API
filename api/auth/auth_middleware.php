<?php
// auth_middleware.php - JWT Authentication Middleware
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/response_helper.php';

function authenticateJWT($required_role = null) {
    $jwt = JWTHelper::getJWTFromHeader();
    
    if (!$jwt) {
        ResponseHelper::unauthorized("No token provided");
    }
    
    $payload = JWTHelper::validateJWT($jwt, JWT_SECRET);
    
    if (!$payload) {
        ResponseHelper::unauthorized("Invalid or expired token");
    }
    
    // Check role if specified
    if ($required_role && isset($payload['role']) && $payload['role'] !== $required_role) {
        ResponseHelper::forbidden("Insufficient permissions. Required role: {$required_role}");
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

/**
 * Check if user has required permissions
 */
function checkPermission($required_permissions = []) {
    $user = getCurrentUser();
    
    if (!$user) {
        ResponseHelper::unauthorized("Authentication required");
    }
    
    if (empty($required_permissions)) {
        return $user;
    }
    
    $user_permissions = $user['permissions'] ?? [];
    
    foreach ($required_permissions as $permission) {
        if (!in_array($permission, $user_permissions)) {
            ResponseHelper::forbidden("Missing permission: {$permission}");
        }
    }
    
    return $user;
}

/**
 * Validate request method
 */
function validateMethod($allowed_methods) {
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowed_methods)) {
        ResponseHelper::error("Method not allowed. Allowed methods: " . implode(', ', $allowed_methods), 405, "METHOD_NOT_ALLOWED");
    }
}

/**
 * Get JSON input with validation
 */
function getJsonInput() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        ResponseHelper::error("Invalid JSON data", 400, "INVALID_JSON");
    }
    
    return $data;
}
?>

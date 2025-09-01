<?php
// auth_middleware.php - JWT Authentication Middleware
<<<<<<< HEAD
require_once __DIR__ . '/../db.php'; // Add this line to include the config file
=======
<<<<<<<< HEAD:auth/auth_middleware.php
require_once '../db.php'; // Add this line to include the config file
========
require_once __DIR__ . '/../db.php'; // Add this line to include the config file
>>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289:api/auth/auth_middleware.php
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289

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
    
<<<<<<< HEAD
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
=======
    // Check role if specified
    if ($required_role && isset($payload['role']) && $payload['role'] !== $required_role) {
        http_response_code(403);
        echo json_encode(array("message" => "Insufficient permissions", "status" => false));
        exit;
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
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
<<<<<<< HEAD
?>
=======
?>
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289

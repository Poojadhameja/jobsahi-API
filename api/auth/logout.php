<?php
// logout.php - JWT-based logout with server-side token blacklisting
require '../cors.php';
require_once '../jwt_token/jwt_helper.php';

// Verify token exists and is valid
$current_user = authenticateJWT();

// Get the JWT token from header
$jwt = JWTHelper::getJWTFromHeader();
$user_id = $current_user['user_id'] ?? null;

// ✅ Blacklist the token on server-side
if ($jwt && JWTHelper::blacklistToken($jwt, $user_id)) {
    http_response_code(200);
    echo json_encode(array(
        "message" => "Logout successful. Token has been revoked.", 
        "status" => true
    ));
} else {
    http_response_code(200);
    echo json_encode(array(
        "message" => "Logout successful. Please remove the token from client storage.", 
        "status" => true
    ));
}
?>
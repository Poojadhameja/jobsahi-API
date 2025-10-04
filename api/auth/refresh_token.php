<?php
require '../cors.php';
// Authenticate current token
$current_user = authenticateJWT();

// Generate new token
$payload = [
    'user_id' => $current_user['user_id'],
    'email' => $current_user['email'],
    'name' => $current_user['name'],
    'role' => $current_user['role'],
    'phone_number' => $current_user['phone_number'],
    'iat' => time(),
    'exp' => time() + JWT_EXPIRY
];

$new_token = JWTHelper::generateJWT($payload, JWT_SECRET);

http_response_code(200);
echo json_encode(array(
    "message" => "Token refreshed successfully",
    "status" => true,
    "token" => $new_token,
    "expires_in" => JWT_EXPIRY
));
?>
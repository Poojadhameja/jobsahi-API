<?php
include '../CORS.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate user (any role)
$current_user = authenticateJWT();

http_response_code(200);
echo json_encode(array(
    "message" => "Access granted to protected resource",
    "status" => true,
    "user" => $current_user
));
?>

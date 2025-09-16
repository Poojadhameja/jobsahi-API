<?php
include '../CORS.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate user and require admin role
authenticateJWT('admin');

http_response_code(200);
echo json_encode(array(
    "message" => "Access granted to admin-only resource",
    "status" => true
));
?>
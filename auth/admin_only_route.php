<?php
// admin_only_route.php - Example of an admin-only route
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../auth/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate user and require admin role
authenticateJWT('admin');

http_response_code(200);
echo json_encode(array(
    "message" => "Access granted to admin-only resource",
    "status" => true
));
?>
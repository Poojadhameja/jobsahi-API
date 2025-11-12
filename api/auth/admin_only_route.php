<?php
// admin_only_route.php - Example of an admin-only route
require_once '../cors.php';

// Authenticate user and require admin role
authenticateJWT('admin');

http_response_code(200);
echo json_encode(array(
    "message" => "Access granted to admin-only resource",
    "status" => true
));
?>
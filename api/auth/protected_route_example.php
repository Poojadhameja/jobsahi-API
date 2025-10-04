<?php
// protected_route_example.php - Example of a protected route
require_once '../cors.php';

// Authenticate user (any role)
$current_user = authenticateJWT();

http_response_code(200);
echo json_encode(array(
    "message" => "Access granted to protected resource",
    "status" => true,
    "user" => $current_user
));
?>
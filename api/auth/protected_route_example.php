<?php
<<<<<<< HEAD
<<<<<<< HEAD
// protected_route_example.php - Example of a protected route
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

=======
require '../cors.php';
>>>>>>> dfdb9388f97f0ad9898e04e43042129728ce7246
=======
require '../cors.php';
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1
// Authenticate user (any role)
$current_user = authenticateJWT();

http_response_code(200);
echo json_encode(array(
    "message" => "Access granted to protected resource",
    "status" => true,
    "user" => $current_user
));
?>

<?php
// logout.php - JWT-based logout (client-side token removal)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("message" => "Only POST requests allowed", "status" => false));
    exit;
}

// Verify token exists and is valid
$current_user = authenticateJWT();

// Note: JWT tokens are stateless, so we can't invalidate them server-side
// The client should remove the token from storage
http_response_code(200);
echo json_encode(array(
    "message" => "Logout successful. Please remove the token from client storage.", 
    "status" => true
));
?>
<?php
// logout.php - JWT-based logout (client-side token removal)
require '../cors.php';
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
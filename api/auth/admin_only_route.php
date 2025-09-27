<?php
require '../cors.php';
authenticateJWT('admin');

http_response_code(200);
echo json_encode(array(
    "message" => "Access granted to admin-only resource",
    "status" => true
));
?>
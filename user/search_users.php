<?php
// search_users.php - Search users by name
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get search value from GET or POST JSON
if (isset($_GET['search'])) {
    $search_value = $_GET['search'];
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $search_value = isset($data['search']) ? $data['search'] : '';
}

if (empty($search_value)) {
    echo json_encode(["message" => "Search value is required", "status" => false]);
    exit;
}

include "../database/config.php";

$search_value = mysqli_real_escape_string($conn, $search_value); // prevent SQL injection

$sql = "SELECT id, name, email, role, phone_number, is_verified 
        FROM users 
        WHERE id LIKE '{$search_value}%' 
           OR email LIKE '{$search_value}%' 
           OR phone_number LIKE '{$search_value}%'
        ORDER BY name ASC";

$result = mysqli_query($conn, $sql) or die("SQL Query Failed.");

if (mysqli_num_rows($result) > 0) {
    $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
    echo json_encode(["users" => $users, "count" => count($users), "status" => true]);
} else {
    echo json_encode(["message" => "No users found", "users" => [], "status" => false]);
}
?>

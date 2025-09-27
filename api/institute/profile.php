<?php
require_once '../cors.php';

// ✅ Authenticate JWT (allowed roles: admin, institute)
$current_user = authenticateJWT(['admin', 'institute']); 
$user_role = strtolower($current_user['role']); // assuming payload has 'role'
$user_id = $current_user['user_id'] ?? null; // get user_id from JWT

// ✅ Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// ✅ Role-based SQL condition
if ($user_role === 'admin') {
    // Admin sees all institute profiles (pending and approved)
    $sql = "SELECT 
                id, 
                user_id, 
                location, 
                courses_offered, 
                admin_action,
                created_at, 
                modified_at, 
                deleted_at
            FROM institute_profiles 
            WHERE deleted_at IS NULL 
              AND (admin_action = 'pending' OR admin_action = 'approved')
            ORDER BY created_at DESC";
} else if ($user_role === 'institute') {
    // Institute sees only their own profile
    $sql = "SELECT 
                id, 
                user_id, 
                location, 
                courses_offered, 
                admin_action,
                created_at, 
                modified_at, 
                deleted_at
            FROM institute_profiles 
            WHERE deleted_at IS NULL 
              AND user_id = ?
            ORDER BY created_at DESC";
} else {
    // Other roles → only approved institutes
    $sql = "SELECT 
                id, 
                user_id, 
                location, 
                courses_offered, 
                admin_action,
                created_at, 
                modified_at, 
                deleted_at
            FROM institute_profiles 
            WHERE deleted_at IS NULL 
              AND admin_action = 'approved'
            ORDER BY created_at DESC";
}

// ✅ Use prepared statement for institute role
if ($user_role === 'institute') {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

$institutes = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($institute = mysqli_fetch_assoc($result)) {
        $institutes[] = $institute;
    }
}

// ✅ Close prepared statement if used
if ($user_role === 'institute' && isset($stmt)) {
    mysqli_stmt_close($stmt);
}

echo json_encode([
    "message" => "Institute profiles fetched successfully",
    "status" => true,
    "count" => count($institutes),
    "data" => $institutes,
    "timestamp" => date('Y-m-d H:i:s')
]);

mysqli_close($conn);
?>
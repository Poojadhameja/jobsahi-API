<?php
include '../CORS.php';
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT for multiple roles
$decoded = authenticateJWT(['admin', 'institute']); // decoded JWT payload
$user_role = $decoded['role'] ?? '';

// Build SQL based on role
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
";

// Only admin sees 'pending', others only see 'approval'
if ($user_role !== 'admin') {
    $sql .= " WHERE admin_action = 'approval'";
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $students = [];
        
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "message" => "Institute profiles retrieved successfully",
            "data" => $students,
            "count" => count($students)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve institute profiles",
            "error" => $stmt->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

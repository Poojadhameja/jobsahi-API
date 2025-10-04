<?php
// list_institute_students_by_id.php - List all students of institute by ID
require_once '../cors.php';

// ✅ Authenticate JWT for multiple roles
$decoded = authenticateJWT(['admin', 'institute']); // decoded JWT payload
$user_role = $decoded['role'] ?? '';

// Get institute ID from URL parameter
$institute_id = $_GET['id'] ?? null;

if (!$institute_id) {
    echo json_encode([
        "status" => false,
        "message" => "Institute ID is required"
    ]);
    exit();
}

// Build SQL to get students of specific institute
// Only select columns that exist in student_profiles table
$sql = "SELECT 
    sp.id,
    sp.user_id,
    sp.admin_action,
    sp.created_at,
    sp.modified_at,
    sp.deleted_at,
    ist.id AS institute_id
FROM student_profiles sp
INNER JOIN institute_profiles ist
WHERE ist.id = ?
";

// Only admin sees 'pending', others only see 'approved'
if ($user_role !== 'admin') {
    $sql .= " AND sp.admin_action = 'approved'";
}

$sql .= " ORDER BY sp.created_at DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $students = [];
        
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "message" => "Students retrieved successfully",
            "data" => $students,
            "count" => count($students)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve students",
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
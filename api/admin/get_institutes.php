<?php
// get_institutes.php - Get all institutes (Admin access only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']); // returns array

try {
    // First, let's check what columns exist in both tables
    $checkUsers = $conn->query("DESCRIBE users");
    $checkInstitutes = $conn->query("DESCRIBE institute_profiles");
    
    if (!$checkUsers || !$checkInstitutes) {
        throw new Exception("Cannot access table structure");
    }
    
    // Get column names for users table
    $userColumns = [];
    while ($row = $checkUsers->fetch_assoc()) {
        $userColumns[] = $row['Field'];
    }
    
    // Get column names for institute_profiles table
    $instituteColumns = [];
    while ($row = $checkInstitutes->fetch_assoc()) {
        $instituteColumns[] = $row['Field'];
    }
    
    // Determine the correct user ID column name
    $userIdColumn = 'id'; // default
    if (in_array('user_id', $userColumns)) {
        $userIdColumn = 'user_id';
    } elseif (in_array('id', $userColumns)) {
        $userIdColumn = 'id';
    }
    
    // Determine the correct foreign key column in institute_profiles
    $foreignKeyColumn = 'user_id'; // default
    if (in_array('user_id', $instituteColumns)) {
        $foreignKeyColumn = 'user_id';
    } elseif (in_array('created_by', $instituteColumns)) {
        $foreignKeyColumn = 'created_by';
    } elseif (in_array('admin_id', $instituteColumns)) {
        $foreignKeyColumn = 'admin_id';
    }
    
    // Check if required columns exist in users table
    $emailColumn = in_array('email', $userColumns) ? 'u.email' : 'NULL';
    $nameColumn = in_array('name', $userColumns) ? 'u.name' : (in_array('username', $userColumns) ? 'u.username' : 'NULL');
    $statusColumn = in_array('status', $userColumns) ? 'u.status' : (in_array('is_active', $userColumns) ? 'u.is_active' : 'NULL');
    
    // Build the query with correct column names
    $stmt = $conn->prepare("
        SELECT 
            ip.*,
            u.{$userIdColumn} as user_id,
            {$emailColumn} as user_email,
            {$nameColumn} as contact_person,
            {$statusColumn} as user_status
        FROM institute_profiles ip
        LEFT JOIN users u ON ip.{$foreignKeyColumn} = u.{$userIdColumn}
        ORDER BY ip.created_at DESC
    ");
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $institutes = [];
        
        while ($row = $result->fetch_assoc()) {
            $institutes[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "message" => "Institutes retrieved successfully",
            "data" => $institutes,
            "count" => count($institutes)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve institutes",
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
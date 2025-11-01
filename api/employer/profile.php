<?php
// get_employer_profiles.php - Get employer/recruiter profiles with admin_action filter
require_once '../cors.php';

// ✅ Authenticate user and get decoded token
$decoded_token = authenticateJWT(['admin', 'recruiter']);
$user_role = strtolower($decoded_token['role'] ?? '');
$user_id = $decoded_token['user_id'] ?? null;

// ✅ Build SQL query based on role
if ($user_role === 'admin') {
    // Admin sees all pending and approved profiles
    $sql = "SELECT id, user_id, company_name, company_logo, industry, website, location, admin_action, created_at, modified_at 
            FROM recruiter_profiles 
            WHERE deleted_at IS NULL
            AND (admin_action = 'pending' OR admin_action = 'approved')
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
} 
elseif ($user_role === 'recruiter' && $user_id) {
    // Recruiter sees only their own approved profile
    $sql = "SELECT id, user_id, company_name, company_logo, industry, website, location, admin_action, created_at, modified_at 
            FROM recruiter_profiles 
            WHERE deleted_at IS NULL
            AND admin_action = 'approved'
            AND user_id = ?
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} 
else {
    http_response_code(403);
    echo json_encode(["message" => "Unauthorized or invalid user role", "status" => false]);
    exit;
}

// ✅ Execute query
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    http_response_code(500);
    echo json_encode(["message" => "Database query failed", "status" => false]);
    exit;
}

// ✅ Prepare response
if ($result->num_rows > 0) {
    $profiles = $result->fetch_all(MYSQLI_ASSOC);
    http_response_code(200);
    echo json_encode([
        "profiles" => $profiles,
        "count" => count($profiles),
        "status" => true
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        "profiles" => [],
        "count" => 0,
        "status" => true
    ]);
}

// ✅ Close connection
$stmt->close();
$conn->close();
?>

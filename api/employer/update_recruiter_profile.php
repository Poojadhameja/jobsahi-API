<?php
require_once '../cors.php';

// ✅ Authenticate JWT (allowed roles: admin, recruiter)
$current_user = authenticateJWT(['admin', 'recruiter']);
$user_role = strtolower($current_user['role']);
$user_id = $current_user['user_id']; // ✅ user_id from token

// ✅ Allow only PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["message" => "Only PUT requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// ✅ Determine recruiter_id based on role
if ($user_role === 'admin') {
    // Admin can update any recruiter profile
    $recruiter_id = isset($_GET['recruiter_id']) ? intval($_GET['recruiter_id']) : 0;
    if ($recruiter_id <= 0) {
        echo json_encode(["message" => "Missing or invalid recruiter_id for admin", "status" => false]);
        exit;
    }
} else {
    // Recruiter can only update their own approved profile
    $stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? AND admin_action = 'approved' AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($recruiter_id);
    $stmt->fetch();
    $stmt->close();

    if (!$recruiter_id) {
        echo json_encode(["message" => "Profile not found or not approved for this recruiter", "status" => false]);
        exit;
    }
}

// ✅ Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["message" => "Invalid JSON input", "status" => false]);
    exit;
}

// ✅ Define allowed fields for update
$allowed_fields = ['company_name', 'company_logo', 'industry', 'website', 'location', 'admin_action'];

// ✅ Restrict admin_action updates — only admin can change this field
if ($user_role !== 'admin') {
    unset($allowed_fields[array_search('admin_action', $allowed_fields)]);
}

$update_fields = [];
$params = [];
$types = '';

// ✅ Build dynamic SQL based on provided fields
foreach ($allowed_fields as $field) {
    if (isset($input[$field])) {
        $update_fields[] = "$field = ?";
        $params[] = $input[$field];
        $types .= 's';
    }
}

if (empty($update_fields)) {
    echo json_encode(["message" => "No valid fields provided for update", "status" => false]);
    exit;
}

// ✅ Always update modified_at
$update_fields[] = "modified_at = NOW()";

// ✅ Build SQL query based on role
if ($user_role === 'admin') {
    $sql = "UPDATE recruiter_profiles 
            SET " . implode(', ', $update_fields) . " 
            WHERE id = ? AND deleted_at IS NULL";
    $params[] = $recruiter_id;
    $types .= 'i';
} else {
    // Recruiter can update only their own approved profile
    $sql = "UPDATE recruiter_profiles 
            SET " . implode(', ', $update_fields) . " 
            WHERE id = ? AND user_id = ? AND admin_action = 'approved' AND deleted_at IS NULL";
    $params[] = $recruiter_id;
    $params[] = $user_id;
    $types .= 'ii';
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["message" => "Failed to prepare statement: " . mysqli_error($conn), "status" => false]);
    $conn->close();
    exit;
}

$stmt->bind_param($types, ...$params);

// ✅ Execute update
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // ✅ Fetch updated profile
        if ($user_role === 'admin') {
            $fetch_sql = "SELECT id, user_id, company_name, company_logo, industry, website, location, admin_action, created_at, modified_at 
                          FROM recruiter_profiles 
                          WHERE id = ? AND deleted_at IS NULL";
            $fetch_stmt = $conn->prepare($fetch_sql);
            $fetch_stmt->bind_param('i', $recruiter_id);
        } else {
            $fetch_sql = "SELECT id, user_id, company_name, company_logo, industry, website, location, admin_action, created_at, modified_at 
                          FROM recruiter_profiles 
                          WHERE id = ? AND user_id = ? AND admin_action = 'approved' AND deleted_at IS NULL";
            $fetch_stmt = $conn->prepare($fetch_sql);
            $fetch_stmt->bind_param('ii', $recruiter_id, $user_id);
        }

        $fetch_stmt->execute();
        $result = $fetch_stmt->get_result();

        if ($updated_profile = $result->fetch_assoc()) {
            echo json_encode([
                "message" => "Recruiter profile updated successfully",
                "status" => true,
                "profile" => $updated_profile,
                "updated_by" => $user_role,
                "timestamp" => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                "message" => "Profile updated but not visible to your role",
                "status" => false
            ]);
        }

        $fetch_stmt->close();
    } else {
        echo json_encode([
            "message" => "No record updated. Check recruiter_id or profile may be deleted",
            "status" => false
        ]);
    }
} else {
    echo json_encode(["message" => "Update failed: " . $stmt->error, "status" => false]);
}

$stmt->close();
$conn->close();
?>

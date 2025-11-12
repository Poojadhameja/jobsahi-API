<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT (Admin / Institute)
try {
    $decoded = authenticateJWT(['admin', 'institute']);
    $user_role = strtolower($decoded['role']);
    $user_id   = intval($decoded['user_id'] ?? 0);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Authentication failed: " . $e->getMessage()]);
    exit;
}

// ✅ Allow only PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["success" => false, "message" => "Only PUT requests allowed"]);
    exit;
}

// ✅ Decode JSON
$input = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

// ✅ DB check
if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed: " . mysqli_connect_error()]);
    exit;
}

// ✅ Determine profile_id automatically
$profile_id = 0;

if ($user_role === 'institute') {
    // Fetch institute profile linked to the logged-in user
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $profile_id = intval($row['id']);
    }
    $stmt->close();
} elseif ($user_role === 'admin' && isset($input['profile_id'])) {
    $profile_id = intval($input['profile_id']);
}

// ✅ Validate profile ID
if ($profile_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid or missing institute profile ID"]);
    exit;
}

// ✅ Verify profile exists
$check_sql = "SELECT id, user_id, admin_action FROM institute_profiles WHERE id = ? AND deleted_at IS NULL";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $profile_id);
mysqli_stmt_execute($check_stmt);
$res = mysqli_stmt_get_result($check_stmt);
if (mysqli_num_rows($res) === 0) {
    echo json_encode(["success" => false, "message" => "Institute profile not found"]);
    exit;
}
$profile = mysqli_fetch_assoc($res);
mysqli_stmt_close($check_stmt);

// ✅ Restrict institute user
if ($user_role === 'institute' && $profile['user_id'] !== $user_id) {
    echo json_encode(["success" => false, "message" => "Access denied: You can update only your own profile"]);
    exit;
}

// ✅ Begin transaction
mysqli_autocommit($conn, false);
$success = true;
$error_message = "";

// ✅ Profile fields
$fields_map = [
    'institute_name', 'institute_type', 'website', 'description', 'address',
    'city', 'state', 'country', 'postal_code', 'contact_person',
    'contact_designation', 'accreditation', 'established_year', 'location',
    'courses_offered'
];

$update_fields = [];
$update_values = [];
$types = "";

foreach ($fields_map as $field) {
    if (isset($input[$field])) {
        $update_fields[] = "$field = ?";
        $update_values[] = $input[$field];
        $types .= "s";
    }
}

// ✅ Admin can modify admin_action
if ($user_role === 'admin' && isset($input['admin_action'])) {
    $valid = ['pending', 'approved', 'rejected'];
    if (in_array($input['admin_action'], $valid)) {
        $update_fields[] = "admin_action = ?";
        $update_values[] = $input['admin_action'];
        $types .= "s";
    }
}

// ✅ No data to update
if (empty($update_fields) && empty($input['email']) && empty($input['user_name']) && empty($input['phone_number'])) {
    echo json_encode(["success" => false, "message" => "No valid fields to update"]);
    mysqli_close($conn);
    exit;
}

// ✅ Add modified_at
$update_fields[] = "modified_at = NOW()";

// ✅ Update institute_profiles
if (!empty($update_fields)) {
    $sql = "UPDATE institute_profiles SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $update_values[] = $profile_id;
    $types .= "i";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$update_values);
        if (!mysqli_stmt_execute($stmt)) {
            $success = false;
            $error_message = mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $success = false;
        $error_message = mysqli_error($conn);
    }
}

// ✅ Update users table
if ($success && ($input['email'] ?? $input['user_name'] ?? $input['phone_number'])) {
    $user_update = [];
    $u_values = [];
    $u_types = "";

    if (isset($input['email'])) {
        $user_update[] = "email = ?";
        $u_values[] = $input['email'];
        $u_types .= "s";
    }
    if (isset($input['user_name'])) {
        $user_update[] = "user_name = ?";
        $u_values[] = $input['user_name'];
        $u_types .= "s";
    }
    if (isset($input['phone_number'])) {
        $user_update[] = "phone_number = ?";
        $u_values[] = $input['phone_number'];
        $u_types .= "s";
    }

    $u_values[] = $profile['user_id'];
    $u_types .= "i";

    $u_sql = "UPDATE users SET " . implode(", ", $user_update) . ", updated_at = NOW() WHERE id = ?";
    $u_stmt = mysqli_prepare($conn, $u_sql);
    mysqli_stmt_bind_param($u_stmt, $u_types, ...$u_values);
    if (!mysqli_stmt_execute($u_stmt)) {
        $success = false;
        $error_message = mysqli_stmt_error($u_stmt);
    }
    mysqli_stmt_close($u_stmt);
}

// ✅ Commit & Fetch final record
if ($success) {
    mysqli_commit($conn);

    $fetch_sql = "SELECT 
        p.*, u.email, u.user_name, u.phone_number 
        FROM institute_profiles p
        INNER JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.deleted_at IS NULL";

    $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $profile_id);
    mysqli_stmt_execute($fetch_stmt);
    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch_stmt));
    mysqli_stmt_close($fetch_stmt);

    // ✅ Final grouped response
    $response = [
        "personal_info" => [
            "email" => $data['email'],
            "user_name" => $data['user_name'],
            "phone_number" => $data['phone_number']
        ],
        "institute_info" => [
            "institute_name" => $data['institute_name'],
            "institute_type" => $data['institute_type'],
            "description" => $data['description'],
            "courses_offered" => $data['courses_offered'],
            "established_year" => $data['established_year'],
            "accreditation" => $data['accreditation']
        ],
        "contact_info" => [
            "website" => $data['website'],
            "address" => $data['address'],
            "city" => $data['city'],
            "state" => $data['state'],
            "country" => $data['country'],
            "postal_code" => $data['postal_code'],
            "contact_person" => $data['contact_person'],
            "contact_designation" => $data['contact_designation']
        ],
        "location_info" => [
            "location" => $data['location']
        ],
        "status" => [
            "admin_action" => $data['admin_action'],
            "created_at" => $data['created_at'],
            "modified_at" => $data['modified_at']
        ]
    ];

    echo json_encode([
        "success" => true,
        "message" => "Institute profile updated successfully",
        "data" => $response,
        "meta" => [
            "profile_id" => $profile_id,
            "updated_by" => $user_role,
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0"
        ]
    ], JSON_PRETTY_PRINT);
} else {
    mysqli_rollback($conn);
    echo json_encode(["success" => false, "message" => "Update failed: " . $error_message]);
}

mysqli_autocommit($conn, true);
mysqli_close($conn);
?>

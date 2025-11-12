<?php
// get_faculty_users.php - Fetch faculty users with role-based access
require_once '../cors.php';

// ✅ Authenticate and allow "admin", "institute"
$decoded = authenticateJWT(['admin', 'institute']); 

// ✅ Extract user_id and role from token
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

// ✅ Determine institute_id based on user role
$institute_id = null;

if ($user_role === 'admin') {
    // Admin can specify institute_id via query parameter or fetch from their profile
    if (isset($_GET['institute_id']) && !empty($_GET['institute_id'])) {
        $institute_id = intval($_GET['institute_id']);
    } else {
        // Fetch institute_id from users table for admin (optional)
        $fetch_institute_sql = "SELECT institute_id FROM users WHERE id = ?";
        $fetch_stmt = mysqli_prepare($conn, $fetch_institute_sql);
        mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
        mysqli_stmt_execute($fetch_stmt);
        $fetch_result = mysqli_stmt_get_result($fetch_stmt);
        
        if ($row = mysqli_fetch_assoc($fetch_result)) {
            $institute_id = $row['institute_id'];
        }
        mysqli_stmt_close($fetch_stmt);
    }
} elseif ($user_role === 'institute') {
    // For institute role, fetch institute_id from institute_profiles table
    $fetch_institute_sql = "SELECT id FROM institute_profiles WHERE user_id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_institute_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
    mysqli_stmt_execute($fetch_stmt);
    $fetch_result = mysqli_stmt_get_result($fetch_stmt);
    
    if ($row = mysqli_fetch_assoc($fetch_result)) {
        $institute_id = $row['id'];
    }
    mysqli_stmt_close($fetch_stmt);
    
    // Institute must have an institute_id
    if ($institute_id === null) {
        echo json_encode([
            "message" => "Institute ID not found for the user",
            "status" => false
        ]);
        exit;
    }
}

// ✅ Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
$offset = ($page - 1) * $limit;

// ✅ Filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$filter_admin_action = isset($_GET['admin_action']) ? mysqli_real_escape_string($conn, $_GET['admin_action']) : null;

// ✅ Build WHERE clause based on role
$where_conditions = [];
$params = [];
$param_types = '';

if ($user_role === 'admin') {
    // Admin can see all faculty users or filter by institute_id
    if ($institute_id) {
        $where_conditions[] = "fu.institute_id = ?";
        $params[] = $institute_id;
        $param_types .= 'i';
    }
    
    // Admin can filter by admin_action
    if ($filter_admin_action) {
        $where_conditions[] = "fu.admin_action = ?";
        $params[] = $filter_admin_action;
        $param_types .= 's';
    }
} elseif ($user_role === 'institute') {
    // Institute can only see their own faculty users (approved only)
    $where_conditions[] = "fu.institute_id = ?";
    $where_conditions[] = "fu.admin_action = 'approved'";
    $params[] = $institute_id;
    $param_types .= 'i';
}

// ✅ Add search filter
if ($search) {
    $where_conditions[] = "(fu.name LIKE ? OR fu.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'ss';
}

// ✅ Build final WHERE clause
$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ✅ Count total records
$count_sql = "
    SELECT COUNT(*) as total
    FROM faculty_users fu
    $where_sql
";

$count_stmt = mysqli_prepare($conn, $count_sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
}

mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_row = mysqli_fetch_assoc($count_result);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($count_stmt);

// ✅ Fetch faculty users
$get_sql = "
    SELECT 
        fu.id, 
        fu.institute_id, 
        fu.name, 
        fu.email, 
        fu.phone, 
        fu.admin_action
    FROM faculty_users fu
    $where_sql
    ORDER BY fu.id DESC
    LIMIT ? OFFSET ?
";

// Add limit and offset to params
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

$get_stmt = mysqli_prepare($conn, $get_sql);

if (!$get_stmt) {
    echo json_encode([
        "message" => "Database prepare error: " . mysqli_error($conn),
        "status" => false
    ]);
    exit;
}

if (!empty($params)) {
    mysqli_stmt_bind_param($get_stmt, $param_types, ...$params);
}

mysqli_stmt_execute($get_stmt);
$result = mysqli_stmt_get_result($get_stmt);

$faculty_users = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Optionally fetch institute details separately if needed
    if ($row['institute_id']) {
        $inst_sql = "SELECT * FROM institute_profiles WHERE id = ? LIMIT 1";
        $inst_stmt = mysqli_prepare($conn, $inst_sql);
        mysqli_stmt_bind_param($inst_stmt, "i", $row['institute_id']);
        mysqli_stmt_execute($inst_stmt);
        $inst_result = mysqli_stmt_get_result($inst_stmt);
        $inst_data = mysqli_fetch_assoc($inst_result);
        mysqli_stmt_close($inst_stmt);
        
        // Add available institute data
        if ($inst_data) {
            $row['institute_data'] = $inst_data;
        }
    }
    
    $faculty_users[] = $row;
}

mysqli_stmt_close($get_stmt);
mysqli_close($conn);

// ✅ Response
echo json_encode([
    "message" => "Faculty users fetched successfully",
    "status" => true,
    "data" => $faculty_users,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>
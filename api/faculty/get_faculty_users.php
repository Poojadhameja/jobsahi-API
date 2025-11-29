<?php
// get_faculty_users.php - Fetch faculty users with role-based access
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate and allow "admin", "institute"
$decoded = authenticateJWT(['admin', 'institute']); 

$user_id = $decoded['user_id'];
$user_role = strtolower($decoded['role']);

// --------------------------------------------------------
// 🔍 DETERMINE institute_id BASED ON ROLE
// --------------------------------------------------------
$institute_id = null;

if ($user_role === 'admin') {

    if (isset($_GET['institute_id']) && !empty($_GET['institute_id'])) {
        $institute_id = intval($_GET['institute_id']);
    } else {
        // Optional: try to fetch institute_id from users table
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

    // Fetch institute_id from institute_profiles table
    $fetch_institute_sql = "SELECT id FROM institute_profiles WHERE user_id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_institute_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
    mysqli_stmt_execute($fetch_stmt);
    $fetch_result = mysqli_stmt_get_result($fetch_stmt);

    if ($row = mysqli_fetch_assoc($fetch_result)) {
        $institute_id = $row['id'];
    }

    mysqli_stmt_close($fetch_stmt);

    if ($institute_id === null) {
        echo json_encode([
            "status" => false,
            "message" => "Institute ID not found"
        ]);
        exit;
    }
}

// --------------------------------------------------------
// 🔍 PAGINATION
// --------------------------------------------------------
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
$offset = ($page - 1) * $limit;

// --------------------------------------------------------
// 🔍 SEARCH
// --------------------------------------------------------
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where_conditions = [];
$params = [];
$param_types = '';

if ($user_role === 'admin') {
    if (!empty($institute_id)) {
        $where_conditions[] = "fu.institute_id = ?";
        $params[] = $institute_id;
        $param_types .= 'i';
    }
}

if ($user_role === 'institute') {
    $where_conditions[] = "fu.institute_id = ?";
    $params[] = $institute_id;
    $param_types .= 'i';
}

if ($search) {
    $where_conditions[] = "(fu.name LIKE ? OR fu.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'ss';
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// --------------------------------------------------------
// 🔢 COUNT TOTAL FACULTY
// --------------------------------------------------------
$count_sql = "
    SELECT COUNT(*) AS total
    FROM faculty_users fu
    $where_sql
";

$count_stmt = mysqli_prepare($conn, $count_sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
}

mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);

$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

mysqli_stmt_close($count_stmt);

// --------------------------------------------------------
// 📌 FETCH FACULTY USERS (WITHOUT admin_action)
// --------------------------------------------------------
$get_sql = "
    SELECT 
        fu.id,
        fu.institute_id,
        fu.name,
        fu.email,
        fu.phone,
        fu.role
    FROM faculty_users fu
    $where_sql
    ORDER BY fu.id DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

$get_stmt = mysqli_prepare($conn, $get_sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($get_stmt, $param_types, ...$params);
}

mysqli_stmt_execute($get_stmt);
$result = mysqli_stmt_get_result($get_stmt);

$faculty_users = [];

while ($row = mysqli_fetch_assoc($result)) {

    // Fetch institute details
    $inst_sql = "SELECT id, institute_name, institute_logo FROM institute_profiles WHERE id = ? LIMIT 1";
    $inst_stmt = mysqli_prepare($conn, $inst_sql);
    mysqli_stmt_bind_param($inst_stmt, "i", $row['institute_id']);
    mysqli_stmt_execute($inst_stmt);
    $inst_result = mysqli_stmt_get_result($inst_stmt);

    $row['institute_data'] = mysqli_fetch_assoc($inst_result) ?: null;

    mysqli_stmt_close($inst_stmt);

    $faculty_users[] = $row;
}

mysqli_stmt_close($get_stmt);
mysqli_close($conn);

// --------------------------------------------------------
// 📤 FINAL OUTPUT
// --------------------------------------------------------
echo json_encode([
    "status" => true,
    "message" => "Faculty users fetched successfully",
    "total_records" => $total_records,
    "total_pages" => $total_pages,
    "data" => $faculty_users,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>

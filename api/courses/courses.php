<?php
// courses.php - Get course list with proper role-based visibility
require_once '../cors.php';
// require_once '../db.php'; // ensure $conn is available

try {
    // Authenticate user and get role
    $user = authenticateJWT(['admin', 'student', 'institute']);
    $user_role = $user['role'] ?? 'student';
    $user_id = $user['user_id'] ?? ($user['id'] ?? null);
    $institute_id = null;

    // Determine institute_id from token (for institute users)
    if ($user_role === 'institute') {
        // some JWTs store institute_id separately, others just have user_id
        $institute_id = $user['institute_id'] ?? $user_id;

        if (!$institute_id) {
            throw new Exception("Institute ID missing in token");
        }
    }

    // Base SQL
    $sql = "SELECT id, institute_id, title, description, duration, fee, admin_action 
            FROM courses 
            WHERE 1=1";

    $params = [];
    $types = "";

    // Role-based visibility
    if ($user_role === 'admin') {
        // Admin sees all courses
    } elseif ($user_role === 'institute') {
        // Institute sees only their courses
        $sql .= " AND institute_id = ?";
        $params[] = $institute_id;
        $types .= "i";
    } else {
        // Students/others see only approved courses
        $sql .= " AND admin_action = ?";
        $params[] = 'approved';
        $types .= "s";
    }

    // Optional filters (only for non-institute roles)
    if (!empty($_GET['institute_id']) && $user_role !== 'institute') {
        $sql .= " AND institute_id = ?";
        $params[] = intval($_GET['institute_id']);
        $types .= "i";
    }

    if (!empty($_GET['min_fee']) && is_numeric($_GET['min_fee'])) {
        $sql .= " AND fee >= ?";
        $params[] = floatval($_GET['min_fee']);
        $types .= "d";
    }

    if (!empty($_GET['max_fee']) && is_numeric($_GET['max_fee'])) {
        $sql .= " AND fee <= ?";
        $params[] = floatval($_GET['max_fee']);
        $types .= "d";
    }

    if (!empty($_GET['duration'])) {
        $sql .= " AND duration = ?";
        $params[] = $_GET['duration'];
        $types .= "s";
    }

    if (!empty($_GET['q'])) {
        $sql .= " AND (title LIKE ? OR description LIKE ?)";
        $keyword = "%" . $_GET['q'] . "%";
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= "ss";
    }

    // Order
    $sql .= " ORDER BY id DESC";

    // Prepare and execute query
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception("Get result failed: " . mysqli_stmt_error($stmt));
    }

    $courses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if ($user_role !== 'admin') {
            unset($row['admin_action']); // hide internal status
        }
        $courses[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Courses retrieved successfully",
        "courses" => $courses,
        "total_count" => count($courses),
        "user_role" => $user_role,
        "token_user_id" => $user_id,
        "institute_id_used" => $institute_id
    ]);

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error retrieving courses: " . $e->getMessage(),
        "courses" => []
    ]);
    if (isset($stmt)) mysqli_stmt_close($stmt);
    if (isset($conn)) mysqli_close($conn);
}
?>

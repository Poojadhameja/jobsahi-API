<?php
// courses.php - Get course list with proper role-based visibility
require_once '../cors.php';

try {
    // Authenticate user and get role
    $user = authenticateJWT(['admin', 'student', 'institute']);
    $user_role = $user['role'] ?? 'student';

    // Base SQL - Select all necessary fields
    $sql = "SELECT id, institute_id, title, description, duration, fee, admin_action FROM courses WHERE 1=1";
    
    // Role-based visibility logic
    $params = [];
    $types = "";

    if ($user_role === 'admin') {
        // Admin sees all courses (pending, approved, rejected)
        // No additional WHERE condition needed
    } elseif ($user_role === 'institute') {
        // Institute sees their own courses (all statuses)
        $institute_id = $user['institute_id'] ?? $user['id']; // depending on your user structure
        $sql .= " AND institute_id = ?";
        $params[] = $institute_id;
        $types .= "i";
    } else {
        // Students and other roles see only approved courses
        $sql .= " AND admin_action = ?";
        $params[] = 'approved'; // Changed from 'approved' to 'approved'
        $types .= "s";
    }

    // Optional filters

    // Filter by institute_id (only if user is not already filtered by institute)
    if (!empty($_GET['institute_id']) && $user_role !== 'institute') {
        $sql .= " AND institute_id = ?";
        $params[] = intval($_GET['institute_id']);
        $types .= "i";
    }

    // Filter by min_fee
    if (!empty($_GET['min_fee']) && is_numeric($_GET['min_fee'])) {
        $sql .= " AND fee >= ?";
        $params[] = floatval($_GET['min_fee']);
        $types .= "d";
    }

    // Filter by max_fee
    if (!empty($_GET['max_fee']) && is_numeric($_GET['max_fee'])) {
        $sql .= " AND fee <= ?";
        $params[] = floatval($_GET['max_fee']);
        $types .= "d";
    }

    // Filter by duration
    if (!empty($_GET['duration'])) {
        $sql .= " AND duration = ?";
        $params[] = $_GET['duration'];
        $types .= "s";
    }

    // Search by keyword in title/description
    if (!empty($_GET['q'])) {
        $sql .= " AND (title LIKE ? OR description LIKE ?)";
        $keyword = "%" . mysqli_real_escape_string($conn, $_GET['q']) . "%";
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= "ss";
    }

    // Add ordering
    $sql .= " ORDER BY id DESC";

    // Prepare and execute
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    if (!empty($params)) {
        if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
            throw new Exception("Binding parameters failed: " . mysqli_stmt_error($stmt));
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Getting result failed: " . mysqli_stmt_error($stmt));
    }

    $courses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // For non-admin users, don't expose admin_action field
        if ($user_role !== 'admin') {
            unset($row['admin_action']);
        }
        $courses[] = $row;
    }

    // Success response
    echo json_encode([
        "status" => true,
        "message" => "Courses retrieved successfully",
        "courses" => $courses,
        "total_count" => count($courses),
        "user_role" => $user_role // For debugging, remove in production
    ]);

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

} catch (Exception $e) {
    // Error response
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error retrieving courses: " . $e->getMessage(),
        "courses" => []
    ]);
    
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>
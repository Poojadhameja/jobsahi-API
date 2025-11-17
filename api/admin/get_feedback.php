<?php
// get_feedback.php - Admin can view all student feedbacks with student information (GET only)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate (only admin can view feedbacks)
$decoded = authenticateJWT(['admin']);

// ✅ Allow only GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Only GET method is allowed"
    ]);
    exit();
}

try {
    // ✅ Optional filters
    $status_filter = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'rejected']) 
                     ? $_GET['status'] 
                     : null;
    $order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], ['created_at', 'updated_at']) 
                ? $_GET['order_by'] 
                : 'created_at';
    $order_dir = isset($_GET['order_dir']) && strtoupper($_GET['order_dir']) === 'ASC' 
                 ? 'ASC' 
                 : 'DESC';

    // ✅ Build query to fetch feedbacks with student information
    $where_clause = "";
    $params = [];
    $param_types = "";

    if ($status_filter) {
        $where_clause = "WHERE sf.admin_action = ?";
        $params[] = $status_filter;
        $param_types = "s";
    }

    $sql = "
        SELECT 
            sf.id AS feedback_id,
            sf.subject,
            sf.feedback,
            sf.admin_action,
            sf.created_at,
            sf.updated_at,
            
            -- Student Information
            sp.id AS student_profile_id,
            u.id AS student_user_id,
            u.user_name AS student_name,
            u.email AS student_email,
            u.phone_number AS student_phone,
            u.status AS student_status,
            
            -- Additional Student Profile Info
            sp.trade,
            sp.location,
            sp.education
        FROM student_feedback sf
        INNER JOIN student_profiles sp ON sf.student_id = sp.id
        INNER JOIN users u ON sp.user_id = u.id
        $where_clause
        ORDER BY sf.$order_by $order_dir
    ";

    $stmt = $conn->prepare($sql);
    if ($status_filter) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $feedbacks = [];
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = [
            'feedback_info' => [
                'feedback_id' => intval($row['feedback_id']),
                'subject' => $row['subject'],
                'feedback' => $row['feedback'],
                'admin_action' => $row['admin_action'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ],
            'student_info' => [
                'student_user_id' => intval($row['student_user_id']),
                'student_profile_id' => intval($row['student_profile_id']),
                'student_name' => $row['student_name'],
                'student_email' => $row['student_email'],
                'student_phone' => $row['student_phone'],
                'student_status' => $row['student_status'],
                'trade' => $row['trade'],
                'location' => $row['location'],
                'education' => $row['education']
            ]
        ];
    }
    $stmt->close();

    // ✅ Get counts for different statuses
    $count_sql = "SELECT admin_action, COUNT(*) as count FROM student_feedback GROUP BY admin_action";
    $count_result = $conn->query($count_sql);
    $counts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'total' => 0
    ];

    while ($count_row = $count_result->fetch_assoc()) {
        $status = $count_row['admin_action'];
        $counts[$status] = intval($count_row['count']);
        $counts['total'] += intval($count_row['count']);
    }

    echo json_encode([
        "status" => true,
        "message" => "Feedbacks retrieved successfully",
        "counts" => $counts,
        "total_returned" => count($feedbacks),
        "data" => $feedbacks
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>


<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate user
    $decoded = authenticateJWT(['admin', 'recruiter']);
    $user_role = strtolower($decoded['role'] ?? '');
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    if (!$conn) {
        echo json_encode(["success" => false, "message" => "Database connection failed: " . mysqli_connect_error()]);
        exit;
    }

    // ✅ Determine recruiter_id (for admin, can filter by ?recruiter_id=)
    $recruiter_id = isset($_GET['recruiter_id']) ? intval($_GET['recruiter_id']) : 0;

    if ($user_role === 'admin' && $recruiter_id > 0) {
        // Admin can fetch specific recruiter
        $sql = "SELECT rp.*, u.user_name, u.email, u.phone_number 
                FROM recruiter_profiles rp
                INNER JOIN users u ON rp.user_id = u.id
                WHERE rp.id = ? AND rp.deleted_at IS NULL
                ORDER BY rp.id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $recruiter_id);
    } elseif ($user_role === 'admin') {
        // Admin sees all recruiters (pending + approved)
        $sql = "SELECT rp.*, u.user_name, u.email, u.phone_number 
                FROM recruiter_profiles rp
                INNER JOIN users u ON rp.user_id = u.id
                WHERE rp.deleted_at IS NULL
                AND (rp.admin_action = 'pending' OR rp.admin_action = 'approved')
                ORDER BY rp.id DESC";
        $stmt = $conn->prepare($sql);
    } else {
        // Recruiter sees only their own approved profile
        $sql = "SELECT rp.*, u.user_name, u.email, u.phone_number 
                FROM recruiter_profiles rp
                INNER JOIN users u ON rp.user_id = u.id
                WHERE rp.user_id = ? AND rp.admin_action = 'approved' AND rp.deleted_at IS NULL
                ORDER BY rp.id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // ✅ Prepare response data
    $profiles = [];
    while ($row = $result->fetch_assoc()) {
        // ✅ Construct absolute file URL if file exists
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                    "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $company_logo_url = null;

        if (!empty($row['company_logo'])) {
            $company_logo_url = $base_url . $row['company_logo'];
        }

        $profiles[] = [
            "profile_id" => intval($row['id']),
            "user_id" => intval($row['user_id']),
            "personal_info" => [
                "email" => $row['email'],
                "user_name" => $row['user_name'],
                "phone_number" => $row['phone_number'],
                "location" => $row['location']
            ],
            "professional_info" => [
                "company_name" => $row['company_name'] ?? "N/A",
                "industry" => $row['industry'] ?? "N/A",
                "website" => $row['website'] ?? null
            ],
            "documents" => [
                "company_logo" => $company_logo_url
            ],
            "status" => [
                "admin_action" => $row['admin_action'] ?? "pending",
                "created_at" => $row['created_at'] ?? null,
                "modified_at" => $row['modified_at'] ?? null
            ]
        ];
    }

    // ✅ Final response
    echo json_encode([
        "success" => true,
        "message" => count($profiles) > 0 
            ? "Recruiter profile(s) retrieved successfully" 
            : "No profiles found",
        "data" => [
            "profiles" => $profiles,
            "total_count" => count($profiles),
            "user_role" => $user_role,
            "filters_applied" => [
                "admin_action" => ($user_role === 'admin') ? ['pending', 'approved'] : ['approved'],
                "deleted_at" => "NULL"
            ]
        ],
        "meta" => [
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0",
            "response_format" => "structured"
        ]
    ], JSON_PRETTY_PRINT);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>

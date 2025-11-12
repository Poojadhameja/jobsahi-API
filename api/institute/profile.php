<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT
    $decoded = authenticateJWT(['admin', 'institute']);
    $user_role = strtolower($decoded['role'] ?? '');
    $user_id   = intval($decoded['user_id'] ?? 0);

    // ✅ Allow only GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(["success" => false, "message" => "Only GET requests allowed"]);
        exit;
    }

    // ✅ Database connection check
    if (!$conn) {
        echo json_encode(["success" => false, "message" => "DB connection failed: " . mysqli_connect_error()]);
        exit;
    }

    // ✅ Determine institute_id (for admin filter ?institute_id=)
    $institute_id = isset($_GET['institute_id']) ? intval($_GET['institute_id']) : 0;

    if ($user_role === 'admin' && $institute_id > 0) {
        // Admin fetch specific institute
        $sql = "SELECT p.*, u.email, u.user_name, u.phone_number 
                FROM institute_profiles p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.id = ? AND p.deleted_at IS NULL
                ORDER BY p.id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $institute_id);
    } elseif ($user_role === 'admin') {
        // Admin fetch all
        $sql = "SELECT p.*, u.email, u.user_name, u.phone_number 
                FROM institute_profiles p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.deleted_at IS NULL
                AND (p.admin_action = 'pending' OR p.admin_action = 'approved')
                ORDER BY p.id DESC";
        $stmt = $conn->prepare($sql);
    } else {
        // Institute fetch only their approved profile
        $sql = "SELECT p.*, u.email, u.user_name, u.phone_number 
                FROM institute_profiles p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ? AND p.admin_action = 'approved' AND p.deleted_at IS NULL
                ORDER BY p.id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // ✅ Base URL setup
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . $host . "/jobsahi-API/api/uploads/institute_logo/";

    // ✅ Build structured response
    $profiles = [];
    while ($row = $result->fetch_assoc()) {
        $logo_url = null;

        if (!empty($row['institute_logo'])) {
            $clean_path = str_replace(["\\", "/uploads/institute_logo/", "./", "../"], "", $row['institute_logo']);
            $logo_url = $base_url . $clean_path;

            $local_path = __DIR__ . '/../uploads/institute_logo/' . $clean_path;
            if (!file_exists($local_path)) {
                $logo_url = null; // fallback if not found
            }
        }

        $profiles[] = [
            "profile_id" => intval($row['id']),
            "user_id" => intval($row['user_id']),
            "personal_info" => [
                "email" => $row['email'],
                "user_name" => $row['user_name'],
                "phone_number" => $row['phone_number']
            ],
            "institute_info" => [
                "institute_name" => $row['institute_name'],
                "institute_type" => $row['institute_type'],
                "description" => $row['description'],
                "website" => $row['website'],
                "courses_offered" => $row['courses_offered'],
                "established_year" => $row['established_year'],
                "accreditation" => $row['accreditation']
            ],
            "contact_info" => [
                "address" => $row['address'],
                "city" => $row['city'],
                "state" => $row['state'],
                "country" => $row['country'],
                "postal_code" => $row['postal_code'],
                "contact_person" => $row['contact_person'],
                "contact_designation" => $row['contact_designation']
            ],
            "location_info" => [
                "location" => $row['location']
            ],
            "documents" => [
                "institute_logo" => $logo_url
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
            ? "Institute profile(s) retrieved successfully"
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

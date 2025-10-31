<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT
$current_user = authenticateJWT(['admin', 'institute']);
$user_role = strtolower($current_user['role'] ?? '');
$user_id   = intval($current_user['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["success" => false, "message" => "Only GET requests allowed"]);
    exit;
}

// ✅ DB check
if (!$conn) {
    echo json_encode(["success" => false, "message" => "DB connection failed: " . mysqli_connect_error()]);
    exit;
}

// ✅ Role-based query
if ($user_role === 'admin') {
    $sql = "SELECT p.*, u.email, u.user_name, u.phone_number
            FROM institute_profiles p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.deleted_at IS NULL
              AND (p.admin_action = 'pending' OR p.admin_action = 'approved')
            ORDER BY p.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
} else {
    $sql = "SELECT p.*, u.email, u.user_name, u.phone_number
            FROM institute_profiles p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.deleted_at IS NULL AND p.user_id = ?
            ORDER BY p.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$profiles = [];
while ($row = mysqli_fetch_assoc($result)) {
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
            "courses_offered" => $row['courses_offered'],
            "established_year" => $row['established_year'],
            "accreditation" => $row['accreditation']
        ],
        "contact_info" => [
            "website" => $row['website'],
            "address" => $row['address'],
            "city" => $row['city'],
            "state" => $row['state'],
            "country" => $row['country'],
            "postal_code" => $row['postal_code'],
            "contact_person" => $row['contact_person'],
            "contact_designation" => $row['contact_designation']
        ],
        "location_info" => ["location" => $row['location']],
        "status" => [
            "admin_action" => $row['admin_action'],
            "created_at" => $row['created_at'],
            "modified_at" => $row['modified_at'] ?? null
        ]
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

$response = [
    "success" => true,
    "message" => "Institute profiles retrieved successfully",
    "data" => [
        "profiles" => $profiles,
        "total_count" => count($profiles),
        "user_role" => $user_role
    ],
    "meta" => [
        "timestamp" => date('Y-m-d H:i:s'),
        "api_version" => "1.0",
        "response_format" => "structured"
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>

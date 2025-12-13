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

    // ✅ DB check
    if (!$conn) {
        echo json_encode(["success" => false, "message" => "DB connection failed: " . mysqli_connect_error()]);
        exit;
    }

    // Admin can filter by ?institute_id=
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
        // Admin fetch all profiles (previously pending + approved)
        // 🔥 admin_action removed — now simply fetch all non-deleted profiles
        $sql = "SELECT p.*, u.email, u.user_name, u.phone_number 
                FROM institute_profiles p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.deleted_at IS NULL
                ORDER BY p.id DESC";
        $stmt = $conn->prepare($sql);

    } else {
        // Institute fetch only their own profile (previously only approved)
        // 🔥 admin_action removed — logic unchanged; now returns latest profile
        $sql = "SELECT p.*, u.email, u.user_name, u.phone_number 
                FROM institute_profiles p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ? 
                AND p.deleted_at IS NULL
                ORDER BY p.id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // Base URL for logo
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host      = $_SERVER['HTTP_HOST'];
    $base_url  = $protocol . $host . "/jobsahi-API/api/uploads/institute_logo/";

    // Prepare response
    $profiles = [];

    while ($row = $result->fetch_assoc()) {

        // Build logo URL (R2 support)
        $logo_url = null;
        if (!empty($row['institute_logo'])) {
            // ✅ Check if it's R2 URL
            if (strpos($row['institute_logo'], 'http') === 0 && 
                (strpos($row['institute_logo'], 'r2.dev') !== false || 
                 strpos($row['institute_logo'], 'r2.cloudflarestorage.com') !== false)) {
                // ✅ Already R2 URL - use directly
                $logo_url = $row['institute_logo'];
            } else {
                // ✅ Old local file path - construct server URL (backward compatibility)
                $clean_path = str_replace(["\\", "/uploads/institute_logo/", "./", "../"], "", $row['institute_logo']);
                $logo_url   = $base_url . $clean_path;

                $local_path = __DIR__ . '/../uploads/institute_logo/' . $clean_path;
                if (!file_exists($local_path)) $logo_url = null;
            }
        }

        $profiles[] = [
            "profile_id" => intval($row['id']),
            "user_id"    => intval($row['user_id']),

            "personal_info" => [
                "email"        => $row['email'],
                "user_name"    => $row['user_name'],
                "phone_number" => $row['phone_number']
            ],

            "institute_info" => [
                "institute_name"      => $row['institute_name'],
                "registration_number" => $row['registration_number'],
                "institute_logo"      => $logo_url,
                "institute_type"      => $row['institute_type'],
                "website"             => $row['website'],
                "description"         => $row['description'],
                "accreditation"       => $row['accreditation'],
                "established_year"    => $row['established_year']
            ],

            "contact_info" => [
                "address"             => $row['address'],
                "postal_code"         => $row['postal_code'],
                "contact_person"      => $row['contact_person'],
                "contact_designation" => $row['contact_designation'],
            ],

            "status" => [
                // 🔥 admin_action removed completely
                "created_at"   => $row['created_at'],
                "modified_at"  => $row['modified_at']
            ]
        ];
    }

    echo json_encode([
        "success" => true,
        "message" => count($profiles) ? "Institute profile(s) retrieved successfully" : "No profiles found",
        "data" => [
            "profiles"     => $profiles,
            "total_count"  => count($profiles),
            "user_role"    => $user_role,
            "filters"      => [
                // 🔥 admin_action removed
                "deleted_at" => "NULL"
            ]
        ],
        "meta" => [
            "timestamp"     => date('Y-m-d H:i:s'),
            "api_version"   => "1.0",
            "response_type" => "structured"
        ]

    ], JSON_PRETTY_PRINT);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
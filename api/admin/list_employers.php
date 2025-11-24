<?php 
// list_employers.php - List/manage all employers (Admin access only)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT (admin only)
$decoded = authenticateJWT(['admin']); 

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    /* =========================================================
       📊 1️⃣ Dashboard Summary Counts
       ========================================================= */
    $summary = [
        "total_employers" => 0,
        "pending_approvals" => 0,
        "active_jobs" => 0,
        "monthly_revenue" => 0
    ];

    // ✅ Total Employers
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'recruiter'");
    $stmt->execute();
    $summary['total_employers'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // ✅ Pending Approvals
    $stmt = $conn->prepare("SELECT COUNT(*) AS pending FROM recruiter_profiles");
    $stmt->execute();
    $summary['pending_approvals'] = $stmt->get_result()->fetch_assoc()['pending'] ?? 0;
    $stmt->close();

    // ✅ Active Jobs
    $stmt = $conn->prepare("SELECT COUNT(*) AS active_jobs FROM jobs WHERE status = 'open'");
    $stmt->execute();
    $summary['active_jobs'] = $stmt->get_result()->fetch_assoc()['active_jobs'] ?? 0;
    $stmt->close();

    // ✅ Monthly Revenue (sum of successful transactions in current month)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS revenue
        FROM transactions
        WHERE status = 'success'
          AND MONTH(timestamp) = MONTH(CURRENT_DATE())
          AND YEAR(timestamp) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $summary['monthly_revenue'] = floatval($stmt->get_result()->fetch_assoc()['revenue'] ?? 0);
    $stmt->close();


    /* =========================================================
       2️⃣ Recruiter Listing (Existing Logic)
       ========================================================= */
    $query = "
        SELECT 
            u.id AS user_id,
            u.user_name,
            u.email,
            u.phone_number,
            u.role,
            u.is_verified,
            rp.id AS profile_id,
            rp.company_name,
            rp.company_logo,
            rp.industry,
            rp.website,
            rp.location,
            rp.created_at,
            rp.modified_at
        FROM users u
        LEFT JOIN recruiter_profiles rp ON u.id = rp.user_id
        WHERE u.role = 'recruiter'
        ORDER BY rp.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    $employers = [];

    // ✅ Base URL for logo files
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $logo_base = '/jobsahi-API/api/uploads/recruiter_logo/';

    while ($row = $result->fetch_assoc()) {
        // ✅ Status Mapping

        // ✅ Company logo full URL logic
        $company_logo = $row['company_logo'] ?? "";
        if (!empty($company_logo)) {
            $clean_logo = str_replace(["\\", "/uploads/recruiter_logo/", "./", "../"], "", $company_logo);
            $logo_local = __DIR__ . '/../uploads/recruiter_logo/' . $clean_logo;
            if (file_exists($logo_local)) {
                $company_logo = $protocol . $host . $logo_base . $clean_logo;
            }
        }

        $employers[] = [
            "user_id" => intval($row['user_id']),
            "user_name" => $row['user_name'],
            "email" => $row['email'],
            "phone_number" => $row['phone_number'],
            "role" => $row['role'],
            "is_verified" => intval($row['is_verified'] ?? 0),
            "profile" => [
                "profile_id" => intval($row['profile_id']),
                "company_name" => $row['company_name'] ?? "",
                "company_logo" => $company_logo,
                "industry" => $row['industry'] ?? "",
                "website" => $row['website'] ?? "",
                "location" => $row['location'] ?? "",
                "applied_date" => $row['created_at'],
                "last_modified" => $row['modified_at']
            ]
        ];
    }

    /* =========================================================
       ✅ Final Response
       ========================================================= */
    echo json_encode([
        "status" => true,
        "message" => "Employers retrieved successfully",
        "summary" => $summary,
        "total_count" => count($employers),
        "data" => $employers,
        "meta" => [
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0"
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
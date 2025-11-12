<?php 
// list_employers.php - List/manage all employers (Admin access only)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT (admin only)
$decoded = authenticateJWT(['admin']); 

try {
    // ✅ Query recruiters with full user + profile info
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

        // ✅ Convert verification status to readable text
        if (is_null($row['is_verified'])) {
            $status = "pending";
        } elseif ($row['is_verified'] == 1) {
            $status = "approved";
        } else {
            $status = "rejected";
        }

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
                "company_logo" => $company_logo, // ✅ Updated logo URL
                "industry" => $row['industry'] ?? "",
                "website" => $row['website'] ?? "",
                "location" => $row['location'] ?? "",
                "applied_date" => $row['created_at'],
                "last_modified" => $row['modified_at'],
                "status" => $status  // ✅ readable field for frontend
            ]
        ];
    }

    echo json_encode([
        "status" => true,
        "message" => "Employers retrieved successfully",
        "data" => $employers,
        "total_count" => count($employers)
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

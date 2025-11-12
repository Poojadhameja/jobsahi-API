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

    while ($row = $result->fetch_assoc()) {
        // ✅ Convert verification status to readable text
        if (is_null($row['is_verified'])) {
            $status = "pending";
        } elseif ($row['is_verified'] == 1) {
            $status = "approved";
        } else {
            $status = "rejected";
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
                "company_logo" => $row['company_logo'] ?? "",
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

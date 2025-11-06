<?php 
// list_employers.php - List/manage all employers (Admin access only)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT and allow only admin role
$decoded = authenticateJWT(['admin']); // Only admin can access

try {
    // ✅ Query to get all recruiters with full profile details
    $query = "
        SELECT 
            u.id AS user_id,
            u.email,
            u.role,
            rp.id AS profile_id,
            rp.company_name,
            rp.company_logo,
            rp.industry,
            rp.website,
            rp.location,
            rp.created_at,
            rp.modified_at,
            rp.admin_action
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

        $employers[] = [
            "user_id" => intval($row['user_id']),
            "email" => $row['email'],
            "role" => $row['role'],
            "profile" => [
                "profile_id" => intval($row['profile_id']),
                "company_name" => $row['company_name'],
                "company_logo" => $row['company_logo'],
                "industry" => $row['industry'],
                "website" => $row['website'],
                "location" => $row['location'],
                "applied_date" => $row['created_at'],
                "last_modified" => $row['modified_at'],
                "status" => $row['admin_action'] ?? "pending"
            ]
        ];
    }

    echo json_encode([
        "status" => true,
        "message" => "Employers retrieved successfully",
        "data" => $employers,
        "total_count" => count($employers)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

<?php
// list_employers.php - List/manage all employers (Admin access only)
require_once '../cors.php';

// ✅ Authenticate JWT and allow only admin role
$decoded = authenticateJWT(['admin']); // Only admin can access

try {
    // Query to get all employers/recruiters with their profile information
    $query = "
        SELECT 
            u.id as user_id,
            u.email,
            u.role,
            rp.id as profile_id,
            rp.company_name
        FROM users u
        LEFT JOIN recruiter_profiles rp ON u.id = rp.user_id
        WHERE u.role = 'recruiter'
        ORDER BY u.id DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    $employers = [];
    while ($row = $result->fetch_assoc()) {
        $employers[] = [
            'user_id' => $row['user_id'],
            'email' => $row['email'],
            'role' => $row['role'],
            'profile' => [
                'profile_id' => $row['profile_id'],
                'company_name' => $row['company_name']
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
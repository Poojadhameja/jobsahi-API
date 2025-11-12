<?php
// list_institutes.php - List/manage all institutes
require_once '../cors.php';

// ✅ Authenticate JWT and allow only admin access
$decoded = authenticateJWT(['admin']); // Only admin can access this endpoint

// Extract admin user ID from JWT token
$admin_id = $decoded['user_id'];

try {
    // Query to get all institutes with their profile information
    $stmt = $conn->prepare("
        SELECT 
            u.id as user_id,
            u.user_name,
            u.email,
            u.phone_number,

            ip.id as institute_id,
            ip.institute_name,
            ip.institute_type,
            ip.institute_logo,
            ip.website,
            ip.description,
            ip.address,
            ip.city,
            ip.state,
            ip.country,
            ip.postal_code,
            ip.contact_person,
            ip.contact_designation,
            ip.accreditation,
            ip.established_year,
            ip.location,
            ip.courses_offered,
            ip.admin_action,
            ip.created_at as profile_created_at,
            ip.modified_at as profile_modified_at,
            ip.deleted_at as profile_deleted_at
        FROM users u
        LEFT JOIN institute_profiles ip ON u.id = ip.user_id
        WHERE u.role = 'institute'
        ORDER BY u.id DESC
    ");

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $institutes = [];

        // ✅ Setup URL generation base
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $logo_base = '/jobsahi-API/api/uploads/institute_logo/';

        while ($row = $result->fetch_assoc()) {
            // ✅ Convert institute_logo → full URL (if exists)
            if (!empty($row['institute_logo'])) {
                $clean_logo = str_replace(["\\", "/uploads/institute_logo/", "./", "../"], "", $row['institute_logo']);
                $logo_local = __DIR__ . '/../uploads/institute_logo/' . $clean_logo;
                if (file_exists($logo_local)) {
                    $row['institute_logo'] = $protocol . $host . $logo_base . $clean_logo;
                }
            }

            $institutes[] = [
                'user_info' => [
                    'user_id' => $row['user_id'],
                    'user_name' => $row['user_name'],
                    'email' => $row['email'],
                    'phone_number' => $row['phone_number'],
                ],
                'profile_info' => [
                    'institute_id' => $row['institute_id'],
                    'institute_name' => $row['institute_name'],
                    'institute_type' => $row['institute_type'],
                    'institute_logo' => $row['institute_logo'],
                    'website' => $row['website'],
                    'description' => $row['description'],
                    'address' => $row['address'],
                    'city' => $row['city'],
                    'state' => $row['state'],
                    'country' => $row['country'],
                    'postal_code' => $row['postal_code'],
                    'contact_person' => $row['contact_person'],
                    'contact_designation' => $row['contact_designation'],
                    'accreditation' => $row['accreditation'],
                    'established_year' => $row['established_year'],
                    'location' => $row['location'],
                    'courses_offered' => $row['courses_offered'],
                    'admin_action' => $row['admin_action'],
                    'created_at' => $row['profile_created_at'],
                    'modified_at' => $row['profile_modified_at'],
                    'deleted_at' => $row['profile_deleted_at']
                ]
            ];
        }

        echo json_encode([
            "status" => true,
            "message" => "Institutes retrieved successfully",
            "count" => count($institutes),
            "data" => $institutes
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve institutes",
            "error" => $stmt->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

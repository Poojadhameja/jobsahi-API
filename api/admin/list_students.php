<?php
// list_students.php - List/manage all students
require_once '../cors.php';

// ✅ Authenticate JWT and allow only admin access
$decoded = authenticateJWT(['admin','institute']); // Only admin can access this endpoint

// Extract admin user ID from JWT token
$admin_id = $decoded['user_id'];

try {
    // Query to get all students with their profile information
    $stmt = $conn->prepare("
        SELECT 
            u.id as user_id,
            u.user_name,
            u.email,
            u.phone_number,

            sp.id as profile_id,
            sp.skills,
            sp.education,
            sp.resume,
            sp.certificates,
            sp.portfolio_link,
            sp.linkedin_url,
            sp.dob,
            sp.gender,
            sp.job_type,
            sp.trade,
            sp.location,
            sp.admin_action,
            sp.bio,
            sp.experience,
            sp.graduation_year,
            sp.cgpa,
            sp.created_at as profile_created_at,
            sp.modified_at as profile_modified_at,
            sp.deleted_at as profile_deleted_at
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.role = 'student'
        ORDER BY u.id DESC
    ");

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $students = [];
        
        while ($row = $result->fetch_assoc()) {
            $students[] = [
                'user_info' => [
                    'user_id' => $row['user_id'],
                    'user_name' => $row['user_name'],
                    'email' => $row['email'],
                    'phone_number' => $row['phone_number'],
                ],
                'profile_info' => [
                    'profile_id' => $row['profile_id'],
                    'skills' => $row['skills'],
                    'education' => $row['education'],
                    'resume' => $row['resume'],
                    'certificates' => $row['certificates'],
                    'portfolio_link' => $row['portfolio_link'],
                    'linkedin_url' => $row['linkedin_url'],
                    'dob' => $row['dob'],
                    'gender' => $row['gender'],
                    'job_type' => $row['job_type'],
                    'trade' => $row['trade'],
                    'location' => $row['location'],
                    'admin_action' => $row['admin_action'],
                    'bio' => $row['bio'],
                    'experience' => $row['experience'],
                    'graduation_year' => $row['graduation_year'],
                    'cgpa' => $row['cgpa'],
                    'created_at' => $row['profile_created_at'],
                    'modified_at' => $row['profile_modified_at'],
                    'deleted_at' => $row['profile_deleted_at']
                ]
            ];
        }

        echo json_encode([
            "status" => true,
            "message" => "Students retrieved successfully",
            "count" => count($students),
            "data" => $students
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve students",
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

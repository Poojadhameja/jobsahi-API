<?php
// list_students.php - List/manage all students
require_once '../cors.php';

// ✅ Authenticate JWT and allow only admin access
$decoded = authenticateJWT(['admin']); // Only admin can access this endpoint

// Extract admin user ID from JWT token
$admin_id = $decoded['user_id'];

try {
    // Query to get all students with their profile information
    $stmt = $conn->prepare("
        SELECT 
            u.id as user_id,
            u.username,
            u.email,
            u.first_name,
            u.last_name,
            u.phone_number,
            u.date_of_birth,
            u.gender,
            u.address,
            u.is_active,
            u.created_at as user_created_at,
            sp.id as profile_id,
            sp.education,
            sp.resume,
            sp.certificates,
            sp.portfolio_link,
            sp.linkedin_url,
            sp.skills,
            sp.job_type,
            sp.experience,
            sp.location,
            sp.created_at as profile_created_at,
            sp.updated_at as profile_updated_at
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.role = 'student'
        ORDER BY u.created_at DESC
    ");

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $students = [];
        
        while ($row = $result->fetch_assoc()) {
            $students[] = [
                'user_info' => [
                    'user_id' => $row['user_id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone_number' => $row['phone_number'],
                    'date_of_birth' => $row['date_of_birth'],
                    'gender' => $row['gender'],
                    'address' => $row['address'],
                    'is_active' => $row['is_active'],
                    'created_at' => $row['user_created_at']
                ],
                'profile_info' => [
                    'profile_id' => $row['profile_id'],
                    'education' => $row['education'],
                    'resume' => $row['resume'],
                    'certificates' => $row['certificates'],
                    'portfolio_link' => $row['portfolio_link'],
                    'linkedin_url' => $row['linkedin_url'],
                    'skills' => $row['skills'],
                    'job_type' => $row['job_type'],
                    'experience' => $row['experience'],
                    'location' => $row['location'],
                    'created_at' => $row['profile_created_at'],
                    'updated_at' => $row['profile_updated_at']
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
<?php
// list_students.php - List/manage all students
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT and allow only admin or institute access
$decoded = authenticateJWT(['admin','institute']); 

// Extract user ID (if needed)
$admin_id = $decoded['user_id'] ?? null;

try {
    // ✅ Query with status column (removed sp.admin_action)
    $stmt = $conn->prepare("
        SELECT 
            u.id AS user_id,
            u.user_name,
            u.email,
            u.phone_number,
            u.status AS user_status,

            sp.id AS profile_id,
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
            sp.bio,
            sp.experience,
            sp.graduation_year,
            sp.cgpa,
            sp.created_at AS profile_created_at,
            sp.updated_at AS profile_modified_at,
            sp.deleted_at AS profile_deleted_at
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
                    'status' => ucfirst($row['user_status'] ?? 'Inactive'), // ✅ now works safely
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
        ], JSON_PRETTY_PRINT);

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

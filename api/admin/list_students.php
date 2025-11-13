<?php
// list_students.php - List/manage all students with applied job titles
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT and allow only admin or institute access
$decoded = authenticateJWT(['admin', 'institute']);
$admin_id = $decoded['user_id'] ?? null;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    /* =========================================================
       📊 1️⃣ Dashboard Summary Counts
       ========================================================= */
    $summary = [
        "total_students" => 0,
        "verified_profiles" => 0,
        "placement_ready" => 0,
        "successfully_placed" => 0
    ];

    // Total students
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role='student'");
    $stmt->execute();
    $summary['total_students'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // Verified students
    $stmt = $conn->prepare("SELECT COUNT(*) AS verified FROM users WHERE role='student' AND is_verified = 1");
    $stmt->execute();
    $summary['verified_profiles'] = $stmt->get_result()->fetch_assoc()['verified'] ?? 0;
    $stmt->close();

    // Placement Ready → shortlisted or selected
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT sp.user_id) AS placement_ready
        FROM student_profiles sp
        INNER JOIN applications a ON a.student_id = sp.id
        WHERE a.status IN ('shortlisted','selected') AND a.deleted_at IS NULL
    ");
    $stmt->execute();
    $summary['placement_ready'] = $stmt->get_result()->fetch_assoc()['placement_ready'] ?? 0;
    $stmt->close();

    // Successfully Placed → selected
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT sp.user_id) AS placed
        FROM student_profiles sp
        INNER JOIN applications a ON a.student_id = sp.id
        WHERE a.status = 'selected' AND a.deleted_at IS NULL
    ");
    $stmt->execute();
    $summary['successfully_placed'] = $stmt->get_result()->fetch_assoc()['placed'] ?? 0;
    $stmt->close();

    /* =========================================================
       2️⃣ Fetch Student Details (existing logic)
       ========================================================= */
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
    $stmt->execute();
    $result = $stmt->get_result();

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $resume_folder = '/jobsahi-API/api/uploads/resume/';
    $certificate_folder = '/jobsahi-API/api/uploads/student_certificate/';

    $students = [];
    $student_ids = [];

    while ($row = $result->fetch_assoc()) {
        $resume_url = !empty($row['resume']) ? $protocol . $host . $resume_folder . basename($row['resume']) : null;
        $certificate_url = !empty($row['certificates']) ? $protocol . $host . $certificate_folder . basename($row['certificates']) : null;

        $students[$row['user_id']] = [
            'user_info' => [
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'],
                'email' => $row['email'],
                'phone_number' => $row['phone_number'],
                'status' => ucfirst($row['user_status'] ?? 'Inactive'),
            ],
            'profile_info' => [
                'profile_id' => $row['profile_id'],
                'skills' => $row['skills'],
                'education' => $row['education'],
                'resume' => $resume_url,
                'certificates' => $certificate_url,
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
            ],
            'applied_jobs' => []
        ];
        $student_ids[] = $row['user_id'];
    }
    $stmt->close();

    /* =========================================================
       3️⃣ Return Combined Response
       ========================================================= */
    echo json_encode([
        "status" => true,
        "message" => "Students retrieved successfully",
        "summary" => $summary,
        "count" => count($students),
        "data" => array_values($students),
        "meta" => [
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0"
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>

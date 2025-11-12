<?php
// list_students.php - List/manage all students with applied job titles
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT and allow only admin or institute access
$decoded = authenticateJWT(['admin', 'institute']);
$admin_id = $decoded['user_id'] ?? null;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // ==========================================================
    // 1️⃣ Fetch all students (users + student_profiles)
    // ==========================================================
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

    $students = [];
    $student_ids = [];

    while ($row = $result->fetch_assoc()) {
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
            ],
            'applied_jobs' => [] // placeholder
        ];

        $student_ids[] = $row['user_id'];
    }
    $stmt->close();

  // ==========================================================
// 2️⃣ Fetch mapping of user_id → student_profile_id
// ==========================================================
$student_profile_ids = [];
if (count($student_ids) > 0) {
    $in_clause = implode(',', array_fill(0, count($student_ids), '?'));
    $types = str_repeat('i', count($student_ids));

    $map_stmt = $conn->prepare("
        SELECT id AS profile_id, user_id 
        FROM student_profiles 
        WHERE user_id IN ($in_clause)
    ");
    $map_stmt->bind_param($types, ...$student_ids);
    $map_stmt->execute();
    $map_result = $map_stmt->get_result();

    while ($map = $map_result->fetch_assoc()) {
        $student_profile_ids[$map['profile_id']] = $map['user_id'];
    }
    $map_stmt->close();
}

// ==========================================================
// 3️⃣ Fetch all applications for these student_profile_ids
// ==========================================================
if (count($student_profile_ids) > 0) {
    $profile_ids = array_keys($student_profile_ids);
    $in_clause = implode(',', array_fill(0, count($profile_ids), '?'));
    $types = str_repeat('i', count($profile_ids));

    $app_query = "
        SELECT id AS application_id, job_id, student_id 
        FROM applications 
        WHERE student_id IN ($in_clause)
    ";
    $app_stmt = $conn->prepare($app_query);
    $app_stmt->bind_param($types, ...$profile_ids);
    $app_stmt->execute();
    $app_result = $app_stmt->get_result();

    $applications = [];
    $job_ids = [];

    while ($app = $app_result->fetch_assoc()) {
        $user_id = $student_profile_ids[$app['student_id']] ?? null;
        if ($user_id) {
            $applications[$user_id][] = $app['job_id'];
            $job_ids[] = $app['job_id'];
        }
    }
    $app_stmt->close();

    // ==========================================================
    // 4️⃣ Fetch job titles for these job IDs
    // ==========================================================
    $job_titles = [];
    if (count($job_ids) > 0) {
        $in_jobs = implode(',', array_fill(0, count($job_ids), '?'));
        $types2 = str_repeat('i', count($job_ids));

        $job_stmt = $conn->prepare("SELECT id, title FROM jobs WHERE id IN ($in_jobs)");
        $job_stmt->bind_param($types2, ...$job_ids);
        $job_stmt->execute();
        $job_result = $job_stmt->get_result();

        while ($job = $job_result->fetch_assoc()) {
            $job_titles[$job['id']] = $job['title'];
        }
        $job_stmt->close();
    }

    // ==========================================================
    // 5️⃣ Map job titles to respective user_id (student)
    // ==========================================================
    foreach ($applications as $user_id => $job_list) {
        $titles = [];
        foreach ($job_list as $jid) {
            if (isset($job_titles[$jid])) {
                $titles[] = $job_titles[$jid];
            }
        }
        if (isset($students[$user_id])) {
            $students[$user_id]['applied_jobs'] = $titles;
        }
    }
}


    // ==========================================================
    // 5️⃣ Final Output
    // ==========================================================
    echo json_encode([
        "status" => true,
        "message" => "Students retrieved successfully",
        "count" => count($students),
        "data" => array_values($students)
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

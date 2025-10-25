<?php
// ✅ student_enrollments.php
// Fetch single student full record (profile + latest course/batch if exists)

require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate allowed roles
    $decoded = authenticateJWT(['admin', 'student', 'institute']);
    $user_role = strtolower($decoded['role'] ?? '');
    $user_id   = intval($decoded['user_id'] ?? 0);

    // ✅ Determine student_id based on role
    $student_id = 0;

    if ($user_role === 'student') {
        // Student will get their own profile
        $stmt_sid = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt_sid->bind_param("i", $user_id);
        $stmt_sid->execute();
        $result_sid = $stmt_sid->get_result();
        if ($result_sid->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Student profile not found for this user"]);
            exit;
        }
        $student_id = intval($result_sid->fetch_assoc()['id']);
    } 
    elseif ($user_role === 'admin' && isset($_GET['student_id'])) {
        // Admin can view any student by query param
        $student_id = intval($_GET['student_id']);
    } 
    elseif ($user_role === 'institute' && isset($_GET['student_id'])) {
        // Institute can view specific student
        $student_id = intval($_GET['student_id']);
    } 
    else {
        echo json_encode(["status" => false, "message" => "Student ID missing or unauthorized"]);
        exit;
    }

    // ✅ Step 1: Get student base profile
    $sql_student = "
        SELECT 
            id AS student_id,
            user_id,
            gender,
            dob,
            trade,
            bio,
            education,
            skills,
            projects,
            languages,
            experience,
            graduation_year,
            cgpa,
            resume,
            certificates,
            portfolio_link,
            linkedin_url,
            location
        FROM student_profiles
        WHERE id = ? AND deleted_at IS NULL
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql_student);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_result = $stmt->get_result();

    if ($student_result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Student profile not found",
            "data" => []
        ]);
        exit;
    }

    $student = $student_result->fetch_assoc();

    // ✅ Decode experience JSON properly
    $expDecoded = json_decode($student['experience'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($expDecoded)) {
        $expDecoded = [
            "level" => "",
            "years" => "",
            "details" => []
        ];
    }
    $student['experience'] = $expDecoded;

    // ✅ Step 2: Fetch latest enrollment (if any)
    $sql_enroll = "
        SELECT 
            e.id AS enrollment_id,
            e.status AS enrollment_status,
            e.enrollment_date,
            c.id AS course_id,
            c.title AS course_title,
            c.duration AS course_duration,
            c.mode AS course_mode,
            c.instructor_name,
            b.id AS batch_id,
            b.name AS batch_name,
            b.start_date,
            b.end_date
        FROM student_course_enrollments e
        INNER JOIN courses c ON e.course_id = c.id
        LEFT JOIN batches b ON c.id = b.course_id
        WHERE e.student_id = ?
          AND e.deleted_at IS NULL
        ORDER BY e.enrollment_date DESC
        LIMIT 1
    ";

    // Non-admins can only see approved enrollments/courses
    if ($user_role !== 'admin') {
        $sql_enroll = str_replace(
            "WHERE e.student_id = ?",
            "WHERE e.student_id = ? AND e.admin_action = 'approved' AND c.admin_action = 'approved'",
            $sql_enroll
        );
    }

    $stmt2 = $conn->prepare($sql_enroll);
    $stmt2->bind_param("i", $student_id);
    $stmt2->execute();
    $enroll_result = $stmt2->get_result();
    $enrollment = $enroll_result->fetch_assoc();

    // ✅ Step 3: Calculate Progress Percent (based on start_date and end_date)
    $progress_percent = 0;
    $status_text = "Not Started";

    if (!empty($enrollment['start_date']) && !empty($enrollment['end_date'])) {
        $start = strtotime($enrollment['start_date']);
        $end   = strtotime($enrollment['end_date']);
        $today = strtotime(date('Y-m-d'));

        if ($end > $start) {
            if ($today <= $start) {
                $progress_percent = 0;
                $status_text = "Not Started";
            } elseif ($today >= $end) {
                $progress_percent = 100;
                $status_text = "Completed";
            } else {
                $total_days = ($end - $start) / (60 * 60 * 24);
                $elapsed_days = ($today - $start) / (60 * 60 * 24);
                $progress_percent = round(($elapsed_days / $total_days) * 100, 2);
                $status_text = "Ongoing";
            }
        }
    }

    // ✅ Step 4: Combine data
    $finalData = [
        "student_id"        => $student['student_id'],
        "user_id"           => $student['user_id'],
        "gender"            => $student['gender'],
        "dob"               => $student['dob'],
        "trade"             => $student['trade'],
        "bio"               => $student['bio'],
        "education"         => $student['education'],
        "skills"            => $student['skills'],
        "projects"          => $student['projects'],
        "languages"         => $student['languages'],
        "experience"        => $student['experience'],
        "graduation_year"   => $student['graduation_year'],
        "cgpa"              => $student['cgpa'],
        "resume"            => $student['resume'],
        "certificates"      => $student['certificates'],
        "portfolio_link"    => $student['portfolio_link'],
        "linkedin_url"      => $student['linkedin_url'],
        "location"          => $student['location'],
        "course_id"         => $enrollment['course_id'] ?? null,
        "course_title"      => $enrollment['course_title'] ?? null,
        "course_duration"   => $enrollment['course_duration'] ?? null,
        "course_mode"       => $enrollment['course_mode'] ?? null,
        "instructor_name"   => $enrollment['instructor_name'] ?? null,
        "batch_id"          => $enrollment['batch_id'] ?? null,
        "batch_name"        => $enrollment['batch_name'] ?? null,
        "start_date"        => $enrollment['start_date'] ?? null,
        "end_date"          => $enrollment['end_date'] ?? null,
        "enrollment_status" => $enrollment['enrollment_status'] ?? "not_enrolled",
        "enrollment_date"   => $enrollment['enrollment_date'] ?? null,
        "progress_percent"  => $progress_percent,
        "status"            => $status_text
    ];

    // ✅ Final Response
    echo json_encode([
        "status" => true,
        "count"  => 1,
        "data"   => [$finalData]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Server error",
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>

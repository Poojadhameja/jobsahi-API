<?php
// get_hired_job_detail.php - Get detailed information of a specific hired job
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// ✅ Authenticate JWT (only student or admin)
$current_user = authenticateJWT(['student', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET requests allowed"]);
    exit;
}

// ✅ Validate application_id
$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;
if ($application_id <= 0) {
    echo json_encode(["status" => false, "message" => "Valid application_id is required"]);
    exit;
}

$role = strtolower($current_user['role'] ?? '');
$user_id = $current_user['user_id'] ?? null;

// ✅ Get student profile ID
$student_profile_id = null;

if ($role === 'student') {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$res) {
        echo json_encode(["status" => false, "message" => "Student profile not found"]);
        exit;
    }
    $student_profile_id = (int)$res['id'];
}

// ✅ Main query - Get detailed information of the hired job
$sql = "
    SELECT 
        a.id AS application_id,
        a.student_id,
        a.job_id,
        a.status,
        a.applied_at AS application_date,
        a.modified_at AS hired_date,
        a.job_selected,
        a.cover_letter,
        a.resume_link,
        a.created_at,
        a.modified_at,
        j.title AS job_title,
        j.description AS job_description,
        j.requirements AS job_requirements,
        j.location,
        j.job_type,
        j.salary_min,
        j.salary_max,
        j.experience_required,
        j.application_deadline,
        j.posted_at,
        j.created_at AS job_created_at,
        j.modified_at AS job_modified_at,
        rp.id AS recruiter_profile_id,
        rp.user_id AS recruiter_user_id,
        rp.company_name,
        rp.company_logo,
        rp.industry,
        rp.website,
        rp.location AS company_location,
        rp.gst_pan,
        rci.person_name AS contact_person_name,
        rci.phone AS contact_phone,
        rci.email AS contact_email,
        rci.additional_contact,
        rci.designation AS contact_designation,
        jc.category_name AS job_category
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
    LEFT JOIN recruiter_company_info rci ON rci.job_id = j.id
    LEFT JOIN job_category jc ON j.category_id = jc.id
    WHERE a.id = ?
      AND a.status = 'selected'
      AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')
      AND LOWER(a.admin_action) = 'approved'
";

// ✅ Security: Students can only see their own applications
if ($role === 'student' && $student_profile_id) {
    $sql .= " AND a.student_id = ?";
}

$sql .= " LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
    exit;
}

if ($role === 'student' && $student_profile_id) {
    $stmt->bind_param("ii", $application_id, $student_profile_id);
} else {
    $stmt->bind_param("i", $application_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // ✅ Get shortlisted date from activity_logs (if available)
    $shortlisted_date = null;
    
    $shortlist_check_sql = "
        SELECT created_at 
        FROM activity_logs 
        WHERE reference_table = 'applications' 
          AND reference_id = ? 
          AND action LIKE '%shortlisted%'
        ORDER BY created_at DESC 
        LIMIT 1
    ";
    $shortlist_stmt = $conn->prepare($shortlist_check_sql);
    if ($shortlist_stmt) {
        $shortlist_stmt->bind_param("i", $application_id);
        $shortlist_stmt->execute();
        $shortlist_result = $shortlist_stmt->get_result();
        if ($shortlist_row = $shortlist_result->fetch_assoc()) {
            $shortlisted_date = $shortlist_row['created_at'];
        }
        $shortlist_stmt->close();
    }
    
    // ✅ Get interview details if available
    $interview_details = null;
    $interview_sql = "
        SELECT 
            i.id AS interview_id,
            i.interview_date,
            i.interview_time,
            i.interview_type,
            i.location AS interview_location,
            i.notes AS interview_notes,
            i.status AS interview_status,
            i.created_at AS interview_created_at,
            i.modified_at AS interview_modified_at
        FROM interviews i
        WHERE i.application_id = ?
          AND (i.deleted_at IS NULL OR i.deleted_at = '0000-00-00 00:00:00')
        ORDER BY i.created_at DESC
        LIMIT 1
    ";
    $interview_stmt = $conn->prepare($interview_sql);
    if ($interview_stmt) {
        $interview_stmt->bind_param("i", $application_id);
        $interview_stmt->execute();
        $interview_result = $interview_stmt->get_result();
        if ($interview_row = $interview_result->fetch_assoc()) {
            $interview_details = [
                "interview_id" => (int)$interview_row['interview_id'],
                "interview_date" => $interview_row['interview_date'],
                "interview_time" => $interview_row['interview_time'],
                "interview_type" => $interview_row['interview_type'],
                "interview_location" => $interview_row['interview_location'],
                "interview_notes" => $interview_row['interview_notes'],
                "interview_status" => $interview_row['interview_status'],
                "interview_created_at" => $interview_row['interview_created_at'],
                "interview_modified_at" => $interview_row['interview_modified_at']
            ];
        }
        $interview_stmt->close();
    }
    
    // ✅ Format the response
    $response = [
        "application_id" => (int)$row['application_id'],
        "job_id" => (int)$row['job_id'],
        "status" => $row['status'],
        "job_selected" => (bool)$row['job_selected'],
        "application_date" => $row['application_date'],
        "shortlisted_date" => $shortlisted_date, // NULL if not shortlisted
        "hired_date" => $row['hired_date'],
        
        // Job Details
        "job" => [
            "job_id" => (int)$row['job_id'],
            "title" => $row['job_title'],
            "description" => $row['job_description'],
            "requirements" => $row['job_requirements'],
            "location" => $row['location'],
            "job_type" => $row['job_type'],
            "salary_min" => $row['salary_min'] !== null ? (float)$row['salary_min'] : null,
            "salary_max" => $row['salary_max'] !== null ? (float)$row['salary_max'] : null,
            "experience_required" => $row['experience_required'],
            "application_deadline" => $row['application_deadline'],
            "posted_at" => $row['posted_at'],
            "category" => $row['job_category']
        ],
        
        // Company/Recruiter Details
        "company" => [
            "recruiter_profile_id" => $row['recruiter_profile_id'] ? (int)$row['recruiter_profile_id'] : null,
            "company_name" => $row['company_name'],
            "company_logo" => $row['company_logo'],
            "industry" => $row['industry'],
            "website" => $row['website'],
            "location" => $row['company_location'],
            "gst_pan" => $row['gst_pan']
        ],
        
        // Contact Information
        "contact" => [
            "person_name" => $row['contact_person_name'],
            "phone" => $row['contact_phone'],
            "email" => $row['contact_email'],
            "additional_contact" => $row['additional_contact'],
            "designation" => $row['contact_designation']
        ],
        
        // Application Details
        "application" => [
            "cover_letter" => $row['cover_letter'],
            "resume_link" => $row['resume_link'],
            "created_at" => $row['created_at'],
            "modified_at" => $row['modified_at']
        ],
        
        // Interview Details (if available)
        "interview" => $interview_details
    ];
    
    echo json_encode([
        "status" => true,
        "message" => "Hired job details fetched successfully",
        "data" => $response,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Hired job not found or you don't have permission to access it"
    ]);
}

$stmt->close();
$conn->close();
?>


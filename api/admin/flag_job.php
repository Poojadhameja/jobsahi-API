<?php

// flag_job.php - Flag a job posting (FIXED VERSION)

require_once '../cors.php';

// ✅ Authenticate JWT (allow all authenticated users)
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['job_id']) || empty($input['job_id'])) {
    echo json_encode([
        "status" => false,
        "message" => "Job ID is required"
    ]);
    exit;
}

if (!isset($input['reason']) || empty(trim($input['reason']))) {
    echo json_encode([
        "status" => false,
        "message" => "Reason for flagging is required"
    ]);
    exit;
}

$job_id = intval($input['job_id']);
$reason = trim($input['reason']);
$user_id = $decoded['user_id']; // Get user ID from JWT token
$user_role = strtolower($decoded['role'] ?? 'student'); // Get user role from JWT token

try {
    // Check if job exists
    $checkJob = $conn->prepare("SELECT id FROM jobs WHERE id = ?");
    $checkJob->bind_param("i", $job_id);
    $checkJob->execute();
    $jobResult = $checkJob->get_result();
    
    if ($jobResult->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Job posting not found"
        ]);
        exit;
    }
    $checkJob->close();
    
    // ✅ Handle flagged_by based on user role
    $flagged_by = null;
    
    if ($user_role === 'student') {
        // Get student_profile id from user_id
        $getStudentProfile = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
        $getStudentProfile->bind_param("i", $user_id);
        $getStudentProfile->execute();
        $studentResult = $getStudentProfile->get_result();
        
        if ($studentResult->num_rows > 0) {
            $studentRow = $studentResult->fetch_assoc();
            $flagged_by = intval($studentRow['id']);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Student profile not found. Please contact support."
            ]);
            exit;
        }
        $getStudentProfile->close();
    } else {
        // For admin/recruiter/institute: Use a system/default student_profile id
        // Option 1: Use first available student_profile id as system admin
        // Option 2: Create/use a system admin profile (better approach)
        // Option 3: Use NULL if database allows (but it doesn't)
        
        // Since flagged_by cannot be NULL, we'll use a workaround:
        // Get the first student_profile id to use as a system/admin placeholder
        // OR better: Check if there's a system admin profile
        
        $getSystemProfile = $conn->prepare("
            SELECT sp.id 
            FROM student_profiles sp
            INNER JOIN users u ON sp.user_id = u.id
            WHERE u.role = 'admin' AND u.id = ?
            LIMIT 1
        ");
        $getSystemProfile->bind_param("i", $user_id);
        $getSystemProfile->execute();
        $systemResult = $getSystemProfile->get_result();
        
        if ($systemResult->num_rows > 0) {
            $systemRow = $systemResult->fetch_assoc();
            $flagged_by = intval($systemRow['id']);
        } else {
            // If no admin profile exists, use the first student_profile as placeholder
            // OR create a system entry
            // For now, let's get the first student_profile id
            $getFirstProfile = $conn->query("SELECT id FROM student_profiles ORDER BY id ASC LIMIT 1");
            if ($getFirstProfile && $getFirstProfile->num_rows > 0) {
                $firstRow = $getFirstProfile->fetch_assoc();
                $flagged_by = intval($firstRow['id']);
            } else {
                // No student profiles exist - this is a problem
                echo json_encode([
                    "status" => false,
                    "message" => "System error: No student profiles found. Cannot flag job. Please contact administrator."
                ]);
                exit;
            }
        }
        $getSystemProfile->close();
    }
    
    // Validate flagged_by is set
    if ($flagged_by === null || $flagged_by <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "System error: Could not determine flagged_by value. Please contact support."
        ]);
        exit;
    }
    
    // Check if user has already flagged this job
    // For students, check by student_profile id
    // For admin/recruiter/institute, we still check by the flagged_by value we determined
    $checkFlag = $conn->prepare("SELECT id FROM job_flags WHERE job_id = ? AND flagged_by = ?");
    $checkFlag->bind_param("ii", $job_id, $flagged_by);
    $checkFlag->execute();
    $flagResult = $checkFlag->get_result();
    
    if ($flagResult->num_rows > 0) {
        $existingFlag = $flagResult->fetch_assoc();
        echo json_encode([
            "status" => false,
            "message" => "This job has already been flagged",
            "flag_id" => $existingFlag['id']
        ]);
        exit;
    }
    $checkFlag->close();
    
    // Insert flag record
    $stmt = $conn->prepare("
        INSERT INTO job_flags (job_id, flagged_by, reason, reviewed, created_at) 
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->bind_param("iis", $job_id, $flagged_by, $reason);
    
    if ($stmt->execute()) {
        $flag_id = $stmt->insert_id;
        
        // ✅ Update jobs table: Set admin_action to 'pending' when job is flagged
        $updateJob = $conn->prepare("UPDATE jobs SET admin_action = 'pending', updated_at = NOW() WHERE id = ?");
        $updateJob->bind_param("i", $job_id);
        $updateJob->execute();
        $updateJob->close();
        
        echo json_encode([
            "status" => true,
            "message" => "Job posting flagged successfully",
            "flag_id" => $flag_id,
            "data" => [
                "flag_id" => $flag_id,
                "job_id" => $job_id,
                "flagged_by" => $flagged_by,
                "reason" => $reason,
                "reviewed" => "pending",
                "created_at" => date('Y-m-d H:i:s'),
                "flagged_by_role" => $user_role
            ]
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to flag job posting",
            "error" => $stmt->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage(),
        "error_details" => $e->getTraceAsString()
    ]);
}

$conn->close();

?>


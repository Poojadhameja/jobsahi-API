<?php
// update_application_status.php - Update job application status or selection
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// ✅ Authenticate JWT: only Admin or Recruiter allowed
$decoded = authenticateJWT(['admin', 'recruiter']);
$user_id = intval($decoded['user_id']);
$user_role = strtolower($decoded['role']);

// ✅ Validate Application ID
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($application_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid application ID"
    ]);
    exit();
}

// ✅ Read input JSON (PUT/POST)
$data = json_decode(file_get_contents("php://input"), true);
$new_status   = isset($data['status']) ? strtolower(trim($data['status'])) : '';
$job_selected = isset($data['job_selected']) ? intval($data['job_selected']) : 0;

// ✅ Validate status values
$valid_statuses = ['applied', 'shortlisted', 'rejected', 'selected'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid status. Allowed: " . implode(', ', $valid_statuses)
    ]);
    exit();
}

try {
    // ✅ Step 1: Verify that the application exists and fetch recruiter info
    $check_sql = "
        SELECT 
            a.id AS application_id, 
            a.job_id, 
            j.recruiter_id, 
            rp.user_id AS recruiter_user_id
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        WHERE a.id = ?
        LIMIT 1
    ";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $application_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Application not found"
        ]);
        exit();
    }

    $application = $result->fetch_assoc();

    // ✅ Step 2: Authorization Check
    // Admin → can update all | Recruiter → can only update their own jobs
    if ($user_role === 'recruiter' && $application['recruiter_user_id'] !== $user_id) {
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized: You can only update applications for your own job postings"
        ]);
        exit();
    }

    // ✅ Step 3: Update application (status + job_selected)
    $update_sql = "
        UPDATE applications 
        SET status = ?, 
            job_selected = ?, 
            modified_at = NOW()
        WHERE id = ?
    ";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sii", $new_status, $job_selected, $application_id);
    $update_stmt->execute();

    if ($update_stmt->affected_rows > 0) {
        // ✅ Step 3.1: Update interview status based on application status
        $interview_updated = false;
        $interview_update_message = '';
        
        if ($new_status === 'selected') {
            // When application is selected → mark interview as completed
            $update_interview_sql = "
                UPDATE interviews 
                SET status = 'completed', 
                    modified_at = NOW()
                WHERE application_id = ?
                  AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            ";
            $update_interview_stmt = $conn->prepare($update_interview_sql);
            if ($update_interview_stmt) {
                $update_interview_stmt->bind_param("i", $application_id);
                if ($update_interview_stmt->execute()) {
                    $interview_updated = ($update_interview_stmt->affected_rows > 0);
                    $interview_update_message = $interview_updated 
                        ? "Interview status updated to 'completed'" 
                        : "No interview found for this application";
                } else {
                    $interview_update_message = "Failed to update interview: " . $update_interview_stmt->error;
                }
                $update_interview_stmt->close();
            } else {
                $interview_update_message = "Failed to prepare interview update query: " . $conn->error;
            }
        } elseif ($new_status === 'rejected') {
            // When application is rejected → mark interview as cancelled
            $update_interview_sql = "
                UPDATE interviews 
                SET status = 'cancelled', 
                    modified_at = NOW()
                WHERE application_id = ?
                  AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            ";
            $update_interview_stmt = $conn->prepare($update_interview_sql);
            if ($update_interview_stmt) {
                $update_interview_stmt->bind_param("i", $application_id);
                if ($update_interview_stmt->execute()) {
                    $interview_updated = ($update_interview_stmt->affected_rows > 0);
                    $interview_update_message = $interview_updated 
                        ? "Interview status updated to 'cancelled'" 
                        : "No interview found for this application";
                } else {
                    $interview_update_message = "Failed to update interview: " . $update_interview_stmt->error;
                }
                $update_interview_stmt->close();
            } else {
                $interview_update_message = "Failed to prepare interview update query: " . $conn->error;
            }
        }

        // ✅ Send notification if student is shortlisted
        // ⚠️ Note: Notifications are sent ONLY to students
        if ($new_status === 'shortlisted') {
            // Get student user_id from application
            $student_sql = "
                SELECT sp.user_id, j.title as job_title, j.id as job_id
                FROM applications a
                JOIN student_profiles sp ON a.student_id = sp.id
                JOIN jobs j ON a.job_id = j.id
                WHERE a.id = ?
            ";
            $student_stmt = $conn->prepare($student_sql);
            $student_stmt->bind_param("i", $application_id);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
            
            if ($student_result->num_rows > 0) {
                $student_data = $student_result->fetch_assoc();
                $student_user_id = intval($student_data['user_id']);
                $job_title = $student_data['job_title'];
                $job_id = intval($student_data['job_id']);
                
                // ✅ Send notification to student (students only)
                require_once '../helpers/notification_helper.php';
                $notification_result = NotificationHelper::notifyShortlisted($student_user_id, $job_title, $job_id);
                
                // Log notification result (optional)
                if (!$notification_result['success']) {
                    error_log("Failed to send shortlist notification: " . $notification_result['message']);
                }
            }
            $student_stmt->close();
        }
        
        $response = [
            "status" => true,
            "message" => "Application updated successfully",
            "application_id" => $application_id,
            "new_status" => $new_status,
            "job_selected" => $job_selected,
            "updated_by" => $user_role,
            "timestamp" => date('Y-m-d H:i:s')
        ];
        
        // Add interview update info if status was selected or rejected
        if ($new_status === 'selected' || $new_status === 'rejected') {
            $response["interview_updated"] = $interview_updated;
            $response["interview_update_message"] = $interview_update_message;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "No changes made (maybe already '$new_status')"
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Server Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

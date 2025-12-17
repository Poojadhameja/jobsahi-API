
<?php 
require_once '../cors.php';
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST allowed"]);
    exit;
}

$decoded = authenticateJWT(['recruiter', 'admin']);
$user_role = strtolower($decoded['role']);
$user_id   = intval($decoded['user_id']);

if ($user_role !== 'recruiter') {
    echo json_encode(["status" => false, "message" => "Only recruiters can create jobs"]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($recruiter_id);
$stmt->fetch();
$stmt->close();

if (!$recruiter_id) {
    echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$title                 = $input['title'] ?? '';
$description           = $input['description'] ?? '';
$category_name         = $input['category_name'] ?? '';
$location              = $input['location'] ?? '';
$skills_required       = $input['skills_required'] ?? '';
$salary_min            = $input['salary_min'] ?? 0;
$salary_max            = $input['salary_max'] ?? 0;
$job_type              = $input['job_type'] ?? 'full_time';
$experience_required   = $input['experience_required'] ?? '';
$application_deadline  = $input['application_deadline'] ?? null;
$is_remote             = intval($input['is_remote'] ?? 0);
$no_of_vacancies       = intval($input['no_of_vacancies'] ?? 1);
$status                = $input['status'] ?? 'open';

$person_name           = $input['person_name'] ?? '';
$phone                 = $input['phone'] ?? '';
$additional_contact    = $input['additional_contact'] ?? '';

$conn->begin_transaction();

try {

    $category_id = null;

    $cat = $conn->prepare("SELECT id FROM job_category WHERE LOWER(category_name)=LOWER(?) LIMIT 1");
    $cat->bind_param("s", $category_name);
    $cat->execute();
    $cat->bind_result($category_id);
    $cat->fetch();
    $cat->close();

    if (!$category_id) {
        $current_time = date('Y-m-d H:i:s');
        $ins = $conn->prepare("INSERT INTO job_category (category_name, created_at) VALUES (?, ?)");
        $ins->bind_param("ss", $category_name, $current_time);
        $ins->execute();
        $category_id = $ins->insert_id;
        $ins->close();
    }

    // ✅ Default admin_action = 'approved' (by default jobs are approved)
    $admin_action = 'approved';
    
    // FIXED TYPE STRING:
    // i i s s s s d d s s s i i s s   = 15 params (added admin_action)
    $job_sql = "
        INSERT INTO jobs 
        (recruiter_id, category_id, title, description, location, skills_required,
         salary_min, salary_max, job_type, experience_required, application_deadline,
         is_remote, no_of_vacancies, status, admin_action)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $job_stmt = $conn->prepare($job_sql);
    // correct type string: i i s s s s d d s s s i i s s (added 's' for admin_action)
    $types = "iissssddsssiiss";
    $job_stmt->bind_param(
        $types,
        $recruiter_id,
        $category_id,
        $title,
        $description,
        $location,
        $skills_required,
        $salary_min,
        $salary_max,
        $job_type,
        $experience_required,
        $application_deadline,
        $is_remote,
        $no_of_vacancies,
        $status,
        $admin_action
    );

    $job_stmt->execute();
    $job_id = $job_stmt->insert_id;
    $job_stmt->close();

    $current_time = date('Y-m-d H:i:s');
    $csql = "
        INSERT INTO recruiter_company_info 
        (job_id, recruiter_id, person_name, phone, additional_contact, created_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ";

    $cstmt = $conn->prepare($csql);
    $cstmt->bind_param("iissss", $job_id, $recruiter_id, $person_name, $phone, $additional_contact, $current_time);
    $cstmt->execute();
    $company_info_id = $cstmt->insert_id;
    $cstmt->close();

    $up = $conn->prepare("UPDATE jobs SET company_info_id = ? WHERE id = ?");
    $up->bind_param("ii", $company_info_id, $job_id);
    $up->execute();
    $up->close();

    $conn->commit();
    
    // ✅ Prepare response immediately (before sending notifications)
    $response = [
        "status" => true,
        "message" => "Job created successfully",
        "job_id" => $job_id,
        "category_id" => $category_id,
        "company_info_id" => $company_info_id
    ];
    
    // ✅ Disable output buffering and prepare response
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // ✅ Send response immediately to client (before notifications)
    header('Content-Length: ' . strlen(json_encode($response)));
    echo json_encode($response);
    
    // ✅ Flush response to client immediately
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
    }
    
    // ✅ Now send notifications in background (connection still open, response already sent)
    // ⚠️ Note: Notifications are sent ONLY to all active students
    if ($admin_action === 'approved') {
        // Set execution time limit for notification process
        set_time_limit(120); // 2 minutes for sending notifications to all students
        ignore_user_abort(true); // Continue even if client disconnects
        
        error_log("Job created: job_id=$job_id, title=$title, sending notifications in background...");
        
        require_once '../helpers/notification_helper.php';
        
        // ✅ This sends notification to ALL active students only
        try {
            $notification_result = NotificationHelper::notifyNewJobPosted(
                $title,
                $job_id,
                $location
            );
            
            // Log notification result
            if (!$notification_result['success']) {
                error_log("Failed to send new job notification: " . $notification_result['message']);
            } else {
                $sent_count = $notification_result['success_count'] ?? 0;
                $failed_count = $notification_result['fail_count'] ?? 0;
                error_log("New job notification sent successfully: sent=$sent_count, failed=$failed_count");
            }
        } catch (Exception $e) {
            error_log("Exception while sending notifications: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
        }
    }
    
    // ✅ Close connection after notifications are sent
    $conn->close();

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit();
}
?>

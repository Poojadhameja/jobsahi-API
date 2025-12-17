<?php
// schedule_interview.php - Schedule interview and return joined data (Admin / Recruiter)
require_once '../cors.php';
require_once '../db.php';

header("Content-Type: application/json");

// ✅ Authenticate (Admin / Recruiter)
$decoded = authenticateJWT(['admin', 'recruiter']);
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

/* =========================================================
   GET METHOD: Fetch all scheduled interviews
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // ✅ Get recruiter_profile_id if recruiter role
        $recruiter_profile_id = null;
        if ($user_role === 'recruiter') {
            $rec_profile_stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
            $rec_profile_stmt->bind_param("i", $user_id);
            $rec_profile_stmt->execute();
            $rec_result = $rec_profile_stmt->get_result();
            
            if ($rec_result->num_rows > 0) {
                $rec_profile = $rec_result->fetch_assoc();
                $recruiter_profile_id = intval($rec_profile['id']);
            } else {
                // If recruiter profile not found, return empty
                echo json_encode([
                    "status"  => "success",
                    "message" => "No interviews found.",
                    "data"    => []
                ]);
                exit;
            }
            $rec_profile_stmt->close();
        }

        // ✅ Fetch only latest interview per student per job
        // For recruiter: Only their interviews, For admin: All interviews
        
        // Build subquery - same filtering logic for consistency
        $subquery_extra = "";
        if ($user_role === 'recruiter' && $recruiter_profile_id) {
            $subquery_extra = "INNER JOIN jobs j2 ON a.job_id = j2.id AND j2.recruiter_id = " . intval($recruiter_profile_id);
        }
        
        $sql = "
            SELECT 
                i.id AS interview_id,
                a.id AS application_id,
                sp.id AS student_profile_id,
                u.user_name AS candidateName,
                u.id AS candidateId,
                i.scheduled_at AS date,
                TIME(i.scheduled_at) AS timeSlot,
                i.mode AS interviewMode,
                i.platform_name AS platform_name,
                i.interview_link AS interview_link,
                i.location AS location,
                i.interview_info AS interview_info,
                rp.company_name AS scheduledBy,
                i.created_at AS createdAt,
                i.status
            FROM interviews i
            INNER JOIN applications a ON i.application_id = a.id
            INNER JOIN student_profiles sp ON a.student_id = sp.id
            INNER JOIN users u ON sp.user_id = u.id
            INNER JOIN jobs j ON a.job_id = j.id
            INNER JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
            INNER JOIN (
                SELECT a.student_id, a.job_id, MAX(i2.created_at) AS latest_created
                FROM interviews i2
                INNER JOIN applications a ON i2.application_id = a.id
                " . ($subquery_extra ? $subquery_extra . " " : "") . "
                GROUP BY a.student_id, a.job_id
            ) latest ON latest.student_id = a.student_id 
                     AND latest.job_id = a.job_id 
                     AND latest.latest_created = i.created_at
            WHERE 1=1
            " . ($user_role === 'recruiter' && $recruiter_profile_id ? "AND j.recruiter_id = ?" : "") . "
            ORDER BY i.created_at DESC
        ";

        if ($user_role === 'recruiter' && $recruiter_profile_id) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $recruiter_profile_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $stmt = null;
            $result = $conn->query($sql);
        }
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = [
                "interviewId"   => intval($row['interview_id']), // ✅ added field
                "application_id" => intval($row['application_id']),
                "candidateName" => $row['candidateName'],
                "candidateId"   => intval($row['candidateId']),
                "date"          => date('Y-m-d', strtotime($row['date'])),
                "timeSlot"      => date('H:i', strtotime($row['timeSlot'])),
                "interviewMode" => ucfirst($row['interviewMode']),
                "location"      => $row['interviewMode'] === 'offline' ? $row['location'] : null,
                "platform_name" => $row['interviewMode'] === 'online' ? $row['platform_name'] : null,
                "interview_link" => $row['interviewMode'] === 'online' ? $row['interview_link'] : null,
                "interview_info" => $row['interview_info'],
                "scheduledBy"   => $row['scheduledBy'],
                "status"        => $row['status'],
                "createdAt"     => $row['createdAt']
            ];
        }

        echo json_encode([
            "status"  => "success",
            "message" => "Interviews fetched successfully.",
            "data"    => $data
        ]);
        
        if ($stmt) {
            $stmt->close();
        }
    } catch (Exception $e) {
        echo json_encode([
            "status"  => "error",
            "message" => "Error fetching interviews: " . $e->getMessage()
        ]);
    }
    exit;
}

/* =========================================================
   POST METHOD: Schedule a new interview (using application_id or job_id+student_id)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $application_id = isset($data['application_id']) ? intval($data['application_id']) : 0;
    $job_id         = isset($data['job_id']) ? intval($data['job_id']) : 0;
    $student_id     = isset($data['student_id']) ? intval($data['student_id']) : 0;
    $scheduled_at   = isset($data['scheduled_at']) ? trim($data['scheduled_at']) : '';
    $mode           = isset($data['mode']) ? trim($data['mode']) : 'online';
    $location       = isset($data['location']) ? trim($data['location']) : ''; // For offline interviews
    $platform_name  = isset($data['platform_name']) ? trim($data['platform_name']) : ''; // For online: Zoom, Google Meet, Teams
    $interview_link = isset($data['interview_link']) ? trim($data['interview_link']) : ''; // For online: meeting link
    $status         = isset($data['status']) ? trim($data['status']) : 'scheduled';
    $interview_info = isset($data['interview_info']) ? trim($data['interview_info']) : ''; // Additional info

    // ✅ Validate input - application_id OR (job_id + student_id) required
    if ($application_id <= 0 && ($job_id <= 0 || $student_id <= 0)) {
        echo json_encode(["status" => "error", "message" => "Missing: Either application_id OR (job_id + student_id) is required"]);
        exit();
    }
    if (empty($scheduled_at)) {
        echo json_encode(["status" => "error", "message" => "Scheduled date and time are required"]);
        exit();
    }

    try {
        // ✅ Step 1: Get application_id (either directly or find from job_id + student_id)
        if ($application_id > 0) {
            // Use provided application_id directly
            $find_sql = "SELECT id FROM applications WHERE id = ? LIMIT 1";
            $find_stmt = $conn->prepare($find_sql);
            $find_stmt->bind_param("i", $application_id);
            $find_stmt->execute();
            $find_res = $find_stmt->get_result();

            if ($find_res->num_rows === 0) {
                echo json_encode(["status" => "error", "message" => "Application not found for given application_id"]);
                exit();
            }
        } else {
            // Find application_id using job_id + student_id (backward compatibility)
            $find_sql = "SELECT id FROM applications WHERE job_id = ? AND student_id = ? LIMIT 1";
            $find_stmt = $conn->prepare($find_sql);
            $find_stmt->bind_param("ii", $job_id, $student_id);
            $find_stmt->execute();
            $find_res = $find_stmt->get_result();

            if ($find_res->num_rows === 0) {
                echo json_encode(["status" => "error", "message" => "No application found for given job and student"]);
                exit();
            }

            $app_data = $find_res->fetch_assoc();
            $application_id = intval($app_data['id']);
        }

        // ✅ Step 2: Verify application + recruiter ownership and get student_id
        $check_sql = "
            SELECT a.id, a.student_id, j.recruiter_id, u.user_name AS candidate_name, rp.company_name 
            FROM applications a
            JOIN student_profiles sp ON a.student_id = sp.id
            JOIN users u ON sp.user_id = u.id
            JOIN jobs j ON a.job_id = j.id
            JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
            WHERE a.id = ?
        ";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $application_id);
        $check_stmt->execute();
        $res = $check_stmt->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Application not found or invalid"]);
            exit();
        }

        $app = $res->fetch_assoc();
        $student_id = intval($app['student_id']); // Get student_id from application
        $candidate_name = $app['candidate_name'];
        $company_name   = $app['company_name'];
        
        // ✅ If job_id and student_id were provided, verify they match the application
        if ($job_id > 0 && $student_id > 0) {
            if (intval($app['student_id']) !== $student_id) {
                echo json_encode(["status" => "error", "message" => "Student ID mismatch with application"]);
                exit();
            }
        }

        // ✅ Step 3: Verify recruiter ownership (if recruiter role)
        if ($user_role === 'recruiter') {
            $rec_profile_stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
            $rec_profile_stmt->bind_param("i", $user_id);
            $rec_profile_stmt->execute();
            $rec_result = $rec_profile_stmt->get_result();

            if ($rec_result->num_rows === 0) {
                echo json_encode(["status" => "error", "message" => "Recruiter profile not found"]);
                exit();
            }

            $rec_profile = $rec_result->fetch_assoc();
            $recruiter_profile_id = intval($rec_profile['id']);

            if ($recruiter_profile_id !== intval($app['recruiter_id'])) {
                echo json_encode(["status" => "error", "message" => "You are not authorized to schedule this interview"]);
                exit();
            }
        }

        // ✅ Step 3.5: Check if interview already exists for this application
        $existing_interview_sql = "
            SELECT id, scheduled_at, mode, platform_name, interview_link, location, status, interview_info
            FROM interviews 
            WHERE application_id = ? 
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            ORDER BY created_at DESC
            LIMIT 1
        ";
        $existing_stmt = $conn->prepare($existing_interview_sql);
        $existing_stmt->bind_param("i", $application_id);
        $existing_stmt->execute();
        $existing_result = $existing_stmt->get_result();
        
        if ($existing_result->num_rows > 0) {
            // Interview already exists - return existing interview instead of creating new one
            $existing_interview = $existing_result->fetch_assoc();
            $existing_interview_id = intval($existing_interview['id']);
            
            // Return existing interview details
            $responseData = [
                "interviewId"   => $existing_interview_id,
                "application_id" => $application_id,
                "candidateName" => $candidate_name,
                "candidateId"   => $student_id,
                "date"          => date('Y-m-d', strtotime($existing_interview['scheduled_at'])),
                "timeSlot"      => date('H:i', strtotime($existing_interview['scheduled_at'])),
                "interviewMode" => ucfirst($existing_interview['mode']),
                "location"      => $existing_interview['mode'] === 'offline' ? $existing_interview['location'] : null,
                "platform_name" => $existing_interview['mode'] === 'online' ? $existing_interview['platform_name'] : null,
                "interview_link" => $existing_interview['mode'] === 'online' ? $existing_interview['interview_link'] : null,
                "interview_info" => $existing_interview['interview_info'],
                "scheduledBy"   => $company_name,
                "createdAt"     => date('Y-m-d\TH:i:s', strtotime($existing_interview['scheduled_at']))
            ];

            echo json_encode([
                "status"  => "success",
                "message" => "Interview already exists for this application. Returning existing interview.",
                "data"    => $responseData,
                "is_existing" => true
            ]);
            $existing_stmt->close();
            $conn->close();
            exit();
        }
        $existing_stmt->close();

        // ✅ Step 4: Insert new interview record
        // For offline: location is required, for online: platform_name and interview_link
        if ($mode === 'online') {
            if (empty($interview_link)) {
                echo json_encode(["status" => "error", "message" => "interview_link is required for online interviews"]);
                exit();
            }
            if (empty($platform_name)) {
                echo json_encode(["status" => "error", "message" => "platform_name is required for online interviews"]);
                exit();
            }
        } else {
            if (empty($location)) {
                echo json_encode(["status" => "error", "message" => "location is required for offline interviews"]);
                exit();
            }
        }

        $insert = $conn->prepare("
            INSERT INTO interviews (
                application_id, scheduled_at, mode, platform_name, interview_link, location, 
                status, interview_info, admin_action, created_at, modified_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW())
        ");
        $insert->bind_param("isssssss", $application_id, $scheduled_at, $mode, $platform_name, $interview_link, $location, $status, $interview_info);

        if (!$insert->execute()) {
            throw new Exception("Failed to insert interview: " . $insert->error);
        }

        $interview_id = $insert->insert_id;

        // ✅ Step 5: Update application table (link interview)
        $update = $conn->prepare("
            UPDATE applications 
            SET interview_id = ?, status = 'shortlisted', modified_at = NOW()
            WHERE id = ?
        ");
        $update->bind_param("ii", $interview_id, $application_id);
        $update->execute();

        // ✅ Get student data for notification (before closing connection)
        $notification_data = null;
        $notif_sql = "
            SELECT sp.user_id, j.title as job_title, j.id as job_id
            FROM applications a
            JOIN student_profiles sp ON a.student_id = sp.id
            JOIN jobs j ON a.job_id = j.id
            WHERE a.id = ?
        ";
        $notif_stmt = $conn->prepare($notif_sql);
        $notif_stmt->bind_param("i", $application_id);
        $notif_stmt->execute();
        $notif_result = $notif_stmt->get_result();
        
        if ($notif_result->num_rows > 0) {
            $notif_data = $notif_result->fetch_assoc();
            $notification_data = [
                'student_user_id' => intval($notif_data['user_id']),
                'job_title' => $notif_data['job_title'],
                'job_id' => intval($notif_data['job_id'])
            ];
        }
        $notif_stmt->close();

        // ✅ Step 6: Build response immediately (before sending notifications)
        $responseData = [
            "interviewId"   => $interview_id,
            "application_id" => $application_id,
            "candidateName" => $candidate_name,
            "candidateId"   => $student_id,
            "date"          => date('Y-m-d', strtotime($scheduled_at)),
            "timeSlot"      => date('H:i', strtotime($scheduled_at)),
            "interviewMode" => ucfirst($mode),
            "location"      => $mode === 'offline' ? $location : null,
            "platform_name" => $mode === 'online' ? $platform_name : null,
            "interview_link" => $mode === 'online' ? $interview_link : null,
            "interview_info" => $interview_info,
            "scheduledBy"   => $company_name,
            "createdAt"     => date('Y-m-d\TH:i:s')
        ];

        $response = [
            "status"  => "success",
            "message" => "Interview scheduled successfully.",
            "data"    => $responseData
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
        
        // ✅ Now send notification in background (connection still open, response already sent)
        // ⚠️ Note: Notifications are sent ONLY to students
        if ($notification_data !== null) {
            // Set execution time limit for background process
            set_time_limit(60); // 1 minute for sending notification
            ignore_user_abort(true); // Continue even if client disconnects
            
            error_log("Interview scheduled: interview_id=$interview_id, application_id=$application_id, sending notification in background...");
            
            require_once '../helpers/notification_helper.php';
            
            try {
                $notification_result = NotificationHelper::notifyShortlisted(
                    $notification_data['student_user_id'],
                    $notification_data['job_title'],
                    $notification_data['job_id']
                );
                
                // Log notification result
                if (!$notification_result['success']) {
                    error_log("Failed to send shortlist notification: " . $notification_result['message']);
                } else {
                    error_log("Shortlist notification sent successfully to user_id: " . $notification_data['student_user_id']);
                }
            } catch (Exception $e) {
                error_log("Exception while sending notifications: " . $e->getMessage());
                error_log("Exception trace: " . $e->getTraceAsString());
            }
        } else {
            error_log("Student user not found for notification: application_id=$application_id");
        }
        
        // ✅ Close connection after notifications are sent
        $conn->close();

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->close();
        }
        echo json_encode([
            "status"  => "error",
            "message" => "Error: " . $e->getMessage()
        ]);
        exit;
    }
}
?>

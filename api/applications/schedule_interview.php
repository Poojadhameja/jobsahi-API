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
        // ✅ Fetch only latest interview per student per job
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
                i.location AS location,
                i.feedback AS feedback,
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
                GROUP BY a.student_id, a.job_id
            ) latest ON latest.student_id = a.student_id 
                     AND latest.job_id = a.job_id 
                     AND latest.latest_created = i.created_at
            ORDER BY i.created_at DESC
        ";

        $result = $conn->query($sql);
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = [
                "interviewId"   => intval($row['interview_id']), // ✅ added field
                "candidateName" => $row['candidateName'],
                "candidateId"   => intval($row['candidateId']),
                "date"          => date('Y-m-d', strtotime($row['date'])),
                "timeSlot"      => date('H:i', strtotime($row['timeSlot'])),
                "interviewMode" => ucfirst($row['interviewMode']),
                "location"      => $row['location'],
                "feedback"      => $row['feedback'],
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
    } catch (Exception $e) {
        echo json_encode([
            "status"  => "error",
            "message" => "Error fetching interviews: " . $e->getMessage()
        ]);
    }
    exit;
}

/* =========================================================
   POST METHOD: Schedule a new interview (using job_id)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $job_id        = isset($data['job_id']) ? intval($data['job_id']) : 0;
    $student_id    = isset($data['student_id']) ? intval($data['student_id']) : 0;
    $scheduled_at  = isset($data['scheduled_at']) ? trim($data['scheduled_at']) : '';
    $mode          = isset($data['mode']) ? trim($data['mode']) : 'online';
    $location      = isset($data['location']) ? trim($data['location']) : '';
    $status        = isset($data['status']) ? trim($data['status']) : 'scheduled';
    $feedback      = isset($data['feedback']) ? trim($data['feedback']) : '';

    // ✅ Validate input
    if ($job_id <= 0 || $student_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Missing or invalid job_id or student_id"]);
        exit();
    }
    if (empty($scheduled_at)) {
        echo json_encode(["status" => "error", "message" => "Scheduled date and time are required"]);
        exit();
    }

    try {
        // ✅ Step 1: Find application_id using job_id + student_id
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

        // ✅ Step 2: Verify application + recruiter ownership
        $check_sql = "
            SELECT a.id, a.student_id, j.recruiter_id, u.user_name AS candidate_name, rp.company_name 
            FROM applications a
            JOIN student_profiles sp ON a.student_id = sp.id
            JOIN users u ON sp.user_id = u.id
            JOIN jobs j ON a.job_id = j.id
            JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
            WHERE a.id = ? AND a.student_id = ?
        ";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $application_id, $student_id);
        $check_stmt->execute();
        $res = $check_stmt->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Invalid job_id or student_id"]);
            exit();
        }

        $app = $res->fetch_assoc();
        $candidate_name = $app['candidate_name'];
        $company_name   = $app['company_name'];

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

        // ✅ Step 4: Insert new interview record
        $insert = $conn->prepare("
            INSERT INTO interviews (application_id, scheduled_at, mode, location, status, feedback, admin_action, created_at, modified_at)
            VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW())
        ");
        $insert->bind_param("isssss", $application_id, $scheduled_at, $mode, $location, $status, $feedback);

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

        // ✅ Step 6: Build response
        $responseData = [
            "interviewId"   => $interview_id, // ✅ added here too for frontend consistency
            "candidateName" => $candidate_name,
            "candidateId"   => $student_id,
            "date"          => date('Y-m-d', strtotime($scheduled_at)),
            "timeSlot"      => date('H:i', strtotime($scheduled_at)),
            "interviewMode" => ucfirst($mode),
            "location"      => $location,
            "feedback"      => $feedback,
            "scheduledBy"   => $company_name,
            "createdAt"     => date('Y-m-d\TH:i:s')
        ];

        echo json_encode([
            "status"  => "success",
            "message" => "Interview scheduled successfully.",
            "data"    => $responseData
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "status"  => "error",
            "message" => "Error: " . $e->getMessage()
        ]);
    }

    $conn->close();
    exit;
}
?>

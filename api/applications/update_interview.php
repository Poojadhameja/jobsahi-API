<?php
// update_interview.php — Update existing interview (Admin / Recruiter)
require_once '../cors.php';
require_once '../db.php';

// ✅ Prevent caching for instant updates
header("Content-Type: application/json");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// ✅ Authenticate JWT (Admin / Recruiter)
$decoded = authenticateJWT(['admin', 'recruiter']);
$user_id = $decoded['user_id'];
$user_role = strtolower($decoded['role'] ?? '');

// ✅ Allow only PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["status" => false, "message" => "Only PUT requests allowed"]);
    exit();
}

// ✅ Parse JSON input
$data = json_decode(file_get_contents("php://input"), true);

$interview_id = isset($data['interview_id']) ? intval($data['interview_id']) : 0;
$scheduled_at = isset($data['scheduled_at']) ? trim($data['scheduled_at']) : '';
$status       = isset($data['status']) ? trim($data['status']) : '';
$mode         = isset($data['mode']) ? trim($data['mode']) : '';
$location     = isset($data['location']) ? trim($data['location']) : '';
$platform_name = isset($data['platform_name']) ? trim($data['platform_name']) : '';
$interview_link = isset($data['interview_link']) ? trim($data['interview_link']) : '';
$interview_info = isset($data['interview_info']) ? trim($data['interview_info']) : '';

if ($interview_id <= 0) {
    echo json_encode(["status" => false, "message" => "Missing or invalid interview_id"]);
    exit();
}

try {
    // ✅ Step 1: Fetch interview and recruiter validation
    $fetch_sql = "
        SELECT 
            i.id AS interview_id,
            j.recruiter_id,
            u.user_name AS candidate_name,
            rp.company_name,
            a.student_id
        FROM interviews i
        JOIN applications a ON i.application_id = a.id
        JOIN student_profiles sp ON a.student_id = sp.id
        JOIN users u ON sp.user_id = u.id
        JOIN jobs j ON a.job_id = j.id
        JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        WHERE i.id = ?
        LIMIT 1
    ";
    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param("i", $interview_id);
    $fetch_stmt->execute();
    $res = $fetch_stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Interview not found"]);
        exit();
    }

    $interview = $res->fetch_assoc();
    $recruiter_id = intval($interview['recruiter_id']);
    $candidate_name = $interview['candidate_name'];
    $company_name = $interview['company_name'];

    // ✅ Step 2: Validate recruiter ownership
    if ($user_role === 'recruiter') {
        $rec_stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
        $rec_stmt->bind_param("i", $user_id);
        $rec_stmt->execute();
        $rec_res = $rec_stmt->get_result();

        if ($rec_res->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
            exit();
        }

        $rec_profile = $rec_res->fetch_assoc();
        if (intval($rec_profile['id']) !== $recruiter_id) {
            echo json_encode(["status" => false, "message" => "You are not authorized to update this interview"]);
            exit();
        }
    }

    // ✅ Step 3: Prepare SQL for update (only allowed fields)
    $fields = [];
    $params = [];
    $types  = '';

    if (!empty($scheduled_at)) {
        $fields[] = "scheduled_at = ?";
        $params[] = $scheduled_at;
        $types .= 's';
    }
    if (!empty($status)) {
        $fields[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }
    if (!empty($mode)) {
        $fields[] = "mode = ?";
        $params[] = $mode;
        $types .= 's';
    }
    if (isset($data['location'])) {
        $fields[] = "location = ?";
        $params[] = $location;
        $types .= 's';
    }
    if (isset($data['platform_name'])) {
        $fields[] = "platform_name = ?";
        $params[] = $platform_name;
        $types .= 's';
    }
    if (isset($data['interview_link'])) {
        $fields[] = "interview_link = ?";
        $params[] = $interview_link;
        $types .= 's';
    }
    if (isset($data['interview_info'])) {
        $fields[] = "interview_info = ?";
        $params[] = $interview_info;
        $types .= 's';
    }

    // Always update modified_at
    $fields[] = "modified_at = NOW()";

    if (empty($fields)) {
        echo json_encode(["status" => false, "message" => "No valid fields provided to update"]);
        exit();
    }

    $sql = "UPDATE interviews SET " . implode(", ", $fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $types .= 'i';
    $params[] = $interview_id;
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        echo json_encode(["status" => false, "message" => "Failed to update interview", "error" => $stmt->error]);
        exit();
    }
    
    // ✅ Flush output immediately for instant response
    if (function_exists('fastcgi_finish_request')) {
        // For FastCGI servers - response sent immediately
    } else {
        // For other servers - flush output buffer
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    // ✅ Step 4: Fetch updated record (optimized - single query with all needed data)
    $get_sql = "
        SELECT 
            i.id AS interview_id,
            i.application_id,
            i.scheduled_at,
            i.status,
            i.mode,
            i.location,
            i.platform_name,
            i.interview_link,
            i.interview_info,
            u.user_name AS candidateName,
            u.id AS candidateId,
            sp.id AS student_profile_id,
            rp.company_name AS scheduledBy,
            i.created_at
        FROM interviews i
        JOIN applications a ON i.application_id = a.id
        JOIN student_profiles sp ON a.student_id = sp.id
        JOIN users u ON sp.user_id = u.id
        JOIN jobs j ON a.job_id = j.id
        JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        WHERE i.id = ?
        LIMIT 1
    ";
    $get_stmt = $conn->prepare($get_sql);
    $get_stmt->bind_param("i", $interview_id);
    $get_stmt->execute();
    $updated = $get_stmt->get_result()->fetch_assoc();

    // ✅ Step 5: Response (complete data for instant frontend update)
    $response = [
        "interviewId"   => intval($updated['interview_id']),
        "application_id" => intval($updated['application_id']),
        "candidateName" => $updated['candidateName'],
        "candidateId"   => intval($updated['candidateId']),
        "date"          => date('Y-m-d', strtotime($updated['scheduled_at'])),
        "timeSlot"      => date('H:i', strtotime($updated['scheduled_at'])),
        "status"        => strtolower($updated['status']), // Keep lowercase for consistency
        "interviewMode" => ucfirst($updated['mode']),
        "mode"          => $updated['mode'],
        "location"      => $updated['mode'] === 'offline' ? $updated['location'] : null,
        "platform_name" => $updated['mode'] === 'online' ? $updated['platform_name'] : null,
        "interview_link" => $updated['mode'] === 'online' ? $updated['interview_link'] : null,
        "interview_info" => $updated['interview_info'],
        "scheduledBy"   => $updated['scheduledBy'],
        "createdAt"     => $updated['created_at']
    ];

    echo json_encode([
        "status"  => true,
        "message" => "Interview updated successfully",
        "data"    => $response
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

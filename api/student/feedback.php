<?php
// feedback.php - Student can submit general feedback to admin (POST only)
require_once '../cors.php';
require_once '../db.php';

// Detect HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// ✅ Allow only POST method for submitting feedback
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed"
    ]);
    exit();
}

// ✅ Authenticate (only students can post feedback)
$decoded = authenticateJWT(['student']);
$student_user_id = intval($decoded['user_id']);

// ✅ Parse JSON body
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['feedback']) || empty(trim($data['feedback']))) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Feedback text is required"
    ]);
    exit();
}

$subject = isset($data['subject']) ? trim($data['subject']) : null;
$feedback = trim($data['feedback']);
$admin_action = 'pending'; // Always start as pending

// ✅ Fetch student_id from student_profiles using user_id
$stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
$stmt->bind_param("i", $student_user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => false,
        "message" => "Student profile not found for this user"
    ]);
    exit();
}

$student = $res->fetch_assoc();
$student_id = intval($student['id']);
$stmt->close();

// ✅ Check feedback submission limit: Max 2 feedbacks in 14 days
// Logic: Student can submit max 2 feedbacks within 14 days from the first submission in that window
// After 14 days from the first submission, a new window starts

// Get all feedbacks from this student, ordered by creation date (oldest first)
$check_sql = "SELECT id, created_at FROM student_feedback 
              WHERE student_id = ? 
              ORDER BY created_at ASC";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $student_id);
$check_stmt->execute();
$feedbacks_result = $check_stmt->get_result();
$all_feedbacks = [];
while ($fb = $feedbacks_result->fetch_assoc()) {
    $all_feedbacks[] = $fb;
}
$check_stmt->close();

// If student has submitted feedbacks, check the limit
if (count($all_feedbacks) > 0) {
    $current_time = new DateTime();
    $current_time->setTime(0, 0, 0); // Set to start of day for accurate day calculations
    
    // Find the earliest feedback in the last 14 days
    // This determines the current window
    $earliest_in_window = null;
    
    foreach ($all_feedbacks as $fb) {
        $fb_date = new DateTime($fb['created_at']);
        $fb_date->setTime(0, 0, 0);
        $days_diff = $current_time->diff($fb_date)->days;
        
        // If feedback is within last 14 days
        if ($days_diff < 14) {
            if ($earliest_in_window === null || $fb_date < $earliest_in_window) {
                $earliest_in_window = $fb_date;
            }
        }
    }
    
    // If we found an earliest feedback in the last 14 days, check the limit
    if ($earliest_in_window !== null) {
        // Calculate window end (14 days from earliest feedback)
        $window_end = clone $earliest_in_window;
        $window_end->modify('+14 days');
        
        // Count all feedbacks within this 14-day window
        $count_in_window = 0;
        foreach ($all_feedbacks as $fb) {
            $fb_date = new DateTime($fb['created_at']);
            $fb_date->setTime(0, 0, 0);
            
            // Check if feedback is within the 14-day window from earliest
            if ($fb_date >= $earliest_in_window && $fb_date < $window_end) {
                $count_in_window++;
            }
        }
        
        // If already submitted 2 feedbacks in current window
        if ($count_in_window >= 2) {
            // Check if 14 days have passed since the window start
            $days_since_window_start = $current_time->diff($earliest_in_window)->days;
            
            if ($days_since_window_start < 14) {
                // Calculate remaining days
                $remaining_days = 14 - $days_since_window_start;
                $reset_date = clone $earliest_in_window;
                $reset_date->modify('+14 days');
                
                http_response_code(429);
                echo json_encode([
                    "status" => false,
                    "message" => "आप 14 दिनों में अधिकतम 2 feedback submit कर सकते हैं। कृपया " . $reset_date->format('d/m/Y') . " के बाद फिर से कोशिश करें।",
                    "message_en" => "You can submit maximum 2 feedbacks in 14 days. Please try again after " . $reset_date->format('d/m/Y'),
                    "submissions_in_window" => $count_in_window,
                    "window_start_date" => $earliest_in_window->format('Y-m-d'),
                    "reset_date" => $reset_date->format('Y-m-d'),
                    "remaining_days" => $remaining_days
                ]);
                exit();
            }
            // If 14 days have passed, allow submission (new window starts)
        }
        // If count < 2, allow submission
    }
    // If no feedbacks in last 14 days, allow submission (new window)
}

// ✅ Insert feedback
$sql = "INSERT INTO student_feedback (student_id, subject, feedback, admin_action, created_at)
        VALUES (?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $student_id, $subject, $feedback, $admin_action);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode([
        "status" => true,
        "message" => "Feedback submitted successfully. Admin will review it soon.",
        "feedback_id" => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $stmt->error
    ]);
}
$stmt->close();

$conn->close();
?>


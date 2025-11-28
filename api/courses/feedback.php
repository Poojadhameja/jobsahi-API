<?php
// courses_feedback.php - Create and update course feedback (POST, PUT only)
require_once '../cors.php';
require_once '../db.php';

// Detect HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// ✅ Allow only POST and PUT methods
if (!in_array($method, ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Only POST and PUT methods are allowed"
    ]);
    exit();
}

if ($method === 'POST') {

    // ✅ Authenticate (only students can post feedback)
    $decoded = authenticateJWT(['student']);
    $student_user_id = intval($decoded['user_id']);

    // ✅ Parse JSON body
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['course_id']) || !isset($data['rating']) || !isset($data['feedback'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Required fields: course_id, rating, feedback"
        ]);
        exit();
    }

    $course_id = intval($data['course_id']);
    $rating = intval($data['rating']);
    $feedback = trim($data['feedback']);
    $admin_action = isset($data['admin_action']) && !empty(trim($data['admin_action'])) 
                    ? trim($data['admin_action']) 
                    : 'approved';  // ✅ Default to approved if not provided

    // ✅ Fetch student_id from student_profiles using user_id
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $student_user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Student profile not found for this user"
        ]);
        exit();
    }

    $student = $res->fetch_assoc();
    $student_id = intval($student['id']);
    $stmt->close();

    // ✅ Validate rating range
    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Rating must be between 1 and 5"
        ]);
        exit();
    }

    // ✅ Check if feedback already exists for this student + course
    $check_sql = "SELECT id FROM course_feedback WHERE student_id = ? AND course_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $student_id, $course_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $existing_feedback = $check_result->fetch_assoc();
    $check_stmt->close();

    // ✅ If feedback exists, UPDATE it; otherwise INSERT new
    if ($existing_feedback) {
        // Update existing feedback (preserve original created_at)
        $feedback_id = intval($existing_feedback['id']);
        $update_sql = "UPDATE course_feedback 
                       SET rating = ?, feedback = ?, admin_action = ? 
                       WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("issi", $rating, $feedback, $admin_action, $feedback_id);

        if ($update_stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                "status" => true,
                "message" => "Feedback updated successfully",
                "feedback_id" => $feedback_id,
                "is_updated" => true,
                "admin_action" => $admin_action
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => false,
                "message" => "Database error: " . $update_stmt->error
            ]);
        }
        $update_stmt->close();
    } else {
        // Insert new feedback
        $insert_sql = "INSERT INTO course_feedback (course_id, student_id, rating, feedback, admin_action, created_at)
                       VALUES (?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiiss", $course_id, $student_id, $rating, $feedback, $admin_action);

        if ($insert_stmt->execute()) {
            $feedback_id = $insert_stmt->insert_id;
            http_response_code(201);
            echo json_encode([
                "status" => true,
                "message" => "Feedback submitted successfully",
                "feedback_id" => $feedback_id,
                "is_updated" => false,
                "admin_action" => $admin_action
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => false,
                "message" => "Database error: " . $insert_stmt->error
            ]);
        }
        $insert_stmt->close();
    }

} elseif ($method === 'PUT') {
    // ✅ Update feedback by student or admin
    $decoded = authenticateJWT(['admin', 'student']);
    $user_role = strtolower($decoded['role']);
    $user_id = intval($decoded['user_id']);

    // ✅ Parse body
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['feedback_id'])) {
        echo json_encode([
            "status" => false,
            "message" => "feedback_id is required"
        ]);
        exit();
    }

    $feedback_id = intval($data['feedback_id']);
    $rating = isset($data['rating']) ? intval($data['rating']) : null;
    $feedback = isset($data['feedback']) ? trim($data['feedback']) : null;
    $admin_action = isset($data['admin_action']) ? trim($data['admin_action']) : null;

    // ✅ Check permission: student can update only their own feedback
    if ($user_role === 'student') {
        $check_stmt = $conn->prepare("
            SELECT cf.id 
            FROM course_feedback cf
            JOIN student_profiles sp ON cf.student_id = sp.id
            WHERE cf.id = ? AND sp.user_id = ?
        ");
        $check_stmt->bind_param("ii", $feedback_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "You can only update your own feedback"
            ]);
            exit();
        }
    }

    // ✅ Build dynamic update query
    $fields = [];
    $params = [];
    $types = "";

    if ($rating !== null) {
        $fields[] = "rating = ?";
        $params[] = $rating;
        $types .= "i";
    }
    if ($feedback !== null) {
        $fields[] = "feedback = ?";
        $params[] = $feedback;
        $types .= "s";
    }
    if ($admin_action !== null && $user_role === 'admin') {
        $fields[] = "admin_action = ?";
        $params[] = $admin_action;
        $types .= "s";
    }

    if (empty($fields)) {
        echo json_encode([
            "status" => false,
            "message" => "No fields to update"
        ]);
        exit();
    }

    $query = "UPDATE course_feedback SET " . implode(", ", $fields) . ", modified_at = NOW() WHERE id = ?";
    $params[] = $feedback_id;
    $types .= "i";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Feedback updated successfully"
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update feedback",
            "error" => $stmt->error
        ]);
    }

    $stmt->close();
}

$conn->close();
?>

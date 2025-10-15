<?php
// skill-attempts.php
require_once '../cors.php';
header('Content-Type: application/json');

// Ensure database connection is available
if (!isset($conn) || !$conn instanceof mysqli) {
    respond(["status" => false, "message" => "Database connection not established"]);
}

// ✅ Allow student, recruiter, and admin
$current_user = authenticateJWT(['student', 'recruiter', 'admin']);
$user_role = $current_user['role'] ?? '';
$user_id = $current_user['user_id'] ?? null;

function respond($d) {
    echo json_encode($d);
    exit;
}

// ✅ Helper function to get student_id from student_profiles by user_id
function getStudentIdFromProfile($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    
    return $row ? (int)$row['id'] : null;
}

// ✅ Helper function to get or create student profile
function getOrCreateStudentProfile($conn, $user_id) {
    // First, try to get existing student profile
    $student_id = getStudentIdFromProfile($conn, $user_id);
    
    if ($student_id !== null) {
        return $student_id;
    }
    
    // If not found, create a new student profile
    $stmt = $conn->prepare("INSERT INTO student_profiles (user_id) VALUES (?)");
    if (!$stmt) {
        error_log("Failed to prepare INSERT statement: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        error_log("Failed to create student profile for user_id: $user_id, error: $error");
        return null;
    }
    
    $student_id = $stmt->insert_id;
    $stmt->close();
    error_log("Created new student profile for user_id: $user_id, student_id: $student_id");
    
    return $student_id;
}

// ✅ Helper function to verify student_id exists in student_profiles
function verifyStudentProfile($conn, $student_id) {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

// --------------------
// POST: Record an attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ Only students and recruiters can submit attempts
    if (!in_array($user_role, ['student', 'recruiter'])) {
        respond(["status" => false, "message" => "Only students and recruiters can submit attempts"]);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        respond(["status" => false, "message" => "Invalid JSON input"]);
    }

    // ✅ Required fields
    $required = ['test_id', 'question_id', 'selected_option', 'is_correct', 'attempt_number', 'time_taken_seconds'];
    
    // For recruiters, they can specify a different student_id
    if ($user_role === 'recruiter' && isset($data['student_id'])) {
        $required_fields = $required;
    } else {
        $required_fields = $required;
    }
    
    foreach ($required_fields as $f) {
        if (!isset($data[$f])) {
            respond(["status" => false, "message" => "Missing $f"]);
        }
    }

    // ✅ Determine student_id based on role
    if ($user_role === 'student') {
        // Students can only submit for themselves
        $student_id = getOrCreateStudentProfile($conn, $user_id);
        if ($student_id === null) {
            respond(["status" => false, "message" => "Failed to retrieve or create student profile"]);
        }
    } else if ($user_role === 'recruiter') {
        // Recruiters can submit for a specific student or themselves
        if (isset($data['student_id']) && !empty($data['student_id'])) {
            $student_id = (int)$data['student_id'];
            // Verify the student_id exists
            if (!verifyStudentProfile($conn, $student_id)) {
                respond(["status" => false, "message" => "Invalid student_id: Student profile not found"]);
            }
        } else {
            // If no student_id provided, use recruiter's own profile
            $student_id = getOrCreateStudentProfile($conn, $user_id);
            if ($student_id === null) {
                respond(["status" => false, "message" => "Failed to retrieve or create student profile"]);
            }
        }
    }

    $test_id = (int)$data['test_id'];
    $question_id = (int)$data['question_id'];
    $selected_option = $data['selected_option'];
    $is_correct = (int)$data['is_correct'];
    $attempt_number = (int)$data['attempt_number'];
    $time_taken_seconds = (int)$data['time_taken_seconds'];

    // ✅ Additional input validation
    if (!in_array($selected_option, ['A', 'B', 'C', 'D'])) {
        respond(["status" => false, "message" => "Invalid selected_option. Must be A, B, C, or D"]);
    }
    if ($is_correct !== 0 && $is_correct !== 1) {
        respond(["status" => false, "message" => "Invalid is_correct. Must be 0 or 1"]);
    }

    // ✅ Foreign Key Validations
    // Check if question_id exists
    $checkQ = $conn->prepare("SELECT id FROM skill_questions WHERE id = ?");
    $checkQ->bind_param("i", $question_id);
    $checkQ->execute();
    $qres = $checkQ->get_result();
    if ($qres->num_rows === 0) {
        $checkQ->close();
        respond(["status" => false, "message" => "Invalid question_id"]);
    }
    $checkQ->close();

    // Check if test_id exists
    $checkT = $conn->prepare("SELECT id FROM skill_tests WHERE id = ?");
    $checkT->bind_param("i", $test_id);
    $checkT->execute();
    $tres = $checkT->get_result();
    if ($tres->num_rows === 0) {
        $checkT->close();
        respond(["status" => false, "message" => "Invalid test_id"]);
    }
    $checkT->close();

    // Check if student_id exists in student_profiles
    $checkS = $conn->prepare("SELECT id FROM student_profiles WHERE id = ?");
    $checkS->bind_param("i", $student_id);
    $checkS->execute();
    $sres = $checkS->get_result();
    if ($sres->num_rows === 0) {
        $checkS->close();
        respond(["status" => false, "message" => "Student profile not found"]);
    }
    $checkS->close();

    // ✅ Insert Attempt
    $stmt = $conn->prepare("
        INSERT INTO skill_attempts 
        (student_id, test_id, question_id, selected_option, is_correct, attempt_number, time_taken_seconds)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiisiii", $student_id, $test_id, $question_id, $selected_option, $is_correct, $attempt_number, $time_taken_seconds);

    if ($stmt->execute()) {
        $insert_id = $stmt->insert_id;
        $stmt->close();
        respond(["status" => true, "message" => "Attempt recorded", "insert_id" => $insert_id, "student_id" => $student_id]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        error_log("Insert failed for student_id: $student_id, error: $error");
        respond(["status" => false, "message" => $error]);
    }
}

// --------------------
// GET: Retrieve attempts (Student/Recruiter/Admin)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $student_q = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    $test_q = isset($_GET['test_id']) ? (int)$_GET['test_id'] : null;

    // ✅ Role-based filtering
    if ($user_role === 'student') {
        // Students can only see their own attempts
        $student_q = getOrCreateStudentProfile($conn, $user_id);
        if ($student_q === null) {
            respond(["status" => false, "message" => "Failed to retrieve student profile"]);
        }
    } else if ($user_role === 'recruiter') {
        // Recruiters can see attempts for students they have access to, or their own
        // If no student_id specified, show their own attempts
        if (!$student_q) {
            $student_q = getOrCreateStudentProfile($conn, $user_id);
            if ($student_q === null) {
                respond(["status" => false, "message" => "Failed to retrieve student profile"]);
            }
        }
        // Optional: Add validation to check if recruiter has access to this student
        // This depends on your recruiter-student relationship model
    }
    // Admin can see all attempts or filter by student_id/test_id

    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM skill_attempts WHERE id = ?");
        $stmt->bind_param("i", $id);
    } else {
        $where = [];
        $types = '';
        $vals = [];
        if ($student_q) {
            $where[] = "student_id = ?";
            $types .= 'i';
            $vals[] = $student_q;
        }
        if ($test_q) {
            $where[] = "test_id = ?";
            $types .= 'i';
            $vals[] = $test_q;
        }

        $sql = "SELECT * FROM skill_attempts";
        if (count($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY id ASC";

        $stmt = $conn->prepare($sql);
        if (count($vals)) {
            $stmt->bind_param($types, ...$vals);
        }
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    respond(["status" => true, "data" => $rows]);
}

// --------------------
// PUT: Edit attempt (Admin and Recruiter only)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // ✅ Only admin and recruiter can edit attempts
    if (!in_array($user_role, ['admin', 'recruiter'])) {
        respond(["status" => false, "message" => "Only admin and recruiter can edit attempts"]);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) {
        respond(["status" => false, "message" => "Missing id"]);
    }
    $id = (int)$data['id'];

    // ✅ For recruiter, verify they have access to this attempt's student
    if ($user_role === 'recruiter') {
        $checkAccess = $conn->prepare("SELECT student_id FROM skill_attempts WHERE id = ?");
        $checkAccess->bind_param("i", $id);
        $checkAccess->execute();
        $accRes = $checkAccess->get_result();
        if ($accRes->num_rows === 0) {
            $checkAccess->close();
            respond(["status" => false, "message" => "Attempt not found"]);
        }
        $attemptRow = $accRes->fetch_assoc();
        $attemptStudentId = $attemptRow['student_id'];
        $checkAccess->close();

        // Optional: Add logic to verify recruiter can access this student
        // For now, recruiters can edit any attempt (adjust based on your requirements)
    }

    $fields = [];
    $types = '';
    $vals = [];
    $allowed = ['student_id', 'test_id', 'question_id', 'selected_option', 'is_correct', 'attempt_number', 'time_taken_seconds'];

    foreach ($allowed as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $types .= in_array($f, ['student_id', 'test_id', 'question_id', 'is_correct', 'attempt_number', 'time_taken_seconds']) ? 'i' : 's';
            $vals[] = $data[$f];
        }
    }

    if (count($fields) === 0) {
        respond(["status" => false, "message" => "No fields to update"]);
    }

    $sql = "UPDATE skill_attempts SET " . implode(", ", $fields) . " WHERE id = ?";
    $types .= 'i';
    $vals[] = $id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    if ($stmt->execute()) {
        $stmt->close();
        respond(["status" => true, "message" => "Updated"]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        respond(["status" => false, "message" => $error]);
    }
}

// --------------------
// DELETE: Admin and Recruiter only
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // ✅ Only admin and recruiter can delete attempts
    if (!in_array($user_role, ['admin', 'recruiter'])) {
        respond(["status" => false, "message" => "Only admin and recruiter can delete attempts"]);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) {
        respond(["status" => false, "message" => "Missing id"]);
    }
    $id = (int)$data['id'];

    // ✅ For recruiter, verify they have access to this attempt's student
    if ($user_role === 'recruiter') {
        $checkAccess = $conn->prepare("SELECT student_id FROM skill_attempts WHERE id = ?");
        $checkAccess->bind_param("i", $id);
        $checkAccess->execute();
        $accRes = $checkAccess->get_result();
        if ($accRes->num_rows === 0) {
            $checkAccess->close();
            respond(["status" => false, "message" => "Attempt not found"]);
        }
        $checkAccess->close();
    }

    $stmt = $conn->prepare("DELETE FROM skill_attempts WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        respond(["status" => true, "message" => "Deleted"]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        respond(["status" => false, "message" => $error]);
    }
}

respond(["status" => false, "message" => "Only GET, POST, PUT, DELETE allowed"]);
?>
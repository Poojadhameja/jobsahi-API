<?php
// certificates.php - Issue & Fetch student certificates (Admin, Institute)
require_once '../cors.php';
require_once '../db.php';

// ✅ Define upload folder (absolute + relative)
$upload_dir = __DIR__ . '/../uploads/institute_certificate/';
$relative_path = '/uploads/institute_certificate/';

// ✅ Handle POST request (issue certificate)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ Authenticate Admin or Institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $user_id = intval($decoded['user_id'] ?? 0);

    // ✅ Retrieve form-data
    $student_id   = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $course_id    = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $issue_date   = isset($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');
    $admin_action = isset($_POST['admin_action']) ? $_POST['admin_action'] : 'approved';
    $created_at   = date('Y-m-d H:i:s');
    $modified_at  = date('Y-m-d H:i:s');

    // ✅ Validate required IDs
    if ($student_id <= 0 || $course_id <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Valid Student ID and Course ID are required"
        ]);
        exit();
    }

    // ✅ Handle file upload
    $file_url = "";
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $file_name = 'certificate_' . time() . '.' . $ext;

        // ✅ Allowed extensions
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'csv', 'doc'];
        if (!in_array($ext, $allowed_extensions)) {
            echo json_encode([
                "status" => false,
                "message" => "Invalid file type. Only JPG, JPEG, PNG, CSV, DOC are allowed."
            ]);
            exit();
        }

        // ✅ Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $target_path = $upload_dir . $file_name;
        if (move_uploaded_file($file_tmp, $target_path)) {
            $file_url = $relative_path . $file_name;
        } else {
            echo json_encode(["status" => false, "message" => "File upload failed"]);
            exit();
        }
    } else {
        echo json_encode(["status" => false, "message" => "Certificate file is required"]);
        exit();
    }

    try {
        // ✅ Check duplicate certificate
        $check = $conn->prepare("SELECT id FROM certificates WHERE student_id = ? AND course_id = ?");
        $check->bind_param("ii", $student_id, $course_id);
        $check->execute();
        $res = $check->get_result();
        if ($res->num_rows > 0) {
            echo json_encode([
                "status" => false,
                "message" => "Certificate already exists for this student and course"
            ]);
            exit();
        }

        // ✅ Verify course exists
        $course_check = $conn->prepare("SELECT title FROM courses WHERE id = ?");
        $course_check->bind_param("i", $course_id);
        $course_check->execute();
        $course_result = $course_check->get_result();
        if ($course_result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Invalid course ID"
            ]);
            exit();
        }
        $course_data = $course_result->fetch_assoc();

        // ✅ Insert certificate record
        $stmt = $conn->prepare("INSERT INTO certificates 
            (student_id, course_id, file_url, issue_date, admin_action, created_at, modified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssss", $student_id, $course_id, $file_url, $issue_date, $admin_action, $created_at, $modified_at);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Certificate issued successfully",
                "certificate_id" => $stmt->insert_id,
                "course_title" => $course_data['title'],
                "file_url" => $file_url
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Database insert failed",
                "error" => $stmt->error
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            "status" => false,
            "message" => "Error: " . $e->getMessage()
        ]);
    }

    $conn->close();
    exit();
}

// Default response for non-POST methods
echo json_encode([
    "status" => false,
    "message" => "Method not allowed"
]);
?>

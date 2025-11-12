<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT (Student Only)
    $decoded = authenticateJWT(['student']);
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    // ✅ Allow only POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["success" => false, "message" => "Only POST requests allowed"]);
        exit;
    }

    // ✅ Directory setup
    $upload_dir = __DIR__ . '/../uploads/resume/';
    $relative_path = '/uploads/resume/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // ✅ Allowed extensions
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    // ✅ Check file
    if (empty($_FILES['resume']['name'])) {
        echo json_encode(["success" => false, "message" => "Resume file is required"]);
        exit;
    }

    $fileName = $_FILES['resume']['name'];
    $tmpName = $_FILES['resume']['tmp_name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_extensions)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid file type. Allowed: pdf, jpg, jpeg, png, doc, docx"
        ]);
        exit;
    }

    // ✅ Find student profile
    $stmt = $conn->prepare("SELECT id, resume FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $student = $res->fetch_assoc();
    $stmt->close();

    if (!$student) {
        echo json_encode(["success" => false, "message" => "Student profile not found"]);
        exit;
    }

    $student_id = intval($student['id']);
    $old_resume = $student['resume'] ?? null;

    // ✅ Delete old file if exists
    if (!empty($old_resume)) {
        $old_file = __DIR__ . '/..' . $old_resume;
        if (file_exists($old_file)) unlink($old_file);
    }

    // ✅ Save file with unique student ID
    $safe_name = 'resume_' . $user_id . '.' . $ext;
    $file_path = $upload_dir . $safe_name;
    move_uploaded_file($tmpName, $file_path);

    $resume_relative = $relative_path . $safe_name;

    // ✅ Update database
    $update_sql = "UPDATE student_profiles SET resume = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $resume_relative, $student_id);
    $update_stmt->execute();

    // ✅ Construct full URL for frontend use
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $resume_url = $protocol . $host . "/jobsahi-API/api" . $resume_relative;

    // ✅ Final structured response
    echo json_encode([
        "success" => true,
        "message" => "Resume uploaded successfully",
        "data" => [
            "student_id" => $student_id,
            "user_id" => $user_id,
            "resume_url" => $resume_url,
            "file_name" => basename($resume_url),
            "file_type" => $ext
        ],
        "meta" => [
            "uploaded_at" => date('Y-m-d H:i:s'),
            "api_version" => "1.0",
            "response_format" => "structured"
        ]
    ], JSON_PRETTY_PRINT);

    $update_stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>

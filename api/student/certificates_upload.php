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
    $upload_dir = __DIR__ . '/../uploads/student_certificate/';
    $relative_path = '/uploads/student_certificate/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // ✅ Allowed extensions
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    // ✅ Check file existence
    if (empty($_FILES['certificate']['name'])) {
        echo json_encode(["success" => false, "message" => "Certificate file is required"]);
        exit;
    }

    $fileName = $_FILES['certificate']['name'];
    $tmpName = $_FILES['certificate']['tmp_name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_extensions)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid file type. Allowed: PDF, JPG, JPEG, PNG, DOC, DOCX"
        ]);
        exit;
    }

    // ✅ Get student profile ID
    $stmt = $conn->prepare("SELECT id, certificates FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
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
    $old_certificate = $student['certificates'] ?? null;

    // ✅ Delete old certificate if exists
    if (!empty($old_certificate)) {
        $old_file = __DIR__ . '/..' . $old_certificate;
        if (file_exists($old_file)) unlink($old_file);
    }

    // ✅ Save file as certificate_USERID.ext
    $safe_name = 'certificate_' . $user_id . '.' . $ext;
    $file_path = $upload_dir . $safe_name;

    if (is_uploaded_file($tmpName)) {
        move_uploaded_file($tmpName, $file_path);
    } else {
        rename($tmpName, $file_path);
    }

    $certificate_relative = $relative_path . $safe_name;

    // ✅ Update student profile in DB
    $update_sql = "UPDATE student_profiles 
                   SET certificates = ?, updated_at = NOW() 
                   WHERE id = ? AND deleted_at IS NULL";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $certificate_relative, $student_id);
    $update_stmt->execute();

    // ✅ Build public URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $certificate_url = $protocol . $host . "/jobsahi-API/api" . $certificate_relative;

    // ✅ Final structured response
    echo json_encode([
        "success" => true,
        "message" => "Certificate uploaded successfully",
        "data" => [
            "student_id" => $student_id,
            "user_id" => $user_id,
            "certificate_url" => $certificate_url,
            "file_name" => basename($certificate_url),
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
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>

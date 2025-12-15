<?php
require_once '../cors.php';
require_once '../db.php';
require_once '../helpers/r2_uploader.php'; // ✅ R2 Uploader

try {
    // ✅ Authenticate JWT (Student Only)
    $decoded = authenticateJWT(['student']);
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    // ✅ Allow only POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["success" => false, "message" => "Only POST requests allowed"]);
        exit;
    }

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

    // ✅ Delete old file from R2 if it exists
    if (!empty($old_resume)) {
        // Check if old resume is R2 URL
        if (strpos($old_resume, 'r2.dev') !== false || strpos($old_resume, 'r2.cloudflarestorage.com') !== false) {
            // Extract R2 path from URL
            $parsedUrl = parse_url($old_resume);
            $r2Path = ltrim($parsedUrl['path'], '/');
            
            // Remove bucket name if present in path
            if (strpos($r2Path, 'jobsahi-media/') === 0) {
                $r2Path = str_replace('jobsahi-media/', '', $r2Path);
            }
            
            // Delete from R2
            R2Uploader::deleteFile($r2Path);
        } else {
            // Old local file (backward compatibility)
            $old_file = __DIR__ . '/..' . $old_resume;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
    }

    // ✅ Upload to Cloudflare R2 (Student Profile Resume)
    $r2Path = "student_profile_resume/resume_{$user_id}." . $ext;
    $uploadResult = R2Uploader::uploadFile($tmpName, $r2Path);

    if (!$uploadResult['success']) {
        echo json_encode([
            "success" => false,
            "message" => "Upload failed: " . $uploadResult['message']
        ]);
        exit;
    }

    // ✅ Get R2 public URL
    $r2Url = $uploadResult['url'];

    // ✅ Update database with R2 URL
    $update_sql = "UPDATE student_profiles SET resume = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $r2Url, $student_id);
    $update_stmt->execute();

    // ✅ Final structured response
    echo json_encode([
        "success" => true,
        "message" => "Resume uploaded successfully to R2",
        "data" => [
            "student_id" => $student_id,
            "user_id" => $user_id,
            "resume_url" => $r2Url, // Direct R2 URL
            "file_name" => basename($r2Url),
            "file_type" => $ext
        ],
        "meta" => [
            "uploaded_at" => date('Y-m-d H:i:s'),
            "api_version" => "1.0",
            "response_format" => "structured",
            "storage" => "cloudflare_r2"
        ]
    ], JSON_PRETTY_PRINT);

    $update_stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>

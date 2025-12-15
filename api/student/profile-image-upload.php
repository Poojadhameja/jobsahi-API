<?php
require_once '../cors.php';
require_once '../db.php';
require_once '../helpers/r2_uploader.php'; // ✅ R2 Uploader
require_once '../helpers/r2_path_extractor.php'; // ✅ R2 Path Extractor

try {
    // ✅ Authenticate JWT (Student Only)
    $decoded = authenticateJWT(['student']);
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    // ✅ Allow only POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["success" => false, "message" => "Only POST requests allowed"]);
        exit;
    }

    // ✅ Allowed extensions (only images)
    $allowed_extensions = ['jpg', 'jpeg', 'png'];

    // ✅ Check file existence
    if (empty($_FILES['profile_image']['name'])) {
        echo json_encode(["success" => false, "message" => "Profile image file is required"]);
        exit;
    }

    $fileName = $_FILES['profile_image']['name'];
    $tmpName = $_FILES['profile_image']['tmp_name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_extensions)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid file type. Allowed: JPG, JPEG, PNG"
        ]);
        exit;
    }

    // ✅ Check file size (max 5MB for images)
    $fileSize = $_FILES['profile_image']['size'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($fileSize > $maxSize) {
        echo json_encode([
            "success" => false,
            "message" => "File size exceeds 5MB limit"
        ]);
        exit;
    }

    // ✅ Get student profile ID
    $stmt = $conn->prepare("SELECT id, profile_image FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
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
    $old_profile_image = $student['profile_image'] ?? null;

    // ✅ Delete old profile image from R2 if exists
    if (!empty($old_profile_image)) {
        // Extract R2 path from URL using helper function
        $r2Path = extractProfileImagePath($old_profile_image, $user_id);
        
        if (!empty($r2Path)) {
            // Delete from R2 (don't fail upload if delete fails, just log it)
            $deleteResult = R2Uploader::deleteFile($r2Path);
            if (!$deleteResult['success']) {
                error_log("Failed to delete old R2 file before upload: " . $deleteResult['message'] . " Path: " . $r2Path . " Original URL: " . $old_profile_image);
                // Continue with upload even if delete fails
            } else {
                error_log("Successfully deleted old R2 file before upload: " . $r2Path);
            }
        } else {
            // Old local file (backward compatibility)
            $old_file = __DIR__ . '/../uploads/profile_images/' . basename($old_profile_image);
            if (file_exists($old_file)) {
                @unlink($old_file);
            }
        }
    }

    // ✅ Upload to Cloudflare R2 (Student Profile Image)
    $r2Path = "student_profile_image/profile_{$user_id}." . $ext;
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

    // ✅ Update student profile in DB with R2 URL
    $update_sql = "UPDATE student_profiles 
                   SET profile_image = ?, updated_at = NOW() 
                   WHERE id = ? AND deleted_at IS NULL";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $r2Url, $student_id);
    $update_stmt->execute();

    // ✅ Final structured response
    echo json_encode([
        "success" => true,
        "message" => "Profile image uploaded successfully",
        "data" => [
            "student_id" => $student_id,
            "user_id" => $user_id,
            "profile_image_url" => $r2Url, // Direct R2 URL
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
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>


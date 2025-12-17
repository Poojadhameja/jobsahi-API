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

    // ✅ Check if profile_image column exists
    $check_column_sql = "SELECT COUNT(*) as count 
                         FROM information_schema.COLUMNS 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = 'student_profiles' 
                         AND COLUMN_NAME = 'profile_image'";
    $column_check_result = mysqli_query($conn, $check_column_sql);
    $column_row = mysqli_fetch_assoc($column_check_result);
    $has_profile_image_column = ($column_row['count'] > 0);
    
    if (!$has_profile_image_column) {
        echo json_encode([
            "success" => false,
            "message" => "Profile image column does not exist. Please run migration script first: /api/add_profile_image_column.php"
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
    $profile_image = $student['profile_image'] ?? null;

    $r2DeleteSuccess = false;
    $r2DeleteMessage = '';

    // ✅ Delete profile image from R2 if exists
    if (!empty($profile_image)) {
        // Extract R2 path from URL using helper function
        $r2Path = extractProfileImagePath($profile_image, $user_id);
        
        if (!empty($r2Path)) {
            // Delete from R2
            $deleteResult = R2Uploader::deleteFile($r2Path);
            $r2DeleteSuccess = $deleteResult['success'];
            $r2DeleteMessage = $deleteResult['message'];
            
            if (!$deleteResult['success']) {
                error_log("Failed to delete R2 file: " . $deleteResult['message'] . " Path: " . $r2Path . " Original URL: " . $profile_image);
            } else {
                error_log("Successfully deleted R2 file: " . $r2Path);
            }
        } else {
            // Old local file (backward compatibility)
            $old_file = __DIR__ . '/../uploads/profile_images/' . basename($profile_image);
            if (file_exists($old_file)) {
                @unlink($old_file);
                $r2DeleteSuccess = true;
                $r2DeleteMessage = "Local file deleted";
            }
        }
    }

    // ✅ Update student profile in DB - set profile_image to NULL
    $update_sql = "UPDATE student_profiles 
                   SET profile_image = NULL, updated_at = NOW() 
                   WHERE id = ? AND deleted_at IS NULL";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $student_id);
    $update_stmt->execute();

    // ✅ Final structured response
    $deleteMessage = "Profile image deleted successfully";
    if (!empty($profile_image)) {
        if ($r2DeleteSuccess) {
            $deleteMessage = "Profile image deleted successfully from R2 and database";
        } else if (!empty($r2DeleteMessage)) {
            // Still return success if DB update worked, but note R2 delete issue
            $deleteMessage = "Profile image removed from database. R2 delete note: " . $r2DeleteMessage;
        }
    }
    
    echo json_encode([
        "success" => true,
        "message" => $deleteMessage,
        "data" => [
            "student_id" => $student_id,
            "user_id" => $user_id,
            "r2_deleted" => $r2DeleteSuccess,
        ],
        "meta" => [
            "deleted_at" => date('Y-m-d H:i:s'),
            "api_version" => "1.0",
            "response_format" => "structured",
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


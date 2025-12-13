<?php
// update_course.php - Update existing course (Admin or Institute)
require_once '../cors.php';
require_once '../db.php';
require_once '../helpers/r2_uploader.php'; // ✅ R2 Uploader

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ✅ Authenticate JWT (Admin + Institute)
        $decoded = authenticateJWT(['admin', 'institute']); 
        $role = strtolower($decoded['role'] ?? '');
        $user_id = $decoded['user_id'] ?? ($decoded['id'] ?? null);
        $institute_id = $decoded['institute_id'] ?? $user_id;

        // ✅ Validate Course ID
        $course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($course_id <= 0) {
            echo json_encode(["status" => false, "message" => "Invalid or missing course ID"]);
            exit();
        }

        // ✅ Support both JSON and form-data
        if (isset($_SERVER["CONTENT_TYPE"]) && str_contains($_SERVER["CONTENT_TYPE"], "multipart/form-data")) {
            $data = $_POST;
        } else {
            $data = json_decode(file_get_contents("php://input"), true);
        }

        // ✅ Extract and sanitize fields
        $title        = trim($data['title'] ?? '');
        $description  = trim(strip_tags($data['description'] ?? ''));
        $duration     = trim($data['duration'] ?? '');
        $fee          = floatval($data['fee'] ?? 0);
        $category_id  = intval($data['category_id'] ?? 0);
        $tagged_skills = trim($data['tagged_skills'] ?? '');
        $batch_limit  = intval($data['batch_limit'] ?? 0);
        $status       = trim($data['status'] ?? '');
        $mode         = trim($data['mode'] ?? '');
        $certification_allowed = isset($data['certification_allowed']) ? (int)$data['certification_allowed'] : 0;
        $module_title = trim($data['module_title'] ?? '');
        $module_description = trim(strip_tags($data['module_description'] ?? ''));

        // ✅ Safe media handling (array / string / empty)
        if (isset($data['media'])) {
            if (is_array($data['media'])) {
                $media = json_encode($data['media']);
            } else {
                $media = trim((string)$data['media']);
            }
        } else {
            $media = '';
        }

        // ✅ File upload handling to R2
        $allowedExt = ['jpg', 'jpeg', 'png', 'csv', 'doc'];
        $media_files = [];

        if (!empty($_FILES['media']['name'][0])) {
            foreach ($_FILES['media']['name'] as $key => $name) {
                $tmpName = $_FILES['media']['tmp_name'][$key];
                $error   = $_FILES['media']['error'][$key];
                if ($error === UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExt)) {
                        // ✅ Upload to R2
                        $r2Path = "course_images/" . uniqid("course_", true) . '.' . $ext;
                        $uploadResult = R2Uploader::uploadFile($tmpName, $r2Path);
                        
                        if ($uploadResult['success']) {
                            // Store R2 URL in array
                            $media_files[] = $uploadResult['url'];
                        }
                    }
                }
            }
        }

        // ✅ Merge new uploaded files with existing media (if any)
        if (!empty($media_files)) {
            if (!empty($media)) {
                $existing = json_decode($media, true);
                if (is_array($existing)) {
                    $merged = array_merge($existing, $media_files);
                    $media = json_encode($merged);
                } else {
                    $media = json_encode($media_files);
                }
            } else {
                $media = json_encode($media_files);
            }
        }

        // ✅ Validate required fields
        if (empty($title) || empty($description) || empty($duration) ||
            $fee <= 0 || empty($mode)) {
            echo json_encode(["status" => false, "message" => "All required fields must be filled properly."]);
            exit();
        }

        // ✅ Check if course exists
        $check = $conn->prepare("SELECT institute_id, admin_action FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Course not found"]);
            exit();
        }
        $course = $result->fetch_assoc();
        $current_admin_action = $course['admin_action'];
        $check->close();

        // ✅ Admin can modify admin_action; Institute cannot
        $admin_action = ($role === 'admin')
            ? trim($data['admin_action'] ?? $current_admin_action)
            : $current_admin_action;

        // ✅ Update Query (15 fields - instructor_name removed)
        $stmt = $conn->prepare("
            UPDATE courses 
            SET 
                title = ?, 
                description = ?, 
                duration = ?, 
                fee = ?, 
                category_id = ?, 
                tagged_skills = ?, 
                batch_limit = ?, 
                status = ?, 
                mode = ?, 
                certification_allowed = ?, 
                module_title = ?, 
                module_description = ?, 
                media = ?, 
                admin_action = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssdisississssi",
            $title,
            $description,
            $duration,
            $fee,
            $category_id,
            $tagged_skills,
            $batch_limit,
            $status,
            $mode,
            $certification_allowed,
            $module_title,
            $module_description,
            $media,
            $admin_action,
            $course_id
        );

        // ✅ Execute
        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "✅ Course updated successfully!",
                "course_id" => $course_id,
                "updated_by" => $role,
                "media_saved" => $media_files
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "❌ Failed to update course",
                "error" => $stmt->error
            ]);
        }

        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
    }

    $conn->close();
    exit();
}
?>

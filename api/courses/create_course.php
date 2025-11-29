<?php
require_once '../cors.php';
require_once '../db.php';

$decoded = authenticateJWT(['admin', 'institute']);
$user_role = strtolower($decoded['role'] ?? '');
$user_id   = intval($decoded['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
    exit();
}

if (!in_array($user_role, ['admin', 'institute'])) {
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit();
}

// ✅ Detect JSON or form-data
if (isset($_SERVER["CONTENT_TYPE"]) && str_contains($_SERVER["CONTENT_TYPE"], "multipart/form-data")) {
    $data = $_POST;
} else {
    $data = json_decode(file_get_contents("php://input"), true);
}

if (!$data) {
    echo json_encode(["status" => false, "message" => "Invalid input"]);
    exit();
}

// ✅ Extract fields
$title          = trim($data['title'] ?? '');
$description    = trim($data['description'] ?? '');
$duration       = trim($data['duration'] ?? '');
$category_input = trim($data['category'] ?? '');
$tagged_skills  = trim($data['tagged_skills'] ?? '');
$batch_limit    = intval($data['batch_limit'] ?? 0);
$status         = trim($data['status'] ?? 'active');
$mode           = trim($data['mode'] ?? 'offline');
$certification_allowed = !empty($data['certification_allowed']) ? 1 : 0;
$module_title   = trim($data['module_title'] ?? '');
$module_description = trim($data['module_description'] ?? '');
$fee            = floatval($data['fee'] ?? 0);
$admin_action   = 'pending';

// ✅ Validate required fields
if (empty($title) || empty($description) || empty($duration) || $fee <= 0) {
    echo json_encode(["status" => false, "message" => "Required fields missing"]);
    exit();
}

// ✅ Get institute_id
$institute_id = 0;
if ($user_role === 'institute') {
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $institute_id = $row['id'];
    } else {
        echo json_encode(["status" => false, "message" => "Institute profile not found"]);
        exit();
    }
    $stmt->close();
}

// ✅ Handle media upload
$absoluteUploadPath = "C:\\xampp\\htdocs\\jobsahi-API\\api\\uploads\\institute_course_image";
$relativePathForDb  = "uploads/institute_course_image";
$allowedExt = ['jpg', 'jpeg', 'png', 'csv', 'doc'];
$media_files = [];

if (!is_dir($absoluteUploadPath)) {
    mkdir($absoluteUploadPath, 0777, true);
}

if (!empty($_FILES['media']['name'][0])) {
    foreach ($_FILES['media']['name'] as $key => $name) {
        $tmpName = $_FILES['media']['tmp_name'][$key];
        $error   = $_FILES['media']['error'][$key];

        if ($error === UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExt)) {
                $newName = uniqid("course_", true) . '.' . $ext;
                $targetPath = $absoluteUploadPath . DIRECTORY_SEPARATOR . $newName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $media_files[] = $relativePathForDb . '/' . $newName;
                }
            }
        }
    }
}

$media_json = !empty($media_files) ? json_encode($media_files) : '';

// ✅ Ensure category exists
$category_name = !empty($category_input) ? $category_input : 'Technical';
$category_id = null;

$stmt = $conn->prepare("SELECT id FROM course_category WHERE category_name = ? LIMIT 1");
$stmt->bind_param("s", $category_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $category_id = $row['id'];
    $stmt->free_result();
    $stmt->close();
} else {
    $stmt->close();
    $insertStmt = $conn->prepare("INSERT INTO course_category (category_name) VALUES (?)");
    $insertStmt->bind_param("s", $category_name);
    if ($insertStmt->execute()) {
        $category_id = $insertStmt->insert_id;
    } else {
        echo json_encode(["status" => false, "message" => "Category insert failed", "error" => $insertStmt->error]);
        exit();
    }
    $insertStmt->close();
}

/* ------------------------------------------------------------------
    ✅ FIX ADDED — PREVENT DUPLICATE COURSE TITLE FOR SAME INSTITUTE
    ------------------------------------------------------------------ */
$check = $conn->prepare("
    SELECT id FROM courses 
    WHERE title = ? AND institute_id = ? LIMIT 1
");
$check->bind_param("si", $title, $institute_id);
$check->execute();
$dupResult = $check->get_result();

if ($dupResult && $dupResult->num_rows > 0) {
    echo json_encode([
        "status" => false,
        "message" => "Course already exists for this institute",
        "duplicate" => true
    ]);
    exit();
}
$check->close();
/* ------------------------------------------------------------------ */

// ✅ Insert course record
$stmt = $conn->prepare("
    INSERT INTO courses (
        institute_id, title, description, duration, category_id,
        tagged_skills, batch_limit, status, mode,
        certification_allowed, module_title, module_description,
        media, fee, admin_action, created_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    "isssisississsds",
    $institute_id,
    $title,
    $description,
    $duration,
    $category_id,
    $tagged_skills,
    $batch_limit,
    $status,
    $mode,
    $certification_allowed,
    $module_title,
    $module_description,
    $media_json,
    $fee,
    $admin_action
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Course created successfully",
        "course_id" => $stmt->insert_id,
        "category_id" => $category_id,
        "category_name" => $category_name,
        "media_files" => $media_files
    ]);
} else {
    echo json_encode(["status" => false, "message" => "Insert failed", "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>

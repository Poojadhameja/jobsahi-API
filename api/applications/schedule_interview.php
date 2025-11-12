<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT
    $decoded = authenticateJWT(['admin', 'recruiter']);
    $user_role = strtolower($decoded['role'] ?? '');
    $user_id   = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    // ✅ Allow POST (for FormData upload) and PUT (for JSON or FormData updates)
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'POST' && $method !== 'PUT') {
        echo json_encode(["success" => false, "message" => "Only POST or PUT requests allowed"]);
        exit;
    }

    // ✅ Fetch recruiter_id automatically
    $stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($recruiter_id);
    $stmt->fetch();
    $stmt->close();

    if (!$recruiter_id) {
        echo json_encode(["success" => false, "message" => "Recruiter profile not found"]);
        exit;
    }

    // ✅ Upload folder setup
    $upload_dir = realpath(__DIR__ . '/../uploads/recruiter_logo/') . DIRECTORY_SEPARATOR;
    $relative_path = '/uploads/recruiter_logo/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // ======================================
    // 📌 HANDLE INPUT DATA (POST or PUT)
    // ======================================
    $input = [];
    $file_uploaded = false;
    $contentType = $_SERVER["CONTENT_TYPE"] ?? '';

    /* =====================================================
       ✅ CASE 1: POST — multipart/form-data
    ===================================================== */
    if ($method === 'POST' && strpos($contentType, "multipart/form-data") !== false) {
        $input = $_POST;
    }

    /* =====================================================
       ✅ CASE 2: PUT — multipart/form-data or JSON
    ===================================================== */
    elseif ($method === 'PUT') {
        if (strpos($contentType, "multipart/form-data") !== false) {
            // ✅ Parse PUT FormData manually
            $raw_data = file_get_contents("php://input");
            $boundary = substr($contentType, strpos($contentType, "boundary=") + 9);
            $blocks = preg_split("/-+$boundary/", $raw_data);
            array_pop($blocks);

            foreach ($blocks as $block) {
                if (empty($block)) continue;
                if (strpos($block, 'application/octet-stream') !== false) {
                    preg_match('/name="([^"]*)"; filename="([^"]*)"/', $block, $matches);
                    $name = $matches[1];
                    $filename = $matches[2];
                    preg_match("/Content-Type: (.*)\r\n\r\n/", $block, $typeMatch);
                    $fileType = trim($typeMatch[1]);
                    $fileContent = substr($block, strpos($block, "\r\n\r\n") + 4);
                    $fileContent = substr($fileContent, 0, strlen($fileContent) - 2);
                    $tempFile = tempnam(sys_get_temp_dir(), 'php');
                    file_put_contents($tempFile, $fileContent);
                    $_FILES[$name] = [
                        'name' => $filename,
                        'type' => $fileType,
                        'tmp_name' => $tempFile,
                        'error' => 0,
                        'size' => strlen($fileContent)
                    ];
                } elseif (preg_match('/name="([^"]*)"\r\n\r\n(.*)\r\n/', $block, $matches)) {
                    $input[$matches[1]] = trim($matches[2]);
                }
            }
        } else {
            // ✅ Handle raw JSON
            $raw = file_get_contents("php://input");
            $decoded_json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) $input = $decoded_json;
        }
    }

    // ✅ Handle file upload (POST or PUT)
    if (!empty($_FILES['company_logo']['name'])) {
        $fileName = $_FILES['company_logo']['name'];
        $tmpName = $_FILES['company_logo']['tmp_name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

        if (!in_array($ext, $allowed_exts)) {
            echo json_encode(["success" => false, "message" => "Invalid file type"]);
            exit;
        }

        // ✅ Delete old logo if exists
        $old_stmt = $conn->prepare("SELECT company_logo FROM recruiter_profiles WHERE id = ?");
        $old_stmt->bind_param("i", $recruiter_id);
        $old_stmt->execute();
        $old_result = $old_stmt->get_result();
        if ($old = $old_result->fetch_assoc()) {
            $old_file = realpath(__DIR__ . '/..') . $old['company_logo'];
            if ($old['company_logo'] && file_exists($old_file)) {
                unlink($old_file);
            }
        }
        $old_stmt->close();

        // ✅ Save new file as logo_USERID.ext
        $safe_name = 'logo_' . $user_id . '.' . $ext;
        $file_path = $upload_dir . $safe_name;
        move_uploaded_file($tmpName, $file_path);

        // ✅ Database path for API
        $db_path = $relative_path . $safe_name;
        $input['company_logo'] = $db_path;

        // ✅ Immediately update in DB
        $logo_stmt = $conn->prepare("UPDATE recruiter_profiles SET company_logo = ?, modified_at = NOW() WHERE id = ? AND user_id = ?");
        $logo_stmt->bind_param("sii", $db_path, $recruiter_id, $user_id);
        $logo_stmt->execute();
        $logo_stmt->close();

        $file_uploaded = true;
    }

    // ======================================
    // 📌 PREPARE UPDATE FIELDS
    // ======================================
    $allowed_fields = ['company_name', 'industry', 'website', 'location', 'admin_action'];
    if ($user_role !== 'admin') {
        $allowed_fields = array_diff($allowed_fields, ['admin_action']);
    }

    $update_fields = [];
    $update_values = [];
    $types = '';

    foreach ($allowed_fields as $field) {
        if (!empty($input[$field])) {
            $update_fields[] = "$field = ?";
            $update_values[] = $input[$field];
            $types .= 's';
        }
    }

    if (!empty($update_fields)) {
        $update_fields[] = "modified_at = NOW()";
        $sql = "UPDATE recruiter_profiles 
                SET " . implode(', ', $update_fields) . " 
                WHERE id = ? AND user_id = ? AND deleted_at IS NULL";
        $update_values[] = $recruiter_id;
        $update_values[] = $user_id;
        $types .= 'ii';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$update_values);
        $stmt->execute();
        $stmt->close();
    }

    // ======================================
    // 📌 FETCH UPDATED RECORD
    // ======================================
    $fetch_sql = "SELECT rp.*, u.user_name, u.email, u.phone_number 
                  FROM recruiter_profiles rp
                  INNER JOIN users u ON rp.user_id = u.id
                  WHERE rp.id = ? LIMIT 1";
    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param('i', $recruiter_id);
    $fetch_stmt->execute();
    $result = $fetch_stmt->get_result();
    $profile = $result->fetch_assoc();

    if ($profile) {
        echo json_encode([
            "success" => true,
            "message" => "Recruiter profile updated successfully",
            "data" => [
                "profile_id" => intval($profile['id']),
                "user_id" => intval($profile['user_id']),
                "personal_info" => [
                    "email" => $profile['email'],
                    "user_name" => $profile['user_name'],
                    "phone_number" => $profile['phone_number'],
                    "location" => $profile['location']
                ],
                "professional_info" => [
                    "company_name" => $profile['company_name'],
                    "industry" => $profile['industry'],
                    "website" => $profile['website']
                ],
                "documents" => [
                    "company_logo" => $profile['company_logo'] ? $profile['company_logo'] : null
                ],
                "status" => [
                    "admin_action" => $profile['admin_action'],
                    "created_at" => $profile['created_at'],
                    "modified_at" => $profile['modified_at']
                ]
            ],
            "meta" => [
                "updated_by" => $user_role,
                "timestamp" => date('Y-m-d H:i:s')
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(["success" => false, "message" => "Profile updated but not found"]);
    }

    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>

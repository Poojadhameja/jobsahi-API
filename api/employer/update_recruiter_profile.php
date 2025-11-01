<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT
    $decoded = authenticateJWT(['admin', 'recruiter']);
    $user_role = strtolower($decoded['role'] ?? '');
    $user_id   = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        echo json_encode(["success" => false, "message" => "Only PUT requests allowed"]);
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

    // ✅ Upload folder definitions
    $upload_dir = __DIR__ . '/../uploads/recruiter_logo/';
    $relative_path = '/uploads/recruiter_logo/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // ✅ Parse input for text fields
    $input = [];
    $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
    if (strpos($contentType, "multipart/form-data") !== false) {
        $raw_data = file_get_contents('php://input');
        preg_match_all('/name="([^"]+)"\r\n\r\n(.*?)\r\n--/', $raw_data, $matches);
        foreach ($matches[1] as $i => $field) {
            $input[$field] = trim($matches[2][$i]);
        }
    } else {
        $raw = file_get_contents("php://input");
        $decoded_json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) $input = $decoded_json;
    }

    // ✅ Allowed fields
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

    // ✅ Handle file upload for PUT manually
    $file_uploaded = false;
    if (strpos($contentType, "multipart/form-data") !== false) {
        $boundary = substr($contentType, strpos($contentType, "boundary=") + 9);
        $raw = file_get_contents("php://input");
        $parts = explode("--" . $boundary, $raw);

        foreach ($parts as $part) {
            if (strpos($part, 'name="company_logo"') !== false && preg_match('/filename="([^"]+)"/', $part, $fileMatch)) {
                $fileName = $fileMatch[1];
                $fileData = substr($part, strpos($part, "\r\n\r\n") + 4);
                $fileData = substr($fileData, 0, strrpos($fileData, "\r\n"));

                if (!empty($fileData)) {
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg','jpeg','png','csv','doc'];
                    if (!in_array($ext, $allowed_exts)) {
                        echo json_encode([
                            "success" => false,
                            "message" => "Invalid file type. Allowed: " . implode(', ', $allowed_exts)
                        ]);
                        exit;
                    }

                    // ✅ Delete old logo
                    $old_stmt = $conn->prepare("SELECT company_logo FROM recruiter_profiles WHERE id = ?");
                    $old_stmt->bind_param("i", $recruiter_id);
                    $old_stmt->execute();
                    $old_result = $old_stmt->get_result();
                    if ($old = $old_result->fetch_assoc()) {
                        $old_file = __DIR__ . '/..' . $old['company_logo'];
                        if (file_exists($old_file)) unlink($old_file);
                    }
                    $old_stmt->close();

                    // ✅ Save new logo
                    $safe_name = 'logo_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $file_path = $upload_dir . $safe_name;
                    file_put_contents($file_path, $fileData);

                    $db_path = $relative_path . $safe_name;
                    $update_fields[] = "company_logo = ?";
                    $update_values[] = $db_path;
                    $types .= 's';
                    $file_uploaded = true;
                }
            }
        }
    }

    if (empty($update_fields) && !$file_uploaded) {
        echo json_encode(["success" => false, "message" => "No valid fields to update"]);
        exit;
    }

    $update_fields[] = "modified_at = NOW()";

    // ✅ Execute Update Query
    $sql = "UPDATE recruiter_profiles 
            SET " . implode(', ', $update_fields) . " 
            WHERE id = ? AND user_id = ? AND deleted_at IS NULL";
    $update_values[] = $recruiter_id;
    $update_values[] = $user_id;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$update_values);
    $stmt->execute();

    // ✅ Fetch Updated Record
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
                    "company_logo" => $profile['company_logo'] ?? null
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

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>

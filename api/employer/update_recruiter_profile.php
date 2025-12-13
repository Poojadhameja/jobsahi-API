<?php
require_once '../cors.php';
require_once '../db.php';
require_once '../helpers/r2_uploader.php'; // ✅ R2 Uploader

try {
    // ✅ Authenticate JWT (Admin / Recruiter)
    $decoded = authenticateJWT(['admin', 'recruiter']);
    $user_role = strtolower($decoded['role'] ?? '');
    $user_id   = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    // ---------------------------------------------------------
    // Only POST/PUT allowed
    // ---------------------------------------------------------
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'POST' && $method !== 'PUT') {
        echo json_encode(["success" => false, "message" => "Only POST or PUT allowed"]);
        exit;
    }

    // ---------------------------------------------------------
    // Fetch recruiter profile for this user
    // ---------------------------------------------------------
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

    // ---------------------------------------------------------
    // Upload folder
    // ---------------------------------------------------------
    $upload_dir = __DIR__ . '/../uploads/recruiter_logo/';
    $relative_path = '/uploads/recruiter_logo/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $input = [];
    $contentType = $_SERVER["CONTENT_TYPE"] ?? "";

    // ---------------------------------------------------------
    // POST — multipart/form-data
    // ---------------------------------------------------------
    if ($method === 'POST' && strpos($contentType, "multipart/form-data") !== false) {
        $input = $_POST;
    }

    // ---------------------------------------------------------
    // PUT — multipart/form-data OR JSON
    // ---------------------------------------------------------
    elseif ($method === 'PUT') {

        if (strpos($contentType, "multipart/form-data") !== false) {

            // Manual parsing for PUT multipart
            $raw_data = file_get_contents("php://input");
            $boundary = substr($contentType, strpos($contentType, "boundary=") + 9);
            $blocks   = preg_split("/-+$boundary/", $raw_data);
            array_pop($blocks);

            foreach ($blocks as $block) {
                if (empty(trim($block))) continue;

                // FILE FIELD
                if (strpos($block, 'filename=') !== false) {

                    preg_match('/name="([^"]*)"; filename="([^"]*)"/', $block, $m);
                    if (!isset($m[1]) || !isset($m[2])) continue;

                    $name = $m[1];
                    $filename = $m[2];

                    preg_match("/Content-Type: (.*)\r\n\r\n/", $block, $typeMatch);
                    $mime = trim($typeMatch[1] ?? 'application/octet-stream');

                    $fileContent = substr($block, strpos($block, "\r\n\r\n") + 4);
                    $fileContent = rtrim($fileContent, "\r\n");

                    $tempFile = tempnam(sys_get_temp_dir(), 'php');
                    file_put_contents($tempFile, $fileContent);

                    $_FILES[$name] = [
                        'name' => $filename,
                        'type' => $mime,
                        'tmp_name' => $tempFile,
                        'error' => 0,
                        'size' => strlen($fileContent)
                    ];

                } elseif (preg_match('/name="([^"]*)"\r\n\r\n(.*)\r\n/', $block, $m)) {
                    $input[$m[1]] = trim($m[2]);
                }
            }

        } else {
            // JSON body
            $raw = file_get_contents("php://input");
            $decoded_json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) $input = $decoded_json;
        }
    }

    // ---------------------------------------------------------
    // Handle company_logo upload to R2
    // ---------------------------------------------------------
    if (!empty($_FILES['company_logo']['name'])) {

        $fileName = $_FILES['company_logo']['name'];
        $tmp = $_FILES['company_logo']['tmp_name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(["success" => false, "message" => "Invalid logo format"]);
            exit;
        }

        // ✅ Delete old logo from R2 if exists
        $old_stmt = $conn->prepare("SELECT company_logo FROM recruiter_profiles WHERE id = ?");
        $old_stmt->bind_param("i", $recruiter_id);
        $old_stmt->execute();
        $old_res = $old_stmt->get_result();
        if ($old = $old_res->fetch_assoc()) {
            if (!empty($old['company_logo'])) {
                $old_logo = $old['company_logo'];
                
                // Check if old logo is R2 URL
                if (strpos($old_logo, 'r2.dev') !== false || strpos($old_logo, 'r2.cloudflarestorage.com') !== false) {
                    // Extract R2 path from URL
                    $parsedUrl = parse_url($old_logo);
                    $r2Path = ltrim($parsedUrl['path'], '/');
                    
                    // Remove bucket name if present
                    if (strpos($r2Path, 'jobsahi-media/') === 0) {
                        $r2Path = str_replace('jobsahi-media/', '', $r2Path);
                    }
                    
                    // Delete from R2
                    R2Uploader::deleteFile($r2Path);
                } else {
                    // Old local file (backward compatibility)
                    $old_file = __DIR__ . '/..' . $old_logo;
                    if (file_exists($old_file)) unlink($old_file);
                }
            }
        }
        $old_stmt->close();

        // ✅ Upload to R2
        $r2Path = "company_logos/logo_{$user_id}." . $ext;
        $uploadResult = R2Uploader::uploadFile($tmp, $r2Path);

        if (!$uploadResult['success']) {
            echo json_encode([
                "success" => false,
                "message" => "Logo upload failed: " . $uploadResult['message']
            ]);
            exit;
        }

        // ✅ Use R2 URL
        $input['company_logo'] = $uploadResult['url'];
    }

    // ---------------------------------------------------------
    // Allowed fields (MATCHING DATABASE)
    // ---------------------------------------------------------
    $allowed_fields = [
        'company_name',
        'industry',
        'website',
        'location',
        'gst_pan',
        'company_logo'
    ];


    // ---------------------------------------------------------
    // Build update query
    // ---------------------------------------------------------
    $update_fields = [];
    $update_values = [];
    $types = '';

    foreach ($allowed_fields as $f) {
        if (isset($input[$f]) && $input[$f] !== "") {
            $update_fields[] = "$f = ?";
            $update_values[] = $input[$f];
            $types .= 's';
        }
    }

    if (empty($update_fields)) {
        echo json_encode(["success" => false, "message" => "No valid fields to update"]);
        exit;
    }

    $update_fields[] = "modified_at = NOW()";
    $sql = "UPDATE recruiter_profiles SET " . implode(', ', $update_fields) . " 
            WHERE id = ? AND user_id = ? AND deleted_at IS NULL";

    $update_values[] = $recruiter_id;
    $update_values[] = $user_id;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$update_values);
    $stmt->execute();
    $stmt->close();

    /* ******************************************************
       🔥 AUTO-SYNC LOGIC ADDED (NO OTHER CODE CHANGED)
    *********************************************************/

    // If company_name is updated → update user_name also
    if (isset($input['company_name']) && $input['company_name'] !== "") {

        $newName = $input['company_name'];

        $sync = $conn->prepare("UPDATE users SET user_name = ? WHERE id = ?");
        $sync->bind_param("si", $newName, $user_id);
        $sync->execute();
        $sync->close();
    }

    // 🔥 UPDATE EMAIL AND PHONE_NUMBER IN USERS TABLE
    $user_updates = [];
    $user_values = [];
    $user_types = '';

    // Update email if provided
    if (isset($input['email']) && !empty(trim($input['email']))) {
        $email = trim($input['email']);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["success" => false, "message" => "Invalid email format"]);
            exit;
        }

        // Check if email already exists for another user
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        $check_email->store_result();
        if ($check_email->num_rows > 0) {
            echo json_encode(["success" => false, "message" => "Email already exists"]);
            exit;
        }
        $check_email->close();

        $user_updates[] = "email = ?";
        $user_values[] = $email;
        $user_types .= 's';
    }

    // Update phone_number if provided
    if (isset($input['phone_number']) && !empty(trim($input['phone_number']))) {
        $phone_number = trim($input['phone_number']);
        
        $user_updates[] = "phone_number = ?";
        $user_values[] = $phone_number;
        $user_types .= 's';
    }

    // Update users table if there are fields to update
    if (!empty($user_updates)) {
        $user_updates[] = "updated_at = NOW()";
        $sql_user = "UPDATE users SET " . implode(', ', $user_updates) . " WHERE id = ?";
        $user_values[] = $user_id;
        $user_types .= 'i';

        $user_stmt = $conn->prepare($sql_user);
        $user_stmt->bind_param($user_types, ...$user_values);
        $user_stmt->execute();
        $user_stmt->close();
    }

    // If POST (insert) and user_name exists → auto insert into company_name (only if not given)
    if ($method === "POST") {

        $userRow = $conn->query("SELECT user_name FROM users WHERE id = $user_id")->fetch_assoc();
        $autoName = $userRow['user_name'] ?? '';

        if (!empty($autoName) && empty($input['company_name'])) {
            $conn->query("UPDATE recruiter_profiles SET company_name = '$autoName' WHERE id = $recruiter_id");
        }
    }

    /* ******************************************************/

    // ---------------------------------------------------------
    // Fetch updated profile
    // ---------------------------------------------------------
    $fetch_sql = "SELECT rp.*, u.email, u.user_name, u.phone_number 
                  FROM recruiter_profiles rp
                  INNER JOIN users u ON rp.user_id = u.id
                  WHERE rp.id = ? LIMIT 1";

    $fetch = $conn->prepare($fetch_sql);
    $fetch->bind_param("i", $recruiter_id);
    $fetch->execute();
    $profile = $fetch->get_result()->fetch_assoc();

    // ---------------------------------------------------------
    // Build final response EXACTLY AS PER TABLE
    // ---------------------------------------------------------
    echo json_encode([
        "success" => true,
        "message" => "Recruiter profile updated successfully",
        "data" => [
            "profile_id" => intval($profile['id']),
            "user_id"    => intval($profile['user_id']),

            "personal_info" => [
                "email"        => $profile['email'],
                "user_name"    => $profile['user_name'],
                "phone_number" => $profile['phone_number']
            ],

            "professional_info" => [
                "company_name" => $profile['company_name'],
                "industry"     => $profile['industry'],
                "website"      => $profile['website'],
                "location"     => $profile['location'],
                "gst_pan"      => $profile['gst_pan']
            ],

            "documents" => [
                "company_logo" => $profile['company_logo']
            ],

            "status" => [
                "created_at"   => $profile['created_at'],
                "modified_at"  => $profile['modified_at']
            ]
        ],

        "meta" => [
            "updated_by" => $user_role,
            "timestamp"  => date("Y-m-d H:i:s")
        ]

    ], JSON_PRETTY_PRINT);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>

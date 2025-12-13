<?php 
require_once '../cors.php';
require_once '../db.php';
require_once '../helpers/r2_uploader.php'; // ✅ R2 Uploader

try {
    // ✅ Authenticate JWT (Admin / Institute)
    $decoded   = authenticateJWT(['admin', 'institute']);
    $user_role = strtolower($decoded['role']);
    $user_id   = intval($decoded['user_id'] ?? 0);

    // ✅ Allow POST or PUT
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'POST' && $method !== 'PUT') {
        echo json_encode(["success" => false, "message" => "Only POST or PUT requests allowed"]);
        exit;
    }

    // Fetch institute profile id
    $stmt = $conn->prepare("SELECT id, institute_logo, institute_name FROM institute_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($profile_id, $old_logo, $old_institute_name);
    $stmt->fetch();
    $stmt->close();

    if ($method === 'POST' && $profile_id) {
        echo json_encode(["success" => false, "message" => "Institute profile already exists"]);
        exit;
    }

    if ($method === 'PUT' && !$profile_id) {
        echo json_encode(["success" => false, "message" => "Institute profile not found"]);
        exit;
    }

    // Upload Folder
    $upload_dir    = __DIR__ . '/../uploads/institute_logo/';
    $relative_path = '/uploads/institute_logo/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $input         = [];
    $contentType   = $_SERVER["CONTENT_TYPE"] ?? '';
    $file_uploaded = false;

    // POST
    if ($method === 'POST' && strpos($contentType, "multipart/form-data") !== false) {
        $input = $_POST;
    }
    // PUT JSON + multipart parsing
    elseif ($method === 'PUT') {
        if (strpos($contentType, "multipart/form-data") !== false) {

            $raw_data = file_get_contents("php://input");
            $boundary = substr($contentType, strpos($contentType, "boundary=") + 9);
            $blocks   = preg_split("/-+$boundary/", $raw_data);
            array_pop($blocks);

            foreach ($blocks as $block) {
                if (empty(trim($block))) continue;

                if (
                    strpos($block, 'application/octet-stream') !== false ||
                    strpos($block, 'Content-Type: image') !== false
                ) {
                    preg_match('/name="([^"]*)"; filename="([^"]*)"/', $block, $matches);
                    if (!isset($matches[1]) || !isset($matches[2])) continue;

                    $name     = $matches[1];
                    $filename = $matches[2];

                    preg_match("/Content-Type: (.*)\r\n\r\n/", $block, $typeMatch);
                    $fileType = trim($typeMatch[1] ?? 'application/octet-stream');
                    $fileContent = substr($block, strpos($block, "\r\n\r\n") + 4);
                    $fileContent = substr($fileContent, 0, strlen($fileContent) - 2);

                    $tmp = tempnam(sys_get_temp_dir(), 'php');
                    file_put_contents($tmp, $fileContent);

                    $_FILES[$name] = [
                        'name'     => $filename,
                        'type'     => $fileType,
                        'tmp_name' => $tmp,
                        'error'    => 0,
                        'size'     => strlen($fileContent)
                    ];

                } elseif (preg_match('/name="([^"]*)"\r\n\r\n(.*)\r\n/', $block, $matches)) {
                    $input[$matches[1]] = trim($matches[2]);
                }
            }
        } else {
            $raw = file_get_contents("php://input");
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) $input = $json;
        }
    }

    /* =====================================================
       🔥 LOGO UPLOAD TO R2
    ===================================================== */
    if (!empty($_FILES['institute_logo']['name'])) {
        $file = $_FILES['institute_logo'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

        if (!in_array($ext, $allowed)) {
            echo json_encode(["success" => false, "message" => "Invalid image format"]);
            exit;
        }

        // ✅ Delete old logo from R2 if exists
        if (!empty($old_logo)) {
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
                $old_file = __DIR__ . '/../' . ltrim($old_logo, '/');
                if (file_exists($old_file)) unlink($old_file);
            }
        }

        // ✅ Upload to R2
        $r2Path = "institute_logos/logo_{$user_id}_" . time() . '.' . $ext;
        $uploadResult = R2Uploader::uploadFile($file['tmp_name'], $r2Path);

        if (!$uploadResult['success']) {
            echo json_encode([
                "success" => false,
                "message" => "Logo upload failed: " . $uploadResult['message']
            ]);
            exit;
        }

        // ✅ Use R2 URL
        $input['institute_logo'] = $uploadResult['url'];
        $file_uploaded = true;
    }

    /* =====================================================
       🔥 NEW RULE: user_name AND institute_name MUST MATCH
    ===================================================== */

    if (!empty($input['institute_name'])) {
        $input['user_name'] = $input['institute_name'];
    }

    if (!empty($input['user_name'])) {
        $input['institute_name'] = $input['user_name'];
    }

    // ================================
    // UPDATE users table
    // ================================
    $user_updates = [];
    $user_vals    = [];
    $user_types   = '';

    if (isset($input['email']) && !empty($input['email'])) {
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["success" => false, "message" => "Invalid email format"]);
            exit;
        }

        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $check_email->bind_param("si", $input['email'], $user_id);
        $check_email->execute();
        $check_email->store_result();
        if ($check_email->num_rows > 0) {
            echo json_encode(["success" => false, "message" => "Email already exists"]);
            exit;
        }
        $check_email->close();

        $user_updates[] = "email = ?";
        $user_vals[]    = $input['email'];
        $user_types    .= 's';
    }

    if (!empty($input['user_name'])) {
        $user_updates[] = "user_name = ?";
        $user_vals[]    = $input['user_name'];
        $user_types    .= 's';
    }

    if (isset($input['phone_number']) && !empty($input['phone_number'])) {
        $user_updates[] = "phone_number = ?";
        $user_vals[]    = $input['phone_number'];
        $user_types    .= 's';
    }

    if (!empty($user_updates)) {
        $user_updates[] = "updated_at = NOW()";
        $sql_user = "UPDATE users SET ".implode(', ', $user_updates)." WHERE id = ?";
        $user_vals[] = $user_id;
        $user_types .= 'i';

        $stmt = $conn->prepare($sql_user);
        $stmt->bind_param($user_types, ...$user_vals);
        $stmt->execute();
        $stmt->close();
    }

    /* ================================
       UPDATE institute_profiles
    ================================ */
    $allowed_fields = [
        'institute_name', 'registration_number', 'institute_logo',
        'institute_type', 'website', 'description', 
        'address', 'postal_code',
        'contact_person', 'contact_designation',
        'accreditation', 'established_year'
    ];

    if ($method === 'PUT') {
        $updates = [];
        $vals    = [];
        $types   = '';

        foreach ($allowed_fields as $f) {
            if (isset($input[$f]) && $input[$f] !== "") {
                $updates[] = "$f = ?";
                $vals[]    = $input[$f];
                $types    .= 's';
            }
        }

        if (!empty($updates)) {
            $updates[] = "modified_at = NOW()";
            $sql = "UPDATE institute_profiles SET ".implode(', ', $updates)." 
                    WHERE id = ? AND user_id = ?";
            $vals[] = $profile_id;
            $vals[] = $user_id;
            $types .= 'ii';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$vals);
            $stmt->execute();
            $stmt->close();
        }
    }

    /* ================================
       Fetch updated profile
    ================================ */
    $fetch = $conn->prepare("SELECT p.*, u.email, u.user_name, u.phone_number 
                             FROM institute_profiles p
                             INNER JOIN users u ON p.user_id = u.id
                             WHERE p.id = ? LIMIT 1");
    $fetch->bind_param("i", $profile_id);
    $fetch->execute();
    $profile = $fetch->get_result()->fetch_assoc();
    $fetch->close();

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host     = $_SERVER['HTTP_HOST'];

    $logo_full_url = !empty($profile['institute_logo'])
        ? $protocol . $host . "/jobsahi-API/api" . $profile['institute_logo']
        : null;

    echo json_encode([
        "success" => true,
        "message" => "Institute profile updated successfully",
        "data" => [
            "profile_id" => intval($profile['id']),
            "user_id"    => intval($profile['user_id']),
            "personal_info" => [
                "email"        => $profile['email'],
                "user_name"    => $profile['user_name'],
                "phone_number" => $profile['phone_number']
            ],
            "institute_info" => [
                "institute_name"      => $profile['institute_name'],
                "registration_number" => $profile['registration_number'],
                "institute_logo"      => $logo_full_url,
                "institute_type"      => $profile['institute_type'],
                "website"             => $profile['website'],
                "description"         => $profile['description'],
                "accreditation"       => $profile['accreditation'],
                "established_year"    => $profile['established_year']
            ],
            "contact_info" => [
                "address"            => $profile['address'],
                "postal_code"        => $profile['postal_code'],
                "contact_person"     => $profile['contact_person'],
                "contact_designation"=> $profile['contact_designation']
            ],
            "status" => [
                "created_at"   => $profile['created_at'],
                "modified_at"  => $profile['modified_at']
            ]
        ],
        "meta" => [
            "updated_by" => $user_role,
            "timestamp"  => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>

<?php
require_once '../cors.php';
require_once '../db.php';

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

    // ✅ Fetch institute profile id (if any)
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($profile_id);
    $stmt->fetch();
    $stmt->close();

    // 🎯 NEW: Different behaviour for POST vs PUT
    if ($method === 'POST') {
        // For POST → if profile already exists, BLOCK it
        if ($profile_id) {
            echo json_encode([
                "success" => false,
                "message" => "Institute profile already exists for this user"
            ]);
            exit;
        }
        // For POST with no profile → we will CREATE below
    } else { // PUT
        // For PUT → must have existing profile
        if (!$profile_id) {
            echo json_encode(["success" => false, "message" => "Institute profile not found"]);
            exit;
        }
    }

    // ✅ Upload folder setup
    $upload_dir    = __DIR__ . '/../uploads/institute_logo/';
    $relative_path = '/uploads/institute_logo/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $input         = [];
    $file_uploaded = false;
    $contentType   = $_SERVER["CONTENT_TYPE"] ?? '';

    /* =====================================================
       ✅ CASE 1: POST — multipart/form-data
    ===================================================== */
    if ($method === 'POST' && strpos($contentType, "multipart/form-data") !== false) {
        $input = $_POST;
    }

    /* =====================================================
       ✅ CASE 2: PUT — supports multipart/form-data or JSON
    ===================================================== */
    elseif ($method === 'PUT') {
        if (strpos($contentType, "multipart/form-data") !== false) {

            // Parse multipart
            $raw_data = file_get_contents("php://input");
            $boundary = substr($contentType, strpos($contentType, "boundary=") + 9);
            $blocks   = preg_split("/-+$boundary/", $raw_data);
            array_pop($blocks);

            foreach ($blocks as $block) {
                if (empty($block)) continue;

                if (
                    strpos($block, 'application/octet-stream') !== false ||
                    strpos($block, 'Content-Type: image') !== false
                ) {

                    preg_match('/name="([^"]*)"; filename="([^"]*)"/', $block, $matches);
                    if (!isset($matches[1]) || !isset($matches[2])) continue;
                    $name     = $matches[1];
                    $filename = $matches[2];

                    preg_match("/Content-Type: (.*)\r\n\r\n/", $block, $typeMatch);
                    $fileType    = trim($typeMatch[1] ?? 'application/octet-stream');
                    $fileContent = substr($block, strpos($block, "\r\n\r\n") + 4);
                    $fileContent = substr($fileContent, 0, strlen($fileContent) - 2);

                    $tempFile = tempnam(sys_get_temp_dir(), 'php');
                    file_put_contents($tempFile, $fileContent);

                    $_FILES[$name] = [
                        'name'     => $filename,
                        'type'     => $fileType,
                        'tmp_name' => $tempFile,
                        'error'    => 0,
                        'size'     => strlen($fileContent)
                    ];

                } elseif (preg_match('/name="([^"]*)"\r\n\r\n(.*)\r\n/', $block, $matches)) {
                    $input[$matches[1]] = trim($matches[2]);
                }
            }

        } else {
            // JSON Body
            $raw          = file_get_contents("php://input");
            $decoded_json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) $input = $decoded_json;
        }
    }

    /* =====================================================
       ✅ Handle institute_logo upload  (UNCHANGED)
    ===================================================== */
    if (!empty($_FILES['institute_logo']['name'])) {
        $fileName = $_FILES['institute_logo']['name'];
        $tmpName  = $_FILES['institute_logo']['tmp_name'];
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

        if (!in_array($ext, $allowed_exts)) {
            echo json_encode(["success" => false, "message" => "Invalid file type"]);
            exit;
        }

        // ❗ Only delete old file for PUT (when profile already exists)
        if ($method === 'PUT' && $profile_id) {
            $old_stmt = $conn->prepare("SELECT institute_logo FROM institute_profiles WHERE id = ?");
            $old_stmt->bind_param("i", $profile_id);
            $old_stmt->execute();
            $old_result = $old_stmt->get_result();
            if ($old = $old_result->fetch_assoc()) {
                if (!empty($old['institute_logo'])) {
                    $old_file = __DIR__ . '/..' . $old['institute_logo'];
                    if (file_exists($old_file)) unlink($old_file);
                }
            }
            $old_stmt->close();
        }

        // Save new file
        $safe_name = 'logo_' . $user_id . '.' . $ext;
        $file_path = $upload_dir . $safe_name;

        if (is_uploaded_file($tmpName)) {
            move_uploaded_file($tmpName, $file_path);
        } else {
            // from PUT manual parsing
            rename($tmpName, $file_path);
        }

        $input['institute_logo'] = $relative_path . $safe_name;
        $file_uploaded           = true;
    }

    /* =====================================================
       ✅ Common allowed fields
    ===================================================== */

    $allowed_fields = [
        'institute_name',
        'registration_number',
        'institute_logo',
        'institute_type',
        'website',
        'description',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'contact_person',
        'contact_designation',
        'accreditation',
        'established_year',
        'location',
        'courses_offered'
    ];

    if ($user_role === 'admin') {
        $allowed_fields[] = 'admin_action';
    }

    /* =====================================================
       ✅ DB OPERATION: INSERT for POST, UPDATE for PUT
    ===================================================== */

    if ($method === 'PUT') {
        // ---------- UPDATE EXISTING PROFILE ----------
        $update_fields = [];
        $update_values = [];
        $types         = '';

        foreach ($allowed_fields as $field) {
            if (isset($input[$field]) && $input[$field] !== "") {
                $update_fields[]  = "$field = ?";
                $update_values[]  = $input[$field];
                $types           .= 's';
            }
        }

        if (empty($update_fields)) {
            echo json_encode(["success" => false, "message" => "No valid fields to update"]);
            exit;
        }

        $update_fields[] = "modified_at = NOW()";

        $sql = "UPDATE institute_profiles 
                SET " . implode(', ', $update_fields) . "
                WHERE id = ? AND user_id = ? AND deleted_at IS NULL";

        $update_values[] = $profile_id;
        $update_values[] = $user_id;
        $types          .= 'ii';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$update_values);
        $stmt->execute();

    } else {
        // ---------- CREATE NEW PROFILE (POST) ----------
        $columns      = ['user_id'];
        $placeholders = ['?'];
        $values       = [$user_id];
        $types        = 'i';

        foreach ($allowed_fields as $field) {
            if (isset($input[$field]) && $input[$field] !== "") {
                $columns[]      = $field;
                $placeholders[] = '?';
                $values[]       = $input[$field];
                $types         .= 's';
            }
        }

        if (count($columns) === 1) { // only user_id present
            echo json_encode(["success" => false, "message" => "No valid fields to create profile"]);
            exit;
        }

        $sql = "INSERT INTO institute_profiles (" . implode(', ', $columns) . ", created_at, modified_at)
                VALUES (" . implode(', ', $placeholders) . ", NOW(), NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();

        // NEW profile id
        $profile_id = $conn->insert_id;
    }

    /* =====================================================
       ✅ Fetch created/updated profile (UNCHANGED)
    ===================================================== */
    $fetch_sql = "SELECT p.*, u.email, u.user_name, u.phone_number 
                  FROM institute_profiles p
                  INNER JOIN users u ON p.user_id = u.id
                  WHERE p.id = ? LIMIT 1";

    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param('i', $profile_id);
    $fetch_stmt->execute();
    $result  = $fetch_stmt->get_result();
    $profile = $result->fetch_assoc();

    if ($profile) {
        echo json_encode([
            "success" => true,
            "message" => ($method === 'POST')
                ? "Institute profile created successfully"
                : "Institute profile updated successfully",
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
                    "institute_logo"      => $profile['institute_logo'],
                    "institute_type"      => $profile['institute_type'],
                    "website"             => $profile['website'],
                    "description"         => $profile['description'],
                    "accreditation"       => $profile['accreditation'],
                    "established_year"    => $profile['established_year'],
                    "courses_offered"     => $profile['courses_offered']
                ],

                "contact_info" => [
                    "address"            => $profile['address'],
                    "city"               => $profile['city'],
                    "state"              => $profile['state'],
                    "country"            => $profile['country'],
                    "postal_code"        => $profile['postal_code'],
                    "contact_person"     => $profile['contact_person'],
                    "contact_designation"=> $profile['contact_designation'],
                    "location"           => $profile['location']
                ],

                "status" => [
                    "admin_action" => $profile['admin_action'],
                    "created_at"   => $profile['created_at'],
                    "modified_at"  => $profile['modified_at']
                ]
            ],
            "meta" => [
                "updated_by" => $user_role,
                "timestamp"  => date('Y-m-d H:i:s')
            ]
        ], JSON_PRETTY_PRINT);

    } else {
        echo json_encode(["success" => false, "message" => "Profile saved but not found"]);
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>

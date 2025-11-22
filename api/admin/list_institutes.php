<?php
require_once '../cors.php';

// Only admin allowed
$decoded = authenticateJWT(['admin']);
$admin_id = $decoded['user_id'];

try {
    // Base institute list query
    $stmt = $conn->prepare("
        SELECT 
            u.id as user_id,
            u.user_name,
            u.email,
            u.phone_number,

            ip.id as institute_id,
            ip.institute_name,
            ip.registration_number,      -- ⭐ ADDED
            ip.institute_type,
            ip.institute_logo,
            ip.website,
            ip.description,              -- ⭐ ALREADY THERE
            ip.address,
            ip.postal_code,              -- ⭐ ADDED
            ip.contact_person,           -- ⭐ ADDED
            ip.contact_designation,      -- ⭐ ADDED
            ip.accreditation,
            ip.established_year,         -- ⭐ ADDED
            ip.admin_action,
            ip.created_at as profile_created_at,
            ip.modified_at as profile_modified_at,
            ip.deleted_at as profile_deleted_at

        FROM users u
        LEFT JOIN institute_profiles ip ON u.id = ip.user_id
        WHERE u.role = 'institute'
        ORDER BY u.id DESC
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    $institutes = [];

    // URL base for logos
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $logo_base = '/jobsahi-API/api/uploads/institute_logo/';

    while ($row = $result->fetch_assoc()) {

        $institute_id = $row['institute_id'];

        /* ====================================================
           FETCH COURSE LIST FOR THIS INSTITUTE
        ==================================================== */
        $course_stmt = $conn->prepare("
            SELECT 
                id, 
                title, 
                description,
                duration,
                category_id,           -- ⭐ ADDED (your DB shows this)
                tagged_skills,
                batch_limit,           -- ⭐ ADDED
                status,
                instructor_name,       -- ⭐ ADDED
                mode,
                certification_allowed
            FROM courses
            WHERE institute_id = ?
            ORDER BY id DESC
        ");
        $course_stmt->bind_param("i", $institute_id);
        $course_stmt->execute();
        $courses_res = $course_stmt->get_result();

        $courses = [];
        while ($c = $courses_res->fetch_assoc()) {
            $courses[] = [
                "course_id" => $c["id"],
                "title" => $c["title"],
                "description" => $c["description"],
                "duration" => $c["duration"],
                "category_id" => $c["category_id"],      // ⭐ ADDED
                "tagged_skills" => $c["tagged_skills"],
                "batch_limit" => $c["batch_limit"],      // ⭐ ADDED
                "status" => $c["status"],
                "instructor_name" => $c["instructor_name"],  // ⭐ ADDED
                "mode" => $c["mode"],
                "certification_allowed" => $c["certification_allowed"]
            ];
        }
        $course_stmt->close();

        /* ====================================================
           FIX LOGO PATH
        ==================================================== */
        if (!empty($row['institute_logo'])) {
            $clean_logo = str_replace(
                ["\\", "/uploads/institute_logo/", "./", "../"],
                "",
                $row['institute_logo']
            );
            $logo_local = __DIR__ . '/../uploads/institute_logo/' . $clean_logo;

            if (file_exists($logo_local)) {
                $row['institute_logo'] = $protocol . $host . $logo_base . $clean_logo;
            }
        }

        $institutes[] = [
            'user_info' => [
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'],
                'email' => $row['email'],
                'phone_number' => $row['phone_number'],
            ],
            'profile_info' => [
                'institute_id' => $row['institute_id'],
                'institute_name' => $row['institute_name'],
                'registration_number' => $row['registration_number'],    // ⭐ ADDED
                'institute_type' => $row['institute_type'],
                'institute_logo' => $row['institute_logo'],
                'website' => $row['website'],
                'description' => $row['description'],                    // ⭐ REQUIRED & present
                'address' => $row['address'],
                'postal_code' => $row['postal_code'],                    // ⭐ ADDED
                'contact_person' => $row['contact_person'],              // ⭐ ADDED
                'contact_designation' => $row['contact_designation'],    // ⭐ ADDED
                'accreditation' => $row['accreditation'],
                'established_year' => $row['established_year'],          // ⭐ ADDED
                'admin_action' => $row['admin_action'],
                'created_at' => $row['profile_created_at'],
                'modified_at' => $row['profile_modified_at'],
                'deleted_at' => $row['profile_deleted_at']
            ],
            'courses' => $courses
        ];
    }

    echo json_encode([
        "status" => true,
        "message" => "Institutes retrieved successfully",
        "count" => count($institutes),
        "data" => $institutes
    ]);

} catch (Exception $e) {

    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

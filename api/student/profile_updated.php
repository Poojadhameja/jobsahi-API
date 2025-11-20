<?php
require_once '../cors.php';
require_once '../db.php'; // ✅ DB connection

// ✅ Authenticate JWT (allowed roles: admin, student only)
$current_user = authenticateJWT(['admin', 'student']);
$user_role = strtolower($current_user['role']);
$user_id = $current_user['user_id'];

// ✅ Allow only PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["message" => "Only PUT requests allowed", "status" => false]);
    exit;
}

// ✅ Determine student_id based on role
if ($user_role === 'admin') {
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    if ($student_id <= 0) {
        echo json_encode(["message" => "Missing or invalid student_id for admin", "status" => false]);
        exit;
    }
} 
// ✅ For student role - can only update own profile
elseif ($user_role === 'student') {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($student_id);
    $stmt->fetch();
    $stmt->close();

    if (!$student_id) {
        echo json_encode(["message" => "Profile not found for this student", "status" => false]);
        exit;
    }
} else {
    // ✅ Unauthorized role
    echo json_encode(["message" => "Unauthorized: Only admin and student can update profiles", "status" => false]);
    exit;
}

// ✅ Get JSON input
$input = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["message" => "Invalid JSON input", "status" => false]);
    exit;
}

// ✅ Extract all expected fields (support both flat and structured JSON)
$skills_input     = $input['skills'] ?? $input['professional_info']['skills'] ?? null;
$education_input  = $input['education'] ?? $input['professional_info']['education'] ?? null;
$resume           = $input['resume'] ?? $input['documents']['resume'] ?? null;
$certificates     = $input['certificates'] ?? $input['documents']['certificates'] ?? null;
$dob              = $input['dob'] ?? $input['personal_info']['date_of_birth'] ?? null;
$gender           = $input['gender'] ?? $input['personal_info']['gender'] ?? null;
$job_type         = $input['job_type'] ?? $input['professional_info']['job_type'] ?? null;
$trade            = $input['trade'] ?? $input['professional_info']['trade'] ?? null;
$bio              = $input['bio'] ?? $input['additional_info']['bio'] ?? null;
$experience       = $input['experience'] ?? $input['professional_info']['experience'] ?? null;
$projects         = $input['projects'] ?? $input['professional_info']['projects'] ?? null;
$languages_input  = $input['languages'] ?? $input['professional_info']['languages'] ?? null;
// ✅ Extract social_links from input
$social_links_input = $input['social_links'] ?? null;
$aadhar_number    = $input['aadhar_number'] ?? $input['documents']['aadhar_number'] ?? null;
$graduation_year  = $input['graduation_year'] ?? $input['professional_info']['graduation_year'] ?? null;
$cgpa             = $input['cgpa'] ?? $input['professional_info']['cgpa'] ?? null;
$latitude         = $input['latitude'] ?? $input['personal_info']['latitude'] ?? null;
$longitude        = $input['longitude'] ?? $input['personal_info']['longitude'] ?? null;
$location         = $input['location'] ?? $input['personal_info']['location'] ?? null;
$email            = $input['email'] ?? $input['personal_info']['email'] ?? null;
$user_name        = $input['user_name'] ?? $input['personal_info']['user_name'] ?? null;
$phone_number     = $input['phone_number'] ?? $input['personal_info']['phone_number'] ?? null;
$contact_email    = $input['contact_email'] ?? $input['contact_info']['contact_email'] ?? null;
$contact_phone    = $input['contact_phone'] ?? $input['contact_info']['contact_phone'] ?? null;

// ✅ Process Skills - Convert to JSON array format
$skills = null;
if ($skills_input !== null) {
    if (is_array($skills_input)) {
        // Already array format - clean and validate
        $skillsArray = array_filter(array_map('trim', $skills_input));
        $skillsArray = array_values(array_unique($skillsArray)); // Remove duplicates and re-index
        $skills = json_encode($skillsArray, JSON_UNESCAPED_UNICODE);
    } elseif (is_string($skills_input)) {
        // Comma-separated string - convert to array
        $skillsArray = array_filter(array_map('trim', explode(',', $skills_input)));
        $skillsArray = array_values(array_unique($skillsArray));
        $skills = json_encode($skillsArray, JSON_UNESCAPED_UNICODE);
    }
}

// ✅ Process Education - Structured format
$education = null;
if ($education_input !== null) {
    if (is_array($education_input)) {
        // Structured format: {course_name, college_name, start_date, end_date, cgpa, graduation_year}
        $educationData = [
            "course_name" => trim($education_input['course_name'] ?? ''),
            "college_name" => trim($education_input['college_name'] ?? ''),
            "start_date" => trim($education_input['start_date'] ?? ''),
            "end_date" => trim($education_input['end_date'] ?? ''),
            "cgpa" => !empty($education_input['cgpa']) ? floatval($education_input['cgpa']) : null,
            "graduation_year" => !empty($education_input['graduation_year']) ? intval($education_input['graduation_year']) : null
        ];
        // Also check if graduation_year or cgpa passed separately
        if (empty($educationData['graduation_year']) && !empty($graduation_year)) {
            $educationData['graduation_year'] = intval($graduation_year);
        }
        if (empty($educationData['cgpa']) && !empty($cgpa)) {
            $educationData['cgpa'] = floatval($cgpa);
        }
        $education = json_encode($educationData, JSON_UNESCAPED_UNICODE);
    } elseif (is_string($education_input)) {
        // Plain text - convert to structured format
        $educationData = [
            "course_name" => $education_input,
            "college_name" => "",
            "start_date" => "",
            "end_date" => "",
            "cgpa" => !empty($cgpa) ? floatval($cgpa) : null,
            "graduation_year" => !empty($graduation_year) ? intval($graduation_year) : null
        ];
        $education = json_encode($educationData, JSON_UNESCAPED_UNICODE);
    }
}

// ✅ Process Languages - Convert to JSON array format (spoken languages)
$languages = null;
if ($languages_input !== null) {
    if (is_array($languages_input)) {
        // Already array format - clean and validate
        $languagesArray = array_filter(array_map('trim', $languages_input));
        $languagesArray = array_values(array_unique($languagesArray)); // Remove duplicates
        $languages = json_encode($languagesArray, JSON_UNESCAPED_UNICODE);
    } elseif (is_string($languages_input)) {
        // Comma-separated string - convert to array
        $languagesArray = array_filter(array_map('trim', explode(',', $languages_input)));
        $languagesArray = array_values(array_unique($languagesArray));
        $languages = json_encode($languagesArray, JSON_UNESCAPED_UNICODE);
    }
}

// ✅ Process Experience - Multiple experiences array
if ($experience !== null) {
    if (is_array($experience)) {
        // Validate and clean experience array
        $cleanedExperiences = [];
        foreach ($experience as $exp) {
            if (is_array($exp)) {
                $cleanedExp = [
                    "company_name" => trim($exp['company_name'] ?? ''),
                    "position" => trim($exp['position'] ?? ''),
                    "start_date" => trim($exp['start_date'] ?? ''),
                    "end_date" => trim($exp['end_date'] ?? ''),
                    "company_location" => trim($exp['company_location'] ?? ''),
                    "description" => trim($exp['description'] ?? '')
                ];
                $cleanedExperiences[] = $cleanedExp;
            }
        }
        $experience = json_encode($cleanedExperiences, JSON_UNESCAPED_UNICODE);
    } else {
        // String or other format - try to decode
        $decoded = json_decode($experience, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $experience = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        } else {
            $experience = json_encode([], JSON_UNESCAPED_UNICODE);
        }
    }
}

// ✅ Process Education - Multiple educations array
if ($education_input !== null) {
    if (is_array($education_input)) {
        // Check if it's array of educations or single object
        if (isset($education_input[0]) && is_array($education_input[0])) {
            // Array of educations
            $cleanedEducations = [];
            foreach ($education_input as $edu) {
                if (is_array($edu)) {
                    $cleanedEdu = [
                        "qualification" => trim($edu['qualification'] ?? $edu['course_name'] ?? ''),
                        "institute" => trim($edu['institute'] ?? $edu['college_name'] ?? ''),
                        "start_year" => trim($edu['start_year'] ?? $edu['start_date'] ?? ''),
                        "end_year" => trim($edu['end_year'] ?? $edu['end_date'] ?? ''),
                        "is_pursuing" => isset($edu['is_pursuing']) ? (bool)$edu['is_pursuing'] : false,
                        "pursuing_year" => !empty($edu['pursuing_year']) ? intval($edu['pursuing_year']) : null,
                        "cgpa" => !empty($edu['cgpa']) ? floatval($edu['cgpa']) : null
                    ];
                    $cleanedEducations[] = $cleanedEdu;
                }
            }
            $education = json_encode($cleanedEducations, JSON_UNESCAPED_UNICODE);
        } else {
            // Single education object - convert to array
            $cleanedEdu = [
                "qualification" => trim($education_input['qualification'] ?? $education_input['course_name'] ?? ''),
                "institute" => trim($education_input['institute'] ?? $education_input['college_name'] ?? ''),
                "start_year" => trim($education_input['start_year'] ?? $education_input['start_date'] ?? ''),
                "end_year" => trim($education_input['end_year'] ?? $education_input['end_date'] ?? ''),
                "is_pursuing" => isset($education_input['is_pursuing']) ? (bool)$education_input['is_pursuing'] : false,
                "pursuing_year" => !empty($education_input['pursuing_year']) ? intval($education_input['pursuing_year']) : null,
                "cgpa" => !empty($education_input['cgpa']) ? floatval($education_input['cgpa']) : null
            ];
            // Also check if graduation_year or cgpa passed separately
            if (empty($cleanedEdu['end_year']) && !empty($graduation_year)) {
                $cleanedEdu['end_year'] = strval($graduation_year);
            }
            if (empty($cleanedEdu['cgpa']) && !empty($cgpa)) {
                $cleanedEdu['cgpa'] = floatval($cgpa);
            }
            $education = json_encode([$cleanedEdu], JSON_UNESCAPED_UNICODE);
        }
    } elseif (is_string($education_input)) {
        // Plain text - convert to structured format
        $educationData = [
            "qualification" => $education_input,
            "institute" => "",
            "start_year" => "",
            "end_year" => !empty($graduation_year) ? strval($graduation_year) : "",
            "is_pursuing" => false,
            "pursuing_year" => null,
            "cgpa" => !empty($cgpa) ? floatval($cgpa) : null
        ];
        $education = json_encode([$educationData], JSON_UNESCAPED_UNICODE);
    }
}

// ✅ Process Social Links - Multiple social links array (save in socials field)
$socials = null;
if ($social_links_input !== null) {
    // Handle empty array case - explicitly check for empty array
    if (is_array($social_links_input) && count($social_links_input) === 0) {
        // Empty array provided - save as empty JSON array
        $socials = json_encode([], JSON_UNESCAPED_UNICODE);
    } elseif (is_array($social_links_input) && count($social_links_input) > 0) {
        // Validate and clean social links array
        $cleanedSocials = [];
        foreach ($social_links_input as $index => $link) {
            // Handle both array and object formats
            if (is_array($link)) {
                $title = trim($link['title'] ?? $link['Title'] ?? '');
                $url = trim($link['profile_url'] ?? $link['profileUrl'] ?? $link['url'] ?? '');
                
                // Only add if both title and URL are not empty
                if (!empty($title) && !empty($url)) {
                    $cleanedSocials[] = [
                        "title" => $title,
                        "profile_url" => $url
                    ];
                }
            } elseif (is_object($link)) {
                // Handle object format
                $title = trim($link->title ?? $link->Title ?? '');
                $url = trim($link->profile_url ?? $link->profileUrl ?? $link->url ?? '');
                
                if (!empty($title) && !empty($url)) {
                    $cleanedSocials[] = [
                        "title" => $title,
                        "profile_url" => $url
                    ];
                }
            }
        }
        // Always encode, even if empty after cleaning
        $socials = json_encode($cleanedSocials, JSON_UNESCAPED_UNICODE);
    } elseif (is_string($social_links_input) && !empty(trim($social_links_input))) {
        // If string passed, try to decode it
        $decoded = json_decode($social_links_input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $socials = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        } else {
            // Invalid JSON string - save as empty array
            $socials = json_encode([], JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Invalid format - save as empty array to clear existing data
        $socials = json_encode([], JSON_UNESCAPED_UNICODE);
    }
}
// If social_links_input is null (not provided), keep socials as null (don't update existing value)

// ✅ Convert projects to JSON
if (is_array($projects)) $projects = json_encode($projects, JSON_UNESCAPED_UNICODE);

// ✅ Start transaction
mysqli_autocommit($conn, false);

$update_success = true;
$error_message = "";

// ✅ Update student_profiles table - Build dynamic SQL to only update provided fields
$update_fields = [];
$update_params = [];
$update_types = "";

// Only add fields that are not null (meaning they were provided in request)
if ($skills !== null) { $update_fields[] = "skills = ?"; $update_params[] = $skills; $update_types .= "s"; }
if ($education !== null) { $update_fields[] = "education = ?"; $update_params[] = $education; $update_types .= "s"; }
if ($resume !== null) { $update_fields[] = "resume = ?"; $update_params[] = $resume; $update_types .= "s"; }
if ($certificates !== null) { $update_fields[] = "certificates = ?"; $update_params[] = $certificates; $update_types .= "s"; }
if ($socials !== null) { $update_fields[] = "socials = ?"; $update_params[] = $socials; $update_types .= "s"; }
if ($dob !== null) { $update_fields[] = "dob = ?"; $update_params[] = $dob; $update_types .= "s"; }
if ($gender !== null) { $update_fields[] = "gender = ?"; $update_params[] = $gender; $update_types .= "s"; }
if ($job_type !== null) { $update_fields[] = "job_type = ?"; $update_params[] = $job_type; $update_types .= "s"; }
if ($trade !== null) { $update_fields[] = "trade = ?"; $update_params[] = $trade; $update_types .= "s"; }
if ($bio !== null) { $update_fields[] = "bio = ?"; $update_params[] = $bio; $update_types .= "s"; }
if ($experience !== null) { $update_fields[] = "experience = ?"; $update_params[] = $experience; $update_types .= "s"; }
if ($projects !== null) { $update_fields[] = "projects = ?"; $update_params[] = $projects; $update_types .= "s"; }
if ($languages !== null) { $update_fields[] = "languages = ?"; $update_params[] = $languages; $update_types .= "s"; }
if ($aadhar_number !== null) { $update_fields[] = "aadhar_number = ?"; $update_params[] = $aadhar_number; $update_types .= "s"; }
if ($graduation_year !== null) { $update_fields[] = "graduation_year = ?"; $update_params[] = $graduation_year; $update_types .= "i"; }
if ($cgpa !== null) { $update_fields[] = "cgpa = ?"; $update_params[] = $cgpa; $update_types .= "d"; }
if ($latitude !== null) { $update_fields[] = "latitude = ?"; $update_params[] = $latitude; $update_types .= "d"; }
if ($longitude !== null) { $update_fields[] = "longitude = ?"; $update_params[] = $longitude; $update_types .= "d"; }
if ($location !== null) { $update_fields[] = "location = ?"; $update_params[] = $location; $update_types .= "s"; }
if ($contact_email !== null) { $update_fields[] = "contact_email = ?"; $update_params[] = $contact_email; $update_types .= "s"; }
if ($contact_phone !== null) { $update_fields[] = "contact_phone = ?"; $update_params[] = $contact_phone; $update_types .= "s"; }

// Always update updated_at
$update_fields[] = "updated_at = NOW()";

if (empty($update_fields)) {
    $update_success = false;
    $error_message = "No fields provided to update";
} else {
    $sql = "UPDATE student_profiles SET " . implode(", ", $update_fields) . " WHERE id = ? AND deleted_at IS NULL";
    $update_types .= "i"; // For student_id
    $update_params[] = $student_id;
}

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    $update_success = false;
    $error_message = "Failed to prepare student profile statement: " . mysqli_error($conn);
    
    // Check if socials column exists, if not suggest running migration
    if (strpos(mysqli_error($conn), "Unknown column 'socials'") !== false) {
        $error_message = "Database migration required: Please run 'php artisan migrate' to add socials column. Error: " . mysqli_error($conn);
    }
} else {
    // Bind parameters dynamically
    mysqli_stmt_bind_param($stmt, $update_types, ...$update_params);

    if (!mysqli_stmt_execute($stmt)) {
        $update_success = false;
        $error_message = "Student profile update failed: " . mysqli_stmt_error($stmt);
        
        // Check if socials column exists
        if (strpos(mysqli_stmt_error($stmt), "Unknown column 'socials'") !== false) {
            $error_message = "Database migration required: Please run 'php artisan migrate' to add socials column. Error: " . mysqli_stmt_error($stmt);
        }
    }
    mysqli_stmt_close($stmt);
}

// ✅ Update users table (email/user_name/phone)
if ($update_success && ($email !== null || $user_name !== null || $phone_number !== null)) {
    $user_id_query = "SELECT user_id FROM student_profiles WHERE id = ? AND deleted_at IS NULL";
    $user_stmt = mysqli_prepare($conn, $user_id_query);
    if (!$user_stmt) {
        $update_success = false;
        $error_message = "Failed to prepare user_id query: " . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($user_stmt, "i", $student_id);
        mysqli_stmt_execute($user_stmt);
        mysqli_stmt_bind_result($user_stmt, $user_id_from_profile);
        mysqli_stmt_fetch($user_stmt);
        mysqli_stmt_close($user_stmt);

        if ($user_id_from_profile) {
            $user_update_fields = [];
            $user_params = [];
            $user_types = "";

            if ($email !== null) { $user_update_fields[] = "email = ?"; $user_params[] = $email; $user_types .= "s"; }
            if ($user_name !== null) { $user_update_fields[] = "user_name = ?"; $user_params[] = $user_name; $user_types .= "s"; }
            if ($phone_number !== null) { $user_update_fields[] = "phone_number = ?"; $user_params[] = $phone_number; $user_types .= "s"; }

            if (!empty($user_update_fields)) {
                $user_sql = "UPDATE users SET " . implode(", ", $user_update_fields) . " WHERE id = ?";
                $user_types .= "i";
                $user_params[] = $user_id_from_profile;

                $user_update_stmt = mysqli_prepare($conn, $user_sql);
                if (!$user_update_stmt) {
                    $update_success = false;
                    $error_message = "Failed to prepare user update statement: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($user_update_stmt, $user_types, ...$user_params);
                    if (!mysqli_stmt_execute($user_update_stmt)) {
                        $update_success = false;
                        $error_message = "User update failed: " . mysqli_stmt_error($user_update_stmt);
                    }
                    mysqli_stmt_close($user_update_stmt);
                }
            }
        }
    }
}

// ✅ Commit or rollback transaction
if ($update_success) {
    mysqli_commit($conn);
    echo json_encode([
        "success" => true,
        "message" => "Student profile updated successfully",
        "data" => [
            "profile_updated_id" => $student_id,
            "profile_updated_by_id" => $user_id, // ✅ NEW
            "profile_updated" => true,           // ✅ NEW
            "updated_by" => $user_role,
            "updated_fields" => [
                "student_profile" => true,
                "user_info" => ($email !== null || $user_name !== null || $phone_number !== null)
            ]
        ],
        "meta" => [
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0"
        ]
    ], JSON_PRETTY_PRINT);
} else {
    mysqli_rollback($conn);
    echo json_encode([
        "success" => false,
        "message" => "Update failed: " . $error_message,
        "data" => [
            "profile_updated" => false,
            "profile_updated_by_id" => $user_id, // ✅ NEW
            "error_details" => $error_message,
            "profile_id" => $student_id
        ],
        "meta" => [
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0"
        ]
    ], JSON_PRETTY_PRINT);
}

// ✅ Restore autocommit
mysqli_autocommit($conn, true);

mysqli_close($conn);
?>

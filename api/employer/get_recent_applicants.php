<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Prevent caching for instant updates
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // --------------------------------------------
    // 🔐 AUTHENTICATION
    // --------------------------------------------
    $decoded = authenticateJWT(['recruiter', 'admin']);
    $role = strtolower($decoded['role'] ?? '');
    $user_id = intval($decoded['user_id'] ?? 0);

    if (!$user_id) {
        echo json_encode(["status" => false, "message" => "Unauthorized"]);
        exit;
    }

    // --------------------------------------------
    // 🔍 FIND RECRUITER_PROFILE_ID (FIXED: removed admin_action)
    // --------------------------------------------
    $recruiter_profile_id = null;

    if ($role === "recruiter") {
        $stmt = $conn->prepare("
            SELECT id 
            FROM recruiter_profiles 
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $rp = $stmt->get_result()->fetch_assoc();
        $recruiter_profile_id = $rp['id'] ?? null;

        if (!$recruiter_profile_id) {
            echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
            exit;
        }
    }

    // --------------------------------------------
    // 🔎 SEARCH + PAGINATION
    // --------------------------------------------
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // --------------------------------------------
    // 🧠 BASE SQL — changes depending on role
    // --------------------------------------------
    $where = " WHERE 1 ";

    if ($role === "recruiter") {
        // 👇 Only recruiter’s own job applicants
        $where .= " AND j.recruiter_id = $recruiter_profile_id ";
    }

    if ($search !== "") {
        $safe = "%".$search."%";
        $where .= " AND (u.user_name LIKE '$safe' OR j.title LIKE '$safe')";
    }

    // --------------------------------------------
    // 📌 RECENT APPLICANTS
    // --------------------------------------------
    $recent_sql = "
        SELECT 
            u.user_name AS candidate_name,
            j.title AS job_title,
            DATE_FORMAT(a.applied_at, '%d-%m-%y') AS applied_date,
            a.status
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles sp ON sp.id = a.student_id
        JOIN users u ON u.id = sp.user_id
        $where
        ORDER BY a.applied_at DESC
        LIMIT 5
    ";

    $recent_applicants = $conn->query($recent_sql)->fetch_all(MYSQLI_ASSOC);

    // --------------------------------------------
    // 📌 COUNT TOTAL APPLICANTS
    // --------------------------------------------
    $count_sql = "SELECT COUNT(*) AS total
                  FROM applications a
                  JOIN jobs j ON j.id = a.job_id
                  JOIN student_profiles s ON s.id = a.student_id
                  JOIN users u ON u.id = s.user_id
                  $where";

    $total = $conn->query($count_sql)->fetch_assoc()['total'] ?? 0;

    // --------------------------------------------
    // 📌 MAIN FETCH QUERY
    // --------------------------------------------
    $main_sql = "
        SELECT 
            a.id AS application_id,
            a.status,
            a.cover_letter,
            DATE_FORMAT(a.applied_at, '%d-%m-%Y %h:%i %p') AS applied_date,

            s.id AS student_id,
            s.education,
            s.skills,
            s.bio,
            s.resume,
            s.socials,
            s.location AS candidate_location,
            s.experience AS experience_years,

            u.user_name AS candidate_name,
            u.email AS candidate_email,
            u.phone_number,
            u.is_verified,

            j.id AS job_id,
            j.title AS job_title,
            j.location AS job_location,
            j.job_type

        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles s ON s.id = a.student_id
        JOIN users u ON u.id = s.user_id
        $where
        ORDER BY a.applied_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $result = $conn->query($main_sql);

    // --------------------------------------------
    // 📌 NORMALIZING RESULT
    // --------------------------------------------
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $resume_folder = "/jobsahi-API/api/uploads/resume/";

    $all_applicants = [];

    while ($row = $result->fetch_assoc()) {

        // SKILLS
        $skills = [];
        if (!empty($row['skills'])) {
            $decoded = json_decode($row['skills'], true);
            $skills = is_array($decoded) ? $decoded : explode(",", $row['skills']);
        }

        // RESUME URL
        $resume_url = null;
        if (!empty($row['resume'])) {
            $clean = basename($row['resume']);
            if (file_exists(__DIR__."/../uploads/resume/".$clean)) {
                $resume_url = $protocol.$host.$resume_folder.$clean;
            }
        }

        // SOCIAL LINKS
        $social_links = [];
        if (!empty($row['socials'])) {
            $decoded_socials = json_decode($row['socials'], true);
            if (is_array($decoded_socials)) $social_links = $decoded_socials;
        }

        $all_applicants[] = [
            "application_id" => (int)$row['application_id'],
            "student_id"     => (int)$row['student_id'],
            "job_id"         => (int)$row['job_id'],

            "name"           => $row['candidate_name'],
            "email"          => $row['candidate_email'],
            "phone_number"   => $row['phone_number'] ?: "N/A",

            "education"      => $row['education'] ?: "N/A",
            "skills"         => $skills,

            "bio"            => $row['bio'] ?: "—",
            "status"         => ucfirst($row['status']),
            "verified"       => (bool)$row['is_verified'],

            "location"       => $row['candidate_location'] ?: $row['job_location'],
            "experience"     => $row['experience_years'] ?: "N/A",
            "job_type"       => ucfirst($row['job_type']),
            "applied_for"    => $row['job_title'],

            "applied_date"   => $row['applied_date'],
            "resume_url"     => $resume_url,
            "social_links"   => $social_links,
            "cover_letter"   => $row['cover_letter'] ?: "—"
        ];
    }

    // --------------------------------------------
    // 📌 FINAL RESPONSE
    // --------------------------------------------
    echo json_encode([
        "status" => true,
        "message" => "Applicants fetched successfully",
        "recent_applicants" => $recent_applicants,
        "all_applicants" => [
            "total_records" => $total,
            "fetched" => count($all_applicants),
            "data" => $all_applicants
        ]
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "status" => false,
        "message" => "Server error: ".$e->getMessage()
    ]);
}

$conn->close();
?>

<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate recruiter/admin
    $decoded = authenticateJWT(['recruiter', 'admin']);
    $role = strtolower($decoded['role'] ?? '');
    $user_id = intval($decoded['user_id'] ?? 0);

    if (!$user_id) {
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized", "status" => false]);
        exit;
    }

    // 🔹 Only recruiters allowed
    if ($role !== 'recruiter') {
        http_response_code(403);
        echo json_encode(["message" => "Access denied", "status" => false]);
        exit;
    }

    // 🔹 Get recruiter_profile id (indexed column id,user_id)
    $stmt_rec = $conn->prepare("
        SELECT id 
        FROM recruiter_profiles 
        WHERE user_id = ? AND admin_action = 'approved'
        LIMIT 1
    ");
    $stmt_rec->bind_param("i", $user_id);
    $stmt_rec->execute();
    $rec = $stmt_rec->get_result()->fetch_assoc();
    $recruiter_profile_id = $rec['id'] ?? null;

    if (!$recruiter_profile_id) {
        http_response_code(400);
        echo json_encode(["message" => "Recruiter profile not found or not approved", "status" => false]);
        exit;
    }

    // ==========================================================
    // PART 1 — Recent 5 Applicants (Quick fetch)
    // ==========================================================
    $stmt_recent = $conn->prepare("
        SELECT 
            u.user_name AS candidate_name,
            j.title AS job_title,
            DATE_FORMAT(a.applied_at, '%d-%m-%y') AS applied_date,
            a.status
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles sp ON sp.id = a.student_id
        JOIN users u ON u.id = sp.user_id
        WHERE j.recruiter_id = ?
        ORDER BY a.applied_at DESC
        LIMIT 5
    ");
    $stmt_recent->bind_param("i", $recruiter_profile_id);
    $stmt_recent->execute();
    $recent_applicants = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);

    // ==========================================================
    // PART 2 — All Applicants (Optimized + pagination)
    // ==========================================================
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $base_sql = "
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles s ON s.id = a.student_id
        JOIN users u ON u.id = s.user_id
        WHERE j.recruiter_id = ?
    ";

    $params = [$recruiter_profile_id];
    $types = "i";

    if ($search !== '') {
        $base_sql .= " AND (u.user_name LIKE ? OR j.title LIKE ?)";
        $safe_search = "%" . $search . "%";
        $params[] = $safe_search;
        $params[] = $safe_search;
        $types .= "ss";
    }

    // ✅ Count total (for pagination)
    $stmt_count = $conn->prepare("SELECT COUNT(*) AS total $base_sql");
    $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $total = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;

    // ✅ Main data query (indexed join + pagination)
    $sql_all = "
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
            s.portfolio_link,
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
        $base_sql
        ORDER BY a.applied_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt_all = $conn->prepare($sql_all);
    $stmt_all->bind_param($types, ...$params);
    $stmt_all->execute();
    $result_all = $stmt_all->get_result();

    $all_applicants = [];
    while ($row = $result_all->fetch_assoc()) {
        $skills = [];
        if (!empty($row['skills'])) {
            $decoded = json_decode($row['skills'], true);
            $skills = is_array($decoded) ? $decoded : explode(',', $row['skills']);
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
            "applied_for"    => $row['job_title'],
            "status"         => ucfirst($row['status']),
            "verified"       => (bool)$row['is_verified'],
            "location"       => $row['candidate_location'] ?: $row['job_location'],
            "experience"     => $row['experience_years'] ?: "N/A",
            "job_type"       => ucfirst($row['job_type']),
            "bio"            => $row['bio'] ?: "—",
            "applied_date"   => $row['applied_date'] ?: null,
            "resume_url"     => $row['resume'] ?: null,
            "portfolio_link" => $row['portfolio_link'] ?: null,
            "cover_letter"   => $row['cover_letter'] ?: "—"
        ];
    }

    // ✅ Final Response
    echo json_encode([
        "status" => true,
        "message" => "Applicants fetched successfully",
        "recent_applicants" => $recent_applicants,
        "all_applicants" => [
            "total_records" => $total,
            "fetched" => count($all_applicants),
            "data" => $all_applicants
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

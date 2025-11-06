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

    // 🔹 Get recruiter_profile id
    $sql_rec = "SELECT id FROM recruiter_profiles WHERE user_id = ? AND admin_action = 'approved'";
    $stmt_rec = $conn->prepare($sql_rec);
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
    // 🔹 PART 1 — Recent 5 Applicants (Original logic preserved)
    // ==========================================================
    $sql_recent = "
        SELECT 
            u.user_name AS candidate_name,
            j.title AS job_title,
            DATE_FORMAT(a.applied_at, '%d-%m-%y') AS applied_date,
            a.status
        FROM applications a
        INNER JOIN jobs j ON j.id = a.job_id
        INNER JOIN student_profiles sp ON sp.id = a.student_id
        INNER JOIN users u ON u.id = sp.user_id
        WHERE j.recruiter_id = ?
        ORDER BY a.applied_at DESC
        LIMIT 5
    ";
    $stmt_recent = $conn->prepare($sql_recent);
    $stmt_recent->bind_param("i", $recruiter_profile_id);
    $stmt_recent->execute();
    $result_recent = $stmt_recent->get_result();

    $recent_applicants = [];
    while ($r = $result_recent->fetch_assoc()) {
        $recent_applicants[] = $r;
    }

    // ==========================================================
    // 🔹 PART 2 — All Applicants (Full View with Search)
    // ==========================================================
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $where = "WHERE j.recruiter_id = $recruiter_profile_id";
    if ($search !== '') {
        $safe_search = "%" . $conn->real_escape_string($search) . "%";
        $where .= " AND (u.user_name LIKE '$safe_search' OR j.title LIKE '$safe_search')";
    }

    $sql_all = "
        SELECT 
            a.id AS application_id,
            a.status,
            s.id AS student_id,
            u.user_name AS candidate_name,
            u.email AS candidate_email,
            s.education,
            s.skills,
            s.location AS candidate_location,
            s.experience AS experience_years,
            j.title AS job_title,
            j.location AS job_location,
            j.job_type,
            u.is_verified
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles s ON s.id = a.student_id
        JOIN users u ON u.id = s.user_id
        $where
        ORDER BY a.created_at DESC
    ";

    $result_all = $conn->query($sql_all);
    $all_applicants = [];

    while ($row = $result_all->fetch_assoc()) {
        // Decode skills safely
        $skills = [];
        if (!empty($row['skills'])) {
            $decoded = json_decode($row['skills'], true);
            $skills = is_array($decoded) ? $decoded : explode(',', $row['skills']);
        }

        $all_applicants[] = [
            "application_id" => intval($row['application_id']),
            "name" => $row['candidate_name'],
            "email" => $row['candidate_email'],
            "education" => $row['education'] ?: "N/A",
            "skills" => $skills,
            "applied_for" => $row['job_title'],
            "status" => ucfirst($row['status']),
            "verified" => (bool)$row['is_verified'],
            "location" => $row['candidate_location'] ?: $row['job_location'],
            "experience" => $row['experience_years'] ?: "N/A",
            "job_type" => $row['job_type']
        ];
    }

    // ==========================================================
    // ✅ Final Combined Response
    // ==========================================================
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Applicants fetched successfully",
        "recent_applicants" => $recent_applicants,
        "all_applicants" => [
            "total_records" => count($all_applicants),
            "data" => $all_applicants
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
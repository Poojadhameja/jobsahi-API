<?php
// api/recruiter/candidate_dashboard.php
// Recruiter "InstaMatch Dashboard" – candidate list + summary cards

require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate recruiter/admin
    $decoded = authenticateJWT(['recruiter', 'admin']);
    $role = strtolower($decoded['role'] ?? '');
    $user_id = intval($decoded['user_id'] ?? 0);

    // ✅ Get recruiter_id
    $recruiter_id = 0;
    if ($role === 'recruiter') {
        $stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
            exit;
        }
        $recruiter_id = intval($res->fetch_assoc()['id']);
    }

    // ✅ Optional filters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // -------------------------------------------
    // SUMMARY COUNTS
    // -------------------------------------------
    $where = "WHERE j.recruiter_id = $recruiter_id";
    if ($search !== '') {
        $like = "%$search%";
        $where .= " AND (u.user_name LIKE '$like' OR j.title LIKE '$like')";
    }

    // Total
    $sql_total = "
        SELECT COUNT(DISTINCT a.id) AS cnt
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles s ON s.id = a.student_id
        JOIN users u ON u.id = s.user_id
        $where
    ";
    $total = intval($conn->query($sql_total)->fetch_assoc()['cnt'] ?? 0);

    // Shortlisted
    $sql_short = "
        SELECT COUNT(DISTINCT a.id) AS cnt
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles s ON s.id = a.student_id
        JOIN users u ON u.id = s.user_id
        $where AND LOWER(a.status) = 'shortlisted'
    ";
    $shortlisted = intval($conn->query($sql_short)->fetch_assoc()['cnt'] ?? 0);

    // Interviewed
    $sql_interviewed = "
        SELECT COUNT(DISTINCT a.id) AS cnt
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles s ON s.id = a.student_id
        JOIN users u ON u.id = s.user_id
        WHERE j.recruiter_id = $recruiter_id
          AND EXISTS (
              SELECT 1 FROM interviews i
              WHERE i.application_id = a.id AND i.deleted_at IS NULL
          )
    ";
    $interviewed = intval($conn->query($sql_interviewed)->fetch_assoc()['cnt'] ?? 0);

    // Verified (✅ users.is_verified)
    $sql_verified = "
        SELECT COUNT(DISTINCT a.id) AS cnt
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles s ON s.id = a.student_id
        JOIN users u ON u.id = s.user_id
        $where AND u.is_verified = 1
    ";
    $verified = intval($conn->query($sql_verified)->fetch_assoc()['cnt'] ?? 0);

    // -------------------------------------------
    // CANDIDATE LIST
    // -------------------------------------------
    $sql_list = "
        SELECT 
            a.id AS application_id,
            a.status AS application_status,
            s.id AS student_id,
            u.user_name AS candidate_name,
            u.email AS candidate_email,
            u.phone_number AS candidate_phone,
            u.is_verified,
            s.skills,
            s.location AS candidate_location,
            s.experience AS experience_years,
            j.title AS job_title,
            j.location AS job_location,
            j.job_type,
            (RAND() * 20 + 80) AS match_score, -- fake 80–100
            EXISTS (
                SELECT 1 FROM interviews i
                WHERE i.application_id = a.id AND i.deleted_at IS NULL
            ) AS interviewed
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN student_profiles s ON s.id = a.student_id
        JOIN users u ON u.id = s.user_id
        $where
        ORDER BY match_score DESC
        LIMIT 50
    ";

    $res = $conn->query($sql_list);
    $candidates = [];

    while ($row = $res->fetch_assoc()) {
        // Decode skills (JSON or comma-separated)
        $skills = [];
        if (!empty($row['skills'])) {
            $decoded = json_decode($row['skills'], true);
            $skills = is_array($decoded) ? $decoded : explode(',', $row['skills']);
        }

        $candidates[] = [
            "application_id" => intval($row['application_id']),
            "status" => $row['application_status'],
            "name" => $row['candidate_name'],
            "email" => $row['candidate_email'],
            "phone" => $row['candidate_phone'],
            "verified" => (bool)$row['is_verified'],
            "location" => $row['candidate_location'] ?: $row['job_location'],
            "experience" => $row['experience_years'] ?: "N/A",
            "job_title" => $row['job_title'],
            "job_type" => $row['job_type'],
            "skills" => $skills,
            "match_score" => round(floatval($row['match_score']), 1),
            "interviewed" => boolval($row['interviewed'])
        ];
    }

    echo json_encode([
        "status" => true,
        "summary" => [
            "total_candidates" => $total,
            "shortlisted" => $shortlisted,
            "interviewed" => $interviewed,
            "verified" => $verified
        ],
        "data" => $candidates
    ]);

} catch (Throwable $e) {
    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}
?>

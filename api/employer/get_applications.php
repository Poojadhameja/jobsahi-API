<?php
// api/recruiter/get_applications.php
// Fetch all applicants for recruiter's jobs (View Applicants page - no limit, fetch all)

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

    // ✅ Optional search filter
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // ✅ WHERE condition
    $where = "WHERE j.recruiter_id = $recruiter_id";
    if ($search !== '') {
        $s = "%$search%";
        $where .= " AND (u.user_name LIKE '$s' OR j.title LIKE '$s')";
    }

    // ✅ Fetch all applicants (no limit)
    $sql = "
        SELECT 
            a.id AS application_id,
            a.status,
            s.id AS student_id,
            u.user_name AS candidate_name,
            u.email AS candidate_email,
            s.education,                      -- ✅ from student_profiles
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

    $result = $conn->query($sql);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Decode skills safely
        $skills = [];
        if (!empty($row['skills'])) {
            $decoded = json_decode($row['skills'], true);
            $skills = is_array($decoded) ? $decoded : explode(',', $row['skills']);
        }

        $data[] = [
            "application_id" => intval($row['application_id']),
            "name" => $row['candidate_name'],
            "email" => $row['candidate_email'],
            "education" => $row['education'] ?: "N/A",  // ✅ from student_profiles
            "skills" => $skills,
            "applied_for" => $row['job_title'],
            "status" => ucfirst($row['status']),
            "verified" => (bool)$row['is_verified'],
            "location" => $row['candidate_location'] ?: $row['job_location'],
            "experience" => $row['experience_years'] ?: "N/A",
            "job_type" => $row['job_type']
        ];
    }

    echo json_encode([
        "status" => true,
        "message" => "All applicants fetched successfully",
        "total_records" => count($data),
        "data" => $data
    ]);

} catch (Throwable $e) {
    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}
?>

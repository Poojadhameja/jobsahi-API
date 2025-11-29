<?php
// get_recruiter_jobs_.php - List jobs/applications based on admin_action and user role with dashboard stats
require_once '../cors.php';

// ✅ Authenticate JWT and allow all roles
$decoded = authenticateJWT(['admin', 'recruiter']); // returns array
$role = strtolower($decoded['role'] ?? '');
$user_id = $decoded['user_id'] ?? null;

// Build SQL based on role
try {
    $dashboard_stats = [];

    // If recruiter, get dashboard statistics
    if ($role === 'recruiter' && $user_id) {

        // 1. Jobs Posted Count
        $sql_posted = "SELECT COUNT(*) as jobs_posted 
                       FROM jobs 
                       WHERE recruiter_id IN (
                           SELECT id FROM recruiter_profiles WHERE user_id = ?
                       )";
        $stmt_posted = $conn->prepare($sql_posted);
        $stmt_posted->bind_param("i", $user_id);
        $stmt_posted->execute();
        $result_posted = $stmt_posted->get_result();
        $dashboard_stats['jobs_posted'] = $result_posted->fetch_assoc()['jobs_posted'] ?? 0;
        $stmt_posted->close();

        // 2. Applied Jobs Count
        $sql_applied = "SELECT COUNT(DISTINCT a.id) as applied_count
                        FROM applications a
                        INNER JOIN jobs j ON a.job_id = j.id
                        INNER JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
                        WHERE rp.user_id = ? 
                        AND a.status IN ('pending', 'applied', 'shortlisted', 'rejected')";
        $stmt_applied = $conn->prepare($sql_applied);
        $stmt_applied->bind_param("i", $user_id);
        $stmt_applied->execute();
        $result_applied = $stmt_applied->get_result();
        $dashboard_stats['applied_job'] = $result_applied->fetch_assoc()['applied_count'] ?? 0;
        $stmt_applied->close();

        // 3. Interview Scheduled Count
        $sql_scheduled = "SELECT COUNT(*) as interview_scheduled
                          FROM interviews i
                          INNER JOIN applications a ON i.application_id = a.id
                          INNER JOIN jobs j ON a.job_id = j.id
                          INNER JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
                          WHERE rp.user_id = ? 
                          AND i.status = 'scheduled'";
        $stmt_scheduled = $conn->prepare($sql_scheduled);
        $stmt_scheduled->bind_param("i", $user_id);
        $stmt_scheduled->execute();
        $result_scheduled = $stmt_scheduled->get_result();
        $dashboard_stats['interview_job'] = $result_scheduled->fetch_assoc()['interview_scheduled'] ?? 0;
        $stmt_scheduled->close();

        // ✅ 4. Interview Completed Count (only when application status is 'selected')
        $sql_completed = "SELECT COUNT(*) as interview_completed
                          FROM applications a
                          INNER JOIN jobs j ON a.job_id = j.id
                          INNER JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
                          WHERE rp.user_id = ? 
                          AND a.status = 'selected'
                          AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')";
        $stmt_completed = $conn->prepare($sql_completed);
        $stmt_completed->bind_param("i", $user_id);
        $stmt_completed->execute();
        $result_completed = $stmt_completed->get_result();
        $dashboard_stats['interview_completed'] = $result_completed->fetch_assoc()['interview_completed'] ?? 0;
        $stmt_completed->close();
    }

    // ✅ Get jobs list based on role
    if ($role === 'admin') {
        $sql = "SELECT id, recruiter_id, title, description, location, skills_required, 
                salary_min, salary_max, job_type, experience_required, application_deadline, 
                is_remote, no_of_vacancies, status, admin_action, created_at
                FROM jobs
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
    } elseif ($role === 'recruiter' && $user_id) {
        $sql = "SELECT j.id, j.recruiter_id, j.title, j.description, j.location, 
                j.skills_required, j.salary_min, j.salary_max, j.job_type, 
                j.experience_required, j.application_deadline, j.is_remote, 
                j.no_of_vacancies, j.status, j.admin_action, j.created_at
                FROM jobs j
                INNER JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
                WHERE rp.user_id = ?
                ORDER BY j.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    } else {
        $sql = "SELECT id, recruiter_id, title, description, location, skills_required, 
                salary_min, salary_max, job_type, experience_required, application_deadline, 
                is_remote, no_of_vacancies, status, admin_action, created_at
                FROM jobs
                WHERE admin_action = 'approved'
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $jobs = [];

        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }

        $response = [
            "status" => true,
            "message" => "Jobs retrieved successfully",
            "data" => $jobs,
            "count" => count($jobs)
        ];

        // Add dashboard stats if recruiter
        if ($role === 'recruiter' && !empty($dashboard_stats)) {
            $response['dashboard'] = $dashboard_stats;
        }

        echo json_encode($response);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve jobs",
            "error" => $stmt->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
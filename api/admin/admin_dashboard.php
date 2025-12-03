<?php
require_once '../cors.php';
require_once '../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

try {

    /* --------------------------------------------------
       1️⃣ Total Institutes
    -------------------------------------------------- */
    $totalInstitutes = $conn->query("
        SELECT COUNT(*) AS total 
        FROM institute_profiles
        WHERE deleted_at IS NULL
    ")->fetch_assoc()['total'] ?? 0;

    /* --------------------------------------------------
       2️⃣ Total Recruiters
    -------------------------------------------------- */
    $totalRecruiters = $conn->query("
        SELECT COUNT(*) AS total 
        FROM recruiter_profiles
        WHERE deleted_at IS NULL
    ")->fetch_assoc()['total'] ?? 0;

    /* --------------------------------------------------
       3️⃣ Active Jobs
    -------------------------------------------------- */
    $activeJobs = $conn->query("
        SELECT COUNT(*) AS total 
        FROM jobs 
        WHERE status='open'
          AND admin_action='approved'
    ")->fetch_assoc()['total'] ?? 0;

    /* --------------------------------------------------
       4️⃣ Active Courses
    -------------------------------------------------- */
    $activeCourses = $conn->query("
        SELECT COUNT(*) AS total 
        FROM courses
        WHERE status='active'
          AND admin_action='approved'
    ")->fetch_assoc()['total'] ?? 0;

    /* --------------------------------------------------
       5️⃣ Total Students
    -------------------------------------------------- */
    $totalStudents = $conn->query("
        SELECT COUNT(*) AS total 
        FROM student_profiles
        WHERE deleted_at IS NULL
    ")->fetch_assoc()['total'] ?? 0;

    /* --------------------------------------------------
       Additional Stats (for other sections)
    -------------------------------------------------- */
    $appliedJobs = $conn->query("
        SELECT COUNT(*) AS total 
        FROM applications
        WHERE deleted_at IS NULL
    ")->fetch_assoc()['total'] ?? 0;

    $interviewJobs = $conn->query("
        SELECT COUNT(DISTINCT interview_id) AS total
        FROM applications
        WHERE interview_id IS NOT NULL
    ")->fetch_assoc()['total'] ?? 0;


    /* --------------------------------------------------
       6️⃣ Placement Funnel
       Replace Offers → Active Courses
    -------------------------------------------------- */

    $funnelApplications = $conn->query("
        SELECT COUNT(*) AS c 
        FROM applications
    ")->fetch_assoc()['c'] ?? 0;

    $funnelInterviews = $conn->query("
        SELECT COUNT(*) AS c 
        FROM applications 
        WHERE status='shortlisted'
    ")->fetch_assoc()['c'] ?? 0;

    // ❌ Old
    // $funnelOffers = ...

    // ✅ New → Active Courses
    $funnelActiveCourses = $activeCourses;

    $funnelHired = $conn->query("
        SELECT COUNT(*) AS c 
        FROM applications 
        WHERE status = 'selected'
          AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
    ")->fetch_assoc()['c'] ?? 0;


    /* --------------------------------------------------
       7️⃣ Applications Trend (Last 6 months)
    -------------------------------------------------- */
    $trendResult = $conn->query("
        SELECT 
            DATE_FORMAT(applied_at, '%b') AS month_name,
            COUNT(*) AS total
        FROM applications
        WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(applied_at), MONTH(applied_at)
        ORDER BY applied_at ASC
    ");

    $applicationsTrend = [];
    while ($row = $trendResult->fetch_assoc()) {
        $applicationsTrend[] = $row;
    }


    /* --------------------------------------------------
       8️⃣ Top Jobs in Demand
    -------------------------------------------------- */
    $topJobsResult = $conn->query("
        SELECT 
            j.id,
            j.title,
            COUNT(a.id) AS total_applications
        FROM jobs j
        LEFT JOIN applications a ON a.job_id = j.id
        GROUP BY j.id
        ORDER BY total_applications DESC
        LIMIT 6
    ");

    $topJobs = [];
    while ($row = $topJobsResult->fetch_assoc()) {
        $topJobs[] = $row;
    }


    /* --------------------------------------------------
       FINAL RESPONSE
    -------------------------------------------------- */
    echo json_encode([
        "status" => true,
        "message" => "Admin dashboard data loaded successfully",
        "data" => [
            "cards" => [
                "total_institutes" => $totalInstitutes,
                "total_recruiters" => $totalRecruiters,
                "total_students" => $totalStudents
            ],
            "placement_funnel" => [
                "applications" => $funnelApplications,
                "interviews" => $funnelInterviews,
                "active_courses" => $funnelActiveCourses,  // ✔ new
                "hired" => $funnelHired
            ],
            "applications_trend" => $applicationsTrend,
            "top_jobs_in_demand" => $topJobs
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "error" => $e->getMessage()
    ]);
}
?>

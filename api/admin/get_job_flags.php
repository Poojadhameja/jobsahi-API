<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Admin Only
$decoded = authenticateJWT(['admin']);
$admin_id = $decoded['user_id'] ?? null;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    /* =========================================================
       1️⃣ TOP SUMMARY COUNTS FOR TABS BAR
    ========================================================= */

    // Total flags
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM job_flags");
    $stmt->execute();
    $total_flags = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // Under review (reviewed = 0)
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM job_flags WHERE reviewed = 0");
    $stmt->execute();
    $under_review = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // Resolved (reviewed = 1)
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM job_flags WHERE reviewed = 1");
    $stmt->execute();
    $resolved = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // Blocked jobs (job status = 'closed' or admin_action = rejected)
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM jobs 
        WHERE status = 'closed' OR admin_action = 'rejected'
    ");
    $stmt->execute();
    $blocked_jobs = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();


    /* =========================================================
       2️⃣ JOB FLAG LIST DETAILS  
       JOIN job_flags → jobs → recruiter_profiles
    ========================================================= */

    $sql = "
        SELECT 
            jf.id AS flag_id,
            jf.job_id,
            jf.flagged_by,
            jf.reason,
            jf.reviewed,
            jf.admin_action,
            jf.created_at,

            j.title AS job_title,
            j.recruiter_id,
            j.status AS job_status,

            rp.company_name,

            sp.id AS student_profile_id,
            u.user_name AS student_name
        FROM job_flags jf
        LEFT JOIN jobs j ON jf.job_id = j.id
        LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        LEFT JOIN student_profiles sp ON jf.flagged_by = sp.id
        LEFT JOIN users u ON sp.user_id = u.id
        ORDER BY jf.created_at DESC
    ";

    $result = $conn->query($sql);

    $flags = [];
    while ($row = $result->fetch_assoc()) {

        // UI Severity Logic
        $severity = "Low";
        if (stripos($row['reason'], "fake") !== false) $severity = "High";
        if (stripos($row['reason'], "duplicate") !== false) $severity = "Medium";
        if (stripos($row['reason'], "spam") !== false) $severity = "High";
        if (stripos($row['reason'], "suspicious") !== false) $severity = "Medium";

        // UI Status Logic
        $status = "Under Review";
        if ($row['reviewed'] == 1) $status = "Resolved";
        if ($row['admin_action'] === "rejected") $status = "Blocked";

        $flags[] = [
            "flag_id"      => intval($row['flag_id']),
            "job_id"       => intval($row['job_id']),
            "job_title"    => $row['job_title'],
            "company_name" => $row['company_name'],
            "reason"       => $row['reason'],
            "severity"     => $severity,
            "status"       => $status,
            "created_at"   => $row['created_at']
        ];
    }


    /* =========================================================
       3️⃣ FINAL RESPONSE (MATCHES UI EXACTLY)
    ========================================================= */

    echo json_encode([
        "status" => true,
        "message" => "Job Flag data loaded",

        // 🔵 Top Summary Tabs (same as UI)
        "summary" => [
            "total_flags"   => $total_flags,
            "under_review"  => $under_review,
            "resolved"      => $resolved,
            "blocked_jobs"  => $blocked_jobs
        ],

        // 🟦 Job Flagging Table
        "flags" => $flags
    ]);

} catch (Exception $e) {

    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

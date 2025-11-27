<?php
// recruiter_analytics_reports.php
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// ✅ Authenticate Recruiter or Admin
$decoded = authenticateJWT(['recruiter', 'admin']);
$user_id = intval($decoded['user_id'] ?? 0);

try {
    // --------------------------------------------------------------------
    // 🔹 Step 1: Get recruiter_id from recruiter_profiles (via user_id)
    // --------------------------------------------------------------------
    $stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recruiter = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$recruiter) {
        echo json_encode([
            "status" => false,
            "message" => "Recruiter profile not found for this user"
        ]);
        exit;
    }

    $recruiter_id = intval($recruiter['id']);

    // --------------------------------------------------------------------
    // 🔹 Step 2: Applications by Department (Job Category)
    // --------------------------------------------------------------------
    $applications_sql = "
        SELECT 
            COALESCE(j.title, 'Uncategorized') AS department,
            COUNT(a.id) AS applied,
            SUM(a.status IN ('shortlisted','selected')) AS shortlisted,
            SUM(a.status = 'selected') AS hired
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.id
        WHERE j.recruiter_id = ?
        GROUP BY j.title
        ORDER BY applied DESC
    ";

    $stmt1 = $conn->prepare($applications_sql);
    $stmt1->bind_param("i", $recruiter_id);
    $stmt1->execute();
    $res1 = $stmt1->get_result();

    $applications_by_department = [];
    while ($row = $res1->fetch_assoc()) {
        $applications_by_department[] = [
            "department"   => $row["department"],
            "applied"      => intval($row["applied"]),
            "shortlisted"  => intval($row["shortlisted"]),
            "hired"        => intval($row["hired"])
        ];
    }
    $stmt1->close();

    // --------------------------------------------------------------------
    // 🔹 Step 3: Source of Hire (Pie Chart) - Only Real Data
    // --------------------------------------------------------------------
    $count = function($sql) use ($conn, $recruiter_id) {
        $val = 0; // ✅ pre-declare variable to remove warning
        $st = $conn->prepare($sql);
        $st->bind_param("i", $recruiter_id);
        $st->execute();
        $st->bind_result($val);
        $st->fetch();
        $st->close();
        return intval($val);
    };

    $job_portal      = $count("SELECT COUNT(a.id) FROM applications a INNER JOIN jobs j ON a.job_id = j.id WHERE j.recruiter_id = ?");
    $referrals       = $count("SELECT COUNT(r.id) FROM referrals r INNER JOIN jobs j ON r.job_id = j.id WHERE j.recruiter_id = ?");
    $career_fair     = $count("SELECT COUNT(sj.id) FROM saved_jobs sj INNER JOIN jobs j ON sj.job_id = j.id WHERE j.recruiter_id = ?");
    $interview_stage = $count("SELECT COUNT(DISTINCT i.id) FROM interviews i INNER JOIN applications a ON i.application_id = a.id INNER JOIN jobs j ON a.job_id = j.id WHERE j.recruiter_id = ?");

    $source_of_hire = [
        ["source" => "Job Portal", "count" => $job_portal],
        ["source" => "Referrals", "count" => $referrals],
        ["source" => "Career Fair", "count" => $career_fair],
        ["source" => "Interview Stage", "count" => $interview_stage]
    ];

    // --------------------------------------------------------------------
    // 🔹 Step 4: Key Metrics Summary (Cards) + Added Total Jobs & Applications
    // --------------------------------------------------------------------
    $getValue = function($sql) use ($conn, $recruiter_id) {
        $val = 0; 
        $st = $conn->prepare($sql);
        $st->bind_param("i", $recruiter_id);
        $st->execute();
        $st->bind_result($val);
        $st->fetch();
        $st->close();
        return intval($val);
    };

    // ✅ Total Jobs Posted by Recruiter
    $total_jobs = $getValue("
        SELECT COUNT(id)
        FROM jobs
        WHERE recruiter_id = ?
    ");

    // ✅ Total Applications Received
    $total_applications = $getValue("
        SELECT COUNT(a.id)
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.id
        WHERE j.recruiter_id = ?
    ");

    // ✅ Total Interviews
    $total_interviews = $getValue("
        SELECT COUNT(i.id)
        FROM interviews i
        INNER JOIN applications a ON i.application_id = a.id
        INNER JOIN jobs j ON a.job_id = j.id
        WHERE j.recruiter_id = ?
    ");

    // ✅ Total Hires
    $total_hires = $getValue("
        SELECT COUNT(a.id)
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.id
        WHERE j.recruiter_id = ? AND a.status = 'selected'
    ");

    // 💰 Total Recruiter Spending
    $stmt7 = $conn->prepare("
        SELECT COALESCE(SUM(t.amount), 0)
        FROM transactions t
        INNER JOIN recruiter_profiles rp ON rp.user_id = t.user_id
        WHERE rp.id = ? AND t.status = 'success'
    ");
    $stmt7->bind_param("i", $recruiter_id);
    $stmt7->execute();
    $stmt7->bind_result($total_spent);
    $stmt7->fetch();
    $stmt7->close();

    // 💹 Derived Metrics
    $avg_cost_per_hire = ($total_hires > 0)
        ? "₹" . number_format($total_spent / $total_hires, 1) . "L"
        : "₹0.0L";

    $interview_to_hire_ratio = ($total_interviews > 0)
        ? round(($total_hires / $total_interviews) * 100, 1) . "%"
        : "0%";

    // --------------------------------------------------------------------
    // ✅ Step 5: Final JSON Output (Matches React UI)
    // --------------------------------------------------------------------
    echo json_encode([
        "status"  => true,
        "message" => "Recruiter Analytics Report Generated",
        "data" => [
            "applications_by_department" => $applications_by_department,
            "source_of_hire" => $source_of_hire,
            "key_metrics" => [
                "total_jobs"              => $total_jobs,
                "total_applications"      => $total_applications,
                "total_interviews"        => $total_interviews,
                "total_hires"             => $total_hires,
                "interview_to_hire_ratio" => $interview_to_hire_ratio,
                "avg_cost_per_hire"       => $avg_cost_per_hire
            ]
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error generating recruiter analytics: " . $e->getMessage()
    ]);
}

$conn->close();
?>

<?php
// recruiter_analytics_reports.php
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// ✅ Authenticate Recruiter or Admin
$decoded = authenticateJWT(['recruiter', 'admin']);
$role = strtolower($decoded['role']);
$user_id = intval($decoded['user_id'] ?? 0);

try {
    // --------------------------------------------------------------------
    // 🔹 Step 1: Get recruiter_id from recruiter_profiles (using user_id)
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
            COALESCE(jc.category_name, 'Uncategorized') AS department,
            COUNT(a.id) AS total_applications,
            SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) AS shortlisted,
            SUM(CASE WHEN a.status = 'selected' THEN 1 ELSE 0 END) AS hired
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.id
        LEFT JOIN job_category jc ON j.category_id = jc.id
        WHERE j.recruiter_id = ?
        GROUP BY jc.category_name
        ORDER BY total_applications DESC
    ";

    $stmt1 = $conn->prepare($applications_sql);
    $stmt1->bind_param("i", $recruiter_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();

    $applications_by_department = [];
    while ($row = $result1->fetch_assoc()) {
        $applications_by_department[] = [
            "department" => $row["department"],
            "total_applications" => intval($row["total_applications"]),
            "shortlisted" => intval($row["shortlisted"]),
            "hired" => intval($row["hired"])
        ];
    }
    $stmt1->close();

    // --------------------------------------------------------------------
    // 🔹 Step 3: Source of Hire (Dynamic)
    // --------------------------------------------------------------------
    // 1️⃣ Job Portal
    $sql_jobportal = "
        SELECT COUNT(a.id) AS total 
        FROM applications a 
        INNER JOIN jobs j ON a.job_id = j.id 
        WHERE j.recruiter_id = ?
    ";
    $stmtA = $conn->prepare($sql_jobportal);
    $stmtA->bind_param("i", $recruiter_id);
    $stmtA->execute();
    $stmtA->bind_result($job_portal);
    $stmtA->fetch();
    $stmtA->close();

    // 2️⃣ Referrals
    $sql_referrals = "
        SELECT COUNT(r.id) AS total 
        FROM referrals r 
        INNER JOIN jobs j ON r.job_id = j.id 
        WHERE j.recruiter_id = ?
    ";
    $stmtB = $conn->prepare($sql_referrals);
    $stmtB->bind_param("i", $recruiter_id);
    $stmtB->execute();
    $stmtB->bind_result($referrals);
    $stmtB->fetch();
    $stmtB->close();

    // 3️⃣ Saved Jobs
    $sql_saved = "
        SELECT COUNT(sj.id) AS total 
        FROM saved_jobs sj 
        INNER JOIN jobs j ON sj.job_id = j.id 
        WHERE j.recruiter_id = ?
    ";
    $stmtC = $conn->prepare($sql_saved);
    $stmtC->bind_param("i", $recruiter_id);
    $stmtC->execute();
    $stmtC->bind_result($saved_jobs);
    $stmtC->fetch();
    $stmtC->close();

    // 4️⃣ Interview Stage
    $sql_interview = "
        SELECT COUNT(DISTINCT i.id) AS total 
        FROM interviews i 
        INNER JOIN applications a ON i.application_id = a.id 
        INNER JOIN jobs j ON a.job_id = j.id 
        WHERE j.recruiter_id = ?
    ";
    $stmtD = $conn->prepare($sql_interview);
    $stmtD->bind_param("i", $recruiter_id);
    $stmtD->execute();
    $stmtD->bind_result($interview_stage);
    $stmtD->fetch();
    $stmtD->close();

    $source_of_hire = [
        ["source" => "Job Portal", "count" => intval($job_portal)],
        ["source" => "Referrals", "count" => intval($referrals)],
        ["source" => "Saved Jobs", "count" => intval($saved_jobs)],
        ["source" => "Interview Stage", "count" => intval($interview_stage)]
    ];

    // --------------------------------------------------------------------
    // 🔹 Step 4: Key Metrics
    // --------------------------------------------------------------------
    // 🧩 Total Jobs
    $stmt3 = $conn->prepare("SELECT COUNT(j.id) FROM jobs j WHERE j.recruiter_id = ?");
    $stmt3->bind_param("i", $recruiter_id);
    $stmt3->execute();
    $stmt3->bind_result($total_jobs);
    $stmt3->fetch();
    $stmt3->close();

    // 🧩 Total Applications
    $stmt4 = $conn->prepare("
        SELECT COUNT(a.id)
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.id
        WHERE j.recruiter_id = ?
    ");
    $stmt4->bind_param("i", $recruiter_id);
    $stmt4->execute();
    $stmt4->bind_result($total_applications);
    $stmt4->fetch();
    $stmt4->close();

    // 🧩 Total Interviews
    $stmt5 = $conn->prepare("
        SELECT COUNT(i.id)
        FROM interviews i
        INNER JOIN applications a ON i.application_id = a.id
        INNER JOIN jobs j ON a.job_id = j.id
        WHERE j.recruiter_id = ?
    ");
    $stmt5->bind_param("i", $recruiter_id);
    $stmt5->execute();
    $stmt5->bind_result($total_interviews);
    $stmt5->fetch();
    $stmt5->close();

    // 🧩 Total Hires
    $stmt6 = $conn->prepare("
        SELECT COUNT(a.id)
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.id
        WHERE j.recruiter_id = ? AND a.status = 'selected'
    ");
    $stmt6->bind_param("i", $recruiter_id);
    $stmt6->execute();
    $stmt6->bind_result($total_hires);
    $stmt6->fetch();
    $stmt6->close();

    // 🧩 Total Recruiter Spending (from transactions)
    $stmt7 = $conn->prepare("
        SELECT COALESCE(SUM(t.amount), 0)
        FROM transactions t
        INNER JOIN users u ON t.user_id = u.id
        INNER JOIN recruiter_profiles rp ON rp.user_id = u.id
        WHERE rp.id = ? AND t.status = 'success'
    ");
    $stmt7->bind_param("i", $recruiter_id);
    $stmt7->execute();
    $stmt7->bind_result($total_spent);
    $stmt7->fetch();
    $stmt7->close();

    // 🧩 Average Cost per Hire (real formula)
    if ($total_hires > 0) {
        $avg_cost_per_hire = "₹" . number_format(($total_spent / $total_hires), 2);
    } else {
        $avg_cost_per_hire = "₹0.00";
    }

    // 🧩 Interview-to-Hire ratio
    $ratio = ($total_interviews > 0) ? round(($total_hires / $total_interviews) * 100, 2) : 0;

    // --------------------------------------------------------------------
    // ✅ Final JSON Response
    // --------------------------------------------------------------------
    echo json_encode([
        "status" => true,
        "message" => "Recruiter Analytics Report Generated",
        "data" => [
            "applications_by_department" => $applications_by_department,
            "source_of_hire" => $source_of_hire,
            "key_metrics" => [
                "total_jobs" => intval($total_jobs),
                "total_applications" => intval($total_applications),
                "total_interviews" => intval($total_interviews),
                "total_hires" => intval($total_hires),
                "total_spent" => "₹" . number_format($total_spent, 2),
                "interview_to_hire_ratio" => $ratio . "%",
                "avg_cost_per_hire" => $avg_cost_per_hire
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

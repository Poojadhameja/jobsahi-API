<?php
require_once '../cors.php';
require_once '../db.php';

// Authenticate Admin only
$decoded = authenticateJWT(['admin']);
$admin_id = intval($decoded['user_id']);

// -------------------------------
// FETCH DATA (admin_action removed)
// -------------------------------
$sql = "
    SELECT 
        rp.id AS recruiter_id,
        rp.company_name,
        rp.industry,
        u.user_name AS recruiter_name,
        rp.company_logo,

        -- Job Status (Active/Inactive)
        CASE 
            WHEN j.status = 'open' THEN 'Active'
            ELSE 'Inactive'
        END AS status,

        -- Count total jobs posted
        (SELECT COUNT(*) FROM jobs WHERE recruiter_id = rp.id AND deleted_at IS NULL) AS jobs_posted,

        -- Total Applicants
        (
            SELECT COUNT(*) 
            FROM applications a 
            WHERE a.job_id IN (
                SELECT id FROM jobs WHERE recruiter_id = rp.id
            )
            AND a.deleted_at IS NULL
        ) AS total_applicants,

        -- Shortlisted Applicants
        (
            SELECT COUNT(*) 
            FROM applications a 
            WHERE a.job_id IN (
                SELECT id FROM jobs WHERE recruiter_id = rp.id
            )
            AND a.status = 'shortlisted'
            AND a.deleted_at IS NULL
        ) AS shortlisted,

        -- Last Activity (Job Updated Date)
        (
            SELECT DATE(updated_at) 
            FROM jobs 
            WHERE recruiter_id = rp.id
            ORDER BY updated_at DESC
            LIMIT 1
        ) AS last_activity

    FROM recruiter_profiles rp
    LEFT JOIN users u ON u.id = rp.user_id
    LEFT JOIN jobs j ON j.recruiter_id = rp.id
    WHERE rp.deleted_at IS NULL 
    GROUP BY rp.id
    ORDER BY rp.company_name ASC
";

$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "recruiter_id"     => intval($row["recruiter_id"]),
        "company_name"     => $row["company_name"],
        "recruiter_name"   => $row["recruiter_name"],
        "industry"         => $row["industry"],
        "status"           => $row["status"],
        "jobs_posted"      => intval($row["jobs_posted"]),
        "total_applicants" => intval($row["total_applicants"]),
        "shortlisted"      => intval($row["shortlisted"]),
        "last_activity"    => $row["last_activity"] ?: "-"
    ];
}

// -------------------------------
// SEND RESPONSE
// -------------------------------
echo json_encode([
    "status" => true,
    "message" => "Job Posting Analytics Loaded",
    "data" => $data
]);
exit;
?>
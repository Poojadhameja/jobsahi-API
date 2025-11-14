<?php
require_once '../cors.php';
require_once '../db.php';

try {
    authenticateJWT(['admin']);

    // JOIN employer_feedback → jobs → recruiter_profiles
    $query = "
       SELECT 
    f.id,
    f.rating,
    f.feedback,
    f.created_at,
    r.company_name,
    j.title AS job_title       -- change this based on your DB
FROM employer_feedback f
LEFT JOIN recruiter_profiles r ON r.id = f.recruiter_id
LEFT JOIN jobs j ON j.id = f.job_id
ORDER BY f.created_at DESC

    ";

    $result = $conn->query($query);

    $feedback_list = [];

    while ($row = $result->fetch_assoc()) {
        $feedback_list[] = [
            "id"            => intval($row['id']),
            "company_name"  => $row['company_name'],
            "job_title"     => $row['job_title'] ?? "N/A",
            "rating"        => intval($row['rating']),
            "feedback"      => $row['feedback'],
            "created_at"    => $row['created_at']
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $feedback_list
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>

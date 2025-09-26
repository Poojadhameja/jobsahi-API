<?php
// get_user_stats.php - Get user statistics
require_once '../cors.php';

$stats_sql = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
        SUM(CASE WHEN role = 'recruiter' THEN 1 ELSE 0 END) as recruiters,
        SUM(CASE WHEN role = 'institute' THEN 1 ELSE 0 END) as institutes,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
        SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as unverified_users
    FROM users
";

$result = mysqli_query($conn, $stats_sql) or die("SQL Query Failed");

if (mysqli_num_rows($result) > 0) {
    $stats = mysqli_fetch_assoc($result);
    echo json_encode(array("stats" => $stats, "status" => true));
} else {
    echo json_encode(array("message" => "No data found", "status" => false));
}
?>
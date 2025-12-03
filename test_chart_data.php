<?php
// Test script to check database data for chart
require_once 'api/db.php';

// Replace with your recruiter_id to test
$recruiter_id = 1; // Change this to your recruiter_id

echo "=== Testing Chart Data Query ===\n\n";

// Test 1: Check if recruiter has jobs
echo "1. Checking jobs for recruiter_id = $recruiter_id:\n";
$sql_jobs = "SELECT id, title, category_id, admin_action FROM jobs WHERE recruiter_id = ?";
$stmt = $conn->prepare($sql_jobs);
$stmt->bind_param("i", $recruiter_id);
$stmt->execute();
$result = $stmt->get_result();
$jobs = $result->fetch_all(MYSQLI_ASSOC);
echo "   Found " . count($jobs) . " jobs\n";
foreach ($jobs as $job) {
    echo "   - Job ID: {$job['id']}, Title: {$job['title']}, Category ID: {$job['category_id']}, Admin Action: {$job['admin_action']}\n";
}
$stmt->close();

// Test 2: Check applications for these jobs
echo "\n2. Checking applications for these jobs:\n";
if (!empty($jobs)) {
    $job_ids = array_column($jobs, 'id');
    $job_ids_str = implode(',', $job_ids);
    $sql_apps = "SELECT COUNT(*) as total FROM applications WHERE job_id IN ($job_ids_str)";
    $result = $conn->query($sql_apps);
    $row = $result->fetch_assoc();
    echo "   Total applications: {$row['total']}\n";
    
    // Current month applications
    $sql_month = "SELECT COUNT(*) as total FROM applications 
                   WHERE job_id IN ($job_ids_str) 
                   AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                   AND created_at < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')";
    $result = $conn->query($sql_month);
    $row = $result->fetch_assoc();
    echo "   Current month applications: {$row['total']}\n";
}

// Test 3: Check chart data query (current month)
echo "\n3. Testing chart data query (current month):\n";
$sql_chart = "
    SELECT 
        COALESCE(jc.category_name, 'Uncategorized') AS trade_name,
        COUNT(a.id) AS total_applications
    FROM jobs j
    LEFT JOIN job_category jc ON j.category_id = jc.id
    LEFT JOIN applications a 
        ON a.job_id = j.id
       AND a.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND a.created_at < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
    WHERE j.recruiter_id = ?
      AND (j.admin_action = 'approved' OR j.admin_action IS NULL)
    GROUP BY jc.category_name
    HAVING total_applications > 0
    ORDER BY total_applications DESC
    LIMIT 10
";
$stmt = $conn->prepare($sql_chart);
$stmt->bind_param("i", $recruiter_id);
$stmt->execute();
$result = $stmt->get_result();
$chart_data = $result->fetch_all(MYSQLI_ASSOC);
echo "   Found " . count($chart_data) . " trades with applications\n";
foreach ($chart_data as $row) {
    echo "   - Trade: {$row['trade_name']}, Applications: {$row['total_applications']}\n";
}
$stmt->close();

// Test 4: Check all-time chart data
echo "\n4. Testing chart data query (all-time):\n";
$sql_alltime = "
    SELECT 
        COALESCE(jc.category_name, 'Uncategorized') AS trade_name,
        COUNT(a.id) AS total_applications
    FROM jobs j
    LEFT JOIN job_category jc ON j.category_id = jc.id
    LEFT JOIN applications a ON a.job_id = j.id
    WHERE j.recruiter_id = ?
      AND (j.admin_action = 'approved' OR j.admin_action IS NULL)
    GROUP BY jc.category_name
    HAVING total_applications > 0
    ORDER BY total_applications DESC
    LIMIT 10
";
$stmt = $conn->prepare($sql_alltime);
$stmt->bind_param("i", $recruiter_id);
$stmt->execute();
$result = $stmt->get_result();
$chart_data_alltime = $result->fetch_all(MYSQLI_ASSOC);
echo "   Found " . count($chart_data_alltime) . " trades with applications (all-time)\n";
foreach ($chart_data_alltime as $row) {
    echo "   - Trade: {$row['trade_name']}, Applications: {$row['total_applications']}\n";
}
$stmt->close();

echo "\n=== Summary ===\n";
echo "If you see 0 applications, then the issue is: NO DATA IN DATABASE\n";
echo "If you see applications but chart_data is empty, then the issue is: CODE LOGIC\n";

$conn->close();
?>


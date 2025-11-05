<?php
// create_job_recommendation.php (or similar POST endpoint)
require_once '../cors.php';

$decoded = authenticateJWT(['admin', 'institute']);

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$student_id = $input['student_id'] ?? null;
$job_id = $input['job_id'] ?? null;
$source = $input['source'] ?? 'manual';
$score = $input['score'] ?? 0.0;
$admin_action = $input['admin_action'] ?? 'approved'; // ✅ Default to 'approved'

// Validation
if (!$student_id || !$job_id) {
    echo json_encode(["message" => "student_id and job_id are required", "status" => false]);
    exit;
}

// Insert recommendation
$insert_sql = "INSERT INTO job_recommendations (student_id, job_id, source, score, admin_action, created_at) 
               VALUES (?, ?, ?, ?, ?, NOW())";

$stmt = mysqli_prepare($conn, $insert_sql);
mysqli_stmt_bind_param($stmt, "iisds", $student_id, $job_id, $source, $score, $admin_action);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "message" => "Job recommendation created successfully",
        "status" => true,
        "recommendation_id" => mysqli_insert_id($conn)
    ]);
} else {
    echo json_encode([
        "message" => "Failed to create recommendation: " . mysqli_error($conn),
        "status" => false
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
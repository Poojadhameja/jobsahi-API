<?php
include '../CORS.php';
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']); // returns array

try {
    // First, let's check what columns exist in jobs table
    $checkJobs = $conn->query("DESCRIBE jobs");
    
    if (!$checkJobs) {
        throw new Exception("Cannot access jobs table structure");
    }
    
    // Get column names for jobs table
    $jobColumns = [];
    while ($row = $checkJobs->fetch_assoc()) {
        $jobColumns[] = $row['Field'];
    }
    
    // Determine the correct job ID column name
    $jobIdColumn = 'id'; // default
    if (in_array('job_id', $jobColumns)) {
        $jobIdColumn = 'job_id';
    } elseif (in_array('id', $jobColumns)) {
        $jobIdColumn = 'id';
    }
    
    // Check if required columns exist in jobs table
    $titleColumn = in_array('title', $jobColumns) ? 'title' : (in_array('job_title', $jobColumns) ? 'job_title' : 'NULL');
    $descriptionColumn = in_array('description', $jobColumns) ? 'description' : (in_array('job_description', $jobColumns) ? 'job_description' : 'NULL');
    $statusColumn = in_array('status', $jobColumns) ? 'status' : (in_array('is_active', $jobColumns) ? 'is_active' : 'NULL');
    $createdAtColumn = in_array('created_at', $jobColumns) ? 'created_at' : (in_array('date_created', $jobColumns) ? 'date_created' : 'NULL');
    $companyColumn = in_array('company', $jobColumns) ? 'company' : (in_array('company_name', $jobColumns) ? 'company_name' : 'NULL');
    $locationColumn = in_array('location', $jobColumns) ? 'location' : (in_array('job_location', $jobColumns) ? 'job_location' : 'NULL');
    $salaryColumn = in_array('salary', $jobColumns) ? 'salary' : (in_array('salary_range', $jobColumns) ? 'salary_range' : 'NULL');
    $typeColumn = in_array('type', $jobColumns) ? 'type' : (in_array('job_type', $jobColumns) ? 'job_type' : 'NULL');
    $postedByColumn = in_array('posted_by', $jobColumns) ? 'posted_by' : (in_array('user_id', $jobColumns) ? 'user_id' : 'NULL');
    
    // Build the query with correct column names
    $stmt = $conn->prepare("
        SELECT 
            {$jobIdColumn} as job_id,
            {$titleColumn} as title,
            {$descriptionColumn} as description,
            {$companyColumn} as company,
            {$locationColumn} as location,
            {$salaryColumn} as salary,
            {$typeColumn} as job_type,
            {$statusColumn} as status,
            {$postedByColumn} as posted_by,
            {$createdAtColumn} as created_at
        FROM jobs
        ORDER BY {$createdAtColumn} DESC
    ");
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $jobs = [];
        
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "message" => "Job postings retrieved successfully",
            "data" => $jobs,
            "count" => count($jobs)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve job postings",
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
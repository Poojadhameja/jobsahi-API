<?php
include '../CORS.php';
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']); // returns array

try {
    // Get job ID from URL parameter
    $job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($job_id <= 0) {
        throw new Exception("Invalid job ID provided");
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['status'])) {
        throw new Exception("Status is required");
    }
    
    $new_status = trim($input['status']);
    $admin_notes = isset($input['admin_notes']) ? trim($input['admin_notes']) : '';
    
    // Validate status values
    $allowed_statuses = ['approved', 'rejected', 'pending'];
    if (!in_array($new_status, $allowed_statuses)) {
        throw new Exception("Invalid status. Allowed values: " . implode(', ', $allowed_statuses));
    }
    
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
    $statusColumn = in_array('status', $jobColumns) ? 'status' : (in_array('job_status', $jobColumns) ? 'job_status' : 'status');
    $notesColumn = in_array('admin_notes', $jobColumns) ? 'admin_notes' : (in_array('notes', $jobColumns) ? 'notes' : null);
    $updatedAtColumn = in_array('updated_at', $jobColumns) ? 'updated_at' : (in_array('modified_at', $jobColumns) ? 'modified_at' : null);
    
    // Check if job exists first
    $checkStmt = $conn->prepare("SELECT {$jobIdColumn} FROM jobs WHERE {$jobIdColumn} = ?");
    $checkStmt->bind_param("i", $job_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception("Job not found");
    }
    
    // Build the update query dynamically based on available columns
    $updateFields = ["{$statusColumn} = ?"];
    $params = [$new_status];
    $types = "s";
    
    if ($notesColumn && !empty($admin_notes)) {
        $updateFields[] = "{$notesColumn} = ?";
        $params[] = $admin_notes;
        $types .= "s";
    }
    
    if ($updatedAtColumn) {
        $updateFields[] = "{$updatedAtColumn} = NOW()";
    }
    
    $updateQuery = "UPDATE jobs SET " . implode(', ', $updateFields) . " WHERE {$jobIdColumn} = ?";
    $params[] = $job_id;
    $types .= "i";
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Get updated job details
            $selectFields = [
                "j.{$jobIdColumn} as job_id",
                "j.{$statusColumn} as status"
            ];
            
            if ($notesColumn) {
                $selectFields[] = "j.{$notesColumn} as admin_notes";
            }
            
            if ($updatedAtColumn) {
                $selectFields[] = "j.{$updatedAtColumn} as updated_at";
            }
            
            // Add other common job fields if they exist
            $commonFields = ['title', 'company_name', 'job_title', 'description'];
            foreach ($commonFields as $field) {
                if (in_array($field, $jobColumns)) {
                    $selectFields[] = "j.{$field}";
                }
            }
            
            $selectQuery = "SELECT " . implode(', ', $selectFields) . " FROM jobs j WHERE j.{$jobIdColumn} = ?";
            $selectStmt = $conn->prepare($selectQuery);
            $selectStmt->bind_param("i", $job_id);
            $selectStmt->execute();
            $result = $selectStmt->get_result();
            $updatedJob = $result->fetch_assoc();
            
            echo json_encode([
                "status" => true,
                "message" => "Job status updated successfully",
                "data" => $updatedJob
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "No changes made to job status"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update job status",
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
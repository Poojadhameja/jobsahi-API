<?php 
// create_reports.php - Create new report (Admin, Recruiter access) 
require_once '../cors.php'; 

// ✅ Authenticate JWT and allow only Admin, Student, Institute & Recruiter to create reports 
$decoded = authenticateJWT(['admin', 'recruiter', 'student', 'institute']);  
$user_id = $decoded['user_id'];  
$user_role = $decoded['role']; // role must be available from JWT 

// ✅ Get POST data 
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Debug: Log the received data
error_log("Received input: " . $input);
error_log("Parsed data: " . print_r($data, true));

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid JSON data",
        "json_error" => json_last_error_msg()
    ]);
    exit;
}

$report_type     = isset($data['report_type']) ? $data['report_type'] : ''; 
$filters_applied = isset($data['filters_applied']) ? json_encode($data['filters_applied']) : '{}'; 
$download_url    = isset($data['download_url']) ? $data['download_url'] : ''; 
$admin_action    = isset($data['admin_action']) ? $data['admin_action'] : 'pending';

// Debug: Log the extracted values
error_log("user_id: $user_id");
error_log("report_type: '$report_type'");
error_log("filters_applied: $filters_applied");
error_log("download_url: $download_url");
error_log("admin_action: $admin_action");

// Validate required fields
if (empty($report_type)) {
    echo json_encode([
        "status" => false,
        "message" => "Report type is required"
    ]);
    exit;
}

try { 
    // ✅ Insert report 
    $stmt = $conn->prepare("INSERT INTO reports  
        (generated_by, report_type, filters_applied, download_url, generated_at, admin_action) 
        VALUES (?, ?, ?, ?, NOW(), ?)"); 

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("issss", $user_id, $report_type, $filters_applied, $download_url, $admin_action); 

    // Debug: Log the SQL query
    error_log("Executing query with values: user_id=$user_id, report_type='$report_type', filters_applied='$filters_applied', download_url='$download_url', admin_action='$admin_action'");

    if ($stmt->execute()) { 
        $report_id = $stmt->insert_id;
        
        // Debug: Verify the insert by selecting the record
        $verify_stmt = $conn->prepare("SELECT * FROM reports WHERE id = ?");
        $verify_stmt->bind_param("i", $report_id);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        $inserted_row = $result->fetch_assoc();
        
        error_log("Inserted row: " . print_r($inserted_row, true));
        
        echo json_encode([ 
            "status" => true, 
            "message" => "Report created successfully", 
            "report_id" => $report_id,
            "debug_inserted_data" => $inserted_row // Remove this in production
        ]); 
    } else { 
        echo json_encode([ 
            "status" => false, 
            "message" => "Failed to create report", 
            "error" => $stmt->error 
        ]); 
    } 
    
    $stmt->close();
    
} catch (Exception $e) { 
    echo json_encode([ 
        "status" => false, 
        "message" => "Error: " . $e->getMessage() 
    ]); 
} 

$conn->close(); 
?>
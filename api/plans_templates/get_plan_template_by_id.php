<?php
// get_plan_template_by_id.php - Get plan template by ID (JWT required)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT (any valid user can access plan templates)
$decoded = authenticateJWT(); // returns array
$userRole = isset($decoded['role']) ? strtoupper($decoded['role']) : null;

// Get the ID from URL parameter
$template_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$template_id) {
    echo json_encode([
        "status" => false,
        "message" => "Template ID is required"
    ]);
    exit();
}

try {
    // First, let's check what columns exist in plan_templates table
    $checkTemplates = $conn->query("DESCRIBE plan_templates");
    
    if (!$checkTemplates) {
        throw new Exception("Cannot access plan_templates table structure");
    }
    
    // Get column names for plan_templates table
    $templatesColumns = [];
    while ($row = $checkTemplates->fetch_assoc()) {
        $templatesColumns[] = $row['Field'];
    }
    
    // Determine the correct ID column name
    $idColumn = 'id'; // default
    if (in_array('template_id', $templatesColumns)) {
        $idColumn = 'template_id';
    } elseif (in_array('id', $templatesColumns)) {
        $idColumn = 'id';
    }
    
    // Check if required columns exist in plan_templates table based on the actual schema
    $titleColumn = in_array('title', $templatesColumns) ? 'title' : 'NULL';
    $typeColumn = in_array('type', $templatesColumns) ? 'type' : 'NULL';
    $priceColumn = in_array('price', $templatesColumns) ? 'price' : 'NULL';
    $durationDaysColumn = in_array('duration_days', $templatesColumns) ? 'duration_days' : 'NULL';
    $featuresJsonColumn = in_array('features_json', $templatesColumns) ? 'features_json' : 'NULL';
    $adminActionColumn = in_array('admin_action', $templatesColumns) ? 'admin_action' : 'NULL';

    // ✅ Role-based filtering for admin_action
    $adminActionCondition = "";
    if ($userRole === 'ADMIN') {
        // ADMIN can see all (pending + approval)
        $adminActionCondition = "({$adminActionColumn} = 'pending' OR {$adminActionColumn} = 'approval')";
    } else {
        // Recruiter, Institute, Student → can see only approval
        $adminActionCondition = "({$adminActionColumn} = 'approval')";
    }

    // Build the query with correct column names matching the actual schema
    $stmt = $conn->prepare("
        SELECT 
            {$idColumn} as id,
            {$titleColumn} as title,
            {$typeColumn} as type,
            {$priceColumn} as price,
            {$durationDaysColumn} as duration_days,
            {$featuresJsonColumn} as features_json,
            {$adminActionColumn} as admin_action
        FROM plan_templates
        WHERE {$idColumn} = ? AND {$adminActionCondition}
    ");
    
    $stmt->bind_param("i", $template_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $template = $result->fetch_assoc();
        
        if ($template) {
            echo json_encode([
                "status" => true,
                "message" => "Plan template retrieved successfully",
                "data" => $template
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Plan template not found or access denied"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve plan template",
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

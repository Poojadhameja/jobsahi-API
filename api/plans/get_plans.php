<?php
// get_plans_templates.php - Get all plan templates (JWT required)
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

try {
    // First, check structure of plan_templates table
    $checkPlans = $conn->query("DESCRIBE plan_templates");
    
    if (!$checkPlans) {
        throw new Exception("Cannot access plan_templates table structure");
    }
    
    // Get column names for plan_templates table
    $plansColumns = [];
    while ($row = $checkPlans->fetch_assoc()) {
        $plansColumns[] = $row['Field'];
    }
    
    // Determine the correct ID column name
    $idColumn = 'id'; // default
    if (in_array('plan_template_id', $plansColumns)) {
        $idColumn = 'plan_template_id';
    } elseif (in_array('id', $plansColumns)) {
        $idColumn = 'id';
    }
    
    // Check if required columns exist
    $titleColumn = in_array('title', $plansColumns) ? 'title' : 'NULL';
    $typeColumn = in_array('type', $plansColumns) ? 'type' : 'NULL';
    $priceColumn = in_array('price', $plansColumns) ? 'price' : 'NULL';
    $durationDaysColumn = in_array('duration_days', $plansColumns) ? 'duration_days' : 'NULL';
    $featuresJsonColumn = in_array('features_json', $plansColumns) ? 'features_json' : 'NULL';
    
    // Build query
    $stmt = $conn->prepare("
        SELECT 
            {$idColumn} as id,
            {$titleColumn} as title,
            {$typeColumn} as type,
            {$priceColumn} as price,
            {$durationDaysColumn} as duration_days,
            {$featuresJsonColumn} as features_json
        FROM plan_templates
        ORDER BY {$priceColumn} ASC
    ");
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $plans = [];
        
        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "message" => "Plan templates retrieved successfully",
            "data" => $plans,
            "count" => count($plans)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve plan templates",
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

<?php
include '../CORS.php';
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT (any valid user can access plans)
$decoded = authenticateJWT(); // returns array

try {
    // First, let's check what columns exist in plans table
    $checkPlans = $conn->query("DESCRIBE plans");
    
    if (!$checkPlans) {
        throw new Exception("Cannot access plans table structure");
    }
    
    // Get column names for plans table
    $plansColumns = [];
    while ($row = $checkPlans->fetch_assoc()) {
        $plansColumns[] = $row['Field'];
    }
    
    // Determine the correct ID column name
    $idColumn = 'id'; // default
    if (in_array('plan_id', $plansColumns)) {
        $idColumn = 'plan_id';
    } elseif (in_array('id', $plansColumns)) {
        $idColumn = 'id';
    }
    
    // Check if required columns exist in plans table based on the actual schema
    $titleColumn = in_array('title', $plansColumns) ? 'title' : 'NULL';
    $typeColumn = in_array('type', $plansColumns) ? 'type' : 'NULL';
    $priceColumn = in_array('price', $plansColumns) ? 'price' : 'NULL';
    $durationDaysColumn = in_array('duration_days', $plansColumns) ? 'duration_days' : 'NULL';
    $featuresJsonColumn = in_array('features_json', $plansColumns) ? 'features_json' : 'NULL';
    
    // Build the query with correct column names matching the actual schema
    $stmt = $conn->prepare("
        SELECT 
            {$idColumn} as id,
            {$titleColumn} as title,
            {$typeColumn} as type,
            {$priceColumn} as price,
            {$durationDaysColumn} as duration_days,
            {$featuresJsonColumn} as features_json
        FROM plans
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
            "message" => "Subscription plans retrieved successfully",
            "data" => $plans,
            "count" => count($plans)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve subscription plans",
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
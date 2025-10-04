<?php
// update_plan.php - Update an existing subscription plan (Admin access only)
require_once '../cors.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']);

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    // Require plan ID for update
    if (!isset($input['id']) || empty($input['id'])) {
        throw new Exception("Plan ID is required for update");
    }
    
    $planId = $input['id'];
    
    // Check what columns exist in plans table
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
    
    // Check if required columns exist in plans table
    $titleColumn = in_array('title', $plansColumns) ? 'title' : null;
    $typeColumn = in_array('type', $plansColumns) ? 'type' : null;
    $priceColumn = in_array('price', $plansColumns) ? 'price' : null;
    $durationDaysColumn = in_array('duration_days', $plansColumns) ? 'duration_days' : null;
    $featuresJsonColumn = in_array('features_json', $plansColumns) ? 'features_json' : null;
    
    // Build UPDATE query dynamically
    $updateFields = [];
    $updateValues = [];
    $types = "";
    
    if (isset($input['title']) && $titleColumn) {
        $updateFields[] = "{$titleColumn} = ?";
        $updateValues[] = $input['title'];
        $types .= "s";
    }
    
    if (isset($input['type']) && $typeColumn) {
        $updateFields[] = "{$typeColumn} = ?";
        $updateValues[] = $input['type'];
        $types .= "s";
    }
    
    if (isset($input['price']) && $priceColumn) {
        $updateFields[] = "{$priceColumn} = ?";
        $updateValues[] = $input['price'];
        $types .= "d";
    }
    
    if (isset($input['duration_days']) && $durationDaysColumn) {
        $updateFields[] = "{$durationDaysColumn} = ?";
        $updateValues[] = $input['duration_days'];
        $types .= "i";
    }
    
    if (isset($input['features_json']) && $featuresJsonColumn) {
        $updateFields[] = "{$featuresJsonColumn} = ?";
        $updateValues[] = is_array($input['features_json']) ? json_encode($input['features_json']) : $input['features_json'];
        $types .= "s";
    }
    
    if (empty($updateFields)) {
        throw new Exception("No valid fields to update");
    }
    
    // Add plan ID to values
    $updateValues[] = $planId;
    $types .= "i";
    
    $sql = "UPDATE plans SET " . implode(', ', $updateFields) . " WHERE {$idColumn} = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$updateValues);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => true,
                "message" => "Plan updated successfully",
                "data" => ["id" => $planId]
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Plan not found or no changes made"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update plan",
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
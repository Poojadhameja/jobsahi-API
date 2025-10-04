<?php
// get_plans.php - Get subscription plans (POST, JWT required)
require_once '../cors.php';
// ✅ Authenticate JWT (any valid user can access plans)
$decoded = authenticateJWT(['recruiter','institute','admin']); // returns array with 'role'
// Get user role
$userRole = isset($decoded['role']) ? strtolower($decoded['role']) : '';
try {
    // First, check what columns exist in plans table
    $checkPlans = $conn->query("DESCRIBE plans");
    if (!$checkPlans) {
        throw new Exception("Cannot access plans table structure");
    }
    // Get column names
    $plansColumns = [];
    while ($row = $checkPlans->fetch_assoc()) {
        $plansColumns[] = $row['Field'];
    }
    // Detect correct columns
    $idColumn = in_array('plan_id', $plansColumns) ? 'plan_id' : 'id';
    $titleColumn = in_array('title', $plansColumns) ? 'title' : 'NULL';
    $typeColumn = in_array('type', $plansColumns) ? 'type' : 'NULL';
    $priceColumn = in_array('price', $plansColumns) ? 'price' : 'NULL';
    $durationDaysColumn = in_array('duration_days', $plansColumns) ? 'duration_days' : 'NULL';
    $featuresJsonColumn = in_array('features_json', $plansColumns) ? 'features_json' : 'NULL';
    $createdAtColumn = in_array('created_at', $plansColumns) ? 'created_at' : 'NULL';
    $modifiedAtColumn = in_array('modified_at', $plansColumns) ? 'modified_at' : 'NULL';
    // ✅ Build WHERE condition based on role
    // ✅ Final query
    $sql = "
        SELECT 
            {$idColumn} as id,
            {$titleColumn} as title,
            {$typeColumn} as type,
            {$priceColumn} as price,
            {$durationDaysColumn} as duration_days,
            {$featuresJsonColumn} as features_json,
            {$createdAtColumn} as created_at,
            {$modifiedAtColumn} as modified_at
        FROM plans
        ORDER BY {$priceColumn} DESC
    ";
    $stmt = $conn->prepare($sql);
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
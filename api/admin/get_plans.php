<?php
include '../CORS.php';

// ✅ Authenticate JWT (any valid user can access plans)
$decoded = authenticateJWT(); // returns array

try {
    // Check if ID is provided
    $plan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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

    // Check if required columns exist in plans table
    $titleColumn = in_array('title', $plansColumns) ? 'title' : 'NULL';
    $typeColumn = in_array('type', $plansColumns) ? 'type' : 'NULL';
    $priceColumn = in_array('price', $plansColumns) ? 'price' : 'NULL';
    $durationDaysColumn = in_array('duration_days', $plansColumns) ? 'duration_days' : 'NULL';
    $featuresJsonColumn = in_array('features_json', $plansColumns) ? 'features_json' : 'NULL';

    // ✅ If plan_id is passed → get single plan by ID
    if ($plan_id > 0) {
        $stmt = $conn->prepare("
            SELECT 
                {$idColumn} as id,
                {$titleColumn} as title,
                {$typeColumn} as type,
                {$priceColumn} as price,
                {$durationDaysColumn} as duration_days,
                {$featuresJsonColumn} as features_json
            FROM plans
            WHERE {$idColumn} = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $plan_id);
    } else {
        // ✅ Otherwise → get all plans
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
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $plans = [];

        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }

        if ($plan_id > 0 && empty($plans)) {
            echo json_encode([
                "status" => false,
                "message" => "Plan not found"
            ]);
            exit;
        }

        echo json_encode([
            "status" => true,
            "message" => $plan_id > 0
                ? "Plan fetched successfully"
                : "Subscription plans retrieved successfully",
            "data" => $plan_id > 0 ? $plans[0] : $plans,
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

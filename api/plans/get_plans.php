<?php
// get_plans.php - Get subscription plans (POST, JWT required)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT (any valid user can access plans)
$decoded = authenticateJWT(); // returns array with 'role'

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
    $adminActionColumn = in_array('admin_action', $plansColumns) ? 'admin_action' : 'NULL';

    // ✅ Build WHERE condition based on role
    $whereCondition = "";
    if ($adminActionColumn !== 'NULL') {
        if ($userRole === 'admin') {
            // Admin sees both pending + approved
            $whereCondition = "WHERE {$adminActionColumn} IN ('pending', 'approved')";
        } else {
            // Recruiter, Institute, Student see only approved
            $whereCondition = "WHERE {$adminActionColumn} = 'approved'";
        }
    }

    // ✅ Final query
    $sql = "
        SELECT 
            {$idColumn} as id,
            {$titleColumn} as title,
            {$typeColumn} as type,
            {$priceColumn} as price,
            {$durationDaysColumn} as duration_days,
            {$featuresJsonColumn} as features_json,
            {$adminActionColumn} as admin_action
        FROM plans
        {$whereCondition}
        ORDER BY {$priceColumn} ASC
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

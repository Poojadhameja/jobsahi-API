<?php
// create_plan.php - Create subscription plan (Admin access only)
require_once '../cors.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }

    // Get plans table columns
    $checkPlans = $conn->query("DESCRIBE plans");
    if (!$checkPlans) {
        throw new Exception("Cannot access plans table structure");
    }

    $plansColumns = [];
    while ($row = $checkPlans->fetch_assoc()) {
        $plansColumns[] = $row['Field'];
    }

    $insertFields = [];
    $insertPlaceholders = [];
    $insertValues = [];
    $types = "";

    if (isset($input['title']) && in_array('title', $plansColumns)) {
        $insertFields[] = 'title';
        $insertPlaceholders[] = "?";
        $insertValues[] = $input['title'];
        $types .= "s";
    }

    if (isset($input['type']) && in_array('type', $plansColumns)) {
        $insertFields[] = 'type';
        $insertPlaceholders[] = "?";
        $insertValues[] = $input['type'];
        $types .= "s";
    }

    if (isset($input['price']) && in_array('price', $plansColumns)) {
        $insertFields[] = 'price';
        $insertPlaceholders[] = "?";
        $insertValues[] = $input['price'];
        $types .= "d";
    }

    if (isset($input['duration_days']) && in_array('duration_days', $plansColumns)) {
        $insertFields[] = 'duration_days';
        $insertPlaceholders[] = "?";
        $insertValues[] = $input['duration_days'];
        $types .= "i";
    }

    if (isset($input['features_json']) && in_array('features_json', $plansColumns)) {
        $insertFields[] = 'features_json';
        $insertPlaceholders[] = "?";
        $insertValues[] = is_array($input['features_json']) ? json_encode($input['features_json']) : $input['features_json'];
        $types .= "s";
    }

    if (empty($insertFields)) {
        throw new Exception("No valid fields to insert");
    }

    $sql = "INSERT INTO plans (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$insertValues);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Plan created successfully",
            "data" => ["id" => $conn->insert_id]
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to create plan",
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

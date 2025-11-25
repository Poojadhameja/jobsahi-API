<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate (admin or institute)
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    // -----------------------------------------
    // 🟦 STEP 1: Allow Only POST
    // -----------------------------------------
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["success" => false, "message" => "Only POST method allowed"]);
        exit;
    }

    // -----------------------------------------
    // 🟦 STEP 2: Read JSON Body
    // -----------------------------------------
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || !isset($input['category_name'])) {
        echo json_encode([
            "success" => false,
            "message" => "category_name is required"
        ]);
        exit;
    }

    $category_name = trim($input['category_name']);

    if ($category_name === '') {
        echo json_encode(["success" => false, "message" => "Category name cannot be empty"]);
        exit;
    }

    // -----------------------------------------
    // 🟦 STEP 3: INSERT DIRECTLY (duplicate allowed)
    // -----------------------------------------
    $stmt = $conn->prepare("
        INSERT INTO course_category (category_name, created_at) 
        VALUES (?, NOW())
    ");

    $stmt->bind_param("s", $category_name);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Category created successfully",
            "data" => [
                "id" => $stmt->insert_id,
                "category_name" => $category_name,
                "created_at" => date("Y-m-d H:i:s")
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Database insert failed"
        ]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>

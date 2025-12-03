<?php
// get_course_category.php - Fetch categories, institute-wise usage filter
require_once '../cors.php';
require_once '../db.php';

try {
    // Authenticate user
    $decoded = authenticateJWT(['admin', 'institute', 'student']);
    $user_role = strtolower($decoded['role']);
    $user_id   = intval($decoded['user_id']);

    // -------------------------
    // => Get institute_id (if institute)
    // -------------------------
    $institute_id = 0;

    if ($user_role === 'institute') {
        $stmt = $conn->prepare("
            SELECT id 
            FROM institute_profiles 
            WHERE user_id = ? 
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $institute_id = intval($row['id']);
        }
        $stmt->close();
    }

    // Single category fetch
    $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // =======================================================
    // FETCH SINGLE CATEGORY (Admin → direct | Institute → filter by usage)
    // =======================================================
    if ($category_id > 0) {

        if ($user_role === 'admin') {
            $sql = "
                SELECT id, category_name, created_at 
                FROM course_category 
                WHERE id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $category_id);

        } else {
            // Institute — show category if it exists
            $sql = "
                SELECT id, category_name, created_at
                FROM course_category
                WHERE id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $category_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Category not found"
            ]);
            exit;
        }

        echo json_encode([
            "status" => true,
            "message" => "Category fetched successfully",
            "category" => $result->fetch_assoc()
        ]);
        exit;
    }


    // =======================================================
    // FETCH ALL CATEGORIES (Admin & Institute → all categories)
    // =======================================================

    $sql = "SELECT id, category_name, created_at FROM course_category ORDER BY id ASC";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query error: " . $conn->error);
    }

    $categories = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    echo json_encode([
        "status" => true,
        "message" => "Categories fetched successfully",
        "categories" => $categories
    ]);

} catch (Exception $e) {

    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

<?php
// get_course_category.php - Fetch categories, institute-wise usage filter
require_once '../cors.php';
require_once '../db.php';

try {
    // Authenticate user
    $decoded = authenticateJWT(['admin', 'institute']);
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
            // Institute — show only if USED by this institute
            $sql = "
                SELECT DISTINCT cc.id, cc.category_name, cc.created_at
                FROM course_category cc
                JOIN courses c ON c.category_id = cc.id
                WHERE cc.id = ? AND c.institute_id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $category_id, $institute_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Category not found for this institute"
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
    // FETCH ALL CATEGORIES (Admin → all | Institute → only used ones)
    // =======================================================

    if ($user_role === 'admin') {

        $sql = "SELECT id, category_name, created_at FROM course_category ORDER BY id ASC";

    } else {

        // Institute → only categories USED in its courses
        $sql = "
            SELECT DISTINCT cc.id, cc.category_name, cc.created_at
            FROM course_category cc
            JOIN courses c ON c.category_id = cc.id
            WHERE c.institute_id = {$institute_id}
            ORDER BY cc.id ASC
        ";
    }

    $result = $conn->query($sql);
    $categories = [];

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
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

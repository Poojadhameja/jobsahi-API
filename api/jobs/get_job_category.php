<?php
// get_job_category.php - Fetch all or single job category
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT (Admin, Recruiter allowed)
    $decoded = authenticateJWT(['admin', 'recruiter']);
    $user_id = $decoded['user_id'];
    $user_role = strtolower($decoded['role']);

    // ✅ Optional: Get job category ID from query string
    $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($category_id > 0) {
        // ✅ Fetch specific job category by ID
        $stmt = $conn->prepare("SELECT id, category_name, created_at FROM job_category WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $category = $result->fetch_assoc();
            echo json_encode([
                "status" => true,
                "message" => "Job category fetched successfully",
                "category" => [
                    "id" => intval($category['id']),
                    "category_name" => $category['category_name'],
                    "created_at" => $category['created_at']
                ]
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Job category not found"
            ]);
        }

    } else {
        // ✅ Fetch all job categories
        $sql = "SELECT id, category_name, created_at FROM job_category ORDER BY id ASC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $categories = [];
            while ($row = $result->fetch_assoc()) {
                $categories[] = [
                    "id" => intval($row['id']),
                    "category_name" => $row['category_name'],
                    "created_at" => $row['created_at']
                ];
            }

            echo json_encode([
                "status" => true,
                "message" => "Job categories fetched successfully",
                "categories" => $categories
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "No job categories found",
                "categories" => []
            ]);
        }
    }

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

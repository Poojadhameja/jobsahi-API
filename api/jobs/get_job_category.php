<?php
// get_job_category.php - Fetch all or single job category
require_once '../cors.php';
require_once '../db.php';

try {

    // ✅ Authenticate JWT (Admin, Recruiter allowed)
    $decoded = authenticateJWT(['admin', 'recruiter']);
    $user_id = intval($decoded['user_id']);
    $user_role = strtolower($decoded['role']);

    // -------------------------------------------------------
    // ✅ Recruiter Filter Logic Added (NO LOGIC CHANGED)
    // -------------------------------------------------------
    // If recruiter → get recruiter_profile_id
    $recruiter_profile_id = 0;
    if ($user_role === 'recruiter') {
        $rp = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
        $rp->bind_param("i", $user_id);
        $rp->execute();
        $rp_res = $rp->get_result();
        if ($row = $rp_res->fetch_assoc()) {
            $recruiter_profile_id = intval($row['id']);
        }
        $rp->close();
    }

    // -------------------------------------------------------
    // GET category ID from query
    // -------------------------------------------------------
    $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // -------------------------------------------------------
    // FETCH SINGLE CATEGORY
    // -------------------------------------------------------
    if ($category_id > 0) {

        // 🔒 Recruiter should only see his own categories
        if ($user_role === 'recruiter') {
            $stmt = $conn->prepare("
                SELECT id, category_name, created_at 
                FROM job_category 
                WHERE id = ? AND recruiter_id = ?
            ");
            $stmt->bind_param("ii", $category_id, $recruiter_profile_id);
        } else {
            // Admin → full access
            $stmt = $conn->prepare("
                SELECT id, category_name, created_at 
                FROM job_category 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $category_id);
        }

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

        exit;
    }

    // -------------------------------------------------------
    // FETCH ALL JOB CATEGORIES
    // -------------------------------------------------------

    if ($user_role === 'recruiter') {
        // Recruiter sees only his categories
        $sql = "
            SELECT id, category_name, created_at 
            FROM job_category 
            WHERE recruiter_id = $recruiter_profile_id
            ORDER BY id ASC
        ";
    } else {
        // Admin sees all categories
        $sql = "SELECT id, category_name, created_at FROM job_category ORDER BY id ASC";
    }

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

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

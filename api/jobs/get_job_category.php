<?php
// get_job_category.php - Fetch all or single job category
require_once '../cors.php';
require_once '../db.php';

try {

    // ✅ Authenticate JWT (Admin, Recruiter allowed)
    $decoded = authenticateJWT(['admin', 'recruiter', 'student']);
    $user_id = intval($decoded['user_id']);
    $user_role = strtolower($decoded['role']);

    // -------------------------------------------------------
    // 🔍 STEP 1: Get recruiter_profile_id (required for filter)
    // -------------------------------------------------------
    $recruiter_profile_id = 0;

    if ($user_role === 'recruiter') {
        $rp = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
        $rp->bind_param("i", $user_id);
        $rp->execute();
        $r = $rp->get_result()->fetch_assoc();
        $recruiter_profile_id = $r['id'] ?? 0;
    }

    // -------------------------------------------------------
    // GET category ID from query
    // -------------------------------------------------------
    $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // -------------------------------------------------------
    // FETCH SINGLE CATEGORY
    // -------------------------------------------------------
    if ($category_id > 0) {

        // 🎯 Recruiter filter (NO DB changes, only using jobs table)
        if ($user_role === 'recruiter') {

            $stmt = $conn->prepare("
                SELECT jc.id, jc.category_name, jc.created_at
                FROM job_category jc
                WHERE jc.id = ?
                AND jc.id IN (
                    SELECT DISTINCT category_id 
                    FROM jobs 
                    WHERE recruiter_id = ?
                )
            ");

            $stmt->bind_param("ii", $category_id, $recruiter_profile_id);

        } else {

            // Admin → no filter
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
                "category" => $category
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
    // FETCH ALL CATEGORIES (Filtered for recruiter)
    // -------------------------------------------------------

    if ($user_role === 'recruiter') {

        // 🎯 Show ONLY categories used by this recruiter
        $sql = "
            SELECT DISTINCT jc.id, jc.category_name, jc.created_at
            FROM job_category jc
            INNER JOIN jobs j ON j.category_id = jc.id
            WHERE j.recruiter_id = $recruiter_profile_id
            ORDER BY jc.id ASC
        ";

    } else {

        // Admin → show all
        $sql = "
            SELECT id, category_name, created_at 
            FROM job_category 
            ORDER BY id ASC
        ";
    }

    $result = $conn->query($sql);
    $categories = [];

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Job categories fetched successfully",
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

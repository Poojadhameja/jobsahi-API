<?php
include '../CORS.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
include "../db.php";

// Authenticate user (admin, student)
$user = authenticateJWT(['admin', 'student']);
$role = $user['role']; // e.g., 'admin', 'student', etc.

// ===================== PUT REQUEST: Update Student Profile =====================
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    if ($id <= 0) {
        echo json_encode(["message" => "Invalid or missing student_id parameter", "status" => false]);
        exit;
    }

    $input = json_decode(file_get_contents("php://input"), true);

    $skills = $input['skills'] ?? "";
    $education = $input['education'] ?? "";
    $resume = $input['resume'] ?? "";
    $portfolio_link = $input['portfolio_link'] ?? "";
    $linkedin_url = $input['linkedin_url'] ?? "";
    $dob = $input['dob'] ?? "";
    $gender = $input['gender'] ?? "";
    $job_type = $input['job_type'] ?? "";
    $trade = $input['trade'] ?? "";
    $location = $input['location'] ?? "";

    $sql = "UPDATE student_profiles SET 
                skills = ?, education = ?, resume = ?, portfolio_link = ?, linkedin_url = ?,
                dob = ?, gender = ?, job_type = ?, trade = ?, location = ?, modified_at = NOW()
            WHERE id = ? AND deleted_at IS NULL";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssssssi",
        $skills, $education, $resume, $portfolio_link, $linkedin_url,
        $dob, $gender, $job_type, $trade, $location, $id
    );

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo json_encode([
                "message" => "Student profile updated successfully",
                "status" => true,
                "profile_updated_id" => $id
            ]);
        } else {
            echo json_encode([
                "message" => "No record updated. Check ID or deleted_at",
                "status" => false
            ]);
        }
    } else {
        echo json_encode([
            "message" => "Update failed: " . mysqli_error($conn),
            "status" => false
        ]);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
}

// ===================== GET REQUEST: Fetch Applications =====================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Base query
    $sql = "SELECT * FROM applications WHERE deleted_at IS NULL AND admin_action = 'approval'";

    // If admin, also include pending
    if ($role === 'admin') {
        $sql = "SELECT * FROM applications WHERE deleted_at IS NULL AND (admin_action = 'approval' OR admin_action = 'pending')";
    }

    $result = mysqli_query($conn, $sql);
    $data = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }

    echo json_encode([
        "status" => true,
        "role" => $role,
        "records" => $data
    ]);

    mysqli_close($conn);
    exit;
}

// ===================== METHOD NOT ALLOWED =====================
echo json_encode(["message" => "Method not allowed", "status" => false]);
mysqli_close($conn);
?>

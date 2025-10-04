<?php
require_once '../cors.php';

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

    $skills = $input['skills'] ?? null;
    $education = $input['education'] ?? null;
    $resume = $input['resume'] ?? null;
    $certificates = $input['certificates'] ?? null;
    $portfolio_link = $input['portfolio_link'] ?? null;
    $linkedin_url = $input['linkedin_url'] ?? null;
    $dob = $input['dob'] ?? null;
    $gender = $input['gender'] ?? null;
    $job_type = $input['job_type'] ?? null;
    $trade = $input['trade'] ?? null;
    $location = $input['location'] ?? null;
    $bio = $input['bio'] ?? null;
    $experience = $input['experience'] ?? null;
    $graduation_year = $input['graduation_year'] ?? null;
    $cgpa = $input['cgpa'] ?? null;

    // Build UPDATE query with all fields
    $sql = "UPDATE student_profiles SET 
                skills = ?, 
                education = ?, 
                resume = ?, 
                certificates = ?,
                portfolio_link = ?, 
                linkedin_url = ?,
                dob = ?, 
                gender = ?, 
                job_type = ?, 
                trade = ?, 
                location = ?,
                bio = ?,
                experience = ?,
                graduation_year = ?,
                cgpa = ?,
                modified_at = NOW()
            WHERE id = ? AND deleted_at IS NULL";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssssssssssdi",
        $skills, 
        $education, 
        $resume, 
        $certificates, 
        $portfolio_link, 
        $linkedin_url,
        $dob, 
        $gender, 
        $job_type, 
        $trade, 
        $location,
        $bio,
        $experience,
        $graduation_year,
        $cgpa,
        $id
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
    $sql = "SELECT * FROM applications WHERE deleted_at IS NULL AND admin_action = 'approved'";

    // If admin, also include pending
    if ($role === 'admin') {
        $sql = "SELECT * FROM applications WHERE deleted_at IS NULL AND (admin_action = 'approved' OR admin_action = 'pending')";
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
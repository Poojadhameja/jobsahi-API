<?php
// get-certificate.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        echo json_encode([
            "status" => false,
            "message" => "Certificate ID is required"
        ]);
        exit;
    }

    $certificate_id = intval($_GET['id']);

    // ✅ Use file_url (matches your database structure)
    $sql = "SELECT id, student_id, course_id, issue_date, file_url 
            FROM certificates WHERE id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $certificate_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            echo json_encode([
                "status" => true,
                "certificate" => $row
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Certificate not found"
            ]);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Database query failed"
        ]);
    }
}
?>
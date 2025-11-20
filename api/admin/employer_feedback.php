<?php
require_once '../cors.php';
require_once '../db.php';

try {

    // Authenticate all allowed roles
    $decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']);
    $role    = strtolower($decoded['role'] ?? '');
    $user_id = intval($decoded['user_id'] ?? 0);

    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['POST', 'PUT'])) {
        echo json_encode(["success" => false, "message" => "Only POST and PUT allowed"]);
        exit;
    }

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        echo json_encode(["success" => false, "message" => "Invalid JSON"]);
        exit;
    }

    // Extract fields — matches EXACT DB columns
    $recruiter_id = intval($input['recruiter_id'] ?? 0);
    $job_id       = intval($input['job_id'] ?? 0);
    $rating       = intval($input['rating'] ?? 0);
    $feedback     = trim($input['feedback'] ?? '');

    if ($method === 'POST') {

        // Insert feedback
        $stmt = $conn->prepare("
            INSERT INTO employer_feedback 
                (recruiter_id, job_id, rating, feedback, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiis", $recruiter_id, $job_id, $rating, $feedback);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Feedback submitted successfully",
                "data" => [
                    "id" => $stmt->insert_id,
                    "recruiter_id" => $recruiter_id,
                    "job_id" => $job_id,
                    "rating" => $rating,
                    "feedback" => $feedback
                ]
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Insert failed"]);
        }

        exit;
    }

    if ($method === 'PUT') {

        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(["success" => false, "message" => "id is required"]);
            exit;
        }

        // Update
        $stmt = $conn->prepare("
            UPDATE employer_feedback
            SET rating = ?, feedback = ?
            WHERE id = ?
        ");
        $stmt->bind_param("isi", $rating, $feedback, $id);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Feedback updated successfully"
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Update failed"]);
        }

        exit;
    }

}
catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
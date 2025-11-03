<?php
// create_update_notifications_templates.php - Create/update notification templates (Admin access only)
require_once '../cors.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin','recruiter', 'institute']); // returns array

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception("Invalid JSON input");
    }

    // --- CREATE or UPDATE ---
    if (isset($input['id']) && !empty($input['id'])) {
        // UPDATE existing template
        $templateId = intval($input['id']);

        $updateFields = [];
        $updateValues = [];
        $types = "";

        if (isset($input['name'])) {
            $updateFields[] = "name = ?";
            $updateValues[] = $input['name'];
            $types .= "s";
        }

        if (isset($input['type'])) {
            $updateFields[] = "type = ?";
            $updateValues[] = $input['type'];
            $types .= "s";
        }

        if (isset($input['subject'])) {
            $updateFields[] = "subject = ?";
            $updateValues[] = $input['subject'];
            $types .= "s";
        }

        if (isset($input['body'])) {
            $updateFields[] = "body = ?";
            $updateValues[] = $input['body'];
            $types .= "s";
        }

        if (empty($updateFields)) {
            throw new Exception("No valid fields to update");
        }

        $updateValues[] = $templateId;
        $types .= "i";

        $sql = "UPDATE notifications_templates SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$updateValues);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Notification template updated successfully",
                "data" => ["id" => $templateId]
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to update notification template",
                "error" => $stmt->error
            ]);
        }

    } else {
        // CREATE new template
        if (empty($input['name']) || empty($input['type'])) {
            throw new Exception("Name and type are required");
        }

        $sql = "INSERT INTO notifications_templates (name, type, subject, body, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssss",
            $input['name'],
            $input['type'],
            $input['subject'],
            $input['body']
        );

        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            echo json_encode([
                "status" => true,
                "message" => "Notification template created successfully",
                "data" => ["id" => $newId]
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to create notification template",
                "error" => $stmt->error
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
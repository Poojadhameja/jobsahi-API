<?php
// create_update_notifications_templates.php - Create/update notification templates
require_once '../cors.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin','institute','recruiter']); // returns array

// ✅ Extract user_role from decoded JWT
$user_role = $decoded['role'] ?? null;
$user_id = $decoded['user_id'] ?? $decoded['id'] ?? null;

if (!$user_role) {
    http_response_code(403);
    echo json_encode([
        "status" => false,
        "message" => "Invalid token: role not found"
    ]);
    exit;
}

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

        // ✅ Check if user has permission to update this template
        $checkSql = "SELECT role FROM notifications_templates WHERE id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $templateId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            throw new Exception("Template not found");
        }
        
        $template = $checkResult->fetch_assoc();
        $template_role = $template['role'];

        // ✅ Role-based permission check for UPDATE
        $canUpdate = false;
        if ($user_role === 'admin') {
            // Admin can update all templates
            $canUpdate = true;
        } elseif ($user_role === 'recruiter' && $template_role === 'recruiter') {
            // Recruiter can only update their own templates
            $canUpdate = true;
        } elseif ($user_role === 'institute' && $template_role === 'institute') {
            // Institute can only update their own templates
            $canUpdate = true;
        }

        if (!$canUpdate) {
            throw new Exception("You don't have permission to update this template");
        }

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

        // ✅ Only admin can change role field
        if (isset($input['role']) && $user_role === 'admin') {
            $updateFields[] = "role = ?";
            $updateValues[] = $input['role'];
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

        // ✅ Set role based on user's role (unless admin explicitly sets it)
        if (isset($input['role']) && $user_role === 'admin') {
            // Admin can set any role
            $role = $input['role'];
        } else {
            // Non-admin users: template role matches their own role
            $role = $user_role;
        }

        $sql = "INSERT INTO notifications_templates (name, type, subject, body, role, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param(
            "sssss",
            $input['name'],
            $input['type'],
            $input['subject'],
            $input['body'],
            $role
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
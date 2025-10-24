<?php
// create_certificate_template.php - Create/update certificate template (Admin, Institute access)
require_once '../cors.php';

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'institute']); // returns array

// Get user_id from decoded JWT
$user_id = $decoded['user_id']; // or whatever field contains the user ID

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

$institute_id = isset($data['institute_id']) ? $data['institute_id'] : null;
$template_name = isset($data['template_name']) ? $data['template_name'] : '';
$logo_url = isset($data['logo_url']) ? $data['logo_url'] : '';
$seal_url = isset($data['seal_url']) ? $data['seal_url'] : '';
$signature_url = isset($data['signature_url']) ? $data['signature_url'] : '';
$header_text = isset($data['header_text']) ? $data['header_text'] : '';
$footer_text = isset($data['footer_text']) ? $data['footer_text'] : '';
$background_image_url = isset($data['background_image_url']) ? $data['background_image_url'] : '';
$is_active = isset($data['is_active']) ? $data['is_active'] : 1;
$admin_action = isset($data['admin_action']) ? $data['admin_action'] : 'approved';
$template_id = isset($data['id']) ? $data['id'] : null; // for update operations

try {
    if ($template_id) {
        // ✅ Update existing template
        $stmt = $conn->prepare("UPDATE certificate_templates SET 
                                institute_id = ?, 
                                template_name = ?, 
                                logo_url = ?, 
                                seal_url = ?, 
                                signature_url = ?, 
                                header_text = ?, 
                                footer_text = ?, 
                                background_image_url = ?, 
                                is_active = ?, 
                                admin_action = ?, 
                                modified_at = NOW(),
                                deleted_at = NULL
                                WHERE id = ?");
        $stmt->bind_param("issssssssssi", $institute_id, $template_name, $logo_url, $seal_url, $signature_url, $header_text, $footer_text, $background_image_url, $is_active, $admin_action, $template_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    "status" => true,
                    "message" => "Certificate template updated successfully",
                    "template_id" => $template_id
                ]);
            } else {
                echo json_encode([
                    "status" => false,
                    "message" => "Template not found or no changes made"
                ]);
            }
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to update certificate template",
                "error" => $stmt->error
            ]);
        }
    } else {
        // ✅ Create new template
        $stmt = $conn->prepare("INSERT INTO certificate_templates 
                                (institute_id, template_name, logo_url, seal_url, signature_url, header_text, footer_text, background_image_url, is_active, created_at, modified_at, deleted_at, admin_action)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NULL, ?)");
        $stmt->bind_param("isssssssss", $institute_id, $template_name, $logo_url, $seal_url, $signature_url, $header_text, $footer_text, $background_image_url, $is_active, $admin_action);
        
        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Certificate template created successfully",
                "template_id" => $stmt->insert_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to create certificate template",
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

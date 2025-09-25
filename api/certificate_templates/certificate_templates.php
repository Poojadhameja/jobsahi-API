<?php
// certificate_templates.php - List certificate templates with role-based admin_action
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and get user role
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']); // returns array with 'role'
$userRole = $decoded['role'];

try {
    // Build SQL based on role
    if ($userRole === 'admin') {
        // Admin can see all pending and approved templates
        $sql = "
            SELECT 
                id, 
                institute_id,
                template_name, 
                logo_url, 
                seal_url, 
                signature_url, 
                header_text, 
                footer_text, 
                background_image_url, 
                is_active, 
                created_at, 
                modified_at, 
                deleted_at, 
                admin_action
            FROM certificate_templates
            WHERE is_active = 1
            ORDER BY created_at DESC
        ";
    } else {
        // Other roles see only 'approved' templates
        $sql = "
            SELECT 
                id, 
                institute_id,
                template_name, 
                logo_url, 
                seal_url, 
                signature_url, 
                header_text, 
                footer_text, 
                background_image_url, 
                is_active, 
                created_at, 
                modified_at, 
                deleted_at, 
                admin_action
            FROM certificate_templates
            WHERE is_active = 1 AND admin_action = 'approved'
            ORDER BY created_at DESC
        ";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $templates = [];

        while ($row = $result->fetch_assoc()) {
            $templates[] = [
                'id' => $row['id'],
                'institute_id' => $row['institute_id'],
                'template_name' => $row['template_name'],
                'logo_url' => $row['logo_url'],
                'seal_url' => $row['seal_url'],
                'signature_url' => $row['signature_url'],
                'header_text' => $row['header_text'],
                'footer_text' => $row['footer_text'],
                'background_image_url' => $row['background_image_url'],
                'is_active' => (bool)$row['is_active'],
                'created_at' => $row['created_at'],
                'modified_at' => $row['modified_at'],
                'deleted_at' => $row['deleted_at'],
                'admin_action' => $row['admin_action']
            ];
        }

        echo json_encode([
            "status" => true,
            "message" => "Certificate templates retrieved successfully",
            "data" => $templates,
            "count" => count($templates)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve certificate templates",
            "error" => $stmt->error
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

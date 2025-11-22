<?php
// certificate_templates.php - List certificate templates with role-based admin_action
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT and get user role
$decoded  = authenticateJWT(['admin', 'recruiter', 'institute', 'student']);
$userRole = strtolower($decoded['role'] ?? '');

try {

    // -------------------------------------------------------
    // SQL BASED ON ROLE
    // -------------------------------------------------------
    if ($userRole === 'admin') {
        // Admin can see all active templates (any status)
        $sql = "
            SELECT 
                id,
                institute_id,
                template_name,
                logo,
                seal,
                signature,
                description,
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
        // Others see only active + approved templates
        $sql = "
            SELECT 
                id,
                institute_id,
                template_name,
                logo,
                seal,
                signature,
                description,
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
    $stmt->execute();
    $result = $stmt->get_result();

    $templates = [];

    // -------------------------------------------------------
    // MEDIA PATH SETUP
    // -------------------------------------------------------
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];

    // Upload base folder
    $basePath = '/jobsahi-API/api/uploads/institute_certificate_templates/';

    // Helper function for media
    function getMediaUrl($fileName, $protocol, $host, $basePath) {
        if (empty($fileName)) return null;

        // Clean filename
        $clean = str_replace(
            ["\\", "/uploads/institute_certificate_templates/", "./", "../"],
            "",
            $fileName
        );

        $localPath = __DIR__ . '/../uploads/institute_certificate_templates/' . $clean;

        if (file_exists($localPath)) {
            return $protocol . $host . $basePath . $clean;
        }

        return null;
    }

    // -------------------------------------------------------
    // FORMAT RESPONSE WITH MEDIA URLs
    // -------------------------------------------------------
    while ($row = $result->fetch_assoc()) {

        $templates[] = [
            'id'            => $row['id'],
            'institute_id'  => $row['institute_id'],
            'template_name' => $row['template_name'],
            'description'   => $row['description'],
            'is_active'     => (bool)$row['is_active'],
            'created_at'    => $row['created_at'],
            'modified_at'   => $row['modified_at'],
            'deleted_at'    => $row['deleted_at'],
            'admin_action'  => $row['admin_action'],

            // MEDIA URLS
            'logo'      => getMediaUrl($row['logo'], $protocol, $host, $basePath),
            'seal'      => getMediaUrl($row['seal'], $protocol, $host, $basePath),
            'signature' => getMediaUrl($row['signature'], $protocol, $host, $basePath),
        ];
    }

    echo json_encode([
        "status"  => true,
        "message" => "Certificate templates retrieved successfully",
        "data"    => $templates,
        "count"   => count($templates)
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "status"  => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>

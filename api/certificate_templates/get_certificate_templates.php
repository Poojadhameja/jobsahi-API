<?php
// certificate_templates.php - List + Get by ID, with role-based admin_action
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT
$decoded  = authenticateJWT(['admin', 'recruiter', 'institute', 'student']);
$userRole = strtolower($decoded['role'] ?? '');
$loginInstituteId = intval($decoded['user_id'] ?? 0);   // ✅ FIXED FOR INSTITUTE LOGIN

try {
    // CHECK IF "id" QUERY PARAM EXISTS
    $templateId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // -------------------------------------------------------
    // ROLE BASED CONDITION  (ONLY THIS PART UPDATED)
    // -------------------------------------------------------

    if ($userRole === 'admin') {
        // Admin sees everything (active or inactive)
        $roleFilter = "is_active = 1";

    } elseif ($userRole === 'institute') {
        // Institute sees only its own templates + approved
        // 🔥 FIX: When switching JWT, only that institute's templates will load
        $roleFilter = "is_active = 1 AND admin_action = 'approved' AND institute_id = $loginInstituteId";

    } else {
        // Recruiter/Student: only approved active templates
        $roleFilter = "is_active = 1 AND admin_action = 'approved'";
    }

    // -------------------------------------------------------
    // BASE SQL (COMMON)
    // -------------------------------------------------------
    $baseSelect = "
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
    ";

    // -------------------------------------------------------
    // 1️⃣ IF ID PROVIDED → FETCH SINGLE TEMPLATE
    // -------------------------------------------------------
    if ($templateId > 0) {
        $sql = $baseSelect . " WHERE id = ? AND $roleFilter LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $templateId);
        $stmt->execute();
        $result = $stmt->get_result();

        // MEDIA CONFIG
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $basePath = '/jobsahi-API/api/uploads/institute_certificate_templates/';

        function getMediaUrl($fileName, $protocol, $host, $basePath) {
            if (empty($fileName)) return null;

            $clean = str_replace(["\\", "/uploads/institute_certificate_templates/", "./", "../"], "", $fileName);
            $localPath = __DIR__ . '/../uploads/institute_certificate_templates/' . $clean;

            return file_exists($localPath)
                ? $protocol . $host . $basePath . $clean
                : null;
        }

        if ($row = $result->fetch_assoc()) {
            $data = [
                'id'            => $row['id'],
                'institute_id'  => $row['institute_id'],
                'template_name' => $row['template_name'],
                'description'   => $row['description'],
                'is_active'     => (bool)$row['is_active'],
                'created_at'    => $row['created_at'],
                'modified_at'   => $row['modified_at'],
                'deleted_at'    => $row['deleted_at'],
                'admin_action'  => $row['admin_action'],

                // MEDIA
                'logo'      => getMediaUrl($row['logo'], $protocol, $host, $basePath),
                'seal'      => getMediaUrl($row['seal'], $protocol, $host, $basePath),
                'signature' => getMediaUrl($row['signature'], $protocol, $host, $basePath),
            ];

            echo json_encode([
                "status"  => true,
                "message" => "Certificate template found",
                "data"    => $data
            ]);
            exit;
        }

        echo json_encode([
            "status"  => false,
            "message" => "Template not found"
        ]);
        exit;
    }

    // -------------------------------------------------------
    // 2️⃣ IF NO ID → RETURN ALL TEMPLATES
    // -------------------------------------------------------
    $sql = $baseSelect . " WHERE $roleFilter ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $templates = [];

    // MEDIA CONFIG
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = '/jobsahi-API/api/uploads/institute_certificate_templates/';

    function getMediaUrlList($fileName, $protocol, $host, $basePath) {
        if (empty($fileName)) return null;

        $clean = str_replace(["\\", "/uploads/institute_certificate_templates/", "./", "../"], "", $fileName);
        $localPath = __DIR__ . '/../uploads/institute_certificate_templates/' . $clean;

        return file_exists($localPath)
            ? $protocol . $host . $basePath . $clean
            : null;
    }

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
            'logo'      => getMediaUrlList($row['logo'], $protocol, $host, $basePath),
            'seal'      => getMediaUrlList($row['seal'], $protocol, $host, $basePath),
            'signature' => getMediaUrlList($row['signature'], $protocol, $host, $basePath),
        ];
    }

    echo json_encode([
        "status"  => true,
        "message" => "Certificate templates retrieved successfully",
        "count"   => count($templates),
        "data"    => $templates
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
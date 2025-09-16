<?php
include '../CORS.php';

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT (any valid user can access notification templates)
$decoded = authenticateJWT(); // returns array

try {
    // --- Get {id} from query or REST-style path ---
    $templateIdRaw = $_GET['id'] ?? null;

    if ($templateIdRaw === null || $templateIdRaw === '') {
        // PATH_INFO (if your router sets it)
        $pathInfo = $_SERVER['PATH_INFO'] ?? '';
        if ($pathInfo) {
            $segments = explode('/', trim($pathInfo, '/'));
            $templateIdRaw = end($segments);
        }
    }
    if (($templateIdRaw === null || $templateIdRaw === '') && isset($_SERVER['REQUEST_URI'])) {
        // Fallback: try to parse from the URI like /api/v1/notifications_templates/{id}
        if (preg_match('#/notifications_templates/([^/?]+)#', $_SERVER['REQUEST_URI'], $m)) {
            $templateIdRaw = $m[1];
        }
    }
    if ($templateIdRaw === null || $templateIdRaw === '') {
        throw new Exception("Template ID is required (use ?id=123 or /notifications_templates/123)");
    }

    // --- Discover table schema (same pattern as your list API) ---
    $checkNotificationTemplates = $conn->query("DESCRIBE notifications_templates");
    if (!$checkNotificationTemplates) {
        throw new Exception("Cannot access notifications_templates table structure");
    }

    $notificationTemplatesColumns = [];
    $columnTypes = []; // field => SQL type
    while ($row = $checkNotificationTemplates->fetch_assoc()) {
        $notificationTemplatesColumns[] = $row['Field'];
        $columnTypes[$row['Field']] = $row['Type'];
    }

    // Determine correct ID column and its type
    $idColumn = in_array('template_id', $notificationTemplatesColumns) ? 'template_id' : 'id';
    if (!in_array($idColumn, $notificationTemplatesColumns)) {
        throw new Exception("ID column not found (checked: template_id, id)");
    }
    $idTypeDef = $columnTypes[$idColumn] ?? '';
    $isIntId = (bool) preg_match('/\b(int|bigint|mediumint|smallint|tinyint)\b/i', $idTypeDef);

    // Normalize the bound value & type
    $paramType = $isIntId ? 'i' : 's';
    $boundId = $isIntId ? intval($templateIdRaw) : (string) $templateIdRaw;

    // Optional columns (match your pattern)
    $nameColumn      = in_array('name',       $notificationTemplatesColumns) ? 'name'       : 'NULL';
    $typeColumn      = in_array('type',       $notificationTemplatesColumns) ? 'type'       : 'NULL';
    $subjectColumn   = in_array('subject',    $notificationTemplatesColumns) ? 'subject'    : 'NULL';
    $bodyColumn      = in_array('body',       $notificationTemplatesColumns) ? 'body'       : 'NULL';
    $createdAtColumn = in_array('created_at', $notificationTemplatesColumns) ? 'created_at' : 'NULL';

    // Build query
    $sql = "
        SELECT
            {$idColumn} as id,
            {$nameColumn} as name,
            {$typeColumn} as type,
            {$subjectColumn} as subject,
            {$bodyColumn} as body,
            {$createdAtColumn} as created_at
        FROM notifications_templates
        WHERE {$idColumn} = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param($paramType, $boundId);

    if (!$stmt->execute()) {
        throw new Exception("Execution failed: " . $stmt->error);
    }

    // Support both with and without mysqlnd
    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
        $template = $result->fetch_assoc();
    } else {
        // Fallback: bind_result
        $stmt->store_result();
        $id = $name = $type = $subject = $body = $created_at = null;
        $stmt->bind_result($id, $name, $type, $subject, $body, $created_at);
        $template = null;
        if ($stmt->num_rows > 0 && $stmt->fetch()) {
            $template = [
                'id'         => $id,
                'name'       => $name,
                'type'       => $type,
                'subject'    => $subject,
                'body'       => $body,
                'created_at' => $created_at
            ];
        }
    }

    if ($template) {
        echo json_encode([
            "status"  => true,
            "message" => "Notification template retrieved successfully",
            "data"    => $template
        ]);
    } else {
        echo json_encode([
            "status"  => false,
            "message" => "Notification template not found"
        ]);
    }

    if (isset($result)) { $result->free(); }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        "status"  => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

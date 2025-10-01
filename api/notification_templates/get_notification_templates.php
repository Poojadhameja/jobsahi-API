<?php
// get_notification_templates.php - Get notification templates based on role
require_once '../cors.php';

// ✅ Authenticate JWT (any valid user can access notification templates)
$decoded = authenticateJWT(['admin','institute','recruiter']); // returns array
$user_id = intval($decoded['user_id']);
$user_role = $decoded['role']; // current logged-in user role

try {
    // ✅ Role-based query: fetch templates visible to this user
    // Logic:
    // - Admin can see all templates
    // - Recruiter can see their own templates (role = 'recruiter')
    // - Institute can see their own templates (role = 'institute') and admin templates
    // - Student can see recruiter, institute, and admin templates
    
    $sql = "
        SELECT 
            id,
            name,
            type,
            subject,
            body,
            role,
            created_at
        FROM notifications_templates
        WHERE
    ";

    if ($user_role === 'admin') {
        // Admin can see all templates
        $sql .= " 1=1";
        $stmt = $conn->prepare($sql . " ORDER BY created_at DESC");
    } elseif ($user_role === 'recruiter') {
        // Recruiter can only see their own templates
        $sql .= " role = ?";
        $stmt = $conn->prepare($sql . " ORDER BY created_at DESC");
        $stmt->bind_param("s", $user_role);
    } elseif ($user_role === 'institute') {
        // Institute can see their own templates and admin templates
        $sql .= " (role = ? OR role = 'admin')";
        $stmt = $conn->prepare($sql . " ORDER BY created_at DESC");
        $stmt->bind_param("s", $user_role);
    } elseif ($user_role === 'student') {
        // Student can see templates from recruiter, institute, and admin
        $sql .= " role IN ('recruiter', 'institute', 'admin')";
        $stmt = $conn->prepare($sql . " ORDER BY created_at DESC");
    } else {
        throw new Exception("Invalid user role");
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Error fetching notification templates: " . $conn->error);
    }

    $notificationTemplates = [];
    while ($row = $result->fetch_assoc()) {
        $notificationTemplates[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Notification templates retrieved successfully",
        "data" => $notificationTemplates,
        "count" => count($notificationTemplates)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
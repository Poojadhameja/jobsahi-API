<?php
// referrals.php - Get all or user-specific referrals
require_once '../cors.php';

// ✅ Authenticate and allow multiple roles
$decoded = authenticateJWT(['admin', 'student']);
$user_role = $decoded->role ?? ''; // role from JWT

try {
    // ✅ Optional referrer_id
    $referrer_id = isset($_GET['referrer_id']) ? (int)$_GET['referrer_id'] : 0;

    // ✅ Role-based query
    if ($user_role === 'admin') {
        if ($referrer_id > 0) {
            // Admin + specific referrer
            $sql = "SELECT id, referrer_id, referee_email, job_id, status, admin_action, created_at
                    FROM referrals
                    WHERE referrer_id = ?
                      AND (admin_action = 'pending' OR admin_action = 'approved')
                    ORDER BY created_at DESC";
        } else {
            // Admin + all referrals
            $sql = "SELECT id, referrer_id, referee_email, job_id, status, admin_action, created_at
                    FROM referrals
                    WHERE admin_action IN ('pending','approved')
                    ORDER BY created_at DESC";
        }
    } else {
        if ($referrer_id > 0) {
            // Student + specific referrer
            $sql = "SELECT id, referrer_id, referee_email, job_id, status, admin_action, created_at
                    FROM referrals
                    WHERE referrer_id = ?
                      AND admin_action = 'approved'
                    ORDER BY created_at DESC";
        } else {
            // Student + all referrals (approved only)
            $sql = "SELECT id, referrer_id, referee_email, job_id, status, admin_action, created_at
                    FROM referrals
                    WHERE admin_action = 'approved'
                    ORDER BY created_at DESC";
        }
    }

    // ✅ Prepare and bind if referrer_id is provided
    $stmt = $conn->prepare($sql);
    if ($referrer_id > 0) {
        $stmt->bind_param("i", $referrer_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $referrals = [];
    while ($row = $result->fetch_assoc()) {
        $referrals[] = $row;
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'status' => true,
        'message' => $referrer_id > 0
            ? "Referrals fetched for referrer_id $referrer_id"
            : "All referrals fetched successfully",
        'count' => count($referrals),
        'data' => $referrals
    ], JSON_PRETTY_PRINT);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>

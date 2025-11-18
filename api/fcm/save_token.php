<?php
// save_token.php - Save or update FCM token for a user
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// ✅ Allow only POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed"
    ]);
    exit();
}

// ✅ Authenticate user (any role can save their token)
$decoded = authenticateJWT(['student', 'recruiter', 'institute', 'admin']);
$user_id = intval($decoded['user_id']);

// ✅ Parse JSON body
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['fcm_token']) || empty(trim($data['fcm_token']))) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "fcm_token is required"
    ]);
    exit();
}

$fcm_token = trim($data['fcm_token']);
$device_type = isset($data['device_type']) ? trim($data['device_type']) : null; // android, ios, web
$device_id = isset($data['device_id']) ? trim($data['device_id']) : null;

try {
    // Check if token already exists for this user
    $check_sql = "SELECT id, fcm_token FROM fcm_tokens WHERE user_id = ? AND fcm_token = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $user_id, $fcm_token);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Token already exists, just update the timestamp
        $update_sql = "UPDATE fcm_tokens SET updated_at = NOW(), is_active = 1";
        if ($device_type) {
            $update_sql .= ", device_type = ?";
        }
        if ($device_id) {
            $update_sql .= ", device_id = ?";
        }
        $update_sql .= " WHERE user_id = ? AND fcm_token = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        if ($device_type && $device_id) {
            $update_stmt->bind_param("ssis", $device_type, $device_id, $user_id, $fcm_token);
        } elseif ($device_type) {
            $update_stmt->bind_param("sis", $device_type, $user_id, $fcm_token);
        } elseif ($device_id) {
            $update_stmt->bind_param("sis", $device_id, $user_id, $fcm_token);
        } else {
            $update_stmt->bind_param("is", $user_id, $fcm_token);
        }
        
        $update_stmt->execute();
        $check_stmt->close();
        $update_stmt->close();
        
        http_response_code(200);
        echo json_encode([
            "status" => true,
            "message" => "FCM token updated successfully"
        ]);
    } else {
        // Check if user has a token for this device_id (if provided)
        if ($device_id) {
            $check_device_sql = "SELECT id FROM fcm_tokens WHERE user_id = ? AND device_id = ?";
            $check_device_stmt = $conn->prepare($check_device_sql);
            $check_device_stmt->bind_param("is", $user_id, $device_id);
            $check_device_stmt->execute();
            $device_result = $check_device_stmt->get_result();
            
            if ($device_result->num_rows > 0) {
                // Update existing device token
                $update_device_sql = "UPDATE fcm_tokens SET fcm_token = ?, updated_at = NOW(), is_active = 1";
                if ($device_type) {
                    $update_device_sql .= ", device_type = ?";
                }
                $update_device_sql .= " WHERE user_id = ? AND device_id = ?";
                
                $update_device_stmt = $conn->prepare($update_device_sql);
                if ($device_type) {
                    $update_device_stmt->bind_param("ssis", $fcm_token, $device_type, $user_id, $device_id);
                } else {
                    $update_device_stmt->bind_param("sis", $fcm_token, $user_id, $device_id);
                }
                
                $update_device_stmt->execute();
                $check_device_stmt->close();
                $update_device_stmt->close();
                
                http_response_code(200);
                echo json_encode([
                    "status" => true,
                    "message" => "FCM token updated successfully"
                ]);
            } else {
                // Insert new token
                $insert_sql = "INSERT INTO fcm_tokens (user_id, fcm_token, device_type, device_id, is_active, created_at, updated_at) 
                               VALUES (?, ?, ?, ?, 1, NOW(), NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("isss", $user_id, $fcm_token, $device_type, $device_id);
                $insert_stmt->execute();
                $check_device_stmt->close();
                $insert_stmt->close();
                
                http_response_code(201);
                echo json_encode([
                    "status" => true,
                    "message" => "FCM token saved successfully"
                ]);
            }
        } else {
            // Insert new token without device_id
            $insert_sql = "INSERT INTO fcm_tokens (user_id, fcm_token, device_type, is_active, created_at, updated_at) 
                           VALUES (?, ?, ?, 1, NOW(), NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iss", $user_id, $fcm_token, $device_type);
            $insert_stmt->execute();
            $check_stmt->close();
            $insert_stmt->close();
            
            http_response_code(201);
            echo json_encode([
                "status" => true,
                "message" => "FCM token saved successfully"
            ]);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>


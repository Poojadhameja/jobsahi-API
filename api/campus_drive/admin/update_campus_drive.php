<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Validate drive_id
if (empty($input['drive_id'])) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Missing required field: drive_id"
    ]);
    exit;
}

$drive_id = intval($input['drive_id']);

try {
    // Check if drive exists
    $check = $conn->query("SELECT id FROM campus_drives WHERE id = $drive_id");
    if ($check->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Campus drive not found"
        ]);
        exit;
    }

    // Build update query dynamically
    $updates = [];
    $params = [];
    $types = "";

    $allowed_fields = ['title', 'organizer', 'venue', 'city', 'start_date', 'end_date', 'capacity_per_day', 'status'];
    
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            if ($field === 'status') {
                if (!in_array($input[$field], ['draft', 'live', 'closed'])) {
                    continue;
                }
            }
            if ($field === 'capacity_per_day') {
                $value = intval($input[$field]);
                if ($value <= 0) continue;
                $updates[] = "$field = ?";
                $params[] = $value;
                $types .= "i";
            } else {
                $updates[] = "$field = ?";
                $params[] = mysqli_real_escape_string($conn, $input[$field]);
                $types .= "s";
            }
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "No valid fields to update"
        ]);
        exit;
    }

    // Validate dates if both are being updated
    if (isset($input['start_date']) && isset($input['end_date'])) {
        if (strtotime($input['start_date']) > strtotime($input['end_date'])) {
            http_response_code(400);
            echo json_encode([
                "status" => false,
                "message" => "Start date cannot be after end date"
            ]);
            exit;
        }
    }

    $sql = "UPDATE campus_drives SET " . implode(", ", $updates) . " WHERE id = ?";
    $params[] = $drive_id;
    $types .= "i";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        // Fetch updated drive
        $result = $conn->query("SELECT * FROM campus_drives WHERE id = $drive_id");
        $drive = $result->fetch_assoc();
        
        http_response_code(200);
        echo json_encode([
            "status" => true,
            "message" => "Campus drive updated successfully",
            "data" => $drive
        ]);
    } else {
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to update campus drive: " . mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error updating campus drive",
        "error" => $e->getMessage()
    ]);
}
?>



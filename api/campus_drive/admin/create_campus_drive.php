<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['title', 'organizer', 'venue', 'city', 'start_date', 'end_date', 'capacity_per_day'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Missing required field: $field"
        ]);
        exit;
    }
}

try {
    $title = mysqli_real_escape_string($conn, $input['title']);
    $organizer = mysqli_real_escape_string($conn, $input['organizer']);
    $venue = mysqli_real_escape_string($conn, $input['venue']);
    $city = mysqli_real_escape_string($conn, $input['city']);
    $start_date = mysqli_real_escape_string($conn, $input['start_date']);
    $end_date = mysqli_real_escape_string($conn, $input['end_date']);
    $capacity_per_day = intval($input['capacity_per_day']);
    $status = isset($input['status']) ? mysqli_real_escape_string($conn, $input['status']) : 'draft';

    // Validate status
    if (!in_array($status, ['draft', 'live', 'closed'])) {
        $status = 'draft';
    }

    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Start date cannot be after end date"
        ]);
        exit;
    }

    // Validate capacity
    if ($capacity_per_day <= 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Capacity per day must be greater than 0"
        ]);
        exit;
    }

    // Insert campus drive
    $sql = "INSERT INTO campus_drives (title, organizer, venue, city, start_date, end_date, capacity_per_day, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssssis", $title, $organizer, $venue, $city, $start_date, $end_date, $capacity_per_day, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        $drive_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Fetch created drive
        $result = $conn->query("SELECT * FROM campus_drives WHERE id = $drive_id");
        $drive = $result->fetch_assoc();
        
        http_response_code(201);
        echo json_encode([
            "status" => true,
            "message" => "Campus drive created successfully",
            "data" => $drive
        ]);
    } else {
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to create campus drive: " . mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error creating campus drive",
        "error" => $e->getMessage()
    ]);
}
?>



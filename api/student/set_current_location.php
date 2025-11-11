<?php
require_once '../cors.php';

// Authenticate JWT (allowed roles: student, admin)
$current_user = authenticateJWT(['student', 'admin']); 
$user_role = strtolower($current_user['role']);

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "message" => "Only POST requests allowed", 
        "status" => false
    ]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode([
        "message" => "DB connection failed: " . mysqli_connect_error(), 
        "status" => false
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate required parameters
if (!isset($input['user_id'])) {
    echo json_encode([
        "message" => "Missing required parameter: user_id", 
        "status" => false
    ]);
    exit;
}

$user_id = intval($input['user_id']);

// Try to resolve a human-readable address for given coordinates
function reverseGeocodeAddress($latitude, $longitude) {
    if ($latitude === null || $longitude === null) {
        return null;
    }
    $lat = floatval($latitude);
    $lon = floatval($longitude);

    // Build Nominatim reverse geocoding request (OpenStreetMap)
    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' . urlencode($lat) . '&lon=' . urlencode($lon) . '&zoom=14&addressdetails=1';
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: jobsahi-api/1.0\r\nAccept: application/json\r\n",
            'timeout' => 5,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ];
    $context = stream_context_create($opts);
    try {
        $json = @file_get_contents($url, false, $context);
        if ($json) {
            $data = json_decode($json, true);
            if (!empty($data['address']) && is_array($data['address'])) {
                $address = $data['address'];
                $city = $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['municipality']
                    ?? $address['district']
                    ?? $address['county']
                    ?? $address['suburb']
                    ?? null;
                $state = $address['state']
                    ?? $address['state_district']
                    ?? $address['region']
                    ?? null;

                $parts = array_filter([$city, $state], static function ($value) {
                    return $value !== null && $value !== '';
                });

                if (!empty($parts)) {
                    // Fit within varchar(255) and prefer "City, State"
                    return substr(implode(', ', $parts), 0, 255);
                }
            }

            if (!empty($data['display_name'])) {
                // Fit within varchar(255)
                return substr($data['display_name'], 0, 255);
            }
        }
    } catch (Exception $e) {
        error_log('Reverse geocoding failed: ' . $e->getMessage());
    }
    return null;
}

// Determine client IP considering proxies/CDN
function getClientIp() {
    $headersToCheck = [
        'HTTP_X_FORWARDED_FOR', // may contain multiple, first is original client
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
    ];
    foreach ($headersToCheck as $header) {
        if (!empty($_SERVER[$header])) {
            $value = $_SERVER[$header];
            // X-Forwarded-For can be a comma-separated list
            $parts = array_map('trim', explode(',', $value));
            foreach ($parts as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// IP-based geolocation fallback
function getLocationFromIp($ip) {
    // Local/dev networks are not geolocatable
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return [
            'latitude' => null,
            'longitude' => null,
            'error'  => 'Location detection not available on local network. Send GPS coordinates from device.'
        ];
    }
    try {
        $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=lat,lon,status");
        if ($response) {
            $data = json_decode($response, true);
            if ($data && ($data['status'] ?? '') === 'success') {
                return [
                    'latitude' => isset($data['lat']) ? floatval($data['lat']) : null,
                    'longitude' => isset($data['lon']) ? floatval($data['lon']) : null,
                ];
            }
        }
    } catch (Exception $e) {
        error_log('IP geolocation failed: ' . $e->getMessage());
    }
    return [
        'latitude' => null,
        'longitude' => null,
        'error' => 'Unable to detect device location via IP.'
    ];
}

// Resolve coordinates with this priority:
// 1) Headers X-Latitude/X-Longitude (from app)
// 2) JSON body latitude/longitude (from app)
// 3) IP geolocation (approximate)
$latitude = null;
$longitude = null;

if (isset($_SERVER['HTTP_X_LATITUDE']) && isset($_SERVER['HTTP_X_LONGITUDE'])) {
    $latitude = floatval($_SERVER['HTTP_X_LATITUDE']);
    $longitude = floatval($_SERVER['HTTP_X_LONGITUDE']);
} elseif (isset($input['latitude']) && isset($input['longitude'])) {
    $latitude = floatval($input['latitude']);
    $longitude = floatval($input['longitude']);
} else {
    $clientIp = getClientIp();
    $loc = getLocationFromIp($clientIp);
    $latitude = $loc['latitude'];
    $longitude = $loc['longitude'];
    if ($latitude === null || $longitude === null) {
        // Provide a clearer error suggesting sending GPS from device
        echo json_encode([
            "message" => ($loc['error'] ?? 'Location detection failed via IP.') . ' Send GPS coordinates from device headers or JSON.',
            "status" => false
        ]);
        exit;
    }
}

// At this point, latitude/longitude must be resolved from one of the sources

// Resolve human-readable location string (priority: header -> body -> reverse geocode)
$resolved_location = null;
if (!empty($_SERVER['HTTP_X_LOCATION'])) {
    $resolved_location = substr(trim($_SERVER['HTTP_X_LOCATION']), 0, 255);
} elseif (!empty($input['location'])) {
    $resolved_location = substr(trim($input['location']), 0, 255);
} else {
    $resolved_location = reverseGeocodeAddress($latitude, $longitude);
}

// Validate user_id
if ($user_id <= 0) {
    echo json_encode([
        "message" => "Invalid user_id", 
        "status" => false
    ]);
    exit;
}

// Validate latitude (-90 to 90)
if ($latitude < -90 || $latitude > 90) {
    echo json_encode([
        "message" => "Invalid latitude. Must be between -90 and 90", 
        "status" => false
    ]);
    exit;
}

// Validate longitude (-180 to 180)
if ($longitude < -180 || $longitude > 180) {
    echo json_encode([
        "message" => "Invalid longitude. Must be between -180 and 180", 
        "status" => false
    ]);
    exit;
}

// Role-based access control
if ($user_role === 'student') {
    // Students can only update their own location
    if ($current_user['user_id'] != $user_id) {
        echo json_encode([
            "message" => "Access denied. You can only update your own location", 
            "status" => false
        ]);
        exit;
    }
}

try {
    // Check if student profile exists for this user_id
    $check_sql = "SELECT id FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Record exists - UPDATE
        if ($resolved_location !== null && $resolved_location !== '') {
            $update_sql = "UPDATE student_profiles 
                          SET latitude = ?, longitude = ?, location = ?,  updated_at = NOW() 
                          WHERE user_id = ? AND deleted_at IS NULL";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ddsi", $latitude, $longitude, $resolved_location, $user_id);
        } else {
            $update_sql = "UPDATE student_profiles 
                          SET latitude = ?, longitude = ?,  updated_at = NOW() 
                          WHERE user_id = ? AND deleted_at IS NULL";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ddi", $latitude, $longitude, $user_id);
        }
        
        if (mysqli_stmt_execute($update_stmt)) {
            if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                echo json_encode([
                    "message" => "Location updated successfully",
                    "status" => true,
                    "action" => "updated",
                    "data" => [
                        "user_id" => $user_id,
                        "latitude" => $latitude,
                        "longitude" => $longitude,
                        "location" => $resolved_location
                    ]
                ]);
            } else {
                echo json_encode([
                    "message" => "No changes made to location",
                    "status" => false
                ]);
            }
        } else {
            echo json_encode([
                "message" => "Failed to update location: " . mysqli_error($conn),
                "status" => false
            ]);
        }
        
        mysqli_stmt_close($update_stmt);
    } else {
        // Record doesn't exist - INSERT
        if ($resolved_location !== null && $resolved_location !== '') {
            $insert_sql = "INSERT INTO student_profiles (user_id, latitude, longitude, location, created_at,  updated_at) 
                          VALUES (?, ?, ?, ?, NOW(), NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "idds", $user_id, $latitude, $longitude, $resolved_location);
        } else {
            $insert_sql = "INSERT INTO student_profiles (user_id, latitude, longitude, created_at,  updated_at) 
                          VALUES (?, ?, ?, NOW(), NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "idd", $user_id, $latitude, $longitude);
        }
        
        if (mysqli_stmt_execute($insert_stmt)) {
            echo json_encode([
                "message" => "Location saved successfully",
                "status" => true,
                "action" => "created",
                "data" => [
                    "user_id" => $user_id,
                    "latitude" => $latitude,
                    "longitude" => $longitude,
                    "location" => $resolved_location
                ]
            ]);
        } else {
            echo json_encode([
                "message" => "Failed to save location: " . mysqli_error($conn),
                "status" => false
            ]);
        }
        
        mysqli_stmt_close($insert_stmt);
    }
    
    mysqli_stmt_close($check_stmt);
    
} catch (Exception $e) {
    echo json_encode([
        "message" => "Database error: " . $e->getMessage(),
        "status" => false
    ]);
}

mysqli_close($conn);
?>

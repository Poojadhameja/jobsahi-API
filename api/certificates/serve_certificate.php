<?php
// serve_certificate.php - Serve certificate PDF files with proper headers
require_once '../cors.php';
require_once '../db.php';

// Authenticate user (admin, institute, or student can view certificates)
$decoded = authenticateJWT(['admin', 'institute', 'student']);
$user_role = strtolower($decoded['role'] ?? '');
$user_id = intval($decoded['user_id'] ?? 0);

// Get certificate ID or file path from query parameter
$certificate_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$file_path = isset($_GET['file']) ? $_GET['file'] : '';

// If certificate_id provided, fetch file_url from database
$file_url = '';
if ($certificate_id > 0) {
    $stmt = $conn->prepare("SELECT file_url FROM certificates WHERE id = ? AND admin_action = 'approved' LIMIT 1");
    $stmt->bind_param("i", $certificate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $file_url = $row['file_url'];
    }
    $stmt->close();
}

// ✅ CHECK IF FILE IS IN R2 (Cloudflare R2 URL)
require_once __DIR__ . '/../config/r2_config.php';
$is_r2_url = false;
if (!empty($file_url)) {
    // Check if URL is from R2 (contains r2.dev or R2_PUBLIC_URL)
    $r2_public_url = defined('R2_PUBLIC_URL') ? R2_PUBLIC_URL : '';
    if (!empty($r2_public_url) && strpos($file_url, $r2_public_url) === 0) {
        $is_r2_url = true;
    } elseif (strpos($file_url, 'r2.dev') !== false || strpos($file_url, 'r2.cloudflarestorage.com') !== false) {
        $is_r2_url = true;
    }
}

// If R2 URL, redirect to it (R2 URLs are public and CDN-optimized)
if ($is_r2_url && !empty($file_url)) {
    // Redirect to R2 URL (301 permanent redirect for better caching)
    header('Location: ' . $file_url, true, 301);
    exit;
}

// ✅ FALLBACK: Handle local file storage (for backward compatibility)
if (!empty($file_url)) {
    // Extract filename from URL
    // URL format: http://host/jobsahi-API/api/uploads/institute_certificate/certificate_X.pdf
    $parsed_url = parse_url($file_url);
    $url_path = $parsed_url['path'] ?? '';
    
    // Extract just the filename (e.g., certificate_3.pdf)
    $file_path = basename($url_path);
}

// If file_path provided directly, use it (extract just filename)
if (!empty($file_path)) {
    $file_path = basename($file_path);
}

// If still empty, return error
if (empty($file_path)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(["status" => false, "message" => "Certificate file not found"]);
    exit;
}

// Build full file path
$base_dir = __DIR__ . '/../uploads/institute_certificate/';
// Normalize base directory path
$base_dir = rtrim($base_dir, '/\\') . DIRECTORY_SEPARATOR;
// Build full path safely
$full_path = $base_dir . ltrim($file_path, '/\\');

// Security: Ensure file is within allowed directory
$real_base = realpath($base_dir);
$real_file = realpath($full_path);

// Check if base directory exists
if (!$real_base || !is_dir($real_base)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        "status" => false, 
        "message" => "Server configuration error: uploads directory not found"
    ]);
    exit;
}

// Check if file exists first (before security check)
if (!$real_file || !file_exists($real_file) || !is_file($real_file)) {
    // File doesn't exist - return 404
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        "status" => false, 
        "message" => "Certificate file not found",
        "debug_info" => [
            "certificate_id" => $certificate_id,
            "file_path_param" => $file_path,
            "full_path" => $full_path,
            "base_dir" => $base_dir
        ]
    ]);
    exit;
}

// Security check: ensure file is within base directory (normalize paths for comparison)
$normalized_base = str_replace('\\', '/', $real_base);
$normalized_file = str_replace('\\', '/', $real_file);

if (strpos($normalized_file, $normalized_base) !== 0) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        "status" => false, 
        "message" => "Access denied: Invalid file path"
    ]);
    exit;
}

// Verify it's a PDF file
$file_ext = strtolower(pathinfo($real_file, PATHINFO_EXTENSION));
if ($file_ext !== 'pdf') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["status" => false, "message" => "Invalid file type"]);
    exit;
}

// Get file size
$file_size = filesize($real_file);

// Set proper headers for PDF download/viewing
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($real_file) . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: private, max-age=3600');
header('Pragma: public');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');

// Clear any output buffers to prevent corruption
while (ob_get_level()) {
    ob_end_clean();
}

// Disable output buffering
if (ob_get_level()) {
    ob_end_flush();
}

// Output the file in binary mode
$handle = fopen($real_file, 'rb');
if ($handle) {
    // Output file in chunks to handle large files
    while (!feof($handle)) {
        echo fread($handle, 8192); // 8KB chunks
        flush();
    }
    fclose($handle);
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => false, "message" => "Failed to read certificate file"]);
}
exit;
?>


<?php
// google/initiate.php - Generate Google OAuth URL for frontend

require_once '../../../cors.php';
require_once '../../../helpers/oauth_helper.php';

header('Content-Type: application/json');

try {
    // Optional: Get state from request (for CSRF protection)
    $state = isset($_GET['state']) ? trim($_GET['state']) : null;
    
    // Check if credentials are loaded
    if (empty(GOOGLE_CLIENT_ID)) {
        throw new Exception("GOOGLE_CLIENT_ID is not set. Please check your .env file and ensure GOOGLE_CLIENT_ID is configured.");
    }
    
    // Generate OAuth URL
    $authUrl = OAuthHelper::getGoogleAuthUrl($state);
    
    // Check if URL was generated successfully
    if (empty($authUrl)) {
        throw new Exception("Failed to generate OAuth URL. Please check OAuth configuration.");
    }
    
    // Verify client_id is in URL
    if (strpos($authUrl, 'client_id') === false || strpos($authUrl, GOOGLE_CLIENT_ID) === false) {
        throw new Exception("Generated URL is missing client_id. GOOGLE_CLIENT_ID might be empty.");
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "auth_url" => $authUrl,
        "message" => "Google OAuth URL generated successfully"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error generating OAuth URL: " . $e->getMessage()
    ]);
}
?>

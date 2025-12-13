<?php
// OAuth Configuration File
// Loads OAuth credentials from environment variables (.env file)
// ⚠️ IMPORTANT: Never commit .env file or oauth_config.php with real credentials

// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Google OAuth Configuration
// ⚠️ IMPORTANT: These must be set in .env file - no hardcoded values for security
$google_client_id = getenv('GOOGLE_CLIENT_ID') ?: '';
$google_client_secret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
$google_redirect_uri = getenv('GOOGLE_REDIRECT_URI') ?: '';

// Validate that required Google OAuth credentials are set
if (empty($google_client_id) || empty($google_client_secret) || empty($google_redirect_uri)) {
    error_log("ERROR: Google OAuth credentials not configured. Please set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in .env file");
    // Throw error or use empty values - application should handle this gracefully
}

define('GOOGLE_CLIENT_ID', $google_client_id);
define('GOOGLE_CLIENT_SECRET', $google_client_secret);
define('GOOGLE_REDIRECT_URI', $google_redirect_uri);

// LinkedIn OAuth Configuration
// ⚠️ IMPORTANT: These must be set in .env file - no hardcoded values for security
$linkedin_client_id = getenv('LINKEDIN_CLIENT_ID') ?: '';
$linkedin_client_secret = getenv('LINKEDIN_CLIENT_SECRET') ?: '';
$linkedin_redirect_uri = getenv('LINKEDIN_REDIRECT_URI') ?: '';

// Validate that required LinkedIn OAuth credentials are set
if (empty($linkedin_client_id) || empty($linkedin_client_secret) || empty($linkedin_redirect_uri)) {
    error_log("ERROR: LinkedIn OAuth credentials not configured. Please set LINKEDIN_CLIENT_ID, LINKEDIN_CLIENT_SECRET, and LINKEDIN_REDIRECT_URI in .env file");
    // Throw error or use empty values - application should handle this gracefully
}

define('LINKEDIN_CLIENT_ID', $linkedin_client_id);
define('LINKEDIN_CLIENT_SECRET', $linkedin_client_secret);
define('LINKEDIN_REDIRECT_URI', $linkedin_redirect_uri);

// Google OAuth URLs
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo'); // Can also use: https://openidconnect.googleapis.com/v1/userinfo

// LinkedIn OAuth URLs
define('LINKEDIN_AUTH_URL', 'https://www.linkedin.com/oauth/v2/authorization');
define('LINKEDIN_TOKEN_URL', 'https://www.linkedin.com/oauth/v2/accessToken');
define('LINKEDIN_USERINFO_URL', 'https://api.linkedin.com/v2/userinfo'); // OpenID Connect endpoint
?>


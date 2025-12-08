<?php
// OAuth Configuration File
// Loads OAuth credentials from environment variables (.env file)
// ⚠️ IMPORTANT: Never commit .env file or oauth_config.php with real credentials

// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost/jobsahi-API/api/auth/oauth/google/callback.php');

// LinkedIn OAuth Configuration
define('LINKEDIN_CLIENT_ID', getenv('LINKEDIN_CLIENT_ID') ?: '');
define('LINKEDIN_CLIENT_SECRET', getenv('LINKEDIN_CLIENT_SECRET') ?: '');
define('LINKEDIN_REDIRECT_URI', getenv('LINKEDIN_REDIRECT_URI') ?: 'http://localhost/jobsahi-API/api/auth/oauth/linkedin/callback.php');

// Google OAuth URLs
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo'); // Can also use: https://openidconnect.googleapis.com/v1/userinfo

// LinkedIn OAuth URLs
define('LINKEDIN_AUTH_URL', 'https://www.linkedin.com/oauth/v2/authorization');
define('LINKEDIN_TOKEN_URL', 'https://www.linkedin.com/oauth/v2/accessToken');
define('LINKEDIN_USERINFO_URL', 'https://api.linkedin.com/v2/userinfo'); // OpenID Connect endpoint
?>


<?php
/**
 * Cloudflare R2 Configuration File
 * Loads R2 credentials from environment variables (.env file)
 * ⚠️ IMPORTANT: Never commit .env file or r2_config.php with real credentials
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Helper function to get env variable (checks both getenv and $_ENV)
function getEnvValue($key, $default = '') {
    // Try getenv first
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    // Fallback to $_ENV
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    return $default;
}

// Cloudflare R2 Configuration
// Try .env file first, then fallback to direct values (for shared hosting)

// Get values from .env file
$r2_account_id = getEnvValue('R2_ACCOUNT_ID', '');
$r2_access_key = getEnvValue('R2_ACCESS_KEY_ID', '');
$r2_secret_key = getEnvValue('R2_SECRET_ACCESS_KEY', '');
$r2_bucket = getEnvValue('R2_BUCKET_NAME', 'jobsahi-media');
$r2_endpoint = getEnvValue('R2_ENDPOINT', '');
$r2_public_url = getEnvValue('R2_PUBLIC_URL', '');

// ⚠️ FALLBACK: If .env file is not loading on shared hosting, 
// fill these values directly below (REQUIRED for Hostinger)
// Replace the empty strings with your actual R2 credentials
if (empty($r2_account_id)) {
    $r2_account_id = ''; // ⚠️ PUT YOUR R2 ACCOUNT ID HERE
}
if (empty($r2_access_key)) {
    $r2_access_key = ''; // ⚠️ PUT YOUR R2 ACCESS KEY ID HERE
}
if (empty($r2_secret_key)) {
    $r2_secret_key = ''; // ⚠️ PUT YOUR R2 SECRET ACCESS KEY HERE
}
if (empty($r2_bucket)) {
    $r2_bucket = 'jobsahi-media'; // Default bucket name
}
if (empty($r2_endpoint)) {
    $r2_endpoint = ''; // ⚠️ PUT YOUR R2 ENDPOINT HERE (e.g., https://account-id.r2.cloudflarestorage.com)
}
if (empty($r2_public_url)) {
    $r2_public_url = ''; // ⚠️ PUT YOUR R2 PUBLIC DOMAIN HERE (e.g., https://your-domain.r2.dev)
}

// Define constants
define('R2_ACCOUNT_ID', $r2_account_id);
define('R2_ACCESS_KEY_ID', $r2_access_key);
define('R2_SECRET_ACCESS_KEY', $r2_secret_key);
define('R2_BUCKET_NAME', $r2_bucket);
define('R2_ENDPOINT', $r2_endpoint);
define('R2_PUBLIC_URL', $r2_public_url);

// R2 S3-Compatible Endpoint (without bucket name in URL)
// Format: https://{account_id}.r2.cloudflarestorage.com
$r2_endpoint_base = R2_ENDPOINT;
$bucket_name = R2_BUCKET_NAME;

if (!empty($r2_endpoint_base)) {
    // Remove bucket name if present in endpoint
    $r2_endpoint_base = preg_replace('#/' . preg_quote($bucket_name, '#') . '$#', '', $r2_endpoint_base);
    $r2_endpoint_base = rtrim($r2_endpoint_base, '/');
}

if (empty($r2_endpoint_base)) {
    // Auto-generate from account ID if not provided
    $account_id = R2_ACCOUNT_ID;
    if (!empty($account_id)) {
        $r2_endpoint_base = "https://{$account_id}.r2.cloudflarestorage.com";
    }
}
define('R2_ENDPOINT_BASE', $r2_endpoint_base);

// Validate required credentials
function isR2Configured() {
    // Check all required constants
    $account_id = defined('R2_ACCOUNT_ID') ? R2_ACCOUNT_ID : '';
    $access_key = defined('R2_ACCESS_KEY_ID') ? R2_ACCESS_KEY_ID : '';
    $secret_key = defined('R2_SECRET_ACCESS_KEY') ? R2_SECRET_ACCESS_KEY : '';
    $bucket = defined('R2_BUCKET_NAME') ? R2_BUCKET_NAME : '';
    $endpoint = defined('R2_ENDPOINT_BASE') ? R2_ENDPOINT_BASE : '';
    
    return !empty($account_id) && 
           !empty($access_key) && 
           !empty($secret_key) && 
           !empty($bucket) && 
           !empty($endpoint);
}
?>


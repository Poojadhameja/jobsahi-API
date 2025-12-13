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
define('R2_ACCOUNT_ID', getEnvValue('R2_ACCOUNT_ID', ''));
define('R2_ACCESS_KEY_ID', getEnvValue('R2_ACCESS_KEY_ID', ''));
define('R2_SECRET_ACCESS_KEY', getEnvValue('R2_SECRET_ACCESS_KEY', ''));
define('R2_BUCKET_NAME', getEnvValue('R2_BUCKET_NAME', 'jobsahi-media'));
define('R2_ENDPOINT', getEnvValue('R2_ENDPOINT', ''));
define('R2_PUBLIC_URL', getEnvValue('R2_PUBLIC_URL', '')); // Public dev URL for accessing files

// R2 S3-Compatible Endpoint (without bucket name in URL)
// Format: https://{account_id}.r2.cloudflarestorage.com
$r2_endpoint_base = getEnvValue('R2_ENDPOINT', '');
$bucket_name = getEnvValue('R2_BUCKET_NAME', 'jobsahi-media');

if (!empty($r2_endpoint_base)) {
    // Remove bucket name if present in endpoint
    $r2_endpoint_base = preg_replace('#/' . preg_quote($bucket_name, '#') . '$#', '', $r2_endpoint_base);
    $r2_endpoint_base = rtrim($r2_endpoint_base, '/');
}

if (empty($r2_endpoint_base)) {
    // Auto-generate from account ID if not provided
    $account_id = getEnvValue('R2_ACCOUNT_ID', '');
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


<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file in project root
 */
function loadEnv($envFile = null) {
    if ($envFile === null) {
        // Default to project root .env file
        $envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
    }
    
    if (!file_exists($envFile)) {
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
    
    return true;
}

// Auto-load .env file when this file is included
loadEnv();
?>

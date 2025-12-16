<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file in project root
 * Supports both local development and shared hosting (Hostinger, etc.)
 */
function loadEnv($envFile = null) {
    if ($envFile === null) {
        // Try multiple possible locations for .env file
        $possiblePaths = [
            // Local development (XAMPP)
            dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
            // Shared hosting (Hostinger) - try parent directory
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
            // Shared hosting - try public_html parent
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env',
            // Try absolute path from config directory
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env',
            // Try from api directory
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env',
        ];
        
        $envFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $envFile = $path;
                break;
            }
        }
        
        if ($envFile === null) {
            // Log warning but don't fail - might be using direct config
            error_log("Warning: .env file not found. Tried paths: " . implode(', ', $possiblePaths));
            return false;
        }
    }
    
    if (!file_exists($envFile) || !is_readable($envFile)) {
        error_log("Warning: .env file not readable: " . $envFile);
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        error_log("Warning: Failed to read .env file: " . $envFile);
        return false;
    }
    
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
$envLoaded = loadEnv();

// Debug: Log if .env was loaded (useful for troubleshooting)
// Uncomment for debugging:
// if ($envLoaded) {
//     error_log("Success: .env file loaded successfully");
// } else {
//     error_log("Warning: .env file not loaded - using direct config or environment variables");
// }
?>

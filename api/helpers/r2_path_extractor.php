<?php
/**
 * R2 Path Extractor Helper
 * Extracts R2 bucket path from various URL formats
 */

/**
 * Extract R2 path from URL
 * Handles multiple URL formats:
 * - https://bucket-name.r2.dev/path/to/file
 * - https://pub-xxx.r2.dev/path/to/file
 * - https://account-id.r2.cloudflarestorage.com/bucket-name/path/to/file
 * - https://workers.dev/path/to/file
 * 
 * @param string $url The R2 URL
 * @param string $bucketName The bucket name (optional, will use R2_BUCKET_NAME if not provided)
 * @return string The R2 path (e.g., "student_profile_image/profile_12.jpg")
 */
function extractR2PathFromUrl($url, $bucketName = null) {
    if (empty($url)) {
        return '';
    }
    
    if (empty($bucketName)) {
        $bucketName = defined('R2_BUCKET_NAME') ? R2_BUCKET_NAME : 'jobsahi-media';
    }
    
    // Parse the URL
    $parsedUrl = parse_url($url);
    if (!isset($parsedUrl['path'])) {
        return '';
    }
    
    $path = ltrim($parsedUrl['path'], '/');
    
    // Remove bucket name if present at the start
    if (strpos($path, $bucketName . '/') === 0) {
        $path = substr($path, strlen($bucketName) + 1);
    }
    
    // Also check for jobsahi-media prefix (backward compatibility)
    if (strpos($path, 'jobsahi-media/') === 0) {
        $path = str_replace('jobsahi-media/', '', $path);
    }
    
    return $path;
}

/**
 * Extract R2 path from profile image URL
 * Specifically handles profile image URLs and constructs path if needed
 * 
 * @param string $url The profile image URL
 * @param int $userId The user ID (for fallback path construction)
 * @return string The R2 path
 */
function extractProfileImagePath($url, $userId) {
    if (empty($url)) {
        return '';
    }
    
    // Check if it's an R2 URL
    $isR2Url = strpos($url, 'r2.dev') !== false || 
               strpos($url, 'r2.cloudflarestorage.com') !== false || 
               strpos($url, 'pub-') !== false ||
               strpos($url, 'workers.dev') !== false;
    
    if (!$isR2Url) {
        return '';
    }
    
    // Extract path from URL
    $r2Path = extractR2PathFromUrl($url);
    
    // If path is empty, try to construct it from user_id
    if (empty($r2Path)) {
        // Try to get extension from URL
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        if (empty($ext)) {
            // Try common image extensions
            if (strpos($url, '.jpg') !== false || strpos($url, '.jpeg') !== false) {
                $ext = 'jpg';
            } else if (strpos($url, '.png') !== false) {
                $ext = 'png';
            } else {
                $ext = 'jpg'; // Default
            }
        }
        $r2Path = "student_profile_image/profile_{$userId}." . $ext;
    } else {
        // Path exists - verify it's for profile image
        // If it doesn't start with student_profile_image, but contains profile_ and user_id, it's likely correct
        if (strpos($r2Path, 'student_profile_image/') !== 0) {
            // Check if it's a profile image path by checking for profile_ prefix and user_id
            if (strpos($r2Path, 'profile_') !== false && strpos($r2Path, (string)$userId) !== false) {
                // It's likely a profile image, use as is
            } else {
                // Not a profile image path, construct correct one
                $ext = pathinfo($r2Path, PATHINFO_EXTENSION);
                if (empty($ext)) {
                    $ext = pathinfo($url, PATHINFO_EXTENSION) ?: 'jpg';
                }
                $r2Path = "student_profile_image/profile_{$userId}." . $ext;
            }
        }
    }
    
    return $r2Path;
}
?>


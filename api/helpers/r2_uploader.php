<?php
/**
 * Cloudflare R2 File Uploader Helper
 * S3-compatible upload using cURL (no external dependencies required)
 */

require_once __DIR__ . '/../config/r2_config.php';

class R2Uploader {
    
    /**
     * Upload file to Cloudflare R2
     * 
     * @param string $filePath Local file path to upload
     * @param string $r2Path Path in R2 bucket (e.g., 'resumes/resume_123.pdf')
     * @param string $contentType MIME type of the file
     * @return array ['success' => bool, 'url' => string, 'message' => string]
     */
    public static function uploadFile($filePath, $r2Path, $contentType = null) {
        // Validate R2 configuration
        if (!isR2Configured()) {
            return [
                'success' => false,
                'message' => 'R2 configuration is incomplete. Please check your .env file.'
            ];
        }
        
        // Check if file exists
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => 'Local file not found: ' . $filePath
            ];
        }
        
        // Auto-detect content type if not provided
        if (empty($contentType)) {
            $contentType = mime_content_type($filePath);
            if (empty($contentType)) {
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $contentType = self::getMimeType($ext);
            }
        }
        
        // Read file content
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return [
                'success' => false,
                'message' => 'Failed to read file content'
            ];
        }
        
        // Prepare S3-compatible request
        $bucket = R2_BUCKET_NAME;
        $endpoint = R2_ENDPOINT_BASE;
        $host = parse_url($endpoint, PHP_URL_HOST);
        
        // Generate AWS Signature Version 4
        $date = gmdate('Ymd\THis\Z');
        $dateShort = gmdate('Ymd');
        $region = 'auto'; // R2 uses 'auto' as region
        
        // Calculate payload hash FIRST (required for x-amz-content-sha256 header)
        $payloadHash = hash('sha256', $fileContent);
        
        // Build URL path (URL-encode each segment separately, preserve slashes)
        $urlPath = '/' . $bucket;
        if (!empty($r2Path)) {
            $pathParts = explode('/', $r2Path);
            foreach ($pathParts as $part) {
                if ($part !== '') {
                    $urlPath .= '/' . rawurlencode($part);
                }
            }
        }
        
        // Canonical URI must match the URL path exactly
        $canonicalUri = $urlPath;
        $url = $endpoint . $urlPath;
        
        // Prepare headers for canonical request (MUST include x-amz-content-sha256)
        $headersForCanonical = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $date
        ];
        
        // Add content-type only if not empty
        if (!empty($contentType)) {
            $headersForCanonical['content-type'] = $contentType;
        }
        
        // Sort headers alphabetically by key (required for canonical request)
        ksort($headersForCanonical);
        
        // Build canonical headers string
        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headersForCanonical as $key => $value) {
            $keyLower = strtolower(trim($key));
            $valueTrimmed = trim($value);
            // Normalize multiple spaces to single space
            $valueTrimmed = preg_replace('/\s+/', ' ', $valueTrimmed);
            $canonicalHeaders .= $keyLower . ':' . $valueTrimmed . "\n";
            $signedHeadersList[] = $keyLower;
        }
        $signedHeaders = implode(';', $signedHeadersList);
        
        $canonicalQueryString = '';
        
        // Build canonical request
        $canonicalRequest = "PUT\n" .
                           $canonicalUri . "\n" .
                           $canonicalQueryString . "\n" .
                           $canonicalHeaders . "\n" .
                           $signedHeaders . "\n" .
                           $payloadHash;
        
        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = $dateShort . '/' . $region . '/s3/aws4_request';
        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
        $stringToSign = $algorithm . "\n" .
                       $date . "\n" .
                       $credentialScope . "\n" .
                       $hashedCanonicalRequest;
        
        // Calculate signature (using raw binary output for intermediate steps, hex for final)
        $kSecret = 'AWS4' . R2_SECRET_ACCESS_KEY;
        $kDate = hash_hmac('sha256', $dateShort, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning, false); // false = hex output
        
        // Create authorization header
        $authorization = $algorithm . ' ' .
                        'Credential=' . R2_ACCESS_KEY_ID . '/' . $credentialScope . ', ' .
                        'SignedHeaders=' . $signedHeaders . ', ' .
                        'Signature=' . $signature;
        
        // Prepare final headers for cURL (must include x-amz-content-sha256)
        $curlHeaders = [
            'Host: ' . $host,
            'X-Amz-Content-Sha256: ' . $payloadHash,
            'X-Amz-Date: ' . $date,
            'Content-Length: ' . strlen($fileContent),
            'Authorization: ' . $authorization
        ];
        
        // Add Content-Type only if provided
        if (!empty($contentType)) {
            $curlHeaders[] = 'Content-Type: ' . $contentType;
        }
        
        // Execute cURL request
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $fileContent,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if (!empty($error)) {
            return [
                'success' => false,
                'message' => 'cURL error: ' . $error
            ];
        }
        
        // Extract body from response
        $responseBody = substr($response, $headerSize);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            // Success - construct public URL
            $publicUrl = '';
            if (!empty(R2_PUBLIC_URL)) {
                // Use custom public URL if provided
                $publicUrl = rtrim(R2_PUBLIC_URL, '/') . '/' . $r2Path;
            } else {
                // Fallback to endpoint URL (may require authentication)
                $publicUrl = $url;
            }
            
            return [
                'success' => true,
                'url' => $publicUrl,
                'r2_path' => $r2Path,
                'message' => 'File uploaded successfully to R2'
            ];
        } else {
            // Parse XML error response
            $errorMessage = 'Upload failed. HTTP Code: ' . $httpCode;
            if (!empty($responseBody)) {
                $xml = @simplexml_load_string($responseBody);
                if ($xml !== false) {
                    $errorMessage .= '. ' . (string)$xml->Code . ': ' . (string)$xml->Message;
                } else {
                    $errorMessage .= '. Response: ' . substr($responseBody, 0, 500);
                }
            }
            return [
                'success' => false,
                'message' => 'Upload failed. HTTP Code: ' . $httpCode . ', Response: ' . substr($responseBody, 0, 500)
            ];
        }
    }
    
    /**
     * Delete file from R2
     * 
     * @param string $r2Path Path in R2 bucket
     * @return array ['success' => bool, 'message' => string]
     */
    public static function deleteFile($r2Path) {
        if (!isR2Configured()) {
            return [
                'success' => false,
                'message' => 'R2 configuration is incomplete'
            ];
        }
        
        $bucket = R2_BUCKET_NAME;
        $endpoint = R2_ENDPOINT_BASE;
        $host = parse_url($endpoint, PHP_URL_HOST);
        
        // URL-encode the path parts (but keep slashes)
        $encodedPath = '';
        $pathParts = explode('/', $r2Path);
        foreach ($pathParts as $part) {
            if (!empty($part)) {
                $encodedPath .= '/' . rawurlencode($part);
            }
        }
        // Build full URL with encoded path
        $url = "{$endpoint}/{$bucket}" . $encodedPath;
        
        $date = gmdate('Ymd\THis\Z');
        $dateShort = gmdate('Ymd');
        $region = 'auto';
        
        // Prepare headers for canonical request (must be lowercase and sorted)
        $headersMap = [
            'host' => strtolower($host),
            'x-amz-date' => $date
        ];
        
        // Sort headers alphabetically by key
        ksort($headersMap);
        
        // Build canonical headers string
        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headersMap as $key => $value) {
            $canonicalHeaders .= $key . ':' . trim($value) . "\n";
            $signedHeadersList[] = $key;
        }
        $signedHeaders = implode(';', $signedHeadersList);
        
        $canonicalUri = '/' . $bucket . $encodedPath;
        $canonicalQueryString = '';
        $payloadHash = hash('sha256', '');
        
        $canonicalRequest = "DELETE\n" .
                           $canonicalUri . "\n" .
                           $canonicalQueryString . "\n" .
                           $canonicalHeaders . "\n" .
                           $signedHeaders . "\n" .
                           $payloadHash;
        
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = $dateShort . '/' . $region . '/s3/aws4_request';
        $stringToSign = $algorithm . "\n" .
                       $date . "\n" .
                       $credentialScope . "\n" .
                       hash('sha256', $canonicalRequest);
        
        $kSecret = 'AWS4' . R2_SECRET_ACCESS_KEY;
        $kDate = hash_hmac('sha256', $dateShort, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        $authorization = $algorithm . ' ' .
                        'Credential=' . R2_ACCESS_KEY_ID . '/' . $credentialScope . ', ' .
                        'SignedHeaders=' . $signedHeaders . ', ' .
                        'Signature=' . $signature;
        
        $curlHeaders = [
            'Host: ' . $host,
            'X-Amz-Date: ' . $date,
            'Authorization: ' . $authorization
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'File deleted successfully from R2'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Delete failed. HTTP Code: ' . $httpCode
            ];
        }
    }
    
    /**
     * Get MIME type from file extension
     */
    private static function getMimeType($ext) {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'csv' => 'text/csv',
            'txt' => 'text/plain'
        ];
        
        return $mimeTypes[strtolower($ext)] ?? 'application/octet-stream';
    }
}
?>


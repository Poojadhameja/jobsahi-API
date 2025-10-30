<?php
// Response Helper - Standardize API responses
require_once __DIR__ . '/../db.php';

class ResponseHelper {
    
    /**
     * Send success response
     */
    public static function success($data = null, $message = SUCCESS_MESSAGE, $statusCode = HTTP_OK) {
        http_response_code($statusCode);
        echo json_encode([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send error response
     */
    public static function error($message = ERROR_MESSAGE, $statusCode = HTTP_BAD_REQUEST, $errors = null) {
        http_response_code($statusCode);
        echo json_encode([
            'status' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors, $message = VALIDATION_ERROR) {
        self::error($message, HTTP_BAD_REQUEST, $errors);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = UNAUTHORIZED) {
        self::error($message, HTTP_UNAUTHORIZED);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, HTTP_FORBIDDEN);
    }
    
    /**
     * Send not found response
     */
    public static function notFound($message = NOT_FOUND) {
        self::error($message, HTTP_NOT_FOUND);
    }
    
    /**
     * Send method not allowed response
     */
    public static function methodNotAllowed($message = 'Method not allowed') {
        self::error($message, HTTP_METHOD_NOT_ALLOWED);
    }
    
    /**
     * Send internal server error response
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, HTTP_INTERNAL_SERVER_ERROR);
    }
    
    /**
     * Set CORS headers
     */
    public static function setCORS() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: application/json');
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Validate request method
     */
    public static function validateMethod($allowedMethods) {
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            self::methodNotAllowed('Only ' . implode(', ', $allowedMethods) . ' methods are allowed');
        }
    }
    
    /**
     * Get JSON input
     */
    public static function getJsonInput() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('Invalid JSON data');
        }
        
        return $data;
    }
}
?>

<?php
// response_helper.php - Standardized API Response Helper

class ResponseHelper {
    
    /**
     * Send success response
     */
    public static function success($data = null, $message = "Operation completed successfully", $code = 200) {
        http_response_code($code);
        echo json_encode([
            "status" => true,
            "message" => $message,
            "data" => $data,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send error response
     */
    public static function error($message = "An error occurred", $code = 400, $error_code = null) {
        http_response_code($code);
        $response = [
            "status" => false,
            "message" => $message,
            "timestamp" => date('Y-m-d H:i:s')
        ];
        
        if ($error_code) {
            $response["code"] = $error_code;
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors = [], $message = "Validation failed") {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => $message,
            "errors" => $errors,
            "code" => "VALIDATION_ERROR",
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = "Unauthorized access") {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => $message,
            "code" => "UNAUTHORIZED",
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden($message = "Access forbidden") {
        http_response_code(403);
        echo json_encode([
            "status" => false,
            "message" => $message,
            "code" => "FORBIDDEN",
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send not found response
     */
    public static function notFound($message = "Resource not found") {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => $message,
            "code" => "NOT_FOUND",
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send paginated response
     */
    public static function paginated($data, $page, $limit, $total_count, $message = "Data fetched successfully") {
        $total_pages = ceil($total_count / $limit);
        
        echo json_encode([
            "status" => true,
            "message" => $message,
            "data" => $data,
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_count" => (int)$total_count,
                "total_pages" => $total_pages,
                "has_next" => $page < $total_pages,
                "has_prev" => $page > 1
            ],
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Validate required fields
     */
    public static function validateRequired($data, $required_fields) {
        $errors = [];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[$field] = ucfirst($field) . " is required";
            }
        }
        
        if (!empty($errors)) {
            self::validationError($errors);
        }
        
        return true;
    }
    
    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::validationError(['email' => 'Invalid email format']);
        }
        return true;
    }
    
    /**
     * Validate password strength
     */
    public static function validatePassword($password, $min_length = 6) {
        if (strlen($password) < $min_length) {
            self::validationError(['password' => "Password must be at least {$min_length} characters long"]);
        }
        return true;
    }
    
    /**
     * Sanitize input
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generate pagination parameters
     */
    public static function getPaginationParams($default_limit = 20, $max_limit = 100) {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $default_limit;
        $limit = ($limit > 0 && $limit <= $max_limit) ? $limit : $default_limit;
        $offset = ($page - 1) * $limit;
        
        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Generate sorting parameters
     */
    public static function getSortingParams($allowed_fields, $default_field = 'id', $default_order = 'DESC') {
        $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : $default_field;
        $sort_order = isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : $default_order;
        
        // Validate sort parameters
        $sort_by = in_array($sort_by, $allowed_fields) ? $sort_by : $default_field;
        $sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
        
        return [
            'sort_by' => $sort_by,
            'sort_order' => $sort_order
        ];
    }
}
?>

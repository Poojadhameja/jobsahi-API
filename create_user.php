<?php
// create_user.php - User registration with JWT
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once 'jwt_helper.php';
require_once 'auth_middleware.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("message" => "Only POST requests allowed", "status" => false));
    exit;
}

// Get and decode JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// Check if JSON was decoded successfully
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

// Check if required fields exist
$required_fields = ['name', 'email', 'password', 'phone_number'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        http_response_code(400);
        echo json_encode(array("message" => ucfirst($field) . " is required", "status" => false));
        exit;
    }
}

$name = trim($data['name']);
$email = trim($data['email']);
$password = trim($data['password']);
$phone_number = trim($data['phone_number']);
$role = isset($data['role']) ? trim($data['role']) : 'student';

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid email format", "status" => false));
    exit;
}

// Password strength validation
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(array("message" => "Password must be at least 6 characters long", "status" => false));
    exit;
}

include "config.php";

// Check if user already exists
$check_sql = "SELECT id FROM users WHERE email = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        http_response_code(409);
        echo json_encode(array("message" => "Email already exists", "status" => false));
        mysqli_stmt_close($check_stmt);
        mysqli_close($conn);
?>

<?php
// get_user_stats.php - Get user statistics (Admin only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once 'jwt_helper.php';
require_once 'auth_middleware.php';

// Authenticate and require admin role
authenticateJWT('admin');

include 'config.php';

$stats_sql = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
        SUM(CASE WHEN role = 'recruiter' THEN 1 ELSE 0 END) as recruiters,
        SUM(CASE WHEN role = 'institute' THEN 1 ELSE 0 END) as institutes,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
        SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as unverified_users
    FROM users
";

$result = mysqli_query($conn, $stats_sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(array("message" => "Database query failed", "status" => false));
    exit;
}

if (mysqli_num_rows($result) > 0) {
    $stats = mysqli_fetch_assoc($result);
    http_response_code(200);
    echo json_encode(array("stats" => $stats, "status" => true));
} else {
    http_response_code(404);
    echo json_encode(array("message" => "No data found", "status" => false));
}

mysqli_close($conn);
?>

<?php
// get_users_by_role.php - Get users by role (Admin only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once 'jwt_helper.php';
require_once 'auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("message" => "Only POST requests allowed", "status" => false));
    exit;
}

// Authenticate and require admin role
authenticateJWT('admin');

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

$role = isset($data['role']) ? trim($data['role']) : '';

if (empty($role)) {
    http_response_code(400);
    echo json_encode(array("message" => "Role is required", "status" => false));
    exit;
}

// Validate role
$valid_roles = ['student', 'recruiter', 'institute', 'admin'];
if (!in_array($role, $valid_roles)) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid role", "status" => false));
    exit;
}

include "config.php";

$sql = "SELECT id, name, email, role, phone_number, is_verified 
        FROM users 
        WHERE role = ? 
        ORDER BY name ASC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
        http_response_code(200);
        echo json_encode(array("users" => $users, "count" => count($users), "status" => true));
    } else {
        http_response_code(200);
        echo json_encode(array("message" => "No users found for this role", "users" => [], "status" => true));
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database query failed", "status" => false));
}

mysqli_close($conn);
?>

<?php
// verify_user.php - Verify/Unverify user (Admin only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once 'jwt_helper.php';
require_once 'auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(array("message" => "Only PUT requests allowed", "status" => false));
    exit;
}

// Authenticate and require admin role
authenticateJWT('admin');

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

if (!isset($data['uid']) || !isset($data['is_verified'])) {
    http_response_code(400);
    echo json_encode(array("message" => "User ID and verification status are required", "status" => false));
    exit;
}

$user_id = intval($data['uid']);
$is_verified = intval($data['is_verified']);

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid user ID", "status" => false));
    exit;
}

if ($is_verified !== 0 && $is_verified !== 1) {
    http_response_code(400);
    echo json_encode(array("message" => "Verification status must be 0 or 1", "status" => false));
    exit;
}

include "config.php";

// Check if user exists
$check_sql = "SELECT id FROM users WHERE id = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) == 0) {
        http_response_code(404);
        echo json_encode(array("message" => "User not found", "status" => false));
        mysqli_stmt_close($check_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($check_stmt);
}

// Update verification status
$sql = "UPDATE users SET is_verified = ? WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $is_verified, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $status_text = $is_verified ? "verified" : "unverified";
        http_response_code(200);
        echo json_encode(array("message" => "User {$status_text} successfully", "status" => true));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Failed to update verification status", "status" => false));
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare failed", "status" => false));
}

mysqli_close($conn);
?>

<?php
// search_users.php - Search users (Admin only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once 'jwt_helper.php';
require_once 'auth_middleware.php';

// Authenticate and require admin role
authenticateJWT('admin');

// Get search value from GET or POST
if (isset($_GET['search'])) {
    $search_value = $_GET['search'];
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $search_value = isset($data['search']) ? $data['search'] : '';
}

if (empty($search_value)) {
    http_response_code(400);
    echo json_encode(array("message" => "Search value is required", "status" => false));
    exit;
}

include "config.php";

$search_pattern = '%' . $search_value . '%';

$sql = "SELECT id, name, email, role, phone_number, is_verified 
        FROM users 
        WHERE name LIKE ? 
           OR email LIKE ? 
           OR phone_number LIKE ?
        ORDER BY name ASC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "sss", $search_pattern, $search_pattern, $search_pattern);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
        http_response_code(200);
        echo json_encode(array("users" => $users, "count" => count($users), "status" => true));
    } else {
        http_response_code(200);
        echo json_encode(array("message" => "No users found", "users" => [], "status" => false));
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database query failed", "status" => false));
}

mysqli_close($conn);
?>

<?php
// change_password.php - Change user password
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once 'jwt_helper.php';
require_once 'auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(array("message" => "Only PUT requests allowed", "status" => false));
    exit;
}

// Authenticate user
$current_user = authenticateJWT();

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

if (!isset($data['current_password']) || !isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Current password and new password are required", "status" => false));
    exit;
}

$user_id = isset($data['uid']) ? intval($data['uid']) : $current_user['user_id'];
$current_password = trim($data['current_password']);
$new_password = trim($data['new_password']);

// Check if user can change this password (own password or admin)
if ($current_user['role'] !== 'admin' && $current_user['user_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(array("message" => "Access denied", "status" => false));
    exit;
}

if (empty($current_password) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(array("message" => "Passwords cannot be empty", "status" => false));
    exit;
}

if (strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(array("message" => "New password must be at least 6 characters long", "status" => false));
    exit;
}

include "config.php";

// Get current password hash
$check_sql = "SELECT password FROM users WHERE id = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) == 0) {
        http_response_code(404);
        echo json_encode(array("message" => "User not found", "status" => false));
        mysqli_stmt_close($check_stmt);
        mysqli_close($conn);
        exit;
    }
    
    $user = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        http_response_code(401);
        echo json_encode(array("message" => "Current password is incorrect", "status" => false));
        mysqli_close($conn);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database query failed", "status" => false));
    mysqli_close($conn);
    exit;
}

// Update password
$hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
$update_sql = "UPDATE users SET password = ? WHERE id = ?";

if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
    mysqli_stmt_bind_param($update_stmt, "si", $hashed_new_password, $user_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        http_response_code(200);
        echo json_encode(array("message" => "Password changed successfully", "status" => true));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Failed to update password", "status" => false));
    }
    
    mysqli_stmt_close($update_stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare failed", "status" => false));
}

mysqli_close($conn);
?>

<?php
// bulk_operations.php - Bulk operations for users (Admin only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once 'jwt_helper.php';
require_once 'auth_middleware.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    http_response_code(405);
    echo json_encode(array("message" => "Only POST, PUT, DELETE requests allowed", "status" => false));
    exit;
}

// Authenticate and require admin role
authenticateJWT('admin');

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

if (!isset($data['operation']) || !isset($data['user_ids'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Operation and user_ids are required", "status" => false));
    exit;
}

$operation = $data['operation'];
$user_ids = $data['user_ids'];

if (empty($user_ids) || !is_array($user_ids)) {
    http_response_code(400);
    echo json_encode(array("message" => "User IDs array is required and cannot be empty", "status" => false));
    exit;
}

// Validate and sanitize user IDs
$user_ids = array_map('intval', $user_ids);
$user_ids = array_filter($user_ids, function($id) { return $id > 0; });

if (empty($user_ids)) {
    http_response_code(400);
    echo json_encode(array("message" => "Valid user IDs are required", "status" => false));
    exit;
}

include "config.php";

$placeholders = implode(',', array_fill(0, count($user_ids), '?'));
$types = str_repeat('i', count($user_ids));

switch ($operation) {
    case 'verify':
        $sql = "UPDATE users SET is_verified = 1 WHERE id IN ($placeholders)";
        break;
    case 'unverify':
        $sql = "UPDATE users SET is_verified = 0 WHERE id IN ($placeholders)";
        break;
    case 'delete':
        $sql = "DELETE FROM users WHERE id IN ($placeholders)";
        break;
    default:
        http_response_code(400);
        echo json_encode(array("message" => "Invalid operation. Use 'verify', 'unverify', or 'delete'", "status" => false));
        exit;
}

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, $types, ...$user_ids);
    
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        http_response_code(200);
        echo json_encode(array(
            "message" => "Bulk operation completed successfully", 
            "operation" => $operation,
            "affected_rows" => $affected_rows,
            "status" => true
        ));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Bulk operation failed", "status" => false));
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare failed", "status" => false));
}

mysqli_close($conn);
?>

<?php
// .htaccess file for URL rewriting and security
/*
RewriteEngine On

# Enable CORS for all origins (adjust as needed for production)
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"

# Handle preflight requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]

# API Routes
RewriteRule ^api/register$ create_user.php [L,QSA]
RewriteRule ^api/login$ login.php [L,QSA]
RewriteRule ^api/logout$ logout.php [L,QSA]
RewriteRule ^api/refresh$ refresh_token.php [L,QSA]
RewriteRule ^api/profile$ get_user_profile.php [L,QSA]
RewriteRule ^api/users$ get_all_users.php [L,QSA]
RewriteRule ^api/users/([0-9]+)$ get_user.php?uid=$1 [L,QSA]
RewriteRule ^api/users/update$ update_user.php [L,QSA]
RewriteRule ^api/users/delete$ delete_user.php [L,QSA]
RewriteRule ^api/users/stats$ get_user_stats.php [L,QSA]
RewriteRule ^api/users/role$ get_users_by_role.php [L,QSA]
RewriteRule ^api/users/verify$ verify_user.php [L,QSA]
RewriteRule ^api/users/search$ search_users.php [L,QSA]
RewriteRule ^api/users/change-password$ change_password.php [L,QSA]
RewriteRule ^api/users/bulk$ bulk_operations.php [L,QSA]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Hide PHP extensions
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^([^\.]+)$ $1.php [NC,L]
*/
?>

<?php
// API Documentation and Usage Examples

/*
=== JWT REST API Documentation ===

Base URL: http://your-domain.com/api/

Authentication:
- Include JWT token in Authorization header: "Bearer <token>"
- Token expires in 1 hour (configurable in config.php)

=== ENDPOINTS ===

1. USER REGISTRATION
   POST /api/register
   Body: {
     "name": "John Doe",
     "email": "john@example.com",
     "password": "password123",
     "phone_number": "1234567890",
     "role": "student" // optional, defaults to "student"
   }
   Response: {
     "message": "User registered successfully",
     "status": true,
     "user": { user_data },
     "token": "jwt_token_here",
     "expires_in": 3600
   }

2. USER LOGIN
   POST /api/login
   Body: {
     "email": "john@example.com",
     "password": "password123"
   }
   Response: {
     "message": "Login successful",
     "status": true,
     "user": { user_data },
     "token": "jwt_token_here",
     "expires_in": 3600
   }

3. REFRESH TOKEN
   POST /api/refresh
   Headers: Authorization: Bearer <token>
   Response: {
     "message": "Token refreshed successfully",
     "status": true,
     "token": "new_jwt_token_here",
     "expires_in": 3600
   }

4. GET USER PROFILE
   GET /api/profile
   Headers: Authorization: Bearer <token>
   Response: {
     "user": { current_user_data },
     "status": true
   }

5. GET ALL USERS (Admin only)
   GET /api/users
   Headers: Authorization: Bearer <admin_token>
   Response: {
     "users": [ array_of_users ],
     "count": 10,
     "status": true
   }

6. GET SINGLE USER
   GET /api/users/{id}
   Headers: Authorization: Bearer <token>
   Note: Users can only access their own data unless they're admin
   Response: {
     "user": { user_data },
     "status": true
   }

7. UPDATE USER
   PUT /api/users/update
   Headers: Authorization: Bearer <token>
   Body: {
     "uid": 1,
     "uname": "Updated Name",
     "uemail": "updated@example.com",
     "urole": "student",
     "uphone": "9876543210",
     "uverified": 1,
     "upassword": "newpassword123" // optional
   }
   Note: Users can only update their own data unless they're admin

8. DELETE USER
   DELETE /api/users/delete
   Headers: Authorization: Bearer <token>
   Body: {
     "uid": 1
   }
   Note: Users can only delete their own account unless they're admin

9. GET USER STATS (Admin only)
   GET /api/users/stats
   Headers: Authorization: Bearer <admin_token>
   Response: {
     "stats": {
       "total_users": 100,
       "students": 80,
       "recruiters": 15,
       "institutes": 4,
       "admins": 1,
       "verified_users": 95,
       "unverified_users": 5
     },
     "status": true
   }

10. GET USERS BY ROLE (Admin only)
    POST /api/users/role
    Headers: Authorization: Bearer <admin_token>
    Body: {
      "role": "student"
    }
    Response: {
      "users": [ users_with_specified_role ],
      "count": 80,
      "status": true
    }

11. VERIFY/UNVERIFY USER (Admin only)
    PUT /api/users/verify
    Headers: Authorization: Bearer <admin_token>
    Body: {
      "uid": 1,
      "is_verified": 1  // 1 for verified, 0 for unverified
    }

12. SEARCH USERS (Admin only)
    GET /api/users/search?search=john
    OR
    POST /api/users/search
    Headers: Authorization: Bearer <admin_token>
    Body: {
      "search": "john"
    }

13. CHANGE PASSWORD
    PUT /api/users/change-password
    Headers: Authorization: Bearer <token>
    Body: {
      "uid": 1,  // optional, defaults to current user
      "current_password": "oldpassword123",
      "new_password": "newpassword123"
    }

14. BULK OPERATIONS (Admin only)
    POST /api/users/bulk
    Headers: Authorization: Bearer <admin_token>
    Body: {
      "operation": "verify",  // "verify", "unverify", or "delete"
      "user_ids": [1, 2, 3, 4]
    }

15. LOGOUT
    POST /api/logout
    Headers: Authorization: Bearer <token>
    Note: Since JWT is stateless, this just confirms the token is valid.
    Client should remove the token from storage.

=== ERROR RESPONSES ===
All endpoints return appropriate HTTP status codes:
- 200: Success
- 201: Created (registration)
- 400: Bad Request (validation errors)
- 401: Unauthorized (invalid/missing token)
- 403: Forbidden (insufficient permissions)
- 404: Not Found
- 405: Method Not Allowed
- 409: Conflict (duplicate email)
- 500: Internal Server Error

Error format:
{
  "message": "Error description",
  "status": false
}

=== ROLES ===
- student: Default role, basic access
- recruiter: Recruiter access
- institute: Institute access  
- admin: Full administrative access

=== SECURITY FEATURES ===
- Password hashing using PHP's password_hash()
- Prepared statements to prevent SQL injection
- JWT token expiration (1 hour default)
- Role-based access control
- Input validation and sanitization
- Proper HTTP status codes
- CORS headers for cross-origin requests

=== CLIENT USAGE EXAMPLES ===

JavaScript (Fetch API):
```javascript
// Login
const login = async (email, password) => {
  const response = await fetch('/api/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  });
  const data = await response.json();
  if (data.status) {
    localStorage.setItem('token', data.token);
  }
  return data;
};

// Make authenticated request
const getProfile = async () => {
  const token = localStorage.getItem('token');
  const response = await fetch('/api/profile', {
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });
  return response.json();
};

// Register user
const register = async (userData) => {
  const response = await fetch('/api/register', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(userData),
  });
  return response.json();
};
```

cURL Examples:
```bash
# Login
curl -X POST http://your-domain.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123"}'

# Get profile (authenticated)
curl -X GET http://your-domain.com/api/profile \
  -H "Authorization: Bearer your_jwt_token_here"

# Get all users (admin)
curl -X GET http://your-domain.com/api/users \
  -H "Authorization: Bearer admin_jwt_token_here"
```

*/
        exit;
    }
    mysqli_stmt_close($check_stmt);
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new user
$sql = "INSERT INTO users (name, email, password, phone_number, role, is_verified) 
        VALUES (?, ?, ?, ?, ?, 1)";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $hashed_password, $phone_number, $role);
    
    if (mysqli_stmt_execute($stmt)) {
        $user_id = mysqli_insert_id($conn);
        
        // Generate JWT token for the new user
        $payload = [
            'user_id' => $user_id,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRY
        ];
        
        $jwt_token = JWTHelper::generateJWT($payload, JWT_SECRET);
        
        http_response_code(201);
        echo json_encode(array(
            "message" => "User registered successfully", 
            "status" => true,
            "user" => [
                "id" => $user_id,
                "name" => $name,
                "email" => $email,
                "role" => $role
            ],
            "token" => $jwt_token,
            "expires_in" => JWT_EXPIRY
        ));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Registration failed", "status" => false));
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare failed", "status" => false));
}

mysqli_close($conn);
?>
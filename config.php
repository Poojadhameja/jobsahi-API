<?php
// config.php - Database Configuration and JWT Settings
$conn = mysqli_connect("127.0.0.1:3307", "root", "", "jobsahi_database");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// JWT Configuration - Check if constants are already defined
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'jobsahi'); // Use a strong, unique key in production
}
if (!defined('JWT_ALGORITHM')) {
    define('JWT_ALGORITHM', 'HS256');
}
if (!defined('JWT_EXPIRY')) {
    define('JWT_EXPIRY', 3600); // 1 hour in seconds
}
?>
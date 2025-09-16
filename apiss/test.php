<?php
// test.php - Test file to verify API setup
header('Content-Type: application/json');

echo "Testing JOBSAHI API Setup...\n\n";

// Test 1: Include db.php
echo "1. Testing db.php inclusion...\n";
try {
    require_once 'db.php';
    echo "✅ db.php loaded successfully\n";
} catch (Exception $e) {
    echo "❌ db.php failed: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Check if constants are defined
echo "2. Testing constants...\n";
$constants_to_check = [
    'JWT_SECRET',
    'JWT_EXPIRY', 
    'HTTP_OK',
    'SUCCESS_MESSAGE',
    'MAX_FILE_SIZE'
];

foreach ($constants_to_check as $constant) {
    if (defined($constant)) {
        echo "✅ $constant = " . constant($constant) . "\n";
    } else {
        echo "❌ $constant not defined\n";
    }
}

// Test 3: Check database connections
echo "3. Testing database connections...\n";
if (isset($pdo) && $pdo instanceof PDO) {
    echo "✅ PDO connection successful\n";
} else {
    echo "❌ PDO connection failed\n";
}

if (isset($conn) && $conn) {
    echo "✅ MySQLi connection successful\n";
} else {
    echo "❌ MySQLi connection failed\n";
}

// Test 4: Test JWT helper
echo "4. Testing JWT helper...\n";
try {
    require_once 'jwt_token/jwt_helper.php';
    echo "✅ JWT helper loaded\n";
    
    // Test JWT generation
    $test_payload = ['user_id' => 1, 'email' => 'test@test.com'];
    $test_token = JWTHelper::generateJWT($test_payload);
    echo "✅ JWT token generated: " . substr($test_token, 0, 20) . "...\n";
    
    // Test JWT validation
    $decoded = JWTHelper::validateJWT($test_token);
    if ($decoded) {
        echo "✅ JWT token validated successfully\n";
    } else {
        echo "❌ JWT token validation failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ JWT helper failed: " . $e->getMessage() . "\n";
}

// Test 5: Test response helper
echo "5. Testing response helper...\n";
try {
    require_once 'helpers/response_helper.php';
    echo "✅ Response helper loaded\n";
} catch (Exception $e) {
    echo "❌ Response helper failed: " . $e->getMessage() . "\n";
}

echo "\n🎉 API Setup Test Complete!\n";
echo "If all tests passed, your API should be ready to use.\n";
?>

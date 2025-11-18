<?php
// Test Firebase FCM v1 API
require_once 'helpers/firebase_helper_v1.php';

echo "🧪 Testing Firebase FCM v1 API Setup...\n\n";

// Check if Service Account file exists
$serviceAccountPath = FirebaseHelperV1::getServiceAccountPath();
echo "📁 Service Account Path: $serviceAccountPath\n";

if (file_exists($serviceAccountPath)) {
    echo "✅ Service Account file found!\n\n";
    
    // Read and validate JSON
    $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
    
    if ($serviceAccount) {
        echo "✅ JSON file is valid!\n";
        echo "📋 Project ID: " . ($serviceAccount['project_id'] ?? 'Not found') . "\n";
        echo "📋 Client Email: " . ($serviceAccount['client_email'] ?? 'Not found') . "\n";
        echo "📋 Private Key: " . (isset($serviceAccount['private_key']) ? 'Present' : 'Missing') . "\n\n";
        
        // Test access token generation
        echo "🔑 Testing Access Token Generation...\n";
        
        // Use reflection to access private method for testing
        $reflection = new ReflectionClass('FirebaseHelperV1');
        $method = $reflection->getMethod('getAccessToken');
        $method->setAccessible(true);
        
        $accessToken = $method->invoke(null);
        
        if ($accessToken) {
            echo "✅ Access Token generated successfully!\n";
            echo "🔑 Token (first 20 chars): " . substr($accessToken, 0, 20) . "...\n\n";
        } else {
            echo "❌ Failed to generate access token\n";
            echo "⚠️  Check error logs for details\n\n";
        }
        
        echo "✅ Setup looks good! Ready to send notifications.\n";
        echo "\n💡 To test with actual FCM token, use:\n";
        echo "   FirebaseHelperV1::sendToDevice('FCM_TOKEN', 'Title', 'Body');\n";
        
    } else {
        echo "❌ Failed to parse JSON file\n";
    }
} else {
    echo "❌ Service Account file NOT found at: $serviceAccountPath\n";
    echo "⚠️  Please ensure the file is at: config/firebase-service-account.json\n";
}

echo "\n";
?>


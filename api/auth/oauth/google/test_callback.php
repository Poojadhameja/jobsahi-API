<?php
// Test file to debug Google OAuth
require_once '../../../config/oauth_config.php';

echo "<h2>Google OAuth Configuration Test</h2>";
echo "<pre>";
echo "Client ID: " . GOOGLE_CLIENT_ID . "\n";
echo "Client Secret: " . substr(GOOGLE_CLIENT_SECRET, 0, 10) . "...\n";
echo "Redirect URI: " . GOOGLE_REDIRECT_URI . "\n";
echo "Token URL: " . GOOGLE_TOKEN_URL . "\n";
echo "\n";

if (isset($_GET['code'])) {
    $code = urldecode($_GET['code']);
    echo "Received Code: " . substr($code, 0, 50) . "...\n\n";
    
    // Test token exchange
    $tokenData = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];
    
    echo "Token Request Data:\n";
    print_r($tokenData);
    echo "\n";
    
    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: " . $httpCode . "\n";
    if ($curlError) {
        echo "CURL Error: " . $curlError . "\n";
    }
    echo "Response: " . $response . "\n";
    
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "\nParsed Response:\n";
        print_r($responseData);
    }
} else {
    echo "No code received. Use this URL to test:\n";
    echo "<a href='" . GOOGLE_AUTH_URL . "?client_id=" . GOOGLE_CLIENT_ID . "&redirect_uri=" . urlencode(GOOGLE_REDIRECT_URI) . "&response_type=code&scope=openid%20email%20profile&access_type=offline&prompt=consent'>Test Google OAuth</a>";
}
echo "</pre>";
?>


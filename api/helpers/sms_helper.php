<?php
// sms_helper.php - SMS sending functions for OTP

// Check if function already exists to prevent redeclaration
if (!function_exists('sendOTPviaSMS')) {
    /**
     * Send OTP via SMS
     * Supports multiple SMS providers: Fast2SMS, MSG91, TextLocal
     * 
     * @param string $phone_number Phone number (10 digits, without country code)
     * @param string $otp OTP code to send
     * @param string $provider SMS provider ('fast2sms', 'msg91', 'textlocal', 'default')
     * @return bool Success status
     */
    function sendOTPviaSMS($phone_number, $otp, $provider = 'fast2sms') {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Add country code if not present (India: +91)
        if (strlen($phone) == 10) {
            $phone = '91' . $phone; // Add India country code
        }
        
        switch (strtolower($provider)) {
            case 'fast2sms':
                return sendOTPviaFast2SMS($phone, $otp);
            case 'msg91':
                return sendOTPviaMSG91($phone, $otp);
            case 'textlocal':
                return sendOTPviaTextLocal($phone, $otp);
            default:
                // For development/testing - just log it
                error_log("SMS OTP (Development Mode): Phone: $phone, OTP: $otp");
                return true; // Return true for development
        }
    }
}

if (!function_exists('sendOTPviaFast2SMS')) {
    /**
     * Send OTP via Fast2SMS API
     * Get API key from: https://www.fast2sms.com/
     */
    function sendOTPviaFast2SMS($phone, $otp) {
        // TODO: Add your Fast2SMS API key here
        $api_key = ''; // Get from https://www.fast2sms.com/dev/api
        
        if (empty($api_key)) {
            // Development mode - just log
            error_log("Fast2SMS OTP (Dev Mode): Phone: $phone, OTP: $otp");
            return true;
        }
        
        $message = "Your JobSahi login OTP is $otp. Valid for 5 minutes. Do not share this OTP with anyone.";
        
        $url = "https://www.fast2sms.com/dev/bulkV2";
        $data = [
            'authorization' => $api_key,
            'message' => $message,
            'language' => 'english',
            'route' => 'q',
            'numbers' => $phone
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $api_key
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            return isset($result['return']) && $result['return'] === true;
        }
        
        error_log("Fast2SMS Error: $response");
        return false;
    }
}

if (!function_exists('sendOTPviaMSG91')) {
    /**
     * Send OTP via MSG91 API
     * Get API key from: https://msg91.com/
     */
    function sendOTPviaMSG91($phone, $otp) {
        // TODO: Add your MSG91 Auth Key here
        $auth_key = ''; // Get from https://msg91.com/
        $sender_id = 'JOBSAH'; // Your 6 character sender ID
        $template_id = ''; // Your MSG91 template ID
        
        if (empty($auth_key)) {
            // Development mode - just log
            error_log("MSG91 OTP (Dev Mode): Phone: $phone, OTP: $otp");
            return true;
        }
        
        $url = "https://control.msg91.com/api/v5/flow/";
        $data = [
            'template_id' => $template_id,
            'short_url' => '0',
            'recipients' => [
                [
                    'mobiles' => $phone,
                    'var' => $otp
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authkey: ' . $auth_key
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            return isset($result['type']) && $result['type'] === 'success';
        }
        
        error_log("MSG91 Error: $response");
        return false;
    }
}

if (!function_exists('sendOTPviaTextLocal')) {
    /**
     * Send OTP via TextLocal API
     * Get API key from: https://www.textlocal.in/
     */
    function sendOTPviaTextLocal($phone, $otp) {
        // TODO: Add your TextLocal API key here
        $api_key = ''; // Get from https://www.textlocal.in/
        $sender = 'TXTLCL'; // Your TextLocal sender name
        
        if (empty($api_key)) {
            // Development mode - just log
            error_log("TextLocal OTP (Dev Mode): Phone: $phone, OTP: $otp");
            return true;
        }
        
        $message = "Your JobSahi login OTP is $otp. Valid for 5 minutes.";
        
        $url = "https://api.textlocal.in/send/";
        $data = [
            'apikey' => $api_key,
            'numbers' => $phone,
            'message' => $message,
            'sender' => $sender
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            return isset($result['status']) && $result['status'] === 'success';
        }
        
        error_log("TextLocal Error: $response");
        return false;
    }
}
?>


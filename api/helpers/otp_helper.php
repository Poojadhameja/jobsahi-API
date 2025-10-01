<?php
// otp_helper.php - OTP related functions

if (!function_exists('generateOTP')) {
    function generateOTP($length = 4) {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('generateSecureOTP')) {
    function generateSecureOTP($length = 4) {
        $characters = '0123456789';
        $otp = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $otp .= $characters[random_int(0, $max)];
        }
        
        return $otp;
    }
}

if (!function_exists('generateAlphanumericOTP')) {
    function generateAlphanumericOTP($length = 6) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $otp = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $otp .= $characters[random_int(0, $max)];
        }
        
        return $otp;
    }
}

if (!function_exists('verifyOTP')) {
    function verifyOTP($conn, $email, $otp, $type) {
        $sql = "SELECT id, expires_at FROM otp_requests 
                WHERE email = ? AND otp = ? AND type = ? AND used = 0";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sss", $email, $otp, $type);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                $otp_record = mysqli_fetch_assoc($result);
                
                // Check if OTP has expired
                if (strtotime($otp_record['expires_at']) > time()) {
                    // Mark OTP as used
                    $update_sql = "UPDATE otp_requests SET used = 1, used_at = NOW() WHERE id = ?";
                    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                        mysqli_stmt_bind_param($update_stmt, "i", $otp_record['id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                    
                    mysqli_stmt_close($stmt);
                    return ['success' => true, 'message' => 'OTP verified successfully'];
                } else {
                    mysqli_stmt_close($stmt);
                    return ['success' => false, 'message' => 'OTP has expired'];
                }
            } else {
                mysqli_stmt_close($stmt);
                return ['success' => false, 'message' => 'Invalid OTP'];
            }
        } else {
            return ['success' => false, 'message' => 'Database error'];
        }
    }
}

if (!function_exists('cleanExpiredOTPs')) {
    function cleanExpiredOTPs($conn) {
        $sql = "DELETE FROM otp_requests WHERE expires_at < NOW()";
        return mysqli_query($conn, $sql);
    }
}

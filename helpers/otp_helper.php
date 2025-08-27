<?php
// otp_helper.php - OTP related functions

/**
 * Generate a random OTP
 * @param int $length - Length of OTP (default 6)
 * @return string - Generated OTP
 */
if (!function_exists('generateOTP')) {
    function generateOTP($length = 6) {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}

/**
 * Generate a more secure OTP using random_bytes
 * @param int $length - Length of OTP (default 6)
 * @return string - Generated OTP
 */
function generateSecureOTP($length = 6) {
    $characters = '0123456789';
    $otp = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $otp .= $characters[random_int(0, $max)];
    }
    
    return $otp;
}

/**
 * Generate alphanumeric OTP
 * @param int $length - Length of OTP (default 6)
 * @return string - Generated alphanumeric OTP
 */
function generateAlphanumericOTP($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $otp = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $otp .= $characters[random_int(0, $max)];
    }
    
    return $otp;
}

/**
 * Verify OTP from database
 * @param mysqli $conn - Database connection
 * @param string $email - User email
 * @param string $otp - OTP to verify
 * @param string $type - OTP type (e.g., 'password_reset')
 * @return array - Result array with success status and message
 */
function verifyOTP($conn, $email, $otp, $type) {
    $sql = "SELECT id, expires_at FROM otp_requests WHERE email = ? AND otp = ? AND type = ? AND used = 0";
    
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
                return array('success' => true, 'message' => 'OTP verified successfully');
            } else {
                mysqli_stmt_close($stmt);
                return array('success' => false, 'message' => 'OTP has expired');
            }
        } else {
            mysqli_stmt_close($stmt);
            return array('success' => false, 'message' => 'Invalid OTP');
        }
    } else {
        return array('success' => false, 'message' => 'Database error');
    }
}

/**
 * Clean up expired OTPs
 * @param mysqli $conn - Database connection
 * @return bool - Success status
 */
function cleanExpiredOTPs($conn) {
    $sql = "DELETE FROM otp_requests WHERE expires_at < NOW()";
    return mysqli_query($conn, $sql);
}
?>
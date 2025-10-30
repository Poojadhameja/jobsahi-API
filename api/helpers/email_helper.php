<?php
// email_helper.php - Fixed Email sending functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if function already exists to prevent redeclaration
if (!function_exists('sendPasswordResetOTP')) {
    function sendPasswordResetOTP($email, $name, $otp) {
        // Use PHPMailer instead of basic mail() function
        return sendPasswordResetOTPWithPHPMailer($email, $name, $otp);
    }
}

if (!function_exists('sendPasswordResetOTPWithLocalSMTP')) {
    function sendPasswordResetOTPWithLocalSMTP($email, $name, $otp) {
    // Configure SMTP settings programmatically
    ini_set("SMTP", "smtp.gmail.com");
    ini_set("smtp_port", "587");
    ini_set("sendmail_from", "your-email@gmail.com");
    
    $subject = "Password Reset OTP";
    $message = "
    <html>
    <head>
        <title>Password Reset OTP</title>
    </head>
    <body>
        <h2>Password Reset Request</h2>
        <p>Hello $name,</p>
        <p>You have requested to reset your password. Please use the following OTP to proceed:</p>
        <h3 style='color: #007cba; font-size: 24px; letter-spacing: 2px;'>$otp</h3>
        <p>This OTP will expire in 5 minutes.</p>
        <p>If you didn't request this, please ignore this email.</p>
        <p>Best regards,<br>Your App Team</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@yourapp.com" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
    }
}

if (!function_exists('sendPasswordResetOTPWithPHPMailer')) {
    function sendPasswordResetOTPWithPHPMailer($toEmail, $toName, $otp) {
    $mail = new PHPMailer(true);
    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'simranjn54@gmail.com'; // Replace with your Gmail
        $mail->Password = 'ixpk mawa dpzo iwhw'; // Replace with your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Additional SMTP options for XAMPP compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Sender and recipient
        $mail->setFrom('your-gmail@gmail.com', 'JobSahi Support');
        $mail->addAddress($toEmail, $toName);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Password Reset OTP";
        $mail->Body = "
        <html>
        <head>
            <title>Password Reset OTP</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007cba; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .otp { background-color: #007cba; color: white; padding: 15px; text-align: center; font-size: 24px; letter-spacing: 2px; margin: 20px 0; border-radius: 5px; font-weight: bold; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
                .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Hello <strong>$toName</strong>,</p>
                    <p>You have requested to reset your password for your JobSahi account. Please use the following OTP to proceed:</p>
                    <div class='otp'>$otp</div>
                    <div class='warning'>
                        <strong>⚠️ Important:</strong> This OTP will expire in 5 minutes for security reasons.
                    </div>
                    <p>If you didn't request this password reset, please ignore this email and your password will remain unchanged.</p>
                </div>
                <div class='footer'>
                    <p>Best regards,<br><strong>JobSahi Support Team</strong></p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $mail->AltBody = "Hello $toName,\n\nYou have requested to reset your password. Your OTP is: $otp\n\nThis code will expire in 5 minutes.\n\nIf you didn't request this, please ignore this email.\n\nBest regards,\nJobSahi Support Team";
        
        $result = $mail->send();
        
        if ($result) {
            error_log("Password reset OTP sent successfully to: $toEmail");
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        error_log("Exception: " . $e->getMessage());
        return false;
    }
    }
}

if (!function_exists('sendPasswordResetOTPWithDebug')) {
    function sendPasswordResetOTPWithDebug($toEmail, $toName, $otp, $debug = false) {
    $mail = new PHPMailer(true);
    
    try {
        if ($debug) {
            $mail->SMTPDebug = 2; // Enable verbose debug output
            $mail->Debugoutput = 'html';
        }
        
        // SMTP configuration with timeout settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'simranjn54@gmail.com';
        $mail->Password = 'ixpk mawa dpzo iwhw';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 60; // Increase timeout
        
        // Additional options for troubleshooting
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set charset
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        $mail->setFrom('your-gmail@gmail.com', 'JobSahi Support');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = "Password Reset OTP - JobSahi";
        
        $mail->Body = "
        <html>
        <head>
            <title>Password Reset OTP</title>
            <meta charset='UTF-8'>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background-color: #007cba; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                    <h1>Password Reset Request</h1>
                </div>
                <div style='padding: 20px; background-color: #f9f9f9;'>
                    <p>Hello <strong>$toName</strong>,</p>
                    <p>You have requested to reset your password for your JobSahi account.</p>
                    <div style='background-color: #007cba; color: white; padding: 15px; text-align: center; font-size: 24px; letter-spacing: 2px; margin: 20px 0; border-radius: 5px; font-weight: bold;'>$otp</div>
                    <p style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 15px 0;'><strong>⚠️ Important:</strong> This OTP will expire in 5 minutes.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                </div>
                <div style='padding: 20px; text-align: center; color: #666; font-size: 12px;'>
                    <p>Best regards,<br><strong>JobSahi Support Team</strong></p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Debug Mailer Error: " . $e->getMessage());
        if ($debug) {
            echo "Debug Info: " . $e->getMessage();
        }
        return false;
    }
    }
}

// Utility function to test email configuration
if (!function_exists('testEmailConfiguration')) {
    function testEmailConfiguration() {
    $testResult = [
        'php_mail_function' => function_exists('mail'),
        'phpmailer_available' => class_exists('PHPMailer\\PHPMailer\\PHPMailer'),
        'smtp_settings' => [
            'SMTP' => ini_get('SMTP'),
            'smtp_port' => ini_get('smtp_port'),
            'sendmail_from' => ini_get('sendmail_from')
        ]
    ];
    
    return $testResult;
    }
}
?>
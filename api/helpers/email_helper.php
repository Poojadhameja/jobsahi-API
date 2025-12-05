<?php
// email_helper.php - Fixed Email sending functions

// Company Email Configuration (only define if not already defined)
if (!defined('COMPANY_EMAIL')) {
    define('COMPANY_EMAIL', 'himanshushrirang6@gmail.com');
}
if (!defined('COMPANY_NAME')) {
    define('COMPANY_NAME', 'JobSahi');
}
if (!defined('COMPANY_EMAIL_PASSWORD')) {
    // IMPORTANT: Gmail App Password is required for sending emails via SMTP
    // Steps to get Gmail App Password:
    // 1. Go to https://myaccount.google.com/security
    // 2. Enable 2-Step Verification if not already enabled
    // 3. Go to App Passwords: https://myaccount.google.com/apppasswords
    // 4. Select "Mail" and "Other (Custom name)" - enter "JobSahi API"
    // 5. Copy the 16-character password and paste it below
    define('COMPANY_EMAIL_PASSWORD', 'vrkf pwpt wnvm pbkp'); // Gmail App Password for JobSahi forgot OTP
}

// Try to load PHPMailer if available
$phpmailer_available = false;
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $phpmailer_available = class_exists('PHPMailer\PHPMailer\PHPMailer');
}

// Check if function already exists to prevent redeclaration
if (!function_exists('sendPasswordResetOTP')) {
    function sendPasswordResetOTP($email, $name, $otp) {
        global $phpmailer_available;
        
        error_log("Attempting to send OTP email to: $email");
        error_log("PHPMailer available: " . ($phpmailer_available ? 'Yes' : 'No'));
        error_log("Company Email: " . COMPANY_EMAIL);
        error_log("Company Email Password set: " . (!empty(COMPANY_EMAIL_PASSWORD) ? 'Yes' : 'No'));
        
        // Use PHPMailer if available, otherwise fallback to basic mail()
        if ($phpmailer_available) {
            $result = sendPasswordResetOTPWithPHPMailer($email, $name, $otp);
            if (!$result) {
                error_log("PHPMailer failed, trying fallback method");
                return sendPasswordResetOTPWithLocalSMTP($email, $name, $otp);
            }
            return $result;
        } else {
            // Fallback to basic mail() function
            error_log("Using basic mail() function");
            return sendPasswordResetOTPWithLocalSMTP($email, $name, $otp);
        }
    }
}

if (!function_exists('sendPasswordResetOTPWithLocalSMTP')) {
    function sendPasswordResetOTPWithLocalSMTP($email, $name, $otp) {
    error_log("Using basic mail() function to send OTP to: $email");
    
    // Configure SMTP settings programmatically
    ini_set("SMTP", "smtp.gmail.com");
    ini_set("smtp_port", "587");
    ini_set("sendmail_from", COMPANY_EMAIL);
    
    $subject = "Password Reset OTP - JobSahi";
    $message = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset OTP - JobSahi</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #f5f5f5; }
            .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
            .email-body { padding: 45px 35px; background-color: #ffffff; }
            .greeting { font-size: 20px; color: #333333; margin-bottom: 25px; font-weight: 500; }
            .message-text { font-size: 16px; color: #666666; margin-bottom: 35px; line-height: 1.8; }
            .otp-container { background: linear-gradient(135deg, #007cba 0%, #005a8a 100%); border-radius: 12px; padding: 30px; margin: 35px 0; text-align: center; box-shadow: 0 4px 16px rgba(0, 124, 186, 0.25); }
            .otp-code { font-size: 42px; font-weight: 700; color: #ffffff; letter-spacing: 10px; font-family: 'Courier New', monospace; margin: 15px 0; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
            .otp-label { font-size: 13px; color: rgba(255, 255, 255, 0.9); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 15px; font-weight: 600; }
            .warning-box { background-color: #fff8e1; border-left: 4px solid #ffc107; padding: 18px 22px; margin: 30px 0; border-radius: 6px; }
            .warning-box strong { color: #f57c00; font-size: 15px; display: block; margin-bottom: 8px; }
            .warning-box p { color: #666666; font-size: 14px; margin: 0; line-height: 1.6; }
            .footer-text { font-size: 15px; color: #999999; margin-top: 35px; line-height: 1.7; }
            .email-footer { background-color: #f9f9f9; padding: 30px 35px; text-align: center; border-top: 1px solid #eeeeee; }
            .email-footer p { font-size: 13px; color: #999999; margin: 6px 0; }
            .brand-name { font-weight: 700; color: #007cba; }
            .divider { height: 1px; background-color: #eeeeee; margin: 25px 0; }
            @media only screen and (max-width: 600px) {
                .email-body { padding: 30px 20px; }
                .otp-code { font-size: 32px; letter-spacing: 6px; }
                .otp-container { padding: 25px 20px; }
            }
        </style>
    </head>
    <body>
        <div class='email-wrapper'>
            <div class='email-body'>
                <div class='greeting'>Hello <strong>$name</strong>,</div>
                <div class='message-text'>
                    Your password reset OTP for your <span class='brand-name'>JobSahi</span> account is below. Use this code to complete your verification.
                </div>
                <div class='otp-container'>
                    <div class='otp-label'>Your Password Reset OTP</div>
                    <div class='otp-code'>$otp</div>
                </div>
                <div class='warning-box'>
                    <strong>⏰ Important Security Notice</strong>
                    <p>This OTP code will expire in <strong>5 minutes</strong> for security reasons. Please use it promptly.</p>
                </div>
                <div class='footer-text'>
                    If you didn't request this code, please ignore this email. Your account is secure.
                </div>
            </div>
            <div class='email-footer'>
                <p><strong style='color: #007cba;'>JobSahi Support Team</strong></p>
                <p>This is an automated message. Please do not reply to this email.</p>
                <div class='divider'></div>
                <p style='font-size: 12px; color: #bbbbbb;'>© " . date('Y') . " JobSahi. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . COMPANY_NAME . " <" . COMPANY_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . COMPANY_EMAIL . "\r\n";
    
    $result = @mail($email, $subject, $message, $headers);
    
    if ($result) {
        error_log("✅ Email sent successfully using mail() function to: $email");
    } else {
        error_log("❌ mail() function failed for: $email");
        error_log("   Note: mail() function may not work with Gmail SMTP in XAMPP. Please install PHPMailer and set COMPANY_EMAIL_PASSWORD");
    }
    
    return $result;
    }
}

if (!function_exists('sendPasswordResetOTPWithPHPMailer')) {
    function sendPasswordResetOTPWithPHPMailer($toEmail, $toName, $otp) {
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer not available, falling back to basic mail()");
        return sendPasswordResetOTPWithLocalSMTP($toEmail, $toName, $otp);
    }
    
    // Check if password is set
    if (empty(COMPANY_EMAIL_PASSWORD)) {
        error_log("WARNING: COMPANY_EMAIL_PASSWORD is not set. Gmail SMTP requires App Password. Please set it in email_helper.php");
        error_log("To get Gmail App Password: Go to Google Account > Security > 2-Step Verification > App Passwords");
    }
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // Enable verbose debug output (only for error logging)
        $mail->SMTPDebug = 0; // Set to 2 for detailed debugging
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug ($level): $str");
        };
        
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = COMPANY_EMAIL; // Company email
        $mail->Password = COMPANY_EMAIL_PASSWORD ?: ''; // Gmail App Password (add if using PHPMailer)
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 30; // 30 seconds timeout
        
        // Additional SMTP options for XAMPP compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Sender and recipient
        $mail->setFrom(COMPANY_EMAIL, COMPANY_NAME);
        $mail->addReplyTo(COMPANY_EMAIL, COMPANY_NAME);
        $mail->addAddress($toEmail, $toName);
        
        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Password Reset OTP - JobSahi";
        $mail->Body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset OTP - JobSahi</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #f5f5f5; }
                .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
                .email-body { padding: 45px 35px; background-color: #ffffff; }
                .greeting { font-size: 20px; color: #333333; margin-bottom: 25px; font-weight: 500; }
                .message-text { font-size: 16px; color: #666666; margin-bottom: 35px; line-height: 1.8; }
                .otp-container { background: linear-gradient(135deg, #007cba 0%, #005a8a 100%); border-radius: 12px; padding: 30px; margin: 35px 0; text-align: center; box-shadow: 0 4px 16px rgba(0, 124, 186, 0.25); }
                .otp-label { font-size: 13px; color: rgba(255, 255, 255, 0.9); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 15px; font-weight: 600; }
                .otp-code { font-size: 42px; font-weight: 700; color: #ffffff; letter-spacing: 10px; font-family: 'Courier New', monospace; margin: 15px 0; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
                .warning-box { background-color: #fff8e1; border-left: 4px solid #ffc107; padding: 18px 22px; margin: 30px 0; border-radius: 6px; }
                .warning-box strong { color: #f57c00; font-size: 15px; display: block; margin-bottom: 8px; }
                .warning-box p { color: #666666; font-size: 14px; margin: 0; line-height: 1.6; }
                .footer-text { font-size: 15px; color: #999999; margin-top: 35px; line-height: 1.7; }
                .email-footer { background-color: #f9f9f9; padding: 30px 35px; text-align: center; border-top: 1px solid #eeeeee; }
                .email-footer p { font-size: 13px; color: #999999; margin: 6px 0; }
                .brand-name { font-weight: 700; color: #007cba; }
                .divider { height: 1px; background-color: #eeeeee; margin: 25px 0; }
                @media only screen and (max-width: 600px) {
                    .email-body { padding: 30px 20px; }
                    .otp-code { font-size: 32px; letter-spacing: 6px; }
                    .otp-container { padding: 25px 20px; }
                }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <div class='email-body'>
                    <div class='greeting'>Hello <strong>$toName</strong>,</div>
                    <div class='message-text'>
                        Your password reset OTP for your <span class='brand-name'>JobSahi</span> account is below. Use this code to complete your verification.
                    </div>
                    <div class='otp-container'>
                        <div class='otp-label'>Your Password Reset OTP</div>
                        <div class='otp-code'>$otp</div>
                    </div>
                    <div class='warning-box'>
                        <strong>⏰ Important Security Notice</strong>
                        <p>This OTP code will expire in <strong>5 minutes</strong> for security reasons. Please use it promptly.</p>
                    </div>
                    <div class='footer-text'>
                        If you didn't request this code, please ignore this email. Your account is secure.
                    </div>
                </div>
                <div class='email-footer'>
                    <p><strong style='color: #007cba;'>JobSahi Support Team</strong></p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <div class='divider'></div>
                    <p style='font-size: 12px; color: #bbbbbb;'>© " . date('Y') . " JobSahi. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $mail->AltBody = "Password Reset OTP - JobSahi\n\nHello $toName,\n\nYour password reset OTP for your JobSahi account is below.\n\nYour Password Reset OTP: $otp\n\nThis OTP code will expire in 5 minutes for security reasons.\n\nIf you didn't request this code, please ignore this email. Your account is secure.\n\nBest regards,\nJobSahi Support Team\n\nThis is an automated message. Please do not reply to this email.";
        
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ Verification OTP sent successfully to: $toEmail via PHPMailer");
            return true;
        } else {
            error_log("❌ PHPMailer send() returned false for: $toEmail");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ PHPMailer Exception for $toEmail:");
        error_log("   Error Info: " . $mail->ErrorInfo);
        error_log("   Exception Message: " . $e->getMessage());
        error_log("   Exception Trace: " . $e->getTraceAsString());
        return false;
    }
    }
}

if (!function_exists('sendPasswordResetOTPWithDebug')) {
    function sendPasswordResetOTPWithDebug($toEmail, $toName, $otp, $debug = false) {
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer not available, falling back to basic mail()");
        return sendPasswordResetOTPWithLocalSMTP($toEmail, $toName, $otp);
    }
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        if ($debug) {
            $mail->SMTPDebug = 2; // Enable verbose debug output
            $mail->Debugoutput = 'html';
        }
        
        // SMTP configuration with timeout settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = COMPANY_EMAIL; // Company email
        $mail->Password = COMPANY_EMAIL_PASSWORD ?: ''; // Gmail App Password (add if using PHPMailer)
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
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
        
        $mail->setFrom(COMPANY_EMAIL, COMPANY_NAME . ' Support');
        $mail->addReplyTo(COMPANY_EMAIL, COMPANY_NAME . ' Support');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = "Password Reset OTP - JobSahi";
        
        $mail->Body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset OTP - JobSahi</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #f5f5f5; }
                .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
                .email-body { padding: 45px 35px; background-color: #ffffff; }
                .greeting { font-size: 20px; color: #333333; margin-bottom: 25px; font-weight: 500; }
                .message-text { font-size: 16px; color: #666666; margin-bottom: 35px; line-height: 1.8; }
                .otp-container { background: linear-gradient(135deg, #007cba 0%, #005a8a 100%); border-radius: 12px; padding: 30px; margin: 35px 0; text-align: center; box-shadow: 0 4px 16px rgba(0, 124, 186, 0.25); }
                .otp-label { font-size: 13px; color: rgba(255, 255, 255, 0.9); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 15px; font-weight: 600; }
                .otp-code { font-size: 42px; font-weight: 700; color: #ffffff; letter-spacing: 10px; font-family: 'Courier New', monospace; margin: 15px 0; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
                .warning-box { background-color: #fff8e1; border-left: 4px solid #ffc107; padding: 18px 22px; margin: 30px 0; border-radius: 6px; }
                .warning-box strong { color: #f57c00; font-size: 15px; display: block; margin-bottom: 8px; }
                .warning-box p { color: #666666; font-size: 14px; margin: 0; line-height: 1.6; }
                .footer-text { font-size: 15px; color: #999999; margin-top: 35px; line-height: 1.7; }
                .email-footer { background-color: #f9f9f9; padding: 30px 35px; text-align: center; border-top: 1px solid #eeeeee; }
                .email-footer p { font-size: 13px; color: #999999; margin: 6px 0; }
                .brand-name { font-weight: 700; color: #007cba; }
                .divider { height: 1px; background-color: #eeeeee; margin: 25px 0; }
                @media only screen and (max-width: 600px) {
                    .email-body { padding: 30px 20px; }
                    .otp-code { font-size: 32px; letter-spacing: 6px; }
                    .otp-container { padding: 25px 20px; }
                }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <div class='email-body'>
                    <div class='greeting'>Hello <strong>$toName</strong>,</div>
                    <div class='message-text'>
                        Your password reset OTP for your <span class='brand-name'>JobSahi</span> account is below. Use this code to complete your verification.
                    </div>
                    <div class='otp-container'>
                        <div class='otp-label'>Your Password Reset OTP</div>
                        <div class='otp-code'>$otp</div>
                    </div>
                    <div class='warning-box'>
                        <strong>⏰ Important Security Notice</strong>
                        <p>This OTP code will expire in <strong>5 minutes</strong> for security reasons. Please use it promptly.</p>
                    </div>
                    <div class='footer-text'>
                        If you didn't request this code, please ignore this email. Your account is secure.
                    </div>
                </div>
                <div class='email-footer'>
                    <p><strong style='color: #007cba;'>JobSahi Support Team</strong></p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <div class='divider'></div>
                    <p style='font-size: 12px; color: #bbbbbb;'>© " . date('Y') . " JobSahi. All rights reserved.</p>
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
        'phpmailer_available' => class_exists('PHPMailer\PHPMailer\PHPMailer'),
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
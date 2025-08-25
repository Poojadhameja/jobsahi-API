<?php
// email_helper.php - Email sending functions

function sendPasswordResetOTP($email, $name, $otp) {
    // Method 1: Using PHP's mail() function (basic)
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
function sendPasswordResetOTPWithSMTP($email, $name, $otp) {
    // Method 3: Custom SMTP function
    $to = $email;
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
    $headers .= "From: Your App <noreply@yourapp.com>" . "\r\n";
    $headers .= "Reply-To: noreply@yourapp.com" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $message, $headers);
}
?>
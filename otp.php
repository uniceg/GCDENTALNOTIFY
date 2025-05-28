<?php
require_once 'session_helper.php';

// Check if session is valid
if (!validateSession()) {
    echo "<script>alert('Unauthorized access.');window.location.href='login.php';</script>";
    exit();
}

include 'config.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Manila');

// Get email from session user data
$userData = getUserData();
if (!$userData || !isset($userData['email'])) {
    echo "<script>alert('Session data missing.');window.location.href='login.php';</script>";
    exit();
}

$email = $userData['email'];
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$created_at = date('Y-m-d H:i:s');

// Store OTP in session for verification
$_SESSION['otp_data'] = [
    'otp' => $otp,
    'expiry' => $otp_expiry
];

$mail = new PHPMailer(true);
try {
    // Server settings
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'clinicauthentication@gmail.com';
    $mail->Password = 'ierxkcmkmxftggkw';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Additional settings to improve deliverability
    $mail->XMailer = 'Medical Clinic Authentication System';
    $mail->Priority = 1;
    $mail->addCustomHeader('X-MSMail-Priority', 'High');
    $mail->addCustomHeader('Importance', 'High');
    
    // Recipients
    $mail->setFrom('clinicauthentication@gmail.com', 'Medical Clinic Authentication');
    $mail->addAddress($email);
    $mail->addReplyTo('clinicauthentication@gmail.com', 'Medical Clinic Support');
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Your Medical Clinic Authentication Code';
    
    // Improved HTML email template
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authentication Code</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .otp-code { 
                font-size: 32px; 
                font-weight: bold; 
                text-align: center; 
                letter-spacing: 5px;
                color: #2c3e50;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .footer { 
                margin-top: 30px; 
                text-align: center; 
                font-size: 12px; 
                color: #666; 
            }
            .warning {
                background: #fff3cd;
                color: #856404;
                padding: 10px;
                border-radius: 5px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Medical Clinic Authentication</h2>
            </div>
            <p>Hello ' . htmlspecialchars($userData['FirstName']) . ',</p>
            <p>Your authentication code for the Medical Clinic system is:</p>
            <div class="otp-code">' . $otp . '</div>
            <div class="warning">
                <strong>Important:</strong> This code will expire in 10 minutes. Please do not share this code with anyone.
            </div>
            <p>If you did not request this code, please ignore this email or contact support if you have concerns.</p>
            <div class="footer">
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; ' . date('Y') . ' Medical Clinic. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Plain text version for non-HTML email clients
    $mail->AltBody = "Medical Clinic Authentication Code\n\n" .
                     "Hello " . $userData['FirstName'] . ",\n\n" .
                     "Your authentication code is: " . $otp . "\n\n" .
                     "This code will expire in 10 minutes.\n\n" .
                     "If you did not request this code, please ignore this email or contact support.\n\n" .
                     "This is an automated message, please do not reply to this email.";
    
    // Send email
    $mail->send();
    
    // Store OTP in database
    $stmt_insert = mysqli_prepare($conn, "INSERT INTO otp_verification (email, otp, otp_expiry, created_at) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_insert, "ssss", $email, $otp, $otp_expiry, $created_at);
    mysqli_stmt_execute($stmt_insert);
    
    header("Location: verify.php");
    exit();
} catch (Exception $e) {
    error_log("Email Error: " . $mail->ErrorInfo);
    echo "<script>alert('Unable to send authentication code. Please try again later.');window.location.href='login.php';</script>";
    exit();
}
?>
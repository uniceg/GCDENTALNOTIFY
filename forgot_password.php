<?php
session_start();
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Manila');
error_log('Starting password reset process...');

// Use existing config.php instead of manual connection
include 'config.php';

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    error_log('Processing password reset request for email: ' . $email);
    $emailFound = false;
    
    // First check admins table
    $stmt = $conn->prepare("SELECT adminID FROM admins WHERE adminEmail = ?");
    if (!$stmt) {
        error_log('Prepare failed for admin check: ' . $conn->error);
        $message = "An error occurred. Please try again.";
        $message_type = "error";
    } else {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            error_log('Email found in admins table');
            $emailFound = true;
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            error_log('Generated reset token for admin, expires at: ' . $expires);
            
            // Store token in database
            $update_stmt = $conn->prepare("UPDATE admins SET reset_token = ?, reset_expires = ? WHERE adminEmail = ?");
            if (!$update_stmt) {
                error_log('Prepare failed for admin update: ' . $conn->error);
                $message = "An error occurred. Please try again.";
                $message_type = "error";
            } else {
                $update_stmt->bind_param("sss", $token, $expires, $email);
                
                if ($update_stmt->execute()) {
                    error_log('Successfully updated admin reset token');
                    // Create reset link and send email
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/MedicalClinic/reset_password.php?token=" . $token;
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Disable debug for production, enable only for testing
                        $mail->SMTPDebug = 0;  // Set to 0 for production, 3 for debugging
                        
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'medicalclinicnotify@gmail.com';
                        $mail->Password = 'tufhhmtkgkekydvu';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        
                        // Enable TLS explicitly
                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );
                        
                        // Set timeout
                        $mail->Timeout = 60;
                        
                        $mail->setFrom('medicalclinicnotify@gmail.com', 'Medical Clinic Notify+');
                        $mail->addAddress($email);
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset Request - Medical Clinic Notify+';
                        $mail->Body = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                            <title>Password Reset</title>
                            <style>
                                body {
                                    font-family: Arial, sans-serif;
                                    line-height: 1.6;
                                    color: #333;
                                    margin: 0;
                                    padding: 0;
                                    background-color: #f4f4f4;
                                }
                                .container {
                                    max-width: 600px;
                                    margin: 0 auto;
                                    padding: 20px;
                                    background: #ffffff;
                                }
                                .header {
                                    text-align: center;
                                    padding: 20px 0;
                                    background: #2e7d32;
                                    margin: -20px -20px 20px -20px;
                                }
                                .header h1 {
                                    color: #ffffff;
                                    margin: 0;
                                    font-size: 24px;
                                    font-weight: 600;
                                }
                                .content {
                                    padding: 20px 0;
                                }
                                .button {
                                    display: inline-block;
                                    padding: 12px 30px;
                                    background-color: #2e7d32;
                                    color: #ffffff;
                                    text-decoration: none;
                                    border-radius: 5px;
                                    margin: 20px 0;
                                    font-weight: 500;
                                }
                                .button:hover {
                                    background-color: #1b5e20;
                                }
                                .warning {
                                    background-color: #e8f5e9;
                                    border: 1px solid #c8e6c9;
                                    color: #2e7d32;
                                    padding: 15px;
                                    border-radius: 5px;
                                    margin: 20px 0;
                                }
                                .footer {
                                    text-align: center;
                                    padding: 20px 0;
                                    color: #666;
                                    font-size: 12px;
                                    border-top: 1px solid #eee;
                                    margin-top: 20px;
                                }
                                .expiry {
                                    color: #d32f2f;
                                    font-weight: 500;
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>Medical Clinic Notify+</h1>
                                </div>
                                <div class='content'>
                                    <p>Hello,</p>
                                    <p>We received a request to reset your password for your Medical Clinic Notify+ account. To proceed with the password reset, please click the button below:</p>
                                    
                                    <div style='text-align: center;'>
                                        <a href='$reset_link' class='button'>Reset Password</a>
                                    </div>

                                    <div class='warning'>
                                        <strong>Important:</strong>
                                        <ul style='margin: 10px 0; padding-left: 20px;'>
                                            <li>This link will expire in <span class='expiry'>1 hour</span></li>
                                            <li>If you didn't request this password reset, please ignore this email</li>
                                            <li>For security reasons, this link can only be used once</li>
                                        </ul>
                                    </div>

                                    <p>If the button above doesn't work, you can copy and paste the following link into your browser:</p>
                                    <p style='word-break: break-all; color: #666; font-size: 14px;'>$reset_link</p>
                                </div>
                                <div class='footer'>
                                    <p>This is an automated message, please do not reply to this email.</p>
                                    <p>If you need assistance, please contact our support team.</p>
                                    <p>&copy; " . date('Y') . " Medical Clinic Notify+. All rights reserved.</p>
                                </div>
                            </div>
                        </body>
                        </html>";

                        $mail->AltBody = "Medical Clinic Notify+ Password Reset\n\n" .
                                       "Hello,\n\n" .
                                       "We received a request to reset your password. To reset your password, please visit the following link:\n\n" .
                                       $reset_link . "\n\n" .
                                       "This link will expire in 1 hour.\n\n" .
                                       "If you didn't request this password reset, please ignore this email.\n\n" .
                                       "For security reasons, this link can only be used once.\n\n" .
                                       "Best regards,\nMedical Clinic Notify+ Team";

                        $mail->send();
                        error_log('Reset email sent successfully to admin');
                        $message = "Password reset instructions have been sent to your email.";
                        $message_type = "success";
                    } catch (Exception $e) {
                        error_log("PHPMailer Error: " . $e->getMessage());
                        $message = "Failed to send reset email. Please try again later.";
                        $message_type = "error";
                    }
                } else {
                    error_log('Failed to update admin reset token: ' . $update_stmt->error);
                    $message = "An error occurred. Please try again.";
                    $message_type = "error";
                }
                $update_stmt->close();
            }
        }
        $stmt->close();
    }

    // If email not found in admins table, check students table
    if (!$emailFound) {
        error_log('Email not found in admins table, checking students table');
        $stmt = $conn->prepare("SELECT StudentID FROM students WHERE email = ?");
        if (!$stmt) {
            error_log('Prepare failed for student check: ' . $conn->error);
            $message = "An error occurred. Please try again.";
            $message_type = "error";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                error_log('Email found in students table');
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                error_log('Generated reset token for student, expires at: ' . $expires);
                
                // Check if students table has reset columns
                $check_columns = $conn->query("SHOW COLUMNS FROM students LIKE 'reset_%'");
                if ($check_columns->num_rows == 0) {
                    // Add reset columns if they don't exist
                    $alter_query = "ALTER TABLE students 
                                   ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL,
                                   ADD COLUMN reset_expires DATETIME DEFAULT NULL";
                    $conn->query($alter_query);
                }
                
                // Store token in database
                $update_stmt = $conn->prepare("UPDATE students SET reset_token = ?, reset_expires = ? WHERE email = ?");
                if (!$update_stmt) {
                    error_log('Prepare failed for student update: ' . $conn->error);
                    $message = "An error occurred. Please try again.";
                    $message_type = "error";
                } else {
                    $update_stmt->bind_param("sss", $token, $expires, $email);
                    
                    if ($update_stmt->execute()) {
                        error_log('Successfully updated student reset token');
                        // Create reset link and send email
                        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/MedicalClinic/reset_password.php?token=" . $token;
                        $mail = new PHPMailer(true);
                        
                        try {
                            // Server settings
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'medicalclinicnotify@gmail.com';
                            $mail->Password = 'tufhhmtkgkekydvu';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;
                            $mail->SMTPOptions = array(
                                'ssl' => array(
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true
                                )
                            );
                            $mail->Timeout = 60;
                            
                            $mail->setFrom('medicalclinicnotify@gmail.com', 'Medical Clinic Notify+');
                            $mail->addAddress($email);
                            $mail->isHTML(true);
                            $mail->Subject = 'Password Reset Request - Medical Clinic Notify+';
                            
                            // Same green-themed email template for students
                            $mail->Body = "
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <meta charset='UTF-8'>
                                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                                <title>Password Reset</title>
                                <style>
                                    body {
                                        font-family: Arial, sans-serif;
                                        line-height: 1.6;
                                        color: #333;
                                        margin: 0;
                                        padding: 0;
                                        background-color: #f4f4f4;
                                    }
                                    .container {
                                        max-width: 600px;
                                        margin: 0 auto;
                                        padding: 20px;
                                        background: #ffffff;
                                    }
                                    .header {
                                        text-align: center;
                                        padding: 20px 0;
                                        background: #2e7d32;
                                        margin: -20px -20px 20px -20px;
                                    }
                                    .header h1 {
                                        color: #ffffff;
                                        margin: 0;
                                        font-size: 24px;
                                        font-weight: 600;
                                    }
                                    .content {
                                        padding: 20px 0;
                                    }
                                    .button {
                                        display: inline-block;
                                        padding: 12px 30px;
                                        background-color: #2e7d32;
                                        color: #ffffff;
                                        text-decoration: none;
                                        border-radius: 5px;
                                        margin: 20px 0;
                                        font-weight: 500;
                                    }
                                    .button:hover {
                                        background-color: #1b5e20;
                                    }
                                    .warning {
                                        background-color: #e8f5e9;
                                        border: 1px solid #c8e6c9;
                                        color: #2e7d32;
                                        padding: 15px;
                                        border-radius: 5px;
                                        margin: 20px 0;
                                    }
                                    .footer {
                                        text-align: center;
                                        padding: 20px 0;
                                        color: #666;
                                        font-size: 12px;
                                        border-top: 1px solid #eee;
                                        margin-top: 20px;
                                    }
                                    .expiry {
                                        color: #d32f2f;
                                        font-weight: 500;
                                    }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h1>Medical Clinic Notify+</h1>
                                    </div>
                                    <div class='content'>
                                        <p>Hello,</p>
                                        <p>We received a request to reset your password for your Medical Clinic Notify+ account. To proceed with the password reset, please click the button below:</p>
                                        
                                        <div style='text-align: center;'>
                                            <a href='$reset_link' class='button'>Reset Password</a>
                                        </div>

                                        <div class='warning'>
                                            <strong>Important:</strong>
                                            <ul style='margin: 10px 0; padding-left: 20px;'>
                                                <li>This link will expire in <span class='expiry'>1 hour</span></li>
                                                <li>If you didn't request this password reset, please ignore this email</li>
                                                <li>For security reasons, this link can only be used once</li>
                                            </ul>
                                        </div>

                                        <p>If the button above doesn't work, you can copy and paste the following link into your browser:</p>
                                        <p style='word-break: break-all; color: #666; font-size: 14px;'>$reset_link</p>
                                    </div>
                                    <div class='footer'>
                                        <p>This is an automated message, please do not reply to this email.</p>
                                        <p>If you need assistance, please contact our support team.</p>
                                        <p>&copy; " . date('Y') . " Medical Clinic Notify+. All rights reserved.</p>
                                    </div>
                                </div>
                            </body>
                            </html>";

                            $mail->send();
                            error_log('Reset email sent successfully to student');
                            $message = "Password reset instructions have been sent to your email.";
                            $message_type = "success";
                        } catch (Exception $e) {
                            error_log("PHPMailer Error: " . $e->getMessage());
                            $message = "Failed to send reset email. Please try again later.";
                            $message_type = "error";
                        }
                    } else {
                        error_log('Failed to update student reset token: ' . $update_stmt->error);
                        $message = "An error occurred. Please try again.";
                        $message_type = "error";
                    }
                    $update_stmt->close();
                }
            } else {
                error_log('Email not found in either table');
                $message = "No account found with that email address.";
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Medical Clinic Notify+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 60% 40%, #60ad5e 0%, #2e7d32 100%);
            padding: 20px;
        }
        .forgot-container {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(46, 125, 50, 0.18);
            padding: 38px 32px;
            animation: fadeIn 0.7s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-header h2 {
            color: #2e7d32;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2e7d32;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 15px 45px 15px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f5f7fa;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2e7d32;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1);
        }
        .right-icon {
            position: absolute;
            right: 16px;
            top: 42px;
            color: #666;
            font-size: 18px;
            transition: color 0.3s ease;
        }
        .form-group input:focus + .right-icon {
            color: #2e7d32;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .message.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }
        .submit-btn:hover {
            background: #1b5e20;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
        }
        .submit-btn:active {
            transform: translateY(0);
        }
        .submit-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        .submit-btn:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }
        .back-to-login {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .back-to-login a {
            color: #2e7d32;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        .back-to-login a:hover {
            color: #1b5e20;
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .forgot-container {
                padding: 25px 20px;
            }
            .form-group input {
                padding: 13px 40px 13px 14px;
                font-size: 14px;
            }
            .submit-btn {
                padding: 13px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="form-header">
            <h2>Forgot Password</h2>
            <p>Enter your email to reset your password</p>
        </div>
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="forgot_password.php">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                <i class="bi bi-envelope right-icon"></i>
            </div>
            <button type="submit" class="submit-btn">Send Reset Link</button>
        </form>
        <div class="back-to-login">
            <p>Remember your password? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>
<?php
session_start();
include 'config.php'; // Use your existing config.php

date_default_timezone_set('Asia/Manila');
error_log('Starting password reset process...');

$message = "";
$message_type = "";
$valid_token = false;
$email = "";
$user_type = "";

// Verify token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $current_time = date('Y-m-d H:i:s');
    error_log('Verifying token: ' . $token . ' at time: ' . $current_time);
    
    // First check admins table
    $stmt = $conn->prepare("SELECT adminEmail FROM admins WHERE reset_token = ? AND reset_expires > ?");
    if (!$stmt) {
        error_log('Prepare failed for admin token check: ' . $conn->error);
        $message = "An error occurred. Please try again.";
        $message_type = "error";
    } else {
        $stmt->bind_param("ss", $token, $current_time);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            error_log('Valid token found in admins table');
            $stmt->bind_result($email);
            $stmt->fetch();
            $valid_token = true;
            $user_type = 'admin';
        }
        $stmt->close();
    }
    
    // If not found in admins, check students table
    if (!$valid_token) {
        error_log('Token not found in admins table, checking students table');
        $stmt = $conn->prepare("SELECT email FROM students WHERE reset_token = ? AND reset_expires > ?");
        if (!$stmt) {
            error_log('Prepare failed for student token check: ' . $conn->error);
            $message = "An error occurred. Please try again.";
            $message_type = "error";
        } else {
            $stmt->bind_param("ss", $token, $current_time);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                error_log('Valid token found in students table');
                $stmt->bind_result($email);
                $stmt->fetch();
                $valid_token = true;
                $user_type = 'student';
            } else {
                error_log('Token not found in either table or has expired');
                $message = "Invalid or expired reset link. Please request a new one.";
                $message_type = "error";
            }
            $stmt->close();
        }
    }
} else {
    error_log('No token provided in request');
    $message = "Invalid reset link. Please request a new one.";
    $message_type = "error";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    error_log('Processing password reset for email: ' . $email . ' (User type: ' . $user_type . ')');
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Password validation
    if (strlen($new_password) < 8) {
        error_log('Password validation failed: too short');
        $message = "Password must be at least 8 characters long.";
        $message_type = "error";
    } elseif (!preg_match("/[A-Z]/", $new_password)) {
        error_log('Password validation failed: no uppercase');
        $message = "Password must contain at least one uppercase letter.";
        $message_type = "error";
    } elseif (!preg_match("/[a-z]/", $new_password)) {
        error_log('Password validation failed: no lowercase');
        $message = "Password must contain at least one lowercase letter.";
        $message_type = "error";
    } elseif (!preg_match("/[0-9]/", $new_password)) {
        error_log('Password validation failed: no number');
        $message = "Password must contain at least one number.";
        $message_type = "error";
    } elseif (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $new_password)) {
        error_log('Password validation failed: no special character');
        $message = "Password must contain at least one special character.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        error_log('Password validation failed: passwords do not match');
        $message = "Passwords do not match.";
        $message_type = "error";
    } else {
        // Since you don't use password hashing, store as plain text (not recommended for production)
        error_log('Password validated successfully, attempting to update database');
        
        if ($user_type === 'admin') {
            $update_stmt = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_expires = NULL WHERE adminEmail = ?");
        } else {
            $update_stmt = $conn->prepare("UPDATE students SET password = ?, reset_token = NULL, reset_expires = NULL WHERE email = ?");
        }
        
        if (!$update_stmt) {
            error_log('Prepare failed for password update: ' . $conn->error);
            $message = "An error occurred while preparing the update statement.";
            $message_type = "error";
        } else {
            $update_stmt->bind_param("ss", $new_password, $email);
            
            if ($update_stmt->execute()) {
                if ($update_stmt->affected_rows > 0) {
                    error_log('Password successfully updated for ' . $user_type . ' with email: ' . $email);
                    $message = "Password has been reset successfully. You can now login with your new password.";
                    $message_type = "success";
                    $valid_token = false; // Prevent further resets with same token
                } else {
                    error_log('No rows were affected when updating password for ' . $user_type . ' with email: ' . $email);
                    $message = "No changes were made. Please try again.";
                    $message_type = "error";
                }
            } else {
                error_log('Execute failed when updating password: ' . $update_stmt->error);
                $message = "An error occurred while updating the password. Please try again.";
                $message_type = "error";
            }
            $update_stmt->close();
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
    <title>Reset Password | Medical Clinic Notify+</title>
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
        .reset-container {
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
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .right-icon:hover {
            color: #2e7d32;
            transform: scale(1.1);
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
        .password-requirements {
            margin-top: 15px;
            padding: 15px;
            background: #f5f7fa;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        .password-requirements p {
            color: #2e7d32;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .password-requirements ul {
            list-style: none;
            padding-left: 0;
        }
        .password-requirements li {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            font-size: 13px;
            color: #666;
            transition: all 0.3s ease;
        }
        .password-requirements li i {
            margin-right: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .password-requirements li.valid {
            color: #2e7d32;
        }
        .password-requirements li.valid i {
            color: #2e7d32;
        }
        .password-requirements li.invalid {
            color: #c62828;
        }
        .password-requirements li.invalid i {
            color: #c62828;
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
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }
        .submit-btn:disabled {
            background: #c8e6c9;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .submit-btn:not(:disabled):hover {
            background: #1b5e20;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
        }
        .submit-btn:not(:disabled):active {
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
            .reset-container {
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
            .password-requirements {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="form-header">
            <h2>Reset Password</h2>
            <p>Enter your new password below</p>
        </div>
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($valid_token): ?>
            <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($_GET['token']); ?>" id="resetForm">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter new password" required>
                    <span class="right-icon" onclick="togglePassword('password')">
                        <i class="bi bi-eye" id="toggleEye"></i>
                    </span>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    <span class="right-icon" onclick="togglePassword('confirm_password')">
                        <i class="bi bi-eye" id="toggleEyeConfirm"></i>
                    </span>
                </div>
                <div class="password-requirements">
                    <p>Password Requirements:</p>
                    <ul>
                        <li id="length"><i class="bi bi-x-circle"></i> At least 8 characters long</li>
                        <li id="uppercase"><i class="bi bi-x-circle"></i> One uppercase letter</li>
                        <li id="lowercase"><i class="bi bi-x-circle"></i> One lowercase letter</li>
                        <li id="number"><i class="bi bi-x-circle"></i> One number</li>
                        <li id="special"><i class="bi bi-x-circle"></i> One special character</li>
                        <li id="match"><i class="bi bi-x-circle"></i> Passwords match</li>
                    </ul>
                </div>
                <button type="submit" class="submit-btn" id="submitBtn" disabled>Reset Password</button>
            </form>
        <?php else: ?>
            <div class="back-to-login">
                <p><a href="forgot_password.php">Request new reset link</a></p>
            </div>
        <?php endif; ?>
        
        <div class="back-to-login">
            <p>Remember your password? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId === 'password' ? 'toggleEye' : 'toggleEyeConfirm');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        const requirements = {
            length: document.getElementById('length'),
            uppercase: document.getElementById('uppercase'),
            lowercase: document.getElementById('lowercase'),
            number: document.getElementById('number'),
            special: document.getElementById('special'),
            match: document.getElementById('match')
        };

        function validatePassword() {
            const value = password.value;
            const confirmValue = confirmPassword.value;
            let valid = true;

            // Check length
            if (value.length >= 8) {
                requirements.length.classList.add('valid');
                requirements.length.classList.remove('invalid');
                requirements.length.querySelector('i').className = 'bi bi-check-circle';
            } else {
                requirements.length.classList.add('invalid');
                requirements.length.classList.remove('valid');
                requirements.length.querySelector('i').className = 'bi bi-x-circle';
                valid = false;
            }

            // Check uppercase
            if (/[A-Z]/.test(value)) {
                requirements.uppercase.classList.add('valid');
                requirements.uppercase.classList.remove('invalid');
                requirements.uppercase.querySelector('i').className = 'bi bi-check-circle';
            } else {
                requirements.uppercase.classList.add('invalid');
                requirements.uppercase.classList.remove('valid');
                requirements.uppercase.querySelector('i').className = 'bi bi-x-circle';
                valid = false;
            }

            // Check lowercase
            if (/[a-z]/.test(value)) {
                requirements.lowercase.classList.add('valid');
                requirements.lowercase.classList.remove('invalid');
                requirements.lowercase.querySelector('i').className = 'bi bi-check-circle';
            } else {
                requirements.lowercase.classList.add('invalid');
                requirements.lowercase.classList.remove('valid');
                requirements.lowercase.querySelector('i').className = 'bi bi-x-circle';
                valid = false;
            }

            // Check number
            if (/[0-9]/.test(value)) {
                requirements.number.classList.add('valid');
                requirements.number.classList.remove('invalid');
                requirements.number.querySelector('i').className = 'bi bi-check-circle';
            } else {
                requirements.number.classList.add('invalid');
                requirements.number.classList.remove('valid');
                requirements.number.querySelector('i').className = 'bi bi-x-circle';
                valid = false;
            }

            // Check special character - FIXED
            if (/[!@#$%^&*()\-_=+{};:,<.>]/.test(value)) {
                requirements.special.classList.add('valid');
                requirements.special.classList.remove('invalid');
                requirements.special.querySelector('i').className = 'bi bi-check-circle';
            } else {
                requirements.special.classList.add('invalid');
                requirements.special.classList.remove('valid');
                requirements.special.querySelector('i').className = 'bi bi-x-circle';
                valid = false;
            }

            // Check if passwords match
            if (value === confirmValue && value !== '') {
                requirements.match.classList.add('valid');
                requirements.match.classList.remove('invalid');
                requirements.match.querySelector('i').className = 'bi bi-check-circle';
            } else {
                requirements.match.classList.add('invalid');
                requirements.match.classList.remove('valid');
                requirements.match.querySelector('i').className = 'bi bi-x-circle';
                valid = false;
            }

            submitBtn.disabled = !valid;
        }

        // Add event listeners
        password.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);
        
        // Initial validation
        validatePassword();
    </script>
</body>
</html>
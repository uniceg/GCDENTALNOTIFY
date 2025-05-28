<?php
session_start();
include 'config.php';

if (!isset($_GET['token'])) {
    echo "Token is missing.";
    exit();
}

$token = $_GET['token'];

// Verify token - Fixed the datetime comparison
$sql = "SELECT * FROM doctors WHERE reset_token = ? AND reset_expires > ?";
$stmt = $conn->prepare($sql);
$current_time = date('Y-m-d H:i:s'); // Use PHP's current time instead of MySQL's NOW()
$stmt->bind_param("ss", $token, $current_time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Debug information - remove this in production
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
    echo "<strong>Debug Info:</strong><br>";
    echo "Token: " . htmlspecialchars($token) . "<br>";
    echo "Current Time: " . $current_time . "<br>";
    
    // Check if token exists at all
    $debug_sql = "SELECT reset_token, reset_expires FROM doctors WHERE reset_token = ?";
    $debug_stmt = $conn->prepare($debug_sql);
    $debug_stmt->bind_param("s", $token);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    
    if ($debug_result->num_rows > 0) {
        $debug_data = $debug_result->fetch_assoc();
        echo "Token found in DB<br>";
        echo "Expiry Time: " . $debug_data['reset_expires'] . "<br>";
        echo "Token Expired: " . ($debug_data['reset_expires'] <= $current_time ? 'Yes' : 'No');
    } else {
        echo "Token not found in database";
    }
    echo "</div>";
    
    echo "Invalid or expired reset token.";
    exit();
}

$doctor = $result->fetch_assoc();

$message = '';
$messageType = '';

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in all fields.";
        $messageType = "danger";
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = "danger";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "danger";
    } else {
        // Instead of password_hash(), just use the plain password
        $newPassword = $_POST['new_password'];

        // Update password and clear reset token
        $update_sql = "UPDATE doctors SET Password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $newPassword, $token);
        
        if ($update_stmt->execute()) {
            $message = "Password has been reset successfully. You can now login with your new password.";
            $messageType = "success";
            
            // Redirect to login page after 3 seconds
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'doctor_login.php';
                }, 3000);
            </script>";
        } else {
            $message = "Error updating password. Please try again.";
            $messageType = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Medical Clinic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            height: 100vh;
            background: linear-gradient(rgba(46, 125, 50, 0.8), rgba(46, 125, 50, 0.8)), url('loginbg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(46, 125, 50, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.8s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .reset-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(45deg, #2e7d32, #60ad5e, #388e3c);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }
        
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-container img {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 8px rgba(46, 125, 50, 0.3));
        }
        
        .reset-title {
            color: #2e7d32;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .reset-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-label {
            color: #2e7d32;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 12px 45px 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fafafa;
        }
        
        .form-control:focus {
            border-color: #2e7d32;
            box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
            background-color: #fff;
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #2e7d32;
            font-size: 1.1rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #2e7d32;
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #388e3c;
        }
        
        .btn-reset {
            background: linear-gradient(45deg, #2e7d32, #388e3c);
            border: none;
            border-radius: 12px;
            padding: 14px;
            width: 100%;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-reset::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-reset:hover::before {
            left: 100%;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 125, 50, 0.4);
        }
        
        .back-to-login {
            color: #2e7d32;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s ease;
        }
        
        .back-to-login:hover {
            color: #388e3c;
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
            animation: fadeInDown 0.5s ease-out;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .password-requirements {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .password-requirements h6 {
            color: #2e7d32;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
        }
        
        @media (max-width: 576px) {
            .reset-container {
                margin: 20px;
                padding: 30px 25px;
                max-width: none;
            }
            
            .reset-title {
                font-size: 1.75rem;
            }
            
            body {
                padding: 20px;
                align-items: flex-start;
                padding-top: 50px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo-container">
            <img src="MedicalClinicLogo.png" alt="Medical Clinic Logo">
            <h1 class="reset-title">Reset Password</h1>
            <p class="reset-subtitle">Enter your new password for Dr. <?= htmlspecialchars($doctor['FirstName'] . ' ' . $doctor['LastName']) ?></p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?>" role="alert">
                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($messageType !== 'success'): ?>
        <div class="password-requirements">
            <h6><i class="bi bi-shield-check me-2"></i>Password Requirements:</h6>
            <ul>
                <li>Minimum 6 characters long</li>
                <li>Use a combination of letters and numbers</li>
                <li>Avoid using personal information</li>
            </ul>
        </div>
        
        <form method="POST" action="" id="resetForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="new_password" class="form-label">New Password</label>
                <div class="position-relative">
                    <input type="password" class="form-control" id="new_password" name="new_password" required 
                           placeholder="Enter your new password" minlength="6">
                    <i class="bi bi-eye password-toggle" id="passwordToggle1"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <div class="position-relative">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm your new password" minlength="6">
                    <i class="bi bi-eye password-toggle" id="passwordToggle2"></i>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-reset" name="reset_password">
                    <i class="bi bi-shield-check me-2"></i>Reset Password
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <div class="text-center">
            <a href="doctor_login.php" class="back-to-login">
                <i class="bi bi-arrow-left"></i>Back to Login
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        function setupPasswordToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            
            if (toggle && input) {
                toggle.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    
                    this.classList.toggle('bi-eye');
                    this.classList.toggle('bi-eye-slash');
                });
            }
        }
        
        setupPasswordToggle('passwordToggle1', 'new_password');
        setupPasswordToggle('passwordToggle2', 'confirm_password');
        
        // Password match validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePasswords() {
            if (newPassword.value && confirmPassword.value) {
                if (newPassword.value === confirmPassword.value) {
                    confirmPassword.style.borderColor = '#2e7d32';
                    newPassword.style.borderColor = '#2e7d32';
                } else {
                    confirmPassword.style.borderColor = '#dc3545';
                }
            }
        }
        
        if (newPassword && confirmPassword) {
            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        }
        
        // Form validation
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const newPass = newPassword.value;
                const confirmPass = confirmPassword.value;
                
                if (newPass !== confirmPass) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
                
                if (newPass.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return false;
                }
            });
        }
        
        // Auto-dismiss success alerts and redirect
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            let countdown = 3;
            const countdownText = document.createElement('div');
            countdownText.style.marginTop = '10px';
            countdownText.style.fontSize = '0.9rem';
            successAlert.appendChild(countdownText);
            
            const interval = setInterval(() => {
                countdownText.innerHTML = `<i class="bi bi-clock me-1"></i>Redirecting to login in ${countdown} seconds...`;
                countdown--;
                
                if (countdown < 0) {
                    clearInterval(interval);
                }
            }, 1000);
        }
        
        // Auto-dismiss alerts after 5 seconds (except success)
        const alerts = document.querySelectorAll('.alert:not(.alert-success)');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });
        
        // Enhanced form validation styling
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.style.borderColor = '#dc3545';
                } else if (this.id === 'new_password' && this.value.length < 6) {
                    this.style.borderColor = '#dc3545';
                } else {
                    this.style.borderColor = '#2e7d32';
                }
            });
            
            input.addEventListener('input', function() {
                if (this.style.borderColor === 'rgb(220, 53, 69)') {
                    this.style.borderColor = '#e0e0e0';
                }
            });
        });
    </script>
</body>
</html>
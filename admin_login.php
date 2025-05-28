<?php
session_start();
require 'config.php';

// Remove the duplicate database connection since config.php already provides it
// Check for remember me cookie
if (!isset($_SESSION['adminID']) && isset($_COOKIE['admin_remember_email'])) {
    $rememberedEmail = $_COOKIE['admin_remember_email'];
} else {
    $rememberedEmail = '';
}

$error_message = "";
$registration_success = isset($_GET['registered']) && $_GET['registered'] == '1';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    // Query to fetch admin details using email with plain text password comparison
    $stmt = $conn->prepare("SELECT adminID, adminEmail, password, adminName, adminLastName FROM admins WHERE adminEmail = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        
        // Direct password comparison (no hashing)
        if ($password === $row['password']) {
            // Store admin info in session
            $_SESSION['adminID'] = $row['adminID'];
            $_SESSION['adminEmail'] = $row['adminEmail'];
            $_SESSION['adminName'] = $row['adminName'];
            $_SESSION['adminLastName'] = $row['adminLastName'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            
            // Handle remember me functionality
            if ($remember) {
                setcookie('admin_remember_email', $email, time() + (86400 * 30), "/"); // 30 days
            } else {
                // Clear remember me cookie if unchecked
                if (isset($_COOKIE['admin_remember_email'])) {
                    setcookie('admin_remember_email', '', time() - 3600, "/");
                }
            }
            
            // Redirect to admin dashboard
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error_message = "Invalid password";
        }
    } else {
        $error_message = "No account found with that email";
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Medical Clinic Notify+</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            background: linear-gradient(135deg, #29cb3e 0%, #00700f 100%);
            padding: 10px;
            position: relative;
            overflow: hidden;
        }
        .login-container {
            display: flex;
            width: 95%;
            max-width: 1200px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
            backdrop-filter: blur(10px);
            height: 90vh;
        }
        .animation-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
            background: rgba(41, 203, 62, 0.1);
            height: 100%;
        }
        .animation-container img {
            width: 100%;
            max-width: 400px;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
            object-fit: contain;
        }
        .animation-container img:hover {
            transform: scale(1.02);
        }
        .form-container {
            flex: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            overflow-y: auto;
        }
        .form-header {
            margin-bottom: 30px;
            text-align: center;
        }
        .form-header h2 {
            color: rgb(0, 0, 0);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
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
            color: #011f4b;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        .form-group input:focus {
            outline: none;
            border-color: #29cb3e;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(41, 203, 62, 0.1);
        }
        .right-icon {
            position: absolute;
            right: 15px;
            top: 40px;
            color: #666;
            font-size: 18px;
            cursor: pointer;
            z-index: 2;
        }
        .right-icon:hover {
            color: #29cb3e;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #29cb3e;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .submit-btn:hover {
            background: #005703;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 112, 15, 0.2);
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .register-link a {
            color: #011f4b;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .register-link a:hover {
            color: #024351;
            text-decoration: underline;
        }
        .error-message {
            color: #dc3545;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
            display: block;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: #011f4b;
            user-select: none;
            position: relative;
            padding-left: 28px;
        }
        .checkbox-container input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        .checkmark {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 18px;
            width: 18px;
            background-color: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .checkbox-container:hover .checkmark {
            border-color: #29cb3e;
        }
        .checkbox-container input:checked ~ .checkmark {
            background-color: #29cb3e;
            border-color: #29cb3e;
        }
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }
        .checkbox-container input:checked ~ .checkmark:after {
            display: block;
        }
        .checkbox-container .checkmark:after {
            left: 5px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
            }
            .animation-container {
                padding: 20px;
            }
            .form-container {
                padding: 30px;
            }
        }
        @media (max-width: 576px) {
            body {
                padding: 15px;
            }
            .login-container {
                width: 100%;
                border-radius: 15px;
            }
            .form-container {
                padding: 25px;
            }
            .form-header h2 {
                font-size: 24px;
            }
            .form-group input {
                padding: 10px 12px;
                font-size: 13px;
            }
            .submit-btn {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Login form -->
    <div class="login-container">
        <div class="animation-container">
            <img src="./img/GCLINIC.png" alt="Medical Clinic Notify+ Admin">
        </div>
        <div class="form-container">
            <div class="form-header">
                <h2>Admin Login</h2>
                <p>Login to access your Medical Clinic Notify+ admin account</p>
            </div>
            <?php if ($registration_success): ?>
                <div class="message success-message" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; padding: 12px; border-radius: 8px; font-size: 14px; text-align: center; margin-bottom: 12px; display: block;">
                    <i class="bi bi-check-circle-fill" style="color:#2e7d32; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                    Registration successful! Please log in.
                </div>
            <?php endif; ?>
            <form method="POST" action="admin_login.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your admin email" value="<?php echo htmlspecialchars($rememberedEmail); ?>" required>
                    <i class="bi bi-envelope right-icon"></i>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <span class="right-icon" onclick="togglePassword()">
                        <i class="bi bi-eye" id="toggleEye"></i>
                    </span>
                </div>
                <div class="form-group remember-me">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <label class="checkbox-container" style="margin-bottom: 0;">
                            <input type="checkbox" name="remember" id="remember" <?php echo !empty($rememberedEmail) ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <a href="forgot_password.php" style="color: #011f4b; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; margin-left: 10px;">Forgot Password?</a>
                    </div>
                </div>
                <button type="submit" class="submit-btn">Login</button>
                <div class="error-message" style="display:<?php echo !empty($error_message) ? 'block' : 'none'; ?>; background: #fbeaea; color: #c0392b; border: 1px solid #f5c6cb; padding: 12px; border-radius: 8px; font-size: 14px; text-align: center; margin-top: 12px; margin-bottom: 12px;">
                    <?php if (!empty($error_message)): ?>
                        <i class="bi bi-exclamation-triangle-fill" style="color:#c0392b; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                        <?php echo $error_message; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <script>
        // Toggle password visibility
        function togglePassword() {
            var passwordField = document.getElementById('password');
            var eyeIcon = document.getElementById('toggleEye');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        }

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var errorMessage = document.querySelector('.error-message');
                if (errorMessage && errorMessage.style.display === 'block') {
                    errorMessage.style.opacity = '0';
                    setTimeout(function() {
                        errorMessage.style.display = 'none';
                    }, 500);
                }
            }, 5000);
        });
    </script>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

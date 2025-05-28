<?php
session_start();
include 'config.php';

// PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Initialize error message variable
$error_message = "";

// Remember me cookie logic
if (!isset($_SESSION['doctor_id']) && isset($_COOKIE['doctor_remember_email'])) {
    $rememberedEmail = filter_var($_COOKIE['doctor_remember_email'], FILTER_SANITIZE_EMAIL);
} else {
    $rememberedEmail = '';
}

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $remember = isset($_POST['remember']) ? true : false;
    
    if (empty($email) || empty($password)) {
        $error_message = 'Email and Password cannot be empty.';
    } else {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT DoctorID, FirstName, LastName, Email, Password FROM doctors WHERE Email = ? AND Status = 'Active'");
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error_message = "No active doctor account found with this email.";
            } elseif ($result->num_rows === 1) {
                $doctor = $result->fetch_assoc();
                
                // Check password (assuming plain text for now, can be updated to use password_verify)
                if ($password === $doctor['Password']) {
                    // Set session variables
                    $_SESSION['doctor_id'] = $doctor['DoctorID'];
                    $_SESSION['doctor_name'] = $doctor['FirstName'] . ' ' . $doctor['LastName'];
                    $_SESSION['doctor_email'] = $doctor['Email'];
                    $_SESSION['doctor_first_name'] = $doctor['FirstName'];
                    $_SESSION['doctor_last_name'] = $doctor['LastName'];
                    
                    // Handle remember me functionality
                    if ($remember) {
                        setcookie(
                            'doctor_remember_email', 
                            $email, 
                            [
                                'expires' => time() + (86400 * 30), // 30 days
                                'path' => '/',
                                'secure' => false,
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]
                        );
                    } else {
                        setcookie(
                            'doctor_remember_email', 
                            '', 
                            [
                                'expires' => time() - 3600,
                                'path' => '/',
                                'secure' => false,
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]
                        );
                    }
                    
                    // Redirect to doctor dashboard
                    header("Location: doctor_dashboard.php");
                    exit();
                } else {
                    $error_message = "Invalid email or password.";
                }
            }
            
            // Close statement
            $stmt->close();
        }
        
        // Close database connection
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Login - Gordon College Dental Clinic</title>
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
        .doctor-badge {
            display: inline-block;
            background-color: #e3f2fd;
            color: #1565c0;
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            margin-top: 8px;
            font-weight: 500;
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
            border-color: #011f4b;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(1, 31, 75, 0.1);
        }
        .right-icon {
            position: absolute;
            right: 15px;
            top: 40px;
            color: #666;
            font-size: 18px;
            cursor: pointer;
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
        .error-message {
            color: #dc3545;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
            display: block;
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
            border-color: #011f4b;
        }
        .checkbox-container input:checked ~ .checkmark {
            background-color: #011f4b;
            border-color: #011f4b;
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
        .back-link {
            text-align: center;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .back-link a {
            color: #00700f;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .back-link a:hover {
            background-color: #f8f9fa;
            border-color: #00700f;
        }
    </style>
</head>
<body>
    <!-- Login form -->
    <div class="login-container">
        <div class="animation-container">
            <img src="./img/GCLINIC.png" alt="Gordon College Dental Clinic">
        </div>
        <div class="form-container">
            <div class="form-header">
                <h2>Doctor Portal</h2>
                <p>Access your professional account</p>
                <div class="doctor-badge">
                    <i class="bi bi-person-badge"></i> Medical Staff Only
                </div>
            </div>
            
            <form method="POST" action="doctor_login.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your professional email" value="<?php echo htmlspecialchars($rememberedEmail); ?>" required>
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
                        <a href="doctor_forgot_password.php" style="color: #011f4b; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; margin-left: 10px;">Forgot Password?</a>
                    </div>
                </div>
                <button type="submit" class="submit-btn">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </button>
                
                <div class="error-message" style="display:<?php echo !empty($error_message) ? 'block' : 'none'; ?>; background: #fbeaea; color: #c0392b; border: 1px solid #f5c6cb; padding: 12px; border-radius: 8px; font-size: 14px; text-align: center; margin-top: 12px; margin-bottom: 12px;">
                    <?php if (!empty($error_message)): ?>
                        <i class="bi bi-exclamation-triangle-fill" style="color:#c0392b; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                        <?php echo $error_message; ?>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="back-link">
                <a href="index.php">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to Home</span>
                </a>
            </div>
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
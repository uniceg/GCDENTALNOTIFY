<?php
require_once 'session_helper.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $studentID = trim($_POST['studentID']);
    $password = trim($_POST['password']);

    if (empty($studentID) || empty($password)) {
        echo "<script>
                alert('Student ID and Password cannot be empty.');
                window.location.href = 'login.php';
              </script>";
        exit();
    }

    // Database connection
    $conn = new mysqli("localhost", "root", "", "oop");

    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    // Get user data with a single query
    $sql = "SELECT studentID, password, FirstName, LastName, email FROM students WHERE studentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $studentID);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Initialize session with user data
        $userData = [
            'FirstName' => $user['FirstName'],
            'LastName' => $user['LastName'],
            'email' => $user['email']
        ];
        initializeSession($user['studentID'], $userData);
        
        header("Location: studentHome.php");
        exit();
    } else {
        echo "<script>
                alert('Invalid Student ID or password.');
                window.location.href = 'login.php';
              </script>";
    }

    $stmt->close();
    $conn->close();
    exit();
}

// Prefill studentID if cookie exists
$rememberedID = isset($_COOKIE['student_remember']) ? $_COOKIE['student_remember'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Medical Clinic Notify+</title>
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
            background: linear-gradient(135deg, rgb(141, 206, 243) 0%, #011f4b 100%);
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        .login-container {
            display: flex;
            width: 90%;
            max-width: 1200px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
            backdrop-filter: blur(10px);
        }

        .animation-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
            background: rgb(141, 206, 243);
        }

        .animation-container::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 70%;
            width: 1px;
            background: linear-gradient(to bottom, 
                rgba(1, 31, 75, 0) 0%,
                rgba(1, 31, 75, 0.2) 50%,
                rgba(1, 31, 75, 0) 100%);
        }

        .form-container {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .form-header h2 {
            color: #011f4b;
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
            border-color: #011f4b;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(1, 31, 75, 0.1);
        }

        .form-group i {
            position: absolute;
            right: 15px;
            top: 40px;
            color: #666;
        }

        .eye-icon {
            position: absolute;
            right: 15px;
            top: 40px;
            color: #666;
            cursor: pointer;
            font-size: 18px;
        }

        .eye-icon:hover {
            color: #011f4b;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #011f4b;
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
            background: #024351;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(1, 31, 75, 0.2);
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
            display: none;
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

        .remember-me {
            margin-bottom: 15px;
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="animation-container">
            <dotlottie-player 
                src="https://lottie.host/fa8a5e18-1af9-434f-8d12-01ca3aa91e15/dDkcGIx87f.lottie" 
                background="transparent" 
                speed="1" 
                style="width: 100%; max-width: 400px; height: auto;" 
                loop 
                autoplay>
            </dotlottie-player>
        </div>
        
        <div class="form-container">
            <div class="form-header">
                <h2>Welcome Back!</h2>
                <p>Login to access your Medical Clinic Notify+ account</p>
            </div>
            
            <form action="" method="post">
                <div class="form-group">
                    <label for="studentID">User ID</label>
                    <input type="text" id="studentID" name="studentID" placeholder="Enter your ID number" required value="<?php echo htmlspecialchars($rememberedID); ?>">
                    <i class="bi bi-person"></i>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <i class="bi bi-eye eye-icon" id="toggleEye" onclick="togglePassword()"></i>
                </div>
                
                <div class="form-group remember-me">
                    <label class="checkbox-container">
                        Remember me
                        <input type="checkbox" name="remember" id="remember" <?php if($rememberedID) echo 'checked'; ?>>
                        <span class="checkmark"></span>
                    </label>
                </div>
                
                <button type="submit" class="submit-btn">Login</button>
            </form>
            
            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
            
            <div class="error-message" id="errorMessage"></div>
        </div>
    </div>

    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleEye');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>

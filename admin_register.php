<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database config
$host = 'localhost';
$db   = 'medicalclinicnotify';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
$lastOCRRawResponse = '';  // capture raw OCR.space response for debugging
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// OCR.space API key
define('OCR_SPACE_API_KEY', 'K83449453488957');

// Generate next unique admin number for this year
$year = date('Y');
$prefix = "ADM-$year-";

// Find the highest existing admin number for this year
$result = $conn->query("SELECT adminID FROM admins WHERE adminID LIKE '$prefix%' ORDER BY adminID DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $lastId = intval(substr($row['adminID'], -4));
    $nextId = str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
} else {
    $nextId = '0001';
}
$generatedAdminID = $prefix . $nextId;

// OCR function via Tesseract
function processOCR($imagePath) {
    // Path to your Tesseract executable
    $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    if (!file_exists($tesseractPath)) {
        return false;
    }

    // Use Tesseract to perform OCR
    $command = "\"$tesseractPath\" " . escapeshellarg($imagePath) . " stdout";
    $output = shell_exec($command);

    // Clean up the output
    $output = preg_replace('/[^a-zA-Z0-9\\s]/', '', $output);
    $output = trim($output);

    return $output ?: false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Google reCAPTCHA verification
    $recaptcha_secret = "6LfSoTErAAAAAINJReNYZxehfuQEyb4ZNBXmcrFI";
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
    $captcha_success = json_decode($verify);
    
    if (!$captcha_success->success) {
        $error_message = "Please complete the reCAPTCHA verification.";
    } else {
        // Get form data
        $adminName = trim($_POST['adminName']);
        $adminLastName = trim($_POST['adminLastName']);
        $adminMiddleInitial = trim($_POST['adminMiddleInitial']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $contactNumber = trim($_POST['contactNumber']);
        $adminID = $conn->real_escape_string($_POST['adminID']);

        $fullName = strtoupper("$adminName $adminLastName");
        $error = '';
        $status = 0;
        $email_error = '';

        // Check for duplicate email
        $check_email = $conn->prepare('SELECT adminID FROM admins WHERE adminEmail = ?');
        $check_email->bind_param('s', $email);
        $check_email->execute();
        $check_email->store_result();
        if ($check_email->num_rows > 0) {
            $email_error = 'This email is already registered. Please use a different email.';
        }
        $check_email->close();

        // File upload and registration only if no email error
        if (empty($email_error)) {
            // File upload
            if (isset($_FILES['idUpload']) && $_FILES['idUpload']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg','image/png','application/pdf'];
                $type = $_FILES['idUpload']['type'];
                if (!in_array($type, $allowed)) {
                    $error = 'Invalid file type. JPG, PNG or PDF only.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $fname = uniqid() . '_' . basename($_FILES['idUpload']['name']);
                    $path = $uploadDir . $fname;
                    if (move_uploaded_file($_FILES['idUpload']['tmp_name'], $path)) {
                        $ocrText = processOCR($path);
                        $ocrUP = strtoupper($ocrText);
                        // Auto-verify: both first and last name must appear in OCR text
                        $adminNameUP = strtoupper($adminName);
                        $adminLastNameUP = strtoupper($adminLastName);
                        if (stripos($ocrUP, $adminNameUP) !== false && stripos($ocrUP, $adminLastNameUP) !== false) {
                            $status = 1;
                        } else {
                            $error = 'OCR verification failed: Your first and last name must be clearly visible in the uploaded ID image. Please try again with a clearer image.';
                            $status = 0;
                        }
                        
                        // Only insert if status is 1 and no error
                        if ($status === 1 && empty($error)) {
                            $stmt = $conn->prepare(
                                'INSERT INTO admins 
                                 (adminID, adminName, adminLastName, adminMiddleInitial, adminEmail, password, contactNumber, id_verified, ocr_result) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                            );
                            if (!$stmt) {
                                die('Prepare failed: ' . $conn->error);
                            }
                            $stmt->bind_param(
                                'sssssssis',
                                $adminID, $adminName, $adminLastName, $adminMiddleInitial, $email, $password, $contactNumber, $status, $ocrText
                            );
                            if ($stmt->execute()) {
                                // Debug: Print the OCR result
                                error_log("Admin OCR result: " . $ocrText);
                                // Registration successful, redirect to login with success message
                                header('Location: admin_login.php?registered=1');
                                exit();
                            } else {
                                $error = 'Database error: ' . $stmt->error;
                            }
                        }
                    } else {
                        $error = 'Failed to move uploaded file.';
                    }
                }
            } else {
                $error = 'Please upload your ID document.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration | Medical Clinic</title>
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
            background: linear-gradient(135deg, #f6f9fc 0%, #eef2f7 100%);
            padding: 0;
            margin: 0;
            position: relative;
            overflow: hidden;
        }

        .register-container {
            display: flex;
            width: 100%;
            height: 100vh;
            background: #fff;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            position: relative;
        }

        .animation-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
        }

        .animation-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            z-index: 1;
        }

        .form-container {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
            background: #fff;
            position: relative;
            margin: 0;
        }

        .form-header {
            margin-bottom: 30px;
            text-align: left;
            background: #fff;
            padding: 20px 0 20px 0;
        }

        .form-content {
            flex: 1;
            padding-top: 0;
            margin-top: 0;
        }

        .form-header h2 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            height: 48px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4a90e2;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
        }

        .form-group input[type="file"] {
            height: auto;
            padding: 12px;
            background: #f8f9fa;
            border: 2px dashed #e0e0e0;
            cursor: pointer;
        }

        .form-group input[type="file"]:hover {
            border-color: #4a90e2;
            background: #f0f7ff;
        }

        .form-group small {
            display: block;
            margin-top: 6px;
            color: #7f8c8d;
            font-size: 12px;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            height: 48px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.2);
        }

        .message {
            padding: 12px;
            margin: 12px 0;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .login-link {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #7f8c8d;
            background: #fff;
            padding: 20px 0;
        }

        .login-link a {
            color: #4a90e2;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #357abd;
            text-decoration: underline;
        }

        @media (max-width: 992px) {
            .register-container {
                flex-direction: column;
                height: 100vh;
            }

            .animation-container {
                padding: 20px;
                min-height: 200px;
            }

            .form-container {
                padding: 30px;
                height: calc(100vh - 200px);
                margin: 0;
            }

            .form-header {
                padding: 0 0 20px 0;
                margin-top: 0;
            }
        }

        @media (max-width: 576px) {
            .form-container {
                padding: 20px;
                margin: 0;
            }

            .form-header {
                padding: 0 0 20px 0;
                margin-top: 0;
            }

            .form-header h2 {
                font-size: 24px;
            }

            .form-group input,
            .form-group select {
                padding: 10px 12px;
                font-size: 14px;
                height: 44px;
            }

            .submit-btn {
                padding: 12px;
                font-size: 15px;
                height: 44px;
            }
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #7f8c8d;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #4a90e2;
        }

        .password-toggle i {
            font-size: 18px;
        }

        .password-requirements {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 12px;
            margin-top: 8px;
            z-index: 1000;
            border: 1px solid #e0e0e0;
        }

        .password-requirements.show {
            display: block;
            animation: fadeIn 0.2s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
            color: #7f8c8d;
        }

        .requirement:last-child {
            margin-bottom: 0;
        }

        .requirement i {
            margin-right: 8px;
            font-size: 14px;
        }

        .requirement.valid {
            color: #2e7d32;
        }

        .requirement.invalid {
            color: #c62828;
        }

        .recaptcha-container {
            display: flex;
            justify-content: center;
            margin: 12px 0 18px 0;
            width: 100%;
        }

        /* Responsive reCAPTCHA */
        .g-recaptcha {
            transform: scale(1);
            -webkit-transform: scale(1);
            transform-origin: 0 0;
            -webkit-transform-origin: 0 0;
            transition: transform 0.2s;
        }
        @media (max-width: 480px) {
            .g-recaptcha {
                transform: scale(0.85);
                -webkit-transform: scale(0.85);
            }
        }
        @media (max-width: 350px) {
            .g-recaptcha {
                transform: scale(0.7);
                -webkit-transform: scale(0.7);
            }
        }

        #passwordMatch {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="animation-container">
            <div style="position: relative; z-index: 2; text-align: center;">
                <img src="register_gif.gif" alt="Registration Animation" style="width: 100%; max-width: 300px; height: auto; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                <div style="margin-top: 20px; color: #fff; font-weight: 500; font-size: 16px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    Welcome to Medical Clinic Notify+
                </div>
                <div style="margin-top: 8px; color: rgba(255,255,255,0.8); font-size: 14px;">
                    Join our healthcare community
                </div>
            </div>
        </div>
        
        <div class="form-container">
            <div class="form-header">
                <h2>Admin Registration</h2>
                <p>Create your Medical Clinic Notify+ admin account</p>
            </div>
            
            <div class="form-content">
                <form method="POST" action="admin_register.php" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="idUpload">ID Upload</label>
                        <input type="file" id="idUpload" name="idUpload" accept="image/*" required>
                        <small>Upload your ID to auto-fill your information</small>
                    </div>

                    <div class="form-group">
                        <label for="adminName">First Name</label>
                        <input type="text" id="adminName" name="adminName" placeholder="Enter your first name" required>
                    </div>

                    <div class="form-group">
                        <label for="adminLastName">Last Name</label>
                        <input type="text" id="adminLastName" name="adminLastName" placeholder="Enter your last name" required>
                    </div>

                    <div class="form-group">
                        <label for="adminMiddleInitial">Middle Initial</label>
                        <input type="text" id="adminMiddleInitial" name="adminMiddleInitial" placeholder="Enter your middle initial" maxlength="1">
                    </div>

                    <div class="form-group">
                        <label for="adminID">Admin ID</label>
                        <input type="text" id="adminID" name="adminID" value="<?php echo htmlspecialchars($generatedAdminID); ?>" readonly style="background:#f0f0f0; font-weight:600;">
                        <small>This will be your unique Admin ID.</small>
                    </div>

                    <div class="form-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" id="dob" name="dob" required>
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="" disabled selected>Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Rather not say">Rather not say</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="contactNumber">Contact Number</label>
                        <input type="text" id="contactNumber" name="contactNumber" placeholder="Enter your contact number" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-container">
                            <input type="password" id="password" name="password" placeholder="Create a password" required 
                                   onkeyup="validatePassword()" onfocus="showRequirements()" onblur="hideRequirements()">
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="bi bi-eye"></i>
                            </button>
                            <div class="password-requirements" id="passwordRequirements">
                                <div class="requirement" id="length">
                                    <i class="bi bi-circle"></i> At least 8 characters
                                </div>
                                <div class="requirement" id="case">
                                    <i class="bi bi-circle"></i> At least one uppercase and one lowercase letter
                                </div>
                                <div class="requirement" id="numsym">
                                    <i class="bi bi-circle"></i> At least one number and one symbol (non-alphanumeric character)
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group recaptcha-container">
                        <div class="g-recaptcha" data-sitekey="6LfSoTErAAAAAHkhKahjNhhTd3-f8sYk0uPFI08_"></div>
                    </div>

                    <button type="submit" class="submit-btn">Register</button>

                    <?php if (!empty($success_message)): ?>
                        <div class="message success-message" style="margin-top:12px;">
                            <i class="bi bi-check-circle-fill" style="color:#2e7d32; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="message error-message" style="margin-top:12px;">
                            <i class="bi bi-exclamation-triangle-fill" style="color:#c62828; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($email_error)): ?>
                        <div class="message error-message" style="margin-top:12px;">
                            <i class="bi bi-exclamation-triangle-fill" style="color:#c62828; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                            <?php echo $email_error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="message error-message" style="margin-top:12px;">
                            <i class="bi bi-exclamation-triangle-fill" style="color:#c62828; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <div id="passwordMatch" class="message error-message" style="margin-top:12px; display:none;">
                        <i class="bi bi-exclamation-triangle-fill" style="color:#c62828; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                        Passwords do not match
                    </div>

                    <div class="login-link">
                        <p>Already have an account? <a href="admin_login.php">Login here</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleButton = passwordInput.nextElementSibling.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.classList.remove('bi-eye');
                toggleButton.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleButton.classList.remove('bi-eye-slash');
                toggleButton.classList.add('bi-eye');
            }
        }

        function showRequirements() {
            const requirements = document.getElementById('passwordRequirements');
            requirements.classList.add('show');
        }

        function hideRequirements() {
            const requirements = document.getElementById('passwordRequirements');
            // Add a small delay to allow clicking on the requirements popup
            setTimeout(() => {
                requirements.classList.remove('show');
            }, 200);
        }

        function validatePassword() {
            const password = document.getElementById('password').value;
            
            // Password requirements
            const requirements = {
                length: password.length >= 8,
                case: /[A-Z]/.test(password) && /[a-z]/.test(password),
                numsym: /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)
            };

            // Update UI for each requirement
            for (const [requirement, isValid] of Object.entries(requirements)) {
                const element = document.getElementById(requirement);
                const icon = element.querySelector('i');
                
                if (isValid) {
                    element.classList.add('valid');
                    element.classList.remove('invalid');
                    icon.classList.remove('bi-circle');
                    icon.classList.add('bi-check-circle-fill');
                } else {
                    element.classList.add('invalid');
                    element.classList.remove('valid');
                    icon.classList.remove('bi-check-circle-fill');
                    icon.classList.add('bi-circle');
                }
            }

            // Enable/disable submit button based on all requirements
            const submitButton = document.querySelector('.submit-btn');
            const allRequirementsMet = Object.values(requirements).every(Boolean);
            submitButton.disabled = !allRequirementsMet;
            submitButton.style.opacity = allRequirementsMet ? '1' : '0.6';
        }

        // Add server-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            
            if (password.length < 8) {
                e.preventDefault();
                document.getElementById('passwordMatch').style.display = 'block';
                return false;
            }
            return true;
        });
    </script>
</body>
</html>

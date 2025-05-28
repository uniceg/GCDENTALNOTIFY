<?php
// Start session at the very beginning
session_start();

// Initialize session variables with default values
if (!isset($_SESSION['step'])) {
    $_SESSION['step'] = 1;
}
if (!isset($_SESSION['similarity'])) {
    $_SESSION['similarity'] = 0;
}

// Debug session
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost'; 
$db = 'medicalclinicnotify';
$user = 'root';     
$pass = '';         
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step'])) {
        switch ($_POST['step']) {
            case '1':
                // Step 1: Save basic information
                $firstName = trim($_POST['firstName']);
                $lastName = trim($_POST['lastName']);
                $middleInitial = trim($_POST['middleInitial']);
                $dob = trim($_POST['dob']);
                $email = trim($_POST['email']);

                // Validate input
                if (empty($firstName) || empty($lastName) || empty($email)) {
                    $_SESSION['error'] = "Please fill in all required fields.";
                } else {
                    // Check for duplicate email
                    $checkEmail = $conn->prepare("SELECT 1 FROM students WHERE email = ?");
                    $checkEmail->bind_param("s", $email);
                    $checkEmail->execute();
                    $checkEmail->store_result();
                    if ($checkEmail->num_rows > 0) {
                        $_SESSION['error'] = "This email is already registered. Please use a different email.";
                        $checkEmail->close();
                        break; // Stay on step 1
                    }
                    $checkEmail->close();

                    // Store in session
                    $_SESSION['temp_registration'] = [
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'middleInitial' => $middleInitial,
                        'dob' => $dob,
                        'email' => $email
                    ];
                    
                    // Move to step 2
                    $_SESSION['step'] = 2;
                    $_SESSION['success'] = "Basic information saved successfully!";
                    // Reset similarity score
                    $_SESSION['similarity'] = 0;
                }
                break;

            case '2':
                // Step 2: Process ID verification
                if (!isset($_SESSION['temp_registration'])) {
                    $_SESSION['error'] = "Please complete step 1 first.";
                    $_SESSION['step'] = 1;
                } else if (isset($_FILES['idUpload']) && $_FILES['idUpload']['error'] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/png'];
                    $fileType = $_FILES['idUpload']['type'];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        $_SESSION['error'] = "Please upload a JPEG or PNG image.";
                    } else {
                        $uploadDir = "uploads/";
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        $fileName = uniqid() . '_' . basename($_FILES['idUpload']['name']);
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['idUpload']['tmp_name'], $filePath)) {
                            // Set scanning message
                            $_SESSION['scanning'] = true;
                            $_SESSION['message'] = "Scanning ID...";
                            
                            $ocrText = processOCR($filePath);
                            
                            if ($ocrText) {
                                // Check for duplicate OCR result
                                $checkOcr = $conn->prepare("SELECT 1 FROM students WHERE ocr_result = ?");
                                $checkOcr->bind_param("s", $ocrText);
                                $checkOcr->execute();
                                $checkOcr->store_result();
                                if ($checkOcr->num_rows > 0) {
                                    $_SESSION['error'] = "This ID has already been used for registration.";
                                    $checkOcr->close();
                                    unlink($filePath);
                                    unset($_SESSION['scanning']);
                                    unset($_SESSION['message']);
                                    header("Location: register.php");
                                    exit();
                                }
                                $checkOcr->close();

                                $ocrText = strtoupper($ocrText);
                                $firstName = strtoupper($_SESSION['temp_registration']['firstName']);
                                $middleName = strtoupper($_SESSION['temp_registration']['middleInitial']);
                                $lastName = strtoupper($_SESSION['temp_registration']['lastName']);
                                $middleCheck = empty($middleName) ? true : (strpos($ocrText, $middleName) !== false);

                                if (
                                    strpos($ocrText, $firstName) !== false &&
                                    $middleCheck &&
                                    strpos($ocrText, $lastName) !== false
                                ) {
                                    // Name found, pass verification
                                    $_SESSION['step'] = 3;
                                    $_SESSION['id_verified'] = true;
                                    $_SESSION['success'] = "ID verification successful! Your identity has been verified and you may proceed with registration.";
                                    $_SESSION['ocr_result'] = $ocrText;
                                } else {
                                    $_SESSION['error'] = "ID verification failed. Please ensure your ID image is clear and matches the information you provided.";
                                }
                            } else {
                                $_SESSION['error'] = "Failed to process ID image. Please ensure Tesseract OCR is installed.";
                            }
                            
                            // Clean up the uploaded file
                            unlink($filePath);
                            
                            // Clear scanning message
                            unset($_SESSION['scanning']);
                            unset($_SESSION['message']);
                        } else {
                            $_SESSION['error'] = "Failed to upload ID image.";
                        }
                    }
                } else {
                    $_SESSION['error'] = "Please upload an ID image.";
                }
                break;

            case '3':
                // Step 3: Complete registration
                if (!isset($_SESSION['id_verified']) || !$_SESSION['id_verified']) {
                    $_SESSION['error'] = "Please complete ID verification first.";
                    $_SESSION['step'] = 2;
                } else {
                    // Add reCAPTCHA check here
                    $recaptcha_secret = "6LfSoTErAAAAAINJReNYZxehfuQEyb4ZNBXmcrFI";
                    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
                    $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
                    $captcha_success = json_decode($verify);

                    if (!$captcha_success->success) {
                        $_SESSION['error'] = "Please complete the reCAPTCHA verification.";
                        header("Location: register.php");
                        exit();
                    }

                    $studentID = trim($_POST['studentID']);
                    $gender = trim($_POST['gender']);
                    $contactNumber = trim($_POST['contactNumber']);
                    $parentGuardian = trim($_POST['parentGuardian']);
                    $parentContact = trim($_POST['parentContact']);
                    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

                    // Insert into database
                    $stmt = $conn->prepare("INSERT INTO students (StudentID, FirstName, LastName, middleInitial, dob, email, GENDER, ContactNumber, parentGuardian, parentContact, Password, ocr_result, id_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->bind_param("ssssssssssss", $studentID, $_SESSION['temp_registration']['firstName'], $_SESSION['temp_registration']['lastName'], 
                                    $_SESSION['temp_registration']['middleInitial'], $_SESSION['temp_registration']['dob'], $_SESSION['temp_registration']['email'],
                                    $gender, $contactNumber, $parentGuardian, $parentContact, $password, $_SESSION['ocr_result']);

                    if ($stmt->execute()) {
                        // Redirect to login.php with success flag
                        header("Location: login.php?registered=1");
                        exit();
                    } else {
                        $_SESSION['error'] = "Error: " . $stmt->error;
                    }
                }
                break;
        }
    }
    header("Location: register.php");
    exit();
}

// Handle step navigation from URL
if (isset($_GET['step'])) {
    $requestedStep = (int)$_GET['step'];
    if ($requestedStep >= 1 && $requestedStep <= 3) {
        $_SESSION['step'] = $requestedStep;
        header("Location: register.php");
        exit();
    }
}

// Function to process OCR
function processOCR($imagePath) {
    // Check if Tesseract is installed
    $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    if (!file_exists($tesseractPath)) {
        return false;
    }

    // Use Tesseract to perform OCR
    $command = "\"$tesseractPath\" " . escapeshellarg($imagePath) . " stdout";
    $output = shell_exec($command);
    
    // Clean up the output
    $output = preg_replace('/[^a-zA-Z0-9\s]/', '', $output);
    $output = trim($output);
    
    return $output ?: false;
}

// Function to check if name exists in database
function checkNameExists($firstName, $lastName, $middleName, $conn) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE firstName = ? AND lastName = ? AND middleInitial = ?");
    $stmt->bind_param("sss", $firstName, $lastName, $middleName);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | Medical Clinic</title>
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

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            position: relative;
            padding: 0 15px;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: #4a90e2;
            border-color: #4a90e2;
            color: white;
        }

        .step.completed .step-number {
            background: #4CAF50;
            border-color: #4CAF50;
            color: white;
        }

        .step-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .step.active .step-label {
            color: #4a90e2;
        }

        .upload-area {
            border: 2px dashed #4a90e2;
            padding: 25px 15px;
            text-align: center;
            margin: 15px 0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .upload-area:hover {
            background-color: #f0f7ff;
            border-color: #357abd;
        }

        .upload-area i {
            font-size: 36px;
            color: #4a90e2;
            margin-bottom: 10px;
        }

        .upload-area p {
            margin: 5px 0;
            color: #666;
        }

        .step-navigation {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            width: 100%;
        }

        .step-navigation button {
            flex: 1 1 0;
            min-width: 0;
            margin: 0;
            box-sizing: border-box;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 48px;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
        }

        @media (max-width: 992px) {
            .register-container {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }
            .animation-container {
                min-height: 180px;
                padding: 20px;
            }
            .form-container {
                padding: 20px;
                height: auto;
                min-height: 60vh;
            }
        }

        @media (max-width: 576px) {
            .form-container {
                padding: 10px;
            }
            .form-header h2 {
                font-size: 20px;
            }
            .form-group input,
            .form-group select {
                padding: 8px 8px;
                font-size: 14px;
                height: 40px;
            }
            .submit-btn, .btn-secondary {
                padding: 10px;
                font-size: 14px;
                height: 40px;
            }
            .step-indicator {
                flex-direction: column;
                gap: 8px;
                padding: 0;
            }
            .step-label {
                font-size: 12px;
            }
            .animation-container img {
                max-width: 180px;
            }
            .step-navigation {
                flex-direction: column;
                gap: 10px;
                width: 100%;
            }
            .step-navigation button {
                width: 100%;
                margin: 0;
            }
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
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
                <h2>Patient Registration</h2>
                <p>Complete your registration in three simple steps</p>
            </div>
            
            <div class="form-content">
                <div class="step-indicator">
                    <div class="step <?= $_SESSION['step'] == 1 ? 'active' : ($_SESSION['step'] > 1 ? 'completed' : '') ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Basic Info</div>
                    </div>
                    <div class="step <?= $_SESSION['step'] == 2 ? 'active' : ($_SESSION['step'] > 2 ? 'completed' : '') ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">ID Verification</div>
                    </div>
                    <div class="step <?= $_SESSION['step'] == 3 ? 'active' : '' ?>">
                        <div class="step-number">3</div>
                        <div class="step-label">Complete</div>
                    </div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="message error-message">
                        <i class="bi bi-exclamation-triangle-fill" style="color:#c62828; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="message success-message">
                        <i class="bi bi-check-circle-fill" style="color:#2e7d32; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($_SESSION['step'] == 1): ?>
                    <!-- Step 1: Basic Information -->
                    <form method="POST">
                        <input type="hidden" name="step" value="1">
                        
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <input type="text" name="firstName" id="firstName" required placeholder="Enter your first name">
                        </div>

                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <input type="text" name="lastName" id="lastName" required placeholder="Enter your last name">
                        </div>

                        <div class="form-group">
                            <label for="middleInitial">Middle Initial</label>
                            <input type="text" name="middleInitial" id="middleInitial" placeholder="Enter your middle name">
                        </div>

                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" name="dob" id="dob" placeholder="YYYY-MM-DD">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" name="email" id="email" required placeholder="Enter your email address">
                        </div>

                        <button type="submit" class="submit-btn">Save and Continue</button>
                    </form>

                <?php elseif ($_SESSION['step'] == 2): ?>
                    <!-- Step 2: ID Verification -->
                    <form method="POST" enctype="multipart/form-data" id="idUploadForm">
                        <input type="hidden" name="step" value="2">
                        
                        <div class="upload-area" onclick="document.getElementById('idUpload').click()">
                            <i class="bi bi-cloud-upload"></i>
                            <p>Click to upload your ID</p>
                            <p>Supported formats: JPEG, PNG</p>
                            <input type="file" name="idUpload" id="idUpload" accept="image/jpeg,image/png" style="display: none;" required>
                        </div>

                        <div class="step-navigation">
                            <button type="button" class="btn-secondary" onclick="window.location.href='register.php?step=1'">Back to Step 1</button>
                            <button type="submit" class="submit-btn">Verify ID</button>
                        </div>
                    </form>

                <?php elseif ($_SESSION['step'] == 3): ?>
                    <!-- Step 3: Complete Registration -->
                    <form method="POST">
                        <input type="hidden" name="step" value="3">
                        
                        <div class="form-group">
                            <label for="studentID">Patient ID *</label>
                            <input type="text" name="studentID" id="studentID" required readonly placeholder="Auto-generated Patient ID">
                        </div>

                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select name="gender" id="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="contactNumber">Contact Number *</label>
                            <input type="text" name="contactNumber" id="contactNumber" required placeholder="Enter your contact number">
                        </div>

                        <div class="form-group">
                            <label for="parentGuardian">Parent/Guardian</label>
                            <input type="text" name="parentGuardian" id="parentGuardian" placeholder="Enter parent/guardian name">
                        </div>

                        <div class="form-group">
                            <label for="parentContact">Parent Contact</label>
                            <input type="text" name="parentContact" id="parentContact" placeholder="Enter parent/guardian contact">
                        </div>

                        <div class="form-group">
                            <label for="password">Password *</label>
                            <div class="password-container">
                                <input type="password" name="password" id="password" required placeholder="Create a password"
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

                        <div class="step-navigation">
                            <button type="button" class="btn-secondary" onclick="window.location.href='register.php?step=2'">Back to ID Verification</button>
                            <button type="submit" class="submit-btn">Complete Registration</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
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
            setTimeout(() => {
                requirements.classList.remove('show');
            }, 200);
        }

        function validatePassword() {
            const password = document.getElementById('password').value;
            const requirements = {
                length: password.length >= 8,
                case: /[A-Z]/.test(password) && /[a-z]/.test(password),
                numsym: /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)
            };
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

        document.addEventListener('DOMContentLoaded', function() {
            const idUploadForm = document.getElementById('idUploadForm');
            const idUpload = document.getElementById('idUpload');
            const uploadArea = document.querySelector('.upload-area');
            
            if (idUploadForm) {
                idUploadForm.addEventListener('submit', function(e) {
                    if (!idUpload.files.length) {
                        e.preventDefault();
                        alert('Please select an ID image to upload.');
                        return;
                    }
                    
                    const file = idUpload.files[0];
                    const allowedTypes = ['image/jpeg', 'image/png'];
                    
                    if (!allowedTypes.includes(file.type)) {
                        e.preventDefault();
                        alert('Please upload a JPEG or PNG image.');
                        return;
                    }
                    
                    if (file.size > 5 * 1024 * 1024) { // 5MB limit
                        e.preventDefault();
                        alert('File size should not exceed 5MB.');
                        return;
                    }
                });
            }
            
            if (uploadArea && idUpload) {
                uploadArea.addEventListener('click', function() {
                    idUpload.click();
                });
                
                idUpload.addEventListener('change', function() {
                    if (this.files.length) {
                        const fileName = this.files[0].name;
                        const fileInfo = uploadArea.querySelector('p');
                        if (fileInfo) {
                            fileInfo.textContent = 'Selected file: ' + fileName;
                        }
                    }
                });
            }

            // Patient ID auto-generation
            function pad(num, size) {
                let s = num + "";
                while (s.length < size) s = "0" + s;
                return s;
            }

            function generatePatientID() {
                // Use today's date as default
                let date = new Date();
                // If there's a birth date, use that
                const dobInput = document.getElementById('dob');
                if (dobInput && dobInput.value) {
                    date = new Date(dobInput.value);
                }
                const yyyy = date.getFullYear();
                const mm = pad(date.getMonth() + 1, 2);
                const dd = pad(date.getDate(), 2);
                const dateStr = `${yyyy}${mm}${dd}`;
                // Generate a random 4-digit number
                const unique = pad(Math.floor(1000 + Math.random() * 9000), 4);
                return `PT-${dateStr}-${unique}`;
            }

            // Set Patient ID on page load and when DOB changes
            function setPatientID() {
                const studentID = document.getElementById('studentID');
                if (studentID) {
                    studentID.value = generatePatientID();
                }
            }

            setPatientID();

            const dobInput = document.getElementById('dob');
            if (dobInput) {
                dobInput.addEventListener('change', setPatientID);
            }

            // Initialize validation state on page load
            validatePassword();
        });
    </script>
</body>
</html>

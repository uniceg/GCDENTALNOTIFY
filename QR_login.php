<?php
require_once 'session_helper.php';
require_once 'db_connection.php';

// Initialize error message variable
$error_message = "";

// Process QR code data if received
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['studentID'])) {
    $studentID = trim($_POST['studentID']);
    
    if (empty($studentID)) {
        $error_message = "Invalid QR code data.";
    } else {
        // Prepare SQL statement to get student data by ID
        $stmt = $conn->prepare("SELECT studentID, name, email FROM students WHERE studentID = ?");
        
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $studentID);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Update last login timestamp
                $updateStmt = $conn->prepare("UPDATE students SET lastLogin = NOW() WHERE studentID = ?");
                $updateStmt->bind_param("s", $studentID);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Extract first and last name from the full name
                $nameParts = explode(' ', $user['name']);
                $firstName = $nameParts[0] ?? '';
                $lastName = count($nameParts) > 1 ? end($nameParts) : '';
                
                // Create user data array for session
                $sessionData = [
                    'FirstName' => $firstName,
                    'LastName' => $lastName,
                    'fullName' => $user['name'],
                    'email' => $user['email']
                ];
                
                // Initialize session
                initializeSession($studentID, $sessionData);
                
                // Redirect directly to home page (skip OTP for QR login)
                header("Location: studentHome.php");
                exit();
            } else {
                $error_message = "Student ID not found. Please try again or login with your credentials.";
            }
            
            $stmt->close();
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Login - Gordon College Dental Clinic</title>
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
            background: linear-gradient(135deg, #29cb3e 0%, #00700f 100%);
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
            background: rgba(41, 203, 62, 0.8);
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
                rgba(0, 112, 15, 0) 0%,
                rgba(0, 112, 15, 0.2) 50%,
                rgba(0, 112, 15, 0) 100%);
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
            color: #00700f;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .form-header p {
            color: #666;
            font-size: 14px;
        }
        
        .camera-container {
            width: 100%;
            height: 300px;
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            margin-bottom: 25px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        
        #reader {
            width: 100% !important;
            height: 100% !important;
            border-radius: 10px;
        }
        
        #reader video {
            width: 100% !important;
            height: auto !important;
            max-height: 100% !important;
            object-fit: cover !important;
            border-radius: 10px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 14px;
            margin-top: 10px;
            background: #00700f;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .submit-btn:hover {
            background: #29cb3e;
        }
        
        .submit-btn-secondary {
            background: #6c757d;
            margin-top: 10px;
        }
        
        .submit-btn-secondary:hover {
            background: #5a6268;
        }
        
        .file-input-container {
            margin-top: 20px;
            text-align: center;
        }
        
        .file-input-box {
            border: 2px dashed #ddd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-input-box:hover {
            border-color: #011f4b;
        }
        
        .file-input-box i {
            font-size: 30px;
            color: #011f4b;
            margin-bottom: 10px;
        }
        
        .file-input-box p {
            color: #666;
            font-size: 14px;
        }
        
        .file-input {
            display: none;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background-color: #ddd;
        }
        
        .divider-text {
            padding: 0 10px;
            color: #666;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-option {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .back-option a {
            color: #011f4b;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }
        
        /* Firebase loading */
        #loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #00700f;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive media queries */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
            }
            .animation-container {
                padding: 20px;
                max-height: 200px;
            }
            .animation-container::after {
                display: none;
            }
            .form-container {
                padding: 30px;
            }
            .camera-container {
                height: 250px;
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
            .form-header p {
                font-size: 13px;
            }
            .submit-btn {
                padding: 10px;
                font-size: 14px;
            }
            .camera-container {
                height: 200px;
                margin-bottom: 15px;
            }
        }
    </style>
    
    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore-compat.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.4/html5-qrcode.min.js"></script>
</head>
<body>
    <!-- Login form -->
    <div class="login-container">
        <div class="animation-container">
            <dotlottie-player 
                src="https://lottie.host/6ac48b13-5220-4620-8afb-e1b298002c01/F086AZFmmo.lottie" 
                background="transparent" 
                speed="1" 
                style="width: 300px; height: 300px;" 
                loop 
                autoplay
            ></dotlottie-player>
        </div>
        <div class="form-container">
            <div class="form-header">
                <h2>QR Code Login</h2>
                <p>Access your Gordon College Dental Clinic account</p>
            </div>
            
            <div class="error-message" style="display:<?php echo !empty($error_message) ? 'block' : 'none'; ?>; background: #fbeaea; color: #c0392b; border: 1px solid #f5c6cb; padding: 12px; border-radius: 8px; font-size: 14px; text-align: center; margin-bottom: 12px;">
                <?php if (!empty($error_message)): ?>
                    <i class="bi bi-exclamation-triangle-fill" style="color:#c0392b; margin-right:7px; font-size:1.2em; vertical-align:middle;"></i>
                    <?php echo $error_message; ?>
                <?php endif; ?>
            </div>
            
            <p style="text-align:center; margin-bottom:20px; color:#666;">Position your student ID QR code in front of the camera</p>
            
            <!-- QR Scanner container -->
            <div class="camera-container" id="reader"></div>
            
            <!-- Camera control buttons -->
            <button id="startButton" class="submit-btn">
                <i class="bi bi-camera-fill"></i> Start Camera
            </button>
            
            <button id="stopButton" class="submit-btn submit-btn-secondary" style="display:none;">
                <i class="bi bi-stop-fill"></i> Stop Camera
            </button>
            
            <div style="text-align:center; margin:15px 0; position:relative;">
                <div style="display:flex; align-items:center; justify-content:center;">
                    <div style="flex-grow:1; height:1px; background:#ddd;"></div>
                    <div style="margin:0 15px; color:#666; font-size:14px;">OR</div>
                    <div style="flex-grow:1; height:1px; background:#ddd;"></div>
                </div>
            </div>
            
            <!-- QR code image upload option -->
            <div style="margin-bottom:20px; text-align:center;">
                <div id="fileUploadBox" style="border:2px dashed #ddd; border-radius:10px; padding:20px; cursor:pointer; transition:all 0.3s ease;">
                    <i class="bi bi-upload" style="font-size:30px; color:#00700f; display:block; margin-bottom:10px;"></i>
                    <p style="color:#666; margin:0;">Upload QR code image</p>
                </div>
                <input type="file" id="qrFileInput" style="display:none;" accept="image/*">
            </div>
            
            <!-- Hidden form to submit the QR data -->
            <form id="qrForm" method="POST" action="QR_login.php" style="display:none;">
                <input type="hidden" name="studentID" id="studentID">
            </form>
            
            <!-- Hidden container for file scanning -->
            <div id="reader-temp" style="display:none;"></div>
            
            <div class="back-option" style="text-align: center; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <a href="login.php" style="color: #00700f; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid #ddd; border-radius: 8px; transition: all 0.3s ease;">
                    <i class="bi bi-person-fill" style="color:#00700f;"></i>
                    <span>Login with Credentials</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Include Lottie script -->    
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    
    <script>
        // Initialize QR Scanner
        const html5QrCode = new Html5Qrcode("reader");
        const startButton = document.getElementById('startButton');
        const stopButton = document.getElementById('stopButton');
        const qrForm = document.getElementById('qrForm');
        const studentIDField = document.getElementById('studentID');
        
        // QR Code scanning configuration
        const qrConfig = { fps: 10, qrbox: { width: 250, height: 250 } };
        
        // Start camera scanning
        startButton.addEventListener('click', function() {
            // Start scanner with facing mode environment (rear camera if available)
            html5QrCode.start(
                { facingMode: "environment" }, 
                qrConfig,
                onScanSuccess,
                onScanFailure
            )
            .then(() => {
                startButton.style.display = 'none';
                stopButton.style.display = 'block';
            })
            .catch(err => {
                alert('Error starting camera: ' + err);
            });
        });
        
        // Stop camera scanning
        stopButton.addEventListener('click', function() {
            html5QrCode.stop()
            .then(() => {
                startButton.style.display = 'block';
                stopButton.style.display = 'none';
            });
        });
        
        // Handle successful QR scan
        function onScanSuccess(decodedText) {
            // Stop the scanner
            html5QrCode.stop().then(() => {
                startButton.style.display = 'block';
                stopButton.style.display = 'none';
                
                try {
                    // Check if it's JSON (for backward compatibility)
                    const jsonData = JSON.parse(decodedText);
                    if (jsonData.studentID) {
                        studentIDField.value = jsonData.studentID;
                        qrForm.submit();
                    } else {
                        throw new Error("Invalid QR code format");
                    }
                } catch (e) {
                    // If not JSON, assume it's just a student ID string
                    // Clean the text (remove spaces and non-alphanumeric chars)
                    const cleanStudentID = decodedText.replace(/[^a-zA-Z0-9]/g, '').trim();
                    studentIDField.value = cleanStudentID;
                    qrForm.submit();
                }
            });
        }
        
        // Handle QR scan failures (just log them, don't show to user)
        function onScanFailure(error) {
            console.log(`QR scanning error: ${error}`);
            // No need to show the error to the user
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
            
            // Connect file upload box to file input
            const fileUploadBox = document.getElementById('fileUploadBox');
            const qrFileInput = document.getElementById('qrFileInput');
            
            // When clicking upload box, trigger file input
            fileUploadBox.addEventListener('click', function() {
                qrFileInput.click();
            });
            
            // Add visual feedback for drag/hover states
            fileUploadBox.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#00700f';
                this.style.backgroundColor = 'rgba(41, 203, 62, 0.1)';
            });
            
            fileUploadBox.addEventListener('dragleave', function() {
                this.style.borderColor = '#ddd';
                this.style.backgroundColor = 'transparent';
            });
            
            fileUploadBox.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#ddd';
                this.style.backgroundColor = 'transparent';
                
                if (e.dataTransfer.files.length > 0) {
                    qrFileInput.files = e.dataTransfer.files;
                    handleQRFile(e.dataTransfer.files[0]);
                }
            });
            
            // Process the selected QR code image
            qrFileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    handleQRFile(this.files[0]);
                }
            });
            
            // Function to handle QR code image processing
            function handleQRFile(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        // Use Html5QrCode to decode the image
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.width = this.width;
                        canvas.height = this.height;
                        context.drawImage(this, 0, 0, this.width, this.height);
                        const imageData = canvas.toDataURL('image/jpeg');
                        
                        // Create a file reader instance to decode the image
                        try {
                            // We need to create a temporary QR scanner instance
                            const qrCodeReader = new Html5Qrcode("reader-temp");
                            
                            // We'll use the scan file API instead
                            qrCodeReader.scanFile(file, true)
                                .then(decodedText => {
                                    // Success handling - similar to camera code
                                    try {
                                        // Check if it's JSON (for backward compatibility)
                                        const jsonData = JSON.parse(decodedText);
                                        if (jsonData.studentID) {
                                            studentIDField.value = jsonData.studentID;
                                            qrForm.submit();
                                        } else {
                                            throw new Error("Invalid QR code format");
                                        }
                                    } catch (e) {
                                        // If not JSON, assume it's just a student ID string
                                        // Clean the text (remove spaces and non-alphanumeric chars)
                                        const cleanStudentID = decodedText.replace(/[^a-zA-Z0-9]/g, '').trim();
                                        studentIDField.value = cleanStudentID;
                                        qrForm.submit();
                                    }
                                })
                                .catch(error => {
                                    alert('Could not recognize a QR code in this image. Please try another image or use the camera scanner.');
                                });
                        } catch (error) {
                            alert('Error processing image: ' + error);
                        }
                    };
                    img.src = e.target.result;
                };
                reader.onerror = function() {
                    alert('Error reading file.');
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>

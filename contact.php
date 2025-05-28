<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message_content = $_POST['message'] ?? '';
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'medicalclinicnotify@gmail.com';
        $mail->Password = 'owqsmmcggbhwnxgs'; // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SSL instead of TLS
        $mail->Port = 465; // SSL port
        $mail->Timeout = 60;
        $mail->CharSet = 'UTF-8';

        // Additional settings to improve deliverability
        $mail->XMailer = ' ';
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            )
        );

        // Recipients
        $mail->setFrom('medicalclinicnotify@gmail.com', 'Medical Clinic Notify+');
        $mail->addAddress('medicalclinicnotify@gmail.com', 'Medical Clinic Notify+');
        $mail->addReplyTo($email, $name);

        // Handle file attachment
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $fileType = $_FILES['attachment']['type'];
            
            if (in_array($fileType, $allowed)) {
                $mail->addAttachment(
                    $_FILES['attachment']['tmp_name'],
                    $_FILES['attachment']['name']
                );
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Contact Form: $subject";
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { 
                        font-family: 'Segoe UI', Arial, sans-serif; 
                        line-height: 1.6; 
                        color: #333;
                        margin: 0;
                        padding: 0;
                        background-color: #f5f5f5;
                    }
                    .container { 
                        max-width: 600px; 
                        margin: 20px auto; 
                        background: #ffffff;
                        border-radius: 10px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        overflow: hidden;
                    }
                    .header { 
                        background: linear-gradient(135deg, #011f4b 0%, #024351 100%);
                        color: white; 
                        padding: 30px 20px;
                        text-align: center;
                    }
                    .header h2 {
                        margin: 0;
                        font-size: 24px;
                        font-weight: 600;
                    }
                    .content { 
                        padding: 30px 20px;
                        background: #ffffff;
                    }
                    .message-box {
                        background: #f8f9fa;
                        border-left: 4px solid #011f4b;
                        padding: 20px;
                        margin: 20px 0;
                        border-radius: 0 5px 5px 0;
                    }
                    .info-section {
                        margin-bottom: 25px;
                    }
                    .info-section h3 {
                        color: #011f4b;
                        font-size: 18px;
                        margin: 0 0 10px 0;
                        font-weight: 600;
                    }
                    .info-section p {
                        margin: 5px 0;
                        color: #555;
                    }
                    .footer { 
                        background: #f8f9fa;
                        padding: 20px;
                        text-align: center;
                        font-size: 12px;
                        color: #666;
                        border-top: 1px solid #eee;
                    }
                    .highlight {
                        color: #011f4b;
                        font-weight: 500;
                    }
                    .divider {
                        height: 1px;
                        background: #eee;
                        margin: 20px 0;
                    }
                    .file-upload {
                        position: relative;
                        margin-bottom: 15px;
                    }
                    .file-upload-input {
                        width: 100%;
                        padding: 12px 16px;
                        background: #f8f9fa;
                        border: 2px solid #e0e0e0;
                        border-radius: 12px;
                        font-size: 14px;
                        transition: all 0.3s ease;
                        cursor: pointer;
                        color: #2c3e50;
                        font-family: 'Poppins', sans-serif;
                    }
                    .file-upload-input:hover {
                        border-color: #4a90e2;
                        background: #f0f7ff;
                        box-shadow: 0 2px 8px rgba(74, 144, 226, 0.1);
                    }
                    .file-upload-input::file-selector-button {
                        padding: 8px 16px;
                        margin-right: 16px;
                        border: none;
                        border-radius: 8px;
                        background: #4a90e2;
                        color: white;
                        font-weight: 500;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        font-family: 'Poppins', sans-serif;
                    }
                    .file-upload-input::file-selector-button:hover {
                        background: #357abd;
                        transform: translateY(-1px);
                    }
                    .file-upload small {
                        display: block;
                        margin-top: 6px;
                        color: #7f8c8d;
                        font-size: 12px;
                    }
                    .file-upload-btn {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 10px;
                        padding: 12px 20px;
                        background: linear-gradient(to right, #f8f9fa, #ffffff);
                        border: 2px dashed #4a90e2;
                        border-radius: 12px;
                        color: #4a90e2;
                        font-size: 14px;
                        font-weight: 500;
                        transition: all 0.3s ease;
                        position: relative;
                        overflow: hidden;
                    }
                    .file-upload-btn::before {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: linear-gradient(45deg, rgba(74, 144, 226, 0.1), rgba(74, 144, 226, 0.05));
                        opacity: 0;
                        transition: opacity 0.3s ease;
                    }
                    .file-upload-btn:hover {
                        border-color: #357abd;
                        background: linear-gradient(to right, #f0f7ff, #ffffff);
                        transform: translateY(-1px);
                        box-shadow: 0 4px 12px rgba(74, 144, 226, 0.1);
                    }
                    .file-upload-btn:hover::before {
                        opacity: 1;
                    }
                    .file-upload-btn i {
                        font-size: 20px;
                        color: #4a90e2;
                        transition: transform 0.3s ease;
                    }
                    .file-upload-btn:hover i {
                        transform: translateY(-2px);
                    }
                    .file-preview {
                        margin-top: 12px;
                        display: none;
                        animation: slideDown 0.3s ease;
                    }
                    @keyframes slideDown {
                        from {
                            opacity: 0;
                            transform: translateY(-10px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                    .file-preview.active {
                        display: block;
                    }
                    .file-item {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        padding: 12px 16px;
                        background: #ffffff;
                        border: 1px solid #e0e0e0;
                        border-radius: 10px;
                        margin-bottom: 8px;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                        transition: all 0.3s ease;
                    }
                    .file-item:hover {
                        border-color: #4a90e2;
                        box-shadow: 0 4px 12px rgba(74, 144, 226, 0.1);
                    }
                    .file-item i {
                        font-size: 24px;
                        color: #4a90e2;
                        background: rgba(74, 144, 226, 0.1);
                        padding: 8px;
                        border-radius: 8px;
                    }
                    .file-item-name {
                        flex: 1;
                        font-size: 14px;
                        color: #2c3e50;
                        font-weight: 500;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    .file-item-size {
                        font-size: 13px;
                        color: #666;
                        background: #f8f9fa;
                        padding: 4px 8px;
                        border-radius: 6px;
                    }
                    .file-item-remove {
                        color: #dc3545;
                        cursor: pointer;
                        padding: 8px;
                        border-radius: 8px;
                        transition: all 0.2s ease;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .file-item-remove:hover {
                        background: #ffebee;
                        transform: scale(1.1);
                    }
                    .file-error {
                        color: #dc3545;
                        font-size: 13px;
                        margin-top: 8px;
                        display: none;
                        padding: 8px 12px;
                        background: #ffebee;
                        border-radius: 8px;
                        border: 1px solid #ffcdd2;
                        animation: shake 0.5s ease;
                    }
                    @keyframes shake {
                        0%, 100% { transform: translateX(0); }
                        25% { transform: translateX(-5px); }
                        75% { transform: translateX(5px); }
                    }
                    .file-upload-btn.dragover {
                        border-color: #2e7d32;
                        background: #e8f5e9;
                    }
                    .file-upload-btn.dragover i {
                        color: #2e7d32;
                    }
                    .file-type-icon {
                        width: 40px;
                        height: 40px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 8px;
                        background: rgba(74, 144, 226, 0.1);
                    }
                    .file-type-icon i {
                        font-size: 20px;
                        color: #4a90e2;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>New Contact Form Message</h2>
                    </div>
                    <div class='content'>
                        <div class='info-section'>
                            <h3>Contact Information</h3>
                            <p><span class='highlight'>From:</span> $name</p>
                            <p><span class='highlight'>Email:</span> $email</p>
                            <p><span class='highlight'>Subject:</span> $subject</p>
                        </div>
                        <div class='divider'></div>
                        <div class='info-section'>
                            <h3>Message</h3>
                            <div class='message-box'>
                                " . nl2br(htmlspecialchars($message_content)) . "
                            </div>
                        </div>
                    </div>
                    <div class='footer'>
                        <p>This message was sent from the contact form on Medical Clinic Notify+ website.</p>
                        <p>© " . date('Y') . " Medical Clinic Notify+. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

        $mail->AltBody = "New Contact Form Message\n\n" .
            "From: $name\n" .
            "Email: $email\n" .
            "Subject: $subject\n\n" .
            "Message:\n" .
            $message_content . "\n\n" .
            "This message was sent from the contact form on Medical Clinic Notify+ website.";

        $mail->send();
        $message = "Thank you for your message! We will get back to you soon.";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Sorry, there was an error sending your message. Please try again later.";
        $message_type = "error";
        error_log("Contact form error details:");
        error_log("Error message: " . $e->getMessage());
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        error_log("Debug trace: " . $e->getTraceAsString());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Medical Clinic Notify+</title>
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
            height: 100vh;
            background: #ffffff;
            padding: 0;
            overflow: hidden;
        }
        .contact-container {
            max-width: 100%;
            height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            background: #fff;
            box-shadow: none;
        }
        .contact-info {
            padding: 30px;
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .contact-info h2 {
            font-size: 22px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .info-item i {
            font-size: 18px;
            margin-right: 12px;
            margin-top: 3px;
        }
        .info-item div {
            flex: 1;
        }
        .info-item h3 {
            font-size: 15px;
            margin-bottom: 4px;
            font-weight: 500;
        }
        .info-item p {
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.4;
        }
        .contact-form {
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100vh;
            background: #fff;
        }
        .contact-form h2 {
            color: #2c3e50;
            font-size: 22px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 15px;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 13px;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 13px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            height: 40px;
            font-family: 'Poppins', sans-serif;
        }
        .form-group textarea {
            height: 100px;
            resize: none;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4a90e2;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
            height: 40px;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.2);
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
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
        .social-links {
            margin-top: 20px;
        }
        .social-links h3 {
            font-size: 15px;
            margin-bottom: 12px;
            font-weight: 500;
        }
        .social-icons {
            display: flex;
            gap: 12px;
        }
        .social-icons a {
            color: white;
            font-size: 18px;
            transition: all 0.3s ease;
        }
        .social-icons a:hover {
            transform: translateY(-3px);
            opacity: 0.8;
        }
        @media (max-width: 768px) {
            body {
                height: auto;
                overflow-y: auto;
            }
            .contact-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .contact-info,
            .contact-form {
                padding: 25px 20px;
                height: auto;
            }
            .contact-info {
                order: 2;
            }
            .contact-form {
                order: 1;
            }
        }
        @media (max-width: 480px) {
            .contact-info h2,
            .contact-form h2 {
                font-size: 20px;
            }
            .info-item h3 {
                font-size: 14px;
            }
            .info-item p {
                font-size: 12px;
            }
            .form-group label {
                font-size: 12px;
            }
            .form-group input,
            .form-group textarea {
                padding: 8px 10px;
                font-size: 12px;
                height: 36px;
            }
            .form-group textarea {
                height: 80px;
            }
            .submit-btn {
                padding: 10px;
                font-size: 13px;
                height: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="contact-container">
        <div class="contact-info">
            <h2>Get in Touch</h2>
            <div class="info-item">
                <i class="bi bi-geo-alt"></i>
                <div>
                    <h3>Address</h3>
                    <p>Rizal Ave, Olongapo, 2200 Zambales</p>
                </div>
            </div>
            <div class="info-item">
                <i class="bi bi-envelope"></i>
                <div>
                    <h3>Email</h3>
                    <p>medicalclinicnotify@gmail.com</p>
                </div>
            </div>
            <div class="info-item">
                <i class="bi bi-telephone"></i>
                <div>
                    <h3>Phone</h3>
                    <p>+63 9207689036</p>
                </div>
            </div>
            <div class="info-item">
                <i class="bi bi-clock"></i>
                <div>
                    <h3>Working Hours</h3>
                    <p>Monday - Friday: 8:00 AM - 5:00 PM<br>
                    Saturday: 9:00 AM - 2:00 PM</p>
                </div>
            </div>
            <div class="social-links">
                <h3>Follow Us</h3>
                <div class="social-icons">
                    <a href="https://www.facebook.com/" target="_blank"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.twitter.com/" target="_blank"><i class="bi bi-twitter"></i></a>
                    <a href="https://www.instagram.com/" target="_blank"><i class="bi bi-instagram"></i></a>
                    <a href="https://www.gmail.com/" target="_blank"><i class="bi bi-envelope"></i></a>
                </div>
            </div>
        </div>
        <div class="contact-form">
            <h2>Send us a Message</h2>
            <?php
            if ($message) {
                echo '<div class="message ' . $message_type . '">' . $message . '</div>';
            }
            ?>
            <form method="POST" action="contact.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Your Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Your Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required></textarea>
                </div>
                <div class="form-group">
                    <div class="file-upload">
                        <input type="file" id="attachment" name="attachment" class="file-upload-input" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                    </div>
                </div>
                <button type="submit" class="submit-btn">Send Message</button>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const fileUpload = document.querySelector('.file-upload');
            const fileInput = document.querySelector('.file-upload-input');
            const filePreview = document.querySelector('.file-preview');
            const fileError = document.querySelector('.file-error');
            const uploadBtn = document.querySelector('.file-upload-btn');
            
            // Maximum file size (5MB)
            const MAX_FILE_SIZE = 5 * 1024 * 1024;
            // Allowed file types
            const ALLOWED_TYPES = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            
            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadBtn.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadBtn.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadBtn.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight(e) {
                uploadBtn.classList.add('dragover');
            }
            
            function unhighlight(e) {
                uploadBtn.classList.remove('dragover');
            }
            
            uploadBtn.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const file = dt.files[0];
                handleFile(file);
            }
            
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) handleFile(file);
            });
            
            function handleFile(file) {
                if (!file) return;
                
                // Reset error message
                fileError.style.display = 'none';
                
                // Validate file size
                if (file.size > MAX_FILE_SIZE) {
                    fileError.textContent = 'File size exceeds 5MB limit';
                    fileError.style.display = 'block';
                    fileInput.value = '';
                    return;
                }
                
                // Validate file type
                if (!ALLOWED_TYPES.includes(file.type)) {
                    fileError.textContent = 'Invalid file type. Allowed: JPG, PNG, GIF, PDF, DOC, DOCX';
                    fileError.style.display = 'block';
                    fileInput.value = '';
                    return;
                }
                
                // Get file icon based on type
                const fileIcon = getFileIcon(file.type);
                
                // Show file preview
                filePreview.innerHTML = `
                    <div class="file-item">
                        <div class="file-type-icon">
                            <i class="bi ${fileIcon}"></i>
                        </div>
                        <span class="file-item-name">${file.name}</span>
                        <span class="file-item-size">${formatFileSize(file.size)}</span>
                        <i class="bi bi-x-circle file-item-remove"></i>
                    </div>
                `;
                filePreview.classList.add('active');
                
                // Add remove functionality
                const removeBtn = filePreview.querySelector('.file-item-remove');
                removeBtn.addEventListener('click', function() {
                    fileInput.value = '';
                    filePreview.classList.remove('active');
                });
            }
            
            function getFileIcon(type) {
                if (type.startsWith('image/')) return 'bi-image';
                if (type === 'application/pdf') return 'bi-file-pdf';
                if (type.includes('word')) return 'bi-file-word';
                return 'bi-file-earmark';
            }
            
            // Format file size
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        });
    </script>
</body>
</html> 
<?php
include 'config.php';
session_start();

if (!isset($_SESSION['studentID'])) {
    header('location:login.php');
    exit;
}

$student_id = $_SESSION['studentID'];

// Check if student is already verified
$query = "SELECT id_verified FROM students WHERE studentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        .verification-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .verification-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .verification-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .verification-header p {
            color: #666;
        }

        .upload-area {
            border: 2px dashed #ccc;
            padding: 30px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }

        .upload-area i {
            font-size: 48px;
            color: #007bff;
            margin-bottom: 15px;
        }

        .upload-area p {
            margin: 10px 0;
            color: #666;
        }

        .preview-container {
            margin-top: 20px;
            display: none;
        }

        .preview-container img {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .btn-verify {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%;
        }

        .btn-verify:hover {
            background-color: #0056b3;
        }

        .btn-verify:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .verification-result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            display: none;
        }

        .verification-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .verification-failure {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .verification-details {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 14px;
        }

        .loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }

        .loading i {
            font-size: 24px;
            color: #007bff;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-header">
            <h1>ID Verification</h1>
            <p>Please upload a clear photo of your valid ID for verification</p>
        </div>

        <?php if ($student['id_verified']): ?>
            <div class="verification-result verification-success">
                <i class="bi bi-check-circle-fill"></i> Your ID has been verified successfully.
            </div>
        <?php else: ?>
            <div class="upload-area" id="uploadArea">
                <i class="bi bi-cloud-upload"></i>
                <p>Click or drag and drop your ID image here</p>
                <p class="text-muted">Supported formats: JPEG, PNG (Max size: 5MB)</p>
                <input type="file" id="idFile" accept="image/jpeg,image/png" style="display: none;">
            </div>

            <div class="preview-container" id="previewContainer">
                <img id="imagePreview" src="#" alt="ID Preview">
                <button class="btn-verify" id="verifyBtn" disabled>Verify ID</button>
            </div>

            <div class="loading" id="loading">
                <i class="bi bi-arrow-repeat"></i>
                <p>Verifying your ID...</p>
            </div>

            <div class="verification-result" id="verificationResult"></div>
        <?php endif; ?>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const idFile = document.getElementById('idFile');
        const previewContainer = document.getElementById('previewContainer');
        const imagePreview = document.getElementById('imagePreview');
        const verifyBtn = document.getElementById('verifyBtn');
        const loading = document.getElementById('loading');
        const verificationResult = document.getElementById('verificationResult');

        // Handle drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#007bff';
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = '#ccc';
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#ccc';
            const file = e.dataTransfer.files[0];
            handleFile(file);
        });

        // Handle click to upload
        uploadArea.addEventListener('click', () => {
            idFile.click();
        });

        idFile.addEventListener('change', (e) => {
            const file = e.target.files[0];
            handleFile(file);
        });

        function handleFile(file) {
            if (file) {
                // Validate file type
                if (!['image/jpeg', 'image/png'].includes(file.type)) {
                    alert('Please upload a JPEG or PNG image.');
                    return;
                }

                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size too large. Maximum size is 5MB.');
                    return;
                }

                // Preview image
                const reader = new FileReader();
                reader.onload = (e) => {
                    imagePreview.src = e.target.result;
                    previewContainer.style.display = 'block';
                    verifyBtn.disabled = false;
                };
                reader.readAsDataURL(file);
            }
        }

        // Handle verification
        verifyBtn.addEventListener('click', async () => {
            const file = idFile.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('id_file', file);

            loading.style.display = 'block';
            verifyBtn.disabled = true;

            try {
                const response = await fetch('verify_id.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                loading.style.display = 'none';
                verificationResult.style.display = 'block';

                if (result.success) {
                    verificationResult.className = 'verification-result verification-success';
                    verificationResult.innerHTML = `
                        <i class="bi bi-check-circle-fill"></i> ${result.message}
                        <div class="verification-details">
                            <p>Match Score: ${result.match_score}%</p>
                            <p>Extracted Text: ${result.extracted_text}</p>
                            <p>Student Name: ${result.student_name}</p>
                        </div>
                    `;
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    verificationResult.className = 'verification-result verification-failure';
                    verificationResult.innerHTML = `
                        <i class="bi bi-x-circle-fill"></i> ${result.message}
                        <div class="verification-details">
                            <p>Match Score: ${result.match_score}%</p>
                            <p>Extracted Text: ${result.extracted_text}</p>
                            <p>Student Name: ${result.student_name}</p>
                        </div>
                    `;
                    verifyBtn.disabled = false;
                }
            } catch (error) {
                loading.style.display = 'none';
                verificationResult.style.display = 'block';
                verificationResult.className = 'verification-result verification-failure';
                verificationResult.innerHTML = `
                    <i class="bi bi-x-circle-fill"></i> An error occurred during verification.
                `;
                verifyBtn.disabled = false;
            }
        });
    </script>
</body>
</html> 
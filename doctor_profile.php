<?php
session_start();
include 'config.php';

// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit();
}

// Get the logged-in doctor's unique ID from session
$doctorID = $_SESSION['doctor_id'];

// Verify doctor exists and get their information
$doctor_verify_sql = "SELECT * FROM doctors WHERE DoctorID = ? AND Status = 'Active'";
$doctor_verify_stmt = $conn->prepare($doctor_verify_sql);
$doctor_verify_stmt->bind_param("s", $doctorID);
$doctor_verify_stmt->execute();
$doctor_verify_result = $doctor_verify_stmt->get_result();

if ($doctor_verify_result->num_rows === 0) {
    session_destroy();
    header("Location: doctor_login.php?error=invalid_session");
    exit();
}

$doctorInfo = $doctor_verify_result->fetch_assoc();

// Debug: Check if ProfilePhoto column exists
try {
    $check_column = $conn->query("SHOW COLUMNS FROM doctors LIKE 'ProfilePhoto'");
    if ($check_column->num_rows == 0) {
        // Add the column if it doesn't exist
        $conn->query("ALTER TABLE doctors ADD COLUMN ProfilePhoto VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) {
    error_log("Database column check error: " . $e->getMessage());
}

// Get doctor details - ONLY for the logged-in doctor
$sql = "SELECT * FROM doctors WHERE DoctorID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $doctorID);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

// Handle form submission for profile update
$updateMessage = '';
$updateStatus = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $specialization = $_POST['specialization'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    // Handle file upload
    $profilePhoto = $doctor['ProfilePhoto']; // Keep existing photo by default
    
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/doctor_photos/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                $updateMessage = "Failed to create upload directory.";
                $updateStatus = "danger";
            }
        }
        
        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            $updateMessage = "Upload directory is not writable.";
            $updateStatus = "danger";
        } else {
            $fileExtension = strtolower(pathinfo($_FILES['profilePhoto']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                // Check file size (max 5MB)
                if ($_FILES['profilePhoto']['size'] <= 5 * 1024 * 1024) {
                    $fileName = 'doctor_' . $doctorID . '_' . time() . '.' . $fileExtension;
                    $uploadPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $uploadPath)) {
                        // Delete old photo if exists and it's not the default
                        if ($doctor['ProfilePhoto'] && file_exists($doctor['ProfilePhoto']) && strpos($doctor['ProfilePhoto'], 'placeholder') === false) {
                            unlink($doctor['ProfilePhoto']);
                        }
                        $profilePhoto = $uploadPath;
                    } else {
                        $updateMessage = "Error uploading photo. Error code: " . $_FILES['profilePhoto']['error'];
                        $updateStatus = "danger";
                    }
                } else {
                    $updateMessage = "Photo size must be less than 5MB.";
                    $updateStatus = "danger";
                }
            } else {
                $updateMessage = "Only JPG, JPEG, PNG & GIF files are allowed.";
                $updateStatus = "danger";
            }
        }
    }
    
    // Update profile if no upload errors - ONLY for the logged-in doctor
    if (empty($updateMessage)) {
        $update_sql = "UPDATE doctors SET 
                        FirstName = ?, 
                        LastName = ?, 
                        Specialization = ?, 
                        Email = ?, 
                        ContactNumber = ?,
                        ProfilePhoto = ?
                      WHERE DoctorID = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssssss", $firstName, $lastName, $specialization, $email, $phone, $profilePhoto, $doctorID);
        
        if ($update_stmt->execute()) {
            $updateMessage = "Profile updated successfully!";
            $updateStatus = "success";
            
            // Update session variables with new information
            $_SESSION['doctor_name'] = $firstName . ' ' . $lastName;
            $_SESSION['doctor_email'] = $email;
            $_SESSION['doctor_specialization'] = $specialization;
            
            // Refresh doctor data after update
            $stmt->execute();
            $result = $stmt->get_result();
            $doctor = $result->fetch_assoc();
        } else {
            $updateMessage = "Error updating profile: " . $conn->error;
            $updateStatus = "danger";
        }
    }
}

// Get current page for navbar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Profile - Dr. <?= htmlspecialchars($doctor['FirstName']) ?> - Medical Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    /* Exact same CSS variables as student_viewer.php */
    :root {
        --primary: #2e7d32;
        --primary-light: #60ad5e;
        --primary-dark: #1b5e20;
        --text-dark: #263238;
        --text-medium: #546e7a;
        --text-light: #78909c;
        --surface-light: #f5f7fa;
        --surface-medium: #e1e5eb;
        --shadow-sm: 0 2px 6px rgba(0,0,0,0.05);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --radius-sm: 6px;
        --radius-md: 12px;
    }
    
    body {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        background-color: var(--surface-light);
        color: var(--text-dark);
        overflow-x: hidden;
    }
    
    /* Exact same sidebar styles as student_viewer.php */
    .sidebar {
        width: 250px;
        background: var(--primary);
        transition: all 0.3s ease;
        position: fixed;
        height: 100vh;
        z-index: 100;
        box-shadow: var(--shadow-md);
        top: 0;
        left: 0;
    }
    
    .sidebar-collapsed {
        transform: translateX(-250px);
    }
    
    .sidebar-header {
        padding: 20px;
        text-align: center;
    }

    .sidebar-logo {
        width: 70%;
        max-width: 140px;
        transition: transform 0.3s;
    }

    .sidebar-logo:hover {
        transform: scale(1.05);
    }

    .sidebar-divider {
        border-bottom: 1px solid var(--primary-light);
        margin: 8px 20px;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        padding: 14px 18px;
        color: white;
        text-decoration: none;
        transition: all 0.2s ease;
        font-weight: 500;
        font-size: 1rem;
    }

    .sidebar-menu a:hover {
        background: var(--primary-light);
        padding-left: 22px;
    }

    .sidebar-menu a.active {
        background: var(--primary-light);
        border-right: 4px solid white;
    }

    .sidebar-menu i {
        margin-right: 12px;
        font-size: 1.25rem;
        min-width: 24px;
        text-align: center;
    }

    /* Exact same header styles as student_viewer.php */
    .header {
        background: white;
        padding: 15px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: var(--shadow-sm);
        position: sticky;
        top: 0;
        z-index: 90;
        transition: all 0.3s ease;
        margin-left: 0;
        min-height: 70px;
    }
    
    .header-expanded {
        margin-left: 250px;
    }
    
    .header-title {
        font-weight: 600;
        font-size: 1.4rem;
        color: var(--primary);
        margin: 0;
    }
    
    .header-actions {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .toggle-sidebar {
        background: none;
        border: none;
        color: var(--primary);
        cursor: pointer;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        margin-right: 15px;
    }
    
    .toggle-sidebar:hover {
        background-color: var(--surface-light);
    }
    
    .toggle-sidebar i {
        font-size: 1.5rem;
    }
    
    /* Main content styles */
    .main-content {
        margin-left: 0;
        padding: 20px;
        transition: all 0.3s ease;
        background-color: var(--surface-light);
    }
    
    .main-expanded {
        margin-left: 250px;
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 99;
        display: none;
    }
    
    /* Page header styles */
    .page-header {
        background: white;
        padding: 20px;
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
        box-shadow: var(--shadow-sm);
        border-left: 4px solid var(--primary);
    }

    .page-header h1 {
        color: var(--primary);
        margin-bottom: 5px;
        font-size: 1.8rem;
        font-weight: 600;
    }

    .page-header p {
        color: var(--text-medium);
        margin: 0;
        font-size: 0.95rem;
    }
    
    /* Card styles */
    .card {
        border: none;
        box-shadow: var(--shadow-sm);
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
        background: white;
    }
    
    .card-header {
        background: #f8f9fa;
        border-bottom: 1px solid var(--surface-medium);
        font-weight: 600;
        color: var(--text-dark);
        padding: 15px 20px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    /* Form styles */
    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 8px;
    }

    .form-control {
        border: 1px solid var(--surface-medium);
        border-radius: var(--radius-sm);
        padding: 10px 15px;
        font-size: 1rem;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.15);
        outline: none;
    }
    
    /* Button styles */
    .btn-primary {
        background-color: var(--primary);
        border-color: var(--primary);
        border-radius: var(--radius-sm);
        font-weight: 500;
        padding: 10px 20px;
        transition: all 0.2s;
    }
    
    .btn-primary:hover {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
    }

    .btn-outline-primary {
        border-color: var(--primary);
        color: var(--primary);
        background: transparent;
        border-radius: var(--radius-sm);
        font-weight: 500;
        padding: 8px 16px;
        transition: all 0.2s;
    }

    .btn-outline-primary:hover {
        background-color: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .btn-outline-secondary {
        color: #666;
        border-color: #ddd;
        padding: 10px 25px;
        font-weight: 500;
        border-radius: var(--radius-sm);
        transition: all 0.2s;
    }

    .btn-outline-secondary:hover {
        background-color: #f5f5f5;
        color: #333;
    }

    /* Profile specific styles */
    .profile-container {
        background: white;
        border-radius: var(--radius-sm);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    .profile-header {
        background: #f8f9fa;
        padding: 25px;
        border-bottom: 1px solid var(--surface-medium);
    }

    .profile-content {
        padding: 25px;
    }

    .profile-picture-container {
        position: relative;
        display: inline-block;
    }

    .profile-picture {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        box-shadow: var(--shadow-sm);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .profile-picture:hover {
        transform: scale(1.05);
        box-shadow: var(--shadow-md);
    }

    .profile-picture-upload {
        position: absolute;
        bottom: 0;
        right: 0;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 3px solid white;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .profile-picture-upload:hover {
        background: var(--primary-dark);
        transform: scale(1.1);
    }

    .profile-photo-preview {
        max-width: 200px;
        max-height: 200px;
        border-radius: var(--radius-sm);
        margin-top: 10px;
        box-shadow: var(--shadow-sm);
        object-fit: cover;
    }

    .info-group {
        margin-bottom: 20px;
    }

    .info-label {
        font-weight: 600;
        color: var(--text-medium);
        margin-bottom: 5px;
        font-size: 0.9rem;
    }

    .info-value {
        font-size: 1.1rem;
        color: var(--text-dark);
    }

    .divider {
        height: 1px;
        background-color: var(--surface-medium);
        margin: 25px 0;
    }

    .alert {
        border-radius: var(--radius-sm);
        padding: 15px 20px;
        margin-bottom: 20px;
    }

    /* Photo upload validation messages */
    .photo-error {
        color: #dc3545;
        font-size: 0.85rem;
        margin-top: 5px;
    }

    .photo-success {
        color: #28a745;
        font-size: 0.85rem;
        margin-top: 5px;
    }

    /* File input and photo upload styles */
    .file-input-wrapper {
        position: relative;
        overflow: hidden;
        display: inline-block;
    }

    .file-input-wrapper input[type=file] {
        position: absolute;
        left: -9999px;
    }

    .file-input-label {
        background: var(--primary);
        color: white;
        padding: 10px 20px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
    }

    .file-input-label:hover {
        background: var(--primary-dark);
    }

    .photo-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    .remove-photo-btn {
        background: #dc3545;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }

    .remove-photo-btn:hover {
        background: #c82333;
    }

    .profile-name {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 5px;
    }

    .profile-specialization {
        font-size: 1.2rem;
        color: var(--text-medium);
        margin-bottom: 15px;
    }

    /* Responsive styles */
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-250px);
        }
        
        .header, .main-content {
            margin-left: 0 !important;
        }
    }
    
    @media (max-width: 768px) {
        .main-content {
            padding: 15px;
        }
        
        .page-header h1 {
            font-size: 1.5rem;
        }
        
        .profile-header,
        .profile-content {
            padding: 20px;
        }
        
        .profile-picture {
            width: 100px;
            height: 100px;
        }
        
        .profile-name {
            font-size: 1.5rem;
        }
    }
    
    @media (max-width: 576px) {
        .main-content {
            padding: 10px;
        }
        
        .page-header,
        .profile-header,
        .profile-content {
            padding: 15px;
        }
        
        .btn-primary,
        .btn-outline-secondary {
            width: 100%;
            margin-bottom: 10px;
        }
    }
  </style>
</head>
<body>
  <!-- Exact same sidebar as student_viewer.php -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="img/GCLINIC.png" alt="Medical Clinic Logo" class="sidebar-logo">
    </div>
    <div class="sidebar-divider"></div>
    <ul class="sidebar-menu">
        <li><a href="doctor_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'doctor_dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
        </a></li>
        <li><a href="doctor_student.php" class="<?= basename($_SERVER['PHP_SELF']) === 'doctor_student.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-check"></i> <span>My Appointments</span>
        </a></li>
        <li><a href="student_viewer.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_viewer.php' ? 'active' : '' ?>">
            <i class="bi bi-person-lines-fill"></i> <span>My Patients</span>
        </a></li>
        <li><a href="doctor_profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'doctor_profile.php' ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i> <span>My Profile</span>
        </a></li>
        <li><a href="doctor_schedule.php" class="<?= basename($_SERVER['PHP_SELF']) === 'doctor_schedule.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar3"></i> <span>My Schedule</span>
        </a></li>
        <li><a href="doctor_report.php" class="<?= basename($_SERVER['PHP_SELF']) === 'doctor_report.php' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i> <span>My Reports</span>
        </a></li>
    </ul>
    
    <!-- Add logout section at bottom of sidebar -->
    <div class="sidebar-divider" style="margin-top: auto;"></div>
    <ul class="sidebar-menu">
        <li><a href="doctor_login.php" class="logout-link" onclick="return confirmLogout()">
            <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
        </a></li>
    </ul>
  </aside>

  <!-- Simplified header without action buttons -->
  <header class="header header-expanded" id="header">
    <div class="d-flex align-items-center">
        <button class="toggle-sidebar" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="header-title">My Profile - Dr. <?= htmlspecialchars($doctor['FirstName'] . ' ' . $doctor['LastName']) ?></h1>
    </div>
    
    <div class="header-actions">
        <!-- Removed all header action buttons -->
    </div>
  </header>

  <!-- Sidebar overlay -->
  <div id="sidebarOverlay" class="sidebar-overlay"></div>

  <!-- Main content with exact same structure -->
  <main class="main-content main-expanded" id="mainContent">
    <div class="container-fluid">
        <!-- Page header -->
        <div class="page-header">
            <h1><i class="bi bi-person-circle me-2"></i>My Profile</h1>
            <p>Dr. <?= htmlspecialchars($doctor['FirstName'] . ' ' . $doctor['LastName']) ?> - Manage your personal information and account settings</p>
        </div>
        
        <?php if (!empty($updateMessage)): ?>
        <div class="alert alert-<?= $updateStatus ?> alert-dismissible fade show" role="alert">
            <?= $updateMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="profile-container">
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <div class="profile-picture-container">
                            <!-- If you don't have a default image, you can use this as a fallback -->
                            <?php 
                                $profilePhotoSrc = 'https://via.placeholder.com/120x120/2e7d32/white?text=Dr'; // Placeholder
                                if (!empty($doctor['ProfilePhoto']) && file_exists($doctor['ProfilePhoto'])) {
                                    $profilePhotoSrc = $doctor['ProfilePhoto'];
                                }
                            ?>
                            <img src="<?= htmlspecialchars($profilePhotoSrc) ?>" 
                                 alt="Doctor Avatar" 
                                 class="profile-picture" 
                                 id="profilePictureDisplay"
                                 onclick="document.getElementById('profilePhotoInput').click();">
                            <div class="profile-picture-upload" onclick="document.getElementById('profilePhotoInput').click();">
                                <i class="bi bi-camera"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Click to change photo</small>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <h1 class="profile-name"><?= htmlspecialchars($doctor['FirstName'] . ' ' . $doctor['LastName']) ?></h1>
                        <p class="profile-specialization"><?= htmlspecialchars($doctor['Specialization']) ?></p>
                        <p class="text-muted"><i class="bi bi-person-badge me-2"></i> ID: <?= htmlspecialchars($doctor['DoctorID']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="profile-content">
                <!-- View Mode -->
                <div id="viewMode">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">First Name</div>
                                <div class="info-value"><?= htmlspecialchars($doctor['FirstName']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">Last Name</div>
                                <div class="info-value"><?= htmlspecialchars($doctor['LastName']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Specialization</div>
                        <div class="info-value"><?= htmlspecialchars($doctor['Specialization']) ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?= htmlspecialchars($doctor['Email']) ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Contact Number</div>
                        <div class="info-value"><?= htmlspecialchars($doctor['ContactNumber']) ?></div>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button class="btn btn-primary" id="editProfileBtn">
                            <i class="bi bi-pencil-square me-2"></i>Edit Profile
                        </button>
                    </div>
                </div>
                
                <!-- Edit Mode -->
                <div id="editMode" style="display: none;">
                    <form class="profile-form" method="POST" action="" enctype="multipart/form-data">
                        <!-- Photo Upload Section -->
                        <div class="mb-4">
                            <label class="form-label">Profile Photo</label>
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <img src="<?= htmlspecialchars($profilePhotoSrc) ?>" 
                                         alt="Current Photo" 
                                         class="profile-photo-preview" 
                                         id="photoPreview">
                                </div>
                                <div class="col-md-8">
                                    <div class="file-input-wrapper">
                                        <input type="file" 
                                               id="profilePhotoInput" 
                                               name="profilePhoto" 
                                               accept="image/*"
                                               style="display: none;"
                                               onchange="previewPhoto(this)">
                                        <label for="profilePhotoInput" class="btn btn-outline-primary file-input-label">
                                            <i class="bi bi-camera me-2"></i>Choose New Photo
                                        </label>
                                    </div>
                                    <div class="photo-actions mt-2" id="photoActions" style="display: none;">
                                        <button type="button" class="btn btn-sm btn-danger remove-photo-btn" onclick="removePhoto()">
                                            <i class="bi bi-trash me-1"></i> Remove Photo
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Supported formats: JPG, JPEG, PNG, GIF<br>
                                            Maximum size: 5MB
                                        </small>
                                    </div>
                                    <div id="photoMessage"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" value="<?= htmlspecialchars($doctor['FirstName']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" value="<?= htmlspecialchars($doctor['LastName']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" value="<?= htmlspecialchars($doctor['Specialization']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($doctor['Email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($doctor['ContactNumber']) ?>">
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="button" class="btn btn-outline-secondary" id="cancelEditBtn">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary" name="update_profile">
                                <i class="bi bi-check-lg me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Exact same JavaScript as student_viewer.php -->
  <script>
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const sidebar = document.getElementById('sidebar');
    const header = document.getElementById('header');
    const mainContent = document.querySelector('.main-content');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Toggle Sidebar
    function toggleSidebar() {
        const isSidebarCollapsed = sidebar.classList.contains('sidebar-collapsed');
        
        if (isSidebarCollapsed) {
            sidebar.classList.remove('sidebar-collapsed');
            header.classList.add('header-expanded');
            mainContent.classList.add('main-expanded');
            sidebarOverlay.style.display = 'none';
        } else {
            sidebar.classList.add('sidebar-collapsed');
            header.classList.remove('header-expanded');
            mainContent.classList.remove('main-expanded');
            
            if (window.innerWidth <= 992) {
                sidebarOverlay.style.display = 'block';
            }
        }
    }
    
    // Set initial state based on screen size
    function setInitialState() {
        if (window.innerWidth <= 992) {
            sidebar.classList.add('sidebar-collapsed');
            header.classList.remove('header-expanded');
            mainContent.classList.remove('main-expanded');
        } else {
            sidebar.classList.remove('sidebar-collapsed');
            header.classList.add('header-expanded');
            mainContent.classList.add('main-expanded');
        }
    }
    
    // Toggle sidebar event
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Handle overlay click
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            if (!sidebar.classList.contains('sidebar-collapsed')) {
                toggleSidebar();
            }
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 992) {
            sidebar.classList.add('sidebar-collapsed');
            header.classList.remove('header-expanded');
            mainContent.classList.remove('main-expanded');
        }
    });
    
    // Print function
    window.printPage = function() {
        window.print();
    }
    
    // Set initial state
    setInitialState();
    
    // Profile view/edit mode toggle
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editProfileBtn = document.getElementById('editProfileBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    
    if (editProfileBtn && viewMode && editMode) {
        editProfileBtn.addEventListener('click', () => {
            viewMode.style.display = 'none';
            editMode.style.display = 'block';
        });
    }
    
    if (cancelEditBtn && viewMode && editMode) {
        cancelEditBtn.addEventListener('click', () => {
            editMode.style.display = 'none';
            viewMode.style.display = 'block';
            // Reset form if needed
            resetPhotoUpload();
        });
    }
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bootstrapAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bootstrapAlert.close();
        }, 5000);
    });
});

// Photo preview and upload functions
function previewPhoto(input) {
    const file = input.files[0];
    const preview = document.getElementById('photoPreview');
    const profileDisplay = document.getElementById('profilePictureDisplay');
    const actions = document.getElementById('photoActions');
    const message = document.getElementById('photoMessage');
    
    if (message) {
        message.innerHTML = '';
    }
    
    if (file) {
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            if (message) {
                message.innerHTML = '<div class="photo-error">Please select a valid image file (JPG, JPEG, PNG, GIF)</div>';
            }
            input.value = '';
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            if (message) {
                message.innerHTML = '<div class="photo-error">File size must be less than 5MB</div>';
            }
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
            }
            if (profileDisplay) {
                profileDisplay.src = e.target.result;
            }
            if (actions) {
                actions.style.display = 'flex';
            }
            if (message) {
                message.innerHTML = '<div class="photo-success">Photo selected successfully</div>';
            }
        };
        reader.readAsDataURL(file);
    }
}

function removePhoto() {
    const input = document.getElementById('profilePhotoInput');
    const preview = document.getElementById('photoPreview');
    const profileDisplay = document.getElementById('profilePictureDisplay');
    const actions = document.getElementById('photoActions');
    const message = document.getElementById('photoMessage');
    
    const originalSrc = '<?= htmlspecialchars($profilePhotoSrc) ?>';
    
    if (input) input.value = '';
    if (preview) preview.src = originalSrc;
    if (profileDisplay) profileDisplay.src = originalSrc;
    if (actions) actions.style.display = 'none';
    if (message) message.innerHTML = '';
}

function resetPhotoUpload() {
    removePhoto();
}
  </script>
</body>
</html>
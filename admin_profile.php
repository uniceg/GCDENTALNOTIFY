<?php 
session_start();
include 'config.php'; 

// Check if the admin is logged in
if (!isset($_SESSION['adminID'])) {
    header('Location: admin_login.php');
    exit();
}

// Fetch the logged-in admin's details
$adminID = $_SESSION['adminID'];
$query = "SELECT * FROM admins WHERE adminID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $adminID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin_data = $result->fetch_assoc();
} else {
    echo "No admin data found.";
    exit();
}

// Handle form submission for profile update
$updateMessage = '';
$updateStatus = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $adminName = $_POST['adminName'];
    $adminLastName = $_POST['adminLastName'];
    $adminMiddleInitial = $_POST['adminMiddleInitial'];
    $adminEmail = $_POST['adminEmail'];
    $contactNumber = $_POST['contactNumber'];
    
    // Handle profile photo upload
    $profilePhoto = $admin_data['profilePhoto']; // Keep current photo by default
    
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] == 0) {
        $uploadDir = 'uploads/admin_photos/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['profilePhoto']['name'], PATHINFO_EXTENSION);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($fileExtension), $allowedExtensions)) {
            // Check file size (5MB max)
            if ($_FILES['profilePhoto']['size'] <= 5000000) {
                $fileName = 'admin_' . $adminID . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $uploadPath)) {
                    // Delete old photo if exists
                    if (!empty($admin_data['profilePhoto']) && file_exists($admin_data['profilePhoto'])) {
                        unlink($admin_data['profilePhoto']);
                    }
                    $profilePhoto = $uploadPath;
                } else {
                    $updateMessage = "Error uploading profile photo.";
                    $updateStatus = "danger";
                }
            } else {
                $updateMessage = "Profile photo must be less than 5MB.";
                $updateStatus = "danger";
            }
        } else {
            $updateMessage = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            $updateStatus = "danger";
        }
    }
    
    // Update profile - ONLY for the logged-in admin
    if (empty($updateMessage)) {
        $update_sql = "UPDATE admins SET 
                        adminName = ?, 
                        adminLastName = ?, 
                        adminMiddleInitial = ?, 
                        adminEmail = ?, 
                        contactNumber = ?,
                        profilePhoto = ?
                      WHERE adminID = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssssss", $adminName, $adminLastName, $adminMiddleInitial, $adminEmail, $contactNumber, $profilePhoto, $adminID);
        
        if ($update_stmt->execute()) {
            $updateMessage = "Profile updated successfully!";
            $updateStatus = "success";
            
            // Update session variables with new information
            $_SESSION['adminName'] = $adminName;
            $_SESSION['adminLastName'] = $adminLastName;
            $_SESSION['adminEmail'] = $adminEmail;
            
            // Refresh admin data after update
            $stmt->execute();
            $result = $stmt->get_result();
            $admin_data = $result->fetch_assoc();
        } else {
            $updateMessage = "Error updating profile: " . $conn->error;
            $updateStatus = "danger";
        }
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Profile - <?= htmlspecialchars($admin_data['adminName']) ?> - Medical Clinic Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    /* Same CSS variables and styles as before */
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
    
    /* All your existing CSS styles remain the same... */
    /* (keeping the same sidebar, header, main content, card, form, button styles) */
    
    /* Add new styles for photo upload */
    .profile-photo-container {
        position: relative;
        display: inline-block;
    }
    
    .profile-picture {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 4px solid white;
        box-shadow: var(--shadow-sm);
        font-size: 3rem;
        color: white;
        overflow: hidden;
        position: relative;
    }
    
    .profile-picture img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }
    
    .photo-upload-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
        cursor: pointer;
    }
    
    .profile-photo-container:hover .photo-upload-overlay {
        opacity: 1;
    }
    
    .photo-upload-overlay i {
        color: white;
        font-size: 1.5rem;
    }
    
    .file-input-hidden {
        display: none;
    }
    
    .current-photo-info {
        margin-top: 10px;
        font-size: 0.8rem;
        color: var(--text-medium);
        text-align: center;
    }
    
    .photo-upload-help {
        font-size: 0.85rem;
        color: var(--text-medium);
        margin-top: 8px;
    }
    
    /* Sidebar styles */
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
        display: flex;
        flex-direction: column;
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

    /* Logout button styling */
    .logout-link {
        color: #ffcdd2 !important;
        transition: all 0.3s ease !important;
    }

    .logout-link:hover {
        background: #d32f2f !important;
        color: white !important;
        padding-left: 22px !important;
    }

    .logout-link i {
        color: #ffcdd2 !important;
    }

    .logout-link:hover i {
        color: white !important;
    }

    .sidebar-menu:last-child {
        margin-top: auto;
        padding-bottom: 20px;
    }

    /* Header styles */
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

    .btn-outline-danger {
        border-color: #dc3545;
        color: #dc3545;
        background: transparent;
        border-radius: var(--radius-sm);
        font-weight: 500;
        padding: 8px 16px;
        transition: all 0.2s;
    }

    .btn-outline-danger:hover {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }

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

    .profile-name {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 5px;
    }

    .profile-role {
        font-size: 1.2rem;
        color: var(--text-medium);
        margin-bottom: 15px;
    }

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
            font-size: 2.5rem;
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
  <!-- Sidebar (same as before) -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="img/GCLINIC.png" alt="Medical Clinic Logo" class="sidebar-logo">
    </div>
    <div class="sidebar-divider"></div>
    <ul class="sidebar-menu">
        <li><a href="admin_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin_dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
        </a></li>
                <li><a href="admin_profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin_profile.php' ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i> <span>My Profile</span>
        </a></li>
        <li><a href="staff_management.php" class="<?= basename($_SERVER['PHP_SELF']) === 'staff_management.php' ? 'active' : '' ?>">
            <i class="bi bi-person-lines-fill"></i> <span>Staff Management</span>
        </a></li>
        <li><a href="admin_report.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin_report.php' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i> <span>Reports</span>
        </a></li>
    </ul>
    
    <div class="sidebar-divider" style="margin-top: auto;"></div>
    <ul class="sidebar-menu">
        <li><a href="admin_login.php" class="logout-link" onclick="return confirmLogout()">
            <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
        </a></li>
    </ul>
  </aside>

  <!-- Header -->
  <header class="header header-expanded" id="header">
    <div class="d-flex align-items-center">
        <button class="toggle-sidebar" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="header-title">My Profile - <?= htmlspecialchars($admin_data['adminName'] . ' ' . $admin_data['adminLastName']) ?></h1>
    </div>
    
    <div class="header-actions">
        <!-- Removed all header action buttons -->
    </div>
  </header>

  <!-- Sidebar overlay -->
  <div id="sidebarOverlay" class="sidebar-overlay"></div>

  <!-- Main content with updated profile photo section -->
  <main class="main-content main-expanded" id="mainContent">
    <div class="container-fluid">
        <!-- Page header -->
        <div class="page-header">
            <h1><i class="bi bi-person-circle me-2"></i>My Profile</h1>
            <p><?= htmlspecialchars($admin_data['adminName'] . ' ' . $admin_data['adminLastName']) ?> - Manage your personal information and account settings</p>
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
                        <div class="profile-photo-container">
                            <div class="profile-picture">
                                <?php if (!empty($admin_data['profilePhoto']) && file_exists($admin_data['profilePhoto'])): ?>
                                    <img src="<?= htmlspecialchars($admin_data['profilePhoto']) ?>" 
                                         alt="Admin Profile Photo" 
                                         id="profilePhotoPreview">
                                <?php else: ?>
                                    <i class="bi bi-person-gear" id="defaultIcon"></i>
                                <?php endif; ?>
                                <div class="photo-upload-overlay" onclick="document.getElementById('profilePhotoInput').click()">
                                    <i class="bi bi-camera"></i>
                                </div>
                            </div>
                        </div>
                        <div class="current-photo-info">
                            <small class="text-muted">Administrator</small>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <h1 class="profile-name"><?= htmlspecialchars($admin_data['adminName'] . ' ' . $admin_data['adminLastName']) ?></h1>
                        <p class="profile-role">System Administrator</p>
                        <p class="text-muted"><i class="bi bi-person-badge me-2"></i> ID: <?= htmlspecialchars($admin_data['adminID']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="profile-content">
                <!-- View Mode (same as before) -->
                <div id="viewMode">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">First Name</div>
                                <div class="info-value"><?= htmlspecialchars($admin_data['adminName']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">Last Name</div>
                                <div class="info-value"><?= htmlspecialchars($admin_data['adminLastName']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">Middle Initial</div>
                                <div class="info-value"><?= htmlspecialchars($admin_data['adminMiddleInitial'] ?: 'N/A') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">Position</div>
                                <div class="info-value">System Administrator</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?= htmlspecialchars($admin_data['adminEmail']) ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Contact Number</div>
                        <div class="info-value"><?= htmlspecialchars($admin_data['contactNumber'] ?: 'Not provided') ?></div>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button class="btn btn-primary" id="editProfileBtn">
                            <i class="bi bi-pencil-square me-2"></i>Edit Profile
                        </button>
                    </div>
                </div>
                
                <!-- Updated Edit Mode with photo upload -->
                <div id="editMode" style="display: none;">
                    <form class="profile-form" method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label">Profile Photo</label>
                            <div class="text-center mb-3">
                                <div class="profile-photo-container d-inline-block">
                                    <div class="profile-picture">
                                        <?php if (!empty($admin_data['profilePhoto']) && file_exists($admin_data['profilePhoto'])): ?>
                                            <img src="<?= htmlspecialchars($admin_data['profilePhoto']) ?>" 
                                                 alt="Admin Profile Photo" 
                                                 id="editPhotoPreview">
                                        <?php else: ?>
                                            <i class="bi bi-person-gear" id="editDefaultIcon"></i>
                                        <?php endif; ?>
                                        <div class="photo-upload-overlay" onclick="document.getElementById('profilePhotoInput').click()">
                                            <i class="bi bi-camera"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="file" 
                                   class="file-input-hidden" 
                                   id="profilePhotoInput" 
                                   name="profilePhoto" 
                                   accept="image/*"
                                   onchange="previewPhoto(this)">
                            <div class="text-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('profilePhotoInput').click()">
                                    <i class="bi bi-camera me-1"></i>Choose Photo
                                </button>
                                <?php if (!empty($admin_data['profilePhoto'])): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm ms-2" onclick="removePhoto()">
                                        <i class="bi bi-trash me-1"></i>Remove
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="photo-upload-help text-center">
                                <small>JPG, JPEG, PNG or GIF (Max 5MB)</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="adminName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="adminName" name="adminName" value="<?= htmlspecialchars($admin_data['adminName']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="adminLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="adminLastName" name="adminLastName" value="<?= htmlspecialchars($admin_data['adminLastName']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="adminMiddleInitial" class="form-label">Middle Initial</label>
                            <input type="text" class="form-control" id="adminMiddleInitial" name="adminMiddleInitial" value="<?= htmlspecialchars($admin_data['adminMiddleInitial']) ?>" maxlength="1">
                        </div>
                        
                        <div class="mb-3">
                            <label for="adminEmail" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="adminEmail" name="adminEmail" value="<?= htmlspecialchars($admin_data['adminEmail']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactNumber" class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" id="contactNumber" name="contactNumber" value="<?= htmlspecialchars($admin_data['contactNumber']) ?>">
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
  
  <!-- Updated JavaScript with photo functions -->
  <script>
document.addEventListener('DOMContentLoaded', function() {
    // All your existing JavaScript functions remain the same...
    const sidebar = document.getElementById('sidebar');
    const header = document.getElementById('header');
    const mainContent = document.querySelector('.main-content');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            if (!sidebar.classList.contains('sidebar-collapsed')) {
                toggleSidebar();
            }
        });
    }
    
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 992) {
            sidebar.classList.add('sidebar-collapsed');
            header.classList.remove('header-expanded');
            mainContent.classList.remove('main-expanded');
        }
    });
    
    window.printPage = function() {
        window.print();
    }
    
    window.confirmLogout = function() {
        return confirm('Are you sure you want to logout?');
    }
    
    setInitialState();
    
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
        });
    }
    
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bootstrapAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bootstrapAlert.close();
        }, 5000);
    });
});

// Photo preview function
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const editPreview = document.getElementById('editPhotoPreview');
            const editDefaultIcon = document.getElementById('editDefaultIcon');
            const headerPreview = document.getElementById('profilePhotoPreview');
            const headerDefaultIcon = document.getElementById('defaultIcon');
            
            // Update edit mode preview
            if (editPreview) {
                editPreview.src = e.target.result;
                editPreview.style.display = 'block';
            } else {
                // Create new img element if doesn't exist
                const newImg = document.createElement('img');
                newImg.id = 'editPhotoPreview';
                newImg.src = e.target.result;
                newImg.alt = 'Profile Photo Preview';
                newImg.style.width = '100%';
                newImg.style.height = '100%';
                newImg.style.objectFit = 'cover';
                newImg.style.borderRadius = '50%';
                
                const container = editDefaultIcon.parentElement;
                container.appendChild(newImg);
            }
            
            if (editDefaultIcon) {
                editDefaultIcon.style.display = 'none';
            }
            
            // Update header preview
            if (headerPreview) {
                headerPreview.src = e.target.result;
            } else if (headerDefaultIcon) {
                const newHeaderImg = document.createElement('img');
                newHeaderImg.id = 'profilePhotoPreview';
                newHeaderImg.src = e.target.result;
                newHeaderImg.alt = 'Profile Photo';
                newHeaderImg.style.width = '100%';
                newHeaderImg.style.height = '100%';
                newHeaderImg.style.objectFit = 'cover';
                newHeaderImg.style.borderRadius = '50%';
                
                const headerContainer = headerDefaultIcon.parentElement;
                headerContainer.appendChild(newHeaderImg);
                headerDefaultIcon.style.display = 'none';
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Remove photo function
function removePhoto() {
    const editPreview = document.getElementById('editPhotoPreview');
    const editDefaultIcon = document.getElementById('editDefaultIcon');
    const headerPreview = document.getElementById('profilePhotoPreview');
    const headerDefaultIcon = document.getElementById('defaultIcon');
    const fileInput = document.getElementById('profilePhotoInput');
    
    // Reset file input
    fileInput.value = '';
    
    // Show default icons
    if (editDefaultIcon) {
        editDefaultIcon.style.display = 'flex';
    }
    if (headerDefaultIcon) {
        headerDefaultIcon.style.display = 'flex';
    }
    
    // Hide/remove preview images
    if (editPreview) {
        editPreview.remove();
    }
    if (headerPreview) {
        headerPreview.remove();
    }
}
  </script>
</body>
</html>

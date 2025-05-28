<?php
include 'config.php';
session_start();

if (!isset($_SESSION['studentID'])) {
    header('location:login.php');
    exit;
}

$student_id = trim($_SESSION['studentID']);
// Debug: Print the session ID
echo "<!-- Debug: Session Student ID: " . htmlspecialchars($student_id) . " -->";

// Fix the query to use prepared statement and proper column name
$query = "SELECT * FROM students WHERE StudentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// Debug: Print the query result
echo "<!-- Debug: Query executed. Number of rows: " . $result->num_rows . " -->";

// Fetch notifications
$notificationQuery = "SELECT * FROM notifications WHERE studentID = ? AND is_read = FALSE ORDER BY created_at DESC";
$notificationStmt = $conn->prepare($notificationQuery);
$notificationStmt->bind_param("s", $student_id);
$notificationStmt->execute();
$notifications = $notificationStmt->get_result();

if ($result) {
    $student_data = $result->fetch_assoc();
    if (!$student_data) {
        echo "<!-- Debug: No student data found for ID: " . htmlspecialchars($student_id) . " -->";
        echo "No student data found for this ID.";
    } else {
        echo "<!-- Debug: Student data found: " . print_r($student_data, true) . " -->";
    }
} else {
    echo "<!-- Debug: Query error: " . $conn->error . " -->";
    echo "Error fetching student data: " . $conn->error;
    exit;
}

// Debug: Print the final student data
echo "<!-- Debug: Final student_data array: " . print_r($student_data, true) . " -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2e7d32;
            --primary-light: #60ad5e;
            --primary-dark: #1b5e20;
            --secondary: #1565c0;
            --secondary-light: #5e92f3;
            --secondary-dark: #003c8f;
            --text-dark: #263238;
            --text-medium: #546e7a;
            --text-light: #78909c;
            --surface-light: #f5f7fa;
            --surface-medium: #e1e5eb;
            --surface-dark: #cfd8dc;
            --danger: #d32f2f;
            --success: #388e3c;
            --warning: #f57c00;
            --shadow-sm: 0 2px 6px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 20px;
        }
        
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--surface-light);
            color: var(--text-dark);
        }
        
        /* Layout */
        .app-container {
            display: grid;
            min-height: 100vh;
            grid-template-columns: auto 1fr;
            grid-template-rows: auto 1fr;
            grid-template-areas: 
                "sidebar header"
                "sidebar main";
        }
        
        /* Sidebar */
        .sidebar {
            grid-area: sidebar;
            width: 260px;
            background: var(--primary);
            transition: all 0.3s ease;
            position: fixed;
            height: 100vh;
            z-index: 100;
            box-shadow: var(--shadow-md);
        }
        
        .sidebar-collapsed {
            transform: translateX(-260px);
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
        }
        
        .sidebar-logo {
            width: 70%;
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
            padding: 14px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background: var(--primary-light);
            padding-left: 30px;
        }
        
        .sidebar-menu a.active {
            background: var(--primary-light);
            border-right: 4px solid white;
        }
        
        .sidebar-menu i {
            margin-right: 12px;
            font-size: 1.2rem;
            transition: transform 0.2s;
        }
        
        .sidebar-menu a:hover i {
            transform: translateX(3px);
        }
        
        /* Header */
        .header {
            grid-area: header;
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
        }
        
        .header-expanded {
            margin-left: 260px;
        }
        
        .header-title {
            font-weight: 600;
            font-size: 1.4rem;
            color: var(--primary);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .toggle-sidebar:hover {
            background: var(--surface-light);
        }
        
        .welcome-message {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            color: var(--text-medium);
        }
        
        .welcome-message i {
            color: var(--primary);
        }
        
        .notifications {
            position: relative;
        }
        
        .notification-btn {
            background: none;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--primary);
            transition: all 0.2s;
            position: relative;
        }
        
        .notification-btn:hover {
            background: var(--surface-light);
        }
        
        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Main Content */
        .main-content {
            grid-area: main;
            padding: 0;
            transition: all 0.3s ease;
            min-height: 100vh;
            background: #f6faff;
        }
        
        .main-expanded {
            margin-left: 260px;
        }
        
        /* Profile Styles */
        .profile-header-bar {
            background: #fff;
            display: flex;
            align-items: center;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .profile-photo-container {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            overflow: hidden;
            background: #e3f0fc;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid var(--primary);
            flex-shrink: 0;
        }
        
        .profile-photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-photo-container i {
            font-size: 3rem;
            color: var(--primary);
        }
        
        .profile-header-info {
            margin-left: 15px;
            flex-grow: 1;
        }
        
        .profile-header-info h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .profile-id-badge {
            display: inline-block;
            background: #e8f5e9;
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            margin-top: 3px;
        }
        
        .edit-profile-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .edit-profile-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding: 15px;
        }
        
        .info-section {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .info-header {
            background: var(--primary);
            color: white;
            padding: 10px 15px;
            font-weight: 500;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            padding: 15px;
        }
        
        .info-item {
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 5px;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 3px;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 500;
            color: #333;
            word-break: break-word;
        }
        
        /* Make Medical Information section full width */
        .info-section:last-child {
            grid-column: 1 / -1;
        }
        
        /* Notification Dropdown */
        .notification-dropdown {
            position: absolute;
            top: 45px;
            right: 0;
            width: 320px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow: hidden;
            display: none;
            animation: fadeInDown 0.3s;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .notification-header {
            background: var(--primary);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--surface-light);
            display: flex;
            align-items: flex-start;
            gap: 15px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .notification-item:hover {
            background: var(--surface-light);
        }
        
        .notification-icon {
            color: var(--primary);
            background: var(--surface-light);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex-grow: 1;
        }
        
        .notification-message {
            margin-bottom: 5px;
            font-size: 0.9rem;
            color: var(--text-dark);
            line-height: 1.4;
        }
        
        .notification-date {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .no-notifications {
            padding: 30px 20px;
            text-align: center;
            color: var(--text-light);
        }
        
        /* Modals */
        .modal-content {
            border-radius: var(--radius-md);
            border: none;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        
        .modal-header {
            background: var(--primary);
            color: white;
            border-bottom: none;
            padding: 20px 25px;
        }
        
        .modal-title {
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        /* Change Password Modal */
        .change-password-modal-content {
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(1,31,75,0.18);
            padding: 0;
        }
        
        #changePasswordModal .modal-header {
            border-bottom: none;
        }
        
        #changePasswordModal .modal-title {
            font-size: 1.3rem;
        }
        
        #changePasswordModal .form-label {
            font-weight: 500;
        }
        
        #changePasswordModal .form-control {
            border-radius: 8px;
            font-size: 1rem;
        }
        
        #changePasswordModal .input-group .btn {
            border-radius: 0 8px 8px 0;
        }
        
        #changePasswordModal .btn-primary {
            border-radius: 8px;
            font-weight: 500;
            font-size: 1.08rem;
            background: #2563eb;
            border: none;
        }
        
        #changePasswordModal .btn-primary:disabled {
            background: #e0e0e0;
            color: #aaa;
            border: none;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-260px);
            }
            
            .header, .main-content {
                margin-left: 0 !important;
            }
            
            .app-container {
                grid-template-columns: 1fr;
            }
            
            .toggle-sidebar {
                display: flex;
            }
            
            .profile-content {
                grid-template-columns: 1fr;
            }
            
            .profile-header-bar {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
            
            .profile-header-info {
                margin: 10px 0;
            }
            
            .edit-profile-btn {
                margin-top: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .header {
                padding: 15px;
            }
            
            .header-title {
                font-size: 1.2rem;
            }
            
            .welcome-message span {
                display: none;
            }
            
            .main-content {
                padding: 0;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .notification-dropdown {
                width: 100%;
                max-width: 320px;
                right: -15px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="img/GCLINIC.png" alt="Medical Clinic Logo" class="sidebar-logo">
            </div>
            <div class="sidebar-divider"></div>
            <ul class="sidebar-menu">
                <li><a href="studentDashboard.php"><i class="bi bi-house"></i> Home</a></li>
                <li><a href="studentHome.php" class="active"><i class="bi bi-person"></i> Profile</a></li>
                <li><a href="appointment.php"><i class="bi bi-journal-plus"></i> Schedule Appointment</a></li>
                <li><a href="schedule.php"><i class="bi bi-journal-arrow-down"></i> My Appointments</a></li>
                <li><a href="services.php"><i class="bi bi-journal-album"></i> Service</a></li>
                <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Header -->
        <header class="header header-expanded" id="header">
            <div class="d-flex align-items-center">
                <button class="toggle-sidebar me-3" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="header-title"></h1>
            </div>
            
            <div class="header-actions">
                <div class="welcome-message">
                    <i class="bi bi-person-circle"></i>
                    <span>Welcome, <?php echo htmlspecialchars($student_data['firstName'] ?? 'Student'); ?></span>
                </div>
                
                <div class="notifications">
                    <button class="notification-btn" id="notificationBtn">
                        <i class="bi bi-bell-fill"></i>
                        <?php if ($notifications->num_rows > 0): ?>
                            <span class="notification-count"><?php echo $notifications->num_rows; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <i class="bi bi-bell"></i> Notifications
                        </div>
                        <div class="notification-list">
                            <?php if ($notifications->num_rows > 0): ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <div class="notification-item" data-id="<?php echo $notif['notificationID']; ?>">
                                        <div class="notification-icon">
                                            <i class="bi bi-info-circle"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                            <div class="notification-date"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'] ?? '')); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-notifications">
                                    <i class="bi bi-bell-slash mb-2"></i>
                                    <p>No new notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="main-content main-expanded" id="mainContent">
            <!-- Profile Header Bar -->
            <div class="profile-header-bar">
                <div class="profile-photo-container">
                    <?php if (!empty($student_data['profilePhoto']) && file_exists($student_data['profilePhoto'])): ?>
                        <img src="<?php echo htmlspecialchars($student_data['profilePhoto']); ?>" alt="Profile Photo" class="profile-photo">
                    <?php else: ?>
                        <i class="bi bi-person-circle"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-header-info">
                    <h2><?php echo htmlspecialchars(trim(($student_data['firstName'] ?? '') . ' ' . ($student_data['lastName'] ?? ''))); ?></h2>
                    <div class="profile-id-badge"><?php echo htmlspecialchars($student_data['studentID'] ?? ''); ?></div>
                </div>
                <button type="button" class="edit-profile-btn" data-bs-toggle="modal" data-bs-target="#updateProfileModal">
                    <i class="bi bi-pencil-square"></i> Edit Profile
                </button>
            </div>

            <!-- Profile Content Grid -->
            <div class="profile-content">
                <!-- Personal Information -->
                <div class="info-section">
                    <div class="info-header">
                        <i class="bi bi-person-badge"></i> Personal Information
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">College/Program</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['course'] ?? ''); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Gender</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['gender'] ?? ''); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['address'] ?? ''); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="info-section">
                    <div class="info-header">
                        <i class="bi bi-envelope"></i> Contact Information
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['email'] ?? ''); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Alternate Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['altEmail'] ?? ''); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contact Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['contactNumber'] ?? ''); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="info-section">
                    <div class="info-header">
                        <i class="bi bi-shield-plus"></i> Emergency Information
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Parent/Guardian</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['parentGuardian'] ?? ''); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Parent Contact</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['parentContact'] ?? ''); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Emergency Contact</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['emergencyContactName'] ?? ''); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Relationship</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['emergencyContactRelationship'] ?? ''); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="info-section">
                    <div class="info-header">
                        <i class="bi bi-heart-pulse"></i> Medical Information
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Blood Type</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['bloodType'] ?? 'Not specified'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Allergies</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['allergies'] ?? 'None'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Medical Conditions</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['medicalConditions'] ?? 'None'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Medications</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_data['medications'] ?? 'None'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Update Profile Modal -->
    <div class="modal fade" id="updateProfileModal" tabindex="-1" aria-labelledby="updateProfileModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateProfileModalLabel">Update Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="update.php" id="updateProfileForm" class="needs-validation" novalidate enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($student_data['firstName'] ?? ''); ?>" required>
                                    <label for="firstName">First Name</label>
                                    <div class="invalid-feedback">Please enter first name</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($student_data['lastName'] ?? ''); ?>" required>
                                    <label for="lastName">Last Name</label>
                                    <div class="invalid-feedback">Please enter last name</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student_data['email'] ?? ''); ?>" required>
                            <label for="email">Email Address</label>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($student_data['address'] ?? ''); ?>" required>
                            <label for="address">Address</label>
                            <div class="invalid-feedback">Please enter address</div>
                        </div>

                        <div class="form-floating mb-3">
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($student_data['gender']) && $student_data['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($student_data['gender']) && $student_data['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($student_data['gender']) && $student_data['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <label for="gender">Gender</label>
                            <div class="invalid-feedback">Please select gender</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="contactNumber" name="contactNumber" value="<?php echo htmlspecialchars($student_data['contactNumber'] ?? ''); ?>" required pattern="[0-9]{11}">
                            <label for="contactNumber">Contact Number (11 digits)</label>
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="parentGuardian" name="parentGuardian" value="<?php echo htmlspecialchars($student_data['parentGuardian'] ?? ''); ?>" required>
                            <label for="parentGuardian">Parent/Guardian</label>
                            <div class="invalid-feedback">Please enter parent/guardian name</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="parentContact" name="parentContact" value="<?php echo htmlspecialchars($student_data['parentContact'] ?? ''); ?>" pattern="[0-9]{11}">
                            <label for="parentContact">Parent/Guardian Contact (11 digits)</label>
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="emergencyContactName" name="emergencyContactName" value="<?php echo htmlspecialchars($student_data['emergencyContactName'] ?? ''); ?>">
                            <label for="emergencyContactName">Emergency Contact Name</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="emergencyContactRelationship" name="emergencyContactRelationship" value="<?php echo htmlspecialchars($student_data['emergencyContactRelationship'] ?? ''); ?>">
                            <label for="emergencyContactRelationship">Emergency Contact Relationship</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="emergencyContactNumber" name="emergencyContactNumber" value="<?php echo htmlspecialchars($student_data['emergencyContactNumber'] ?? ''); ?>">
                            <label for="emergencyContactNumber">Emergency Contact Number</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="bloodType" name="bloodType" value="<?php echo htmlspecialchars($student_data['bloodType'] ?? ''); ?>">
                            <label for="bloodType">Blood Type</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="allergies" name="allergies" value="<?php echo htmlspecialchars($student_data['allergies'] ?? ''); ?>">
                            <label for="allergies">Allergies</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="medicalConditions" name="medicalConditions" value="<?php echo htmlspecialchars($student_data['medicalConditions'] ?? ''); ?>">
                            <label for="medicalConditions">Medical Conditions</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="medications" name="medications" value="<?php echo htmlspecialchars($student_data['medications'] ?? ''); ?>">
                            <label for="medications">Medications</label>
                        </div>

                        <div class="mb-3 text-center">
                            <label for="profilePhoto" class="form-label" style="font-weight:500; color:#1976d2;">Profile Photo</label>
                            <input type="file" class="form-control" id="profilePhoto" name="profilePhoto" accept="image/*">
                            <div class="form-text">Max size: 2MB. JPG, PNG only.</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content change-password-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel" style="color:#fff;">Change Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2" style="color:#d32f2f;font-size:0.98rem;font-weight:500;">
                        All fields are required. Password must be at least eight (8) characters or more
                    </div>
                    <form id="changePasswordForm" autocomplete="off">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label" style="color:#d32f2f;font-weight:500;">Current password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="currentPassword" placeholder="Password" required minlength="8">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="bi bi-eye-slash"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="newPassword" placeholder="Password" required minlength="8">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="bi bi-eye-slash"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmPassword" placeholder="Password" required minlength="8">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="bi bi-eye-slash"></i></button>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary" id="submitChangePassword" disabled>Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const sidebar = document.getElementById('sidebar');
            const header = document.getElementById('header');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            // Toggle Sidebar
            function toggleSidebar() {
                const isSidebarCollapsed = sidebar.classList.contains('sidebar-collapsed');
                
                if (isSidebarCollapsed) {
                    sidebar.classList.remove('sidebar-collapsed');
                    header.classList.add('header-expanded');
                    mainContent.classList.add('main-expanded');
                } else {
                    sidebar.classList.add('sidebar-collapsed');
                    header.classList.remove('header-expanded');
                    mainContent.classList.remove('main-expanded');
                }
            }
            
            // Set initial state based on screen size
            function setInitialState() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.add('sidebar-collapsed');
                    header.classList.remove('header-expanded');
                    mainContent.classList.remove('main-expanded');
                }
            }
            
            // Toggle sidebar event
            sidebarToggle.addEventListener('click', toggleSidebar);
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.add('sidebar-collapsed');
                    header.classList.remove('header-expanded');
                    mainContent.classList.remove('main-expanded');
                }
            });
            
            // Notification dropdown toggle
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.style.display = notificationDropdown.style.display === 'block' ? 'none' : 'block';
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                notificationDropdown.style.display = 'none';
            });
            
            // Mark notification as read
            document.querySelectorAll('.notification-item').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const notifId = this.getAttribute('data-id');
                    
                    fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'notification_id=' + encodeURIComponent(notifId)
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Remove notification from list
                        this.remove();
                        
                        // Update count
                        const countElement = document.querySelector('.notification-count');
                        if (countElement) {
                            let count = parseInt(countElement.textContent, 10);
                            if (count > 1) {
                                countElement.textContent = count - 1;
                            } else {
                                countElement.remove();
                                const noNotif = document.createElement('div');
                                noNotif.className = 'no-notifications';
                                noNotif.innerHTML = '<i class="bi bi-bell-slash mb-2"></i><p>No new notifications</p>';
                                document.querySelector('.notification-list').innerHTML = '';
                                document.querySelector('.notification-list').appendChild(noNotif);
                            }
                        }
                    });
                });
            });
            
            // Form validation 
            const updateProfileForm = document.getElementById('updateProfileForm');
            if (updateProfileForm) {
                updateProfileForm.addEventListener('submit', function(event) {
                    if (!updateProfileForm.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    updateProfileForm.classList.add('was-validated');
                });
            }
            
            // Set initial state
            setInitialState();
        });
    </script>
</body>
</html>

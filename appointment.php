<?php

include 'config.php';

session_start();

if (!isset($_SESSION['studentID'])) {
    header('location:login.php');
    exit;
}

$student_id = trim($_SESSION['studentID']);

// Fetch student data
$query = "SELECT * FROM students WHERE StudentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student_data = $result->fetch_assoc();

// Fetch notifications
$notificationQuery = "SELECT * FROM notifications WHERE studentID = ? AND is_read = FALSE ORDER BY created_at DESC";
$notificationStmt = $conn->prepare($notificationQuery);
$notificationStmt->bind_param("s", $student_id);
$notificationStmt->execute();
$notifications = $notificationStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment</title>
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
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        .main-expanded {
            margin-left: 260px;
        }
        
        /* Appointment Page */
        .appointment-card {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .appointment-header {
            background: var(--primary);
            padding: 25px 30px;
            color: white;
        }
        
        .appointment-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .appointment-header p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .appointment-body {
            padding: 30px;
        }
        
        /* Date Picker */
        .date-section {
            background: var(--surface-light);
            border-radius: var(--radius-md);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .date-section h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-section h2 i {
            color: var(--secondary);
        }
        
        .date-picker {
            background: white;
            border-radius: var(--radius-sm);
            padding: 20px;
            box-shadow: var(--shadow-sm);
        }
        
        .date-form label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-medium);
        }
        
        .date-input {
            display: block;
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            border: 1px solid var(--surface-medium);
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            transition: all 0.2s;
        }
        
        .date-input:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
            outline: none;
        }
        
        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        
        .search-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* Doctors Section */
        .doctors-section {
            margin-top: 20px;
        }
        
        .doctors-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .doctors-header h2 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .doctors-header h2 i {
            color: var(--secondary);
        }
        
        .doctors-count {
            background: var(--surface-medium);
            color: var(--text-medium);
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .doctor-card {
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            border: 1px solid var(--surface-medium);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--secondary-light);
        }
        
        .doctor-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid var(--surface-light);
        }
        
        .doctor-avatar {
            width: 70px;
            height: 70px;
            background: var(--surface-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 2rem;
            border: 2px solid var(--primary-light);
        }
        
        .doctor-info h3 {
            margin: 0 0 5px;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .doctor-specialty {
            color: var(--text-medium);
            font-size: 0.9rem;
        }
        
        .doctor-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .time-slots {
            background: var(--surface-light);
            border-radius: var(--radius-sm);
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .time-slots-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .time-slots-title {
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .time-slots-title i {
            color: var(--secondary);
        }
        
        .available-tag {
            background: var(--success);
            color: white;
            font-size: 0.75rem;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .time-slot {
            display: flex;
            align-items: center;
            background: white;
            border-radius: var(--radius-sm);
            padding: 10px 15px;
            margin-bottom: 8px;
            border: 1px solid var(--surface-medium);
        }
        
        .time-slot-icon {
            color: var(--secondary);
            margin-right: 10px;
        }
        
        .time-slot-range {
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .book-btn {
            margin-top: auto;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .book-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--text-light);
            margin-bottom: 15px;
        }
        
        .empty-state-text {
            color: var(--text-medium);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .empty-state-subtext {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        /* Loading Spinner */
        .loading-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--surface-medium);
            border-top: 4px solid var(--secondary);
            border-radius: 50%;
            margin: 0 auto 20px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        
        .modal-footer {
            border-top: 1px solid var(--surface-light);
            padding: 15px 25px;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-medium);
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 1px solid var(--surface-medium);
            border-radius: var(--radius-sm);
            padding: 10px 15px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
        }
        
        .form-control:read-only {
            background: var(--surface-light);
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
            
            .doctors-grid {
                grid-template-columns: 1fr;
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
                padding: 15px;
            }
            
            .appointment-header {
                padding: 20px;
            }
            
            .appointment-body {
                padding: 20px;
            }
            
            .date-section {
                padding: 15px;
            }
            
            .notification-dropdown {
                width: 100%;
                max-width: 320px;
                right: -15px;
            }
        }
        
        /* Make specific icons green */
        .date-section h2 .bi-calendar3,
        .doctors-header h2 .bi-person-badge {
            color: var(--primary) !important;
        }
        .search-btn .bi-search {
            color: #fff !important;
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
                <li><a href="studentHome.php"><i class="bi bi-person"></i> Profile</a></li>
                <li><a href="appointment.php" class="active"><i class="bi bi-journal-plus"></i> Schedule Appointment</a></li>
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
            <div class="appointment-card">
                <div class="appointment-header">
                    <h1>Schedule an Appointment</h1>
                    <p>Select a date and find available doctors</p>
                </div>
                
                <div class="appointment-body">
                    <!-- Date Picker Section -->
                    <div class="date-section">
                        <h2><i class="bi bi-calendar3"></i> Select Appointment Date</h2>
                        
                        <div class="date-picker">
                            <form id="dateForm" class="date-form">
                                <label for="getDayWeek">Choose a date for your appointment</label>
                                <input type="date" id="getDayWeek" class="date-input" min="<?php echo date('Y-m-d'); ?>" required>
                                
                                <button type="submit" class="search-btn">
                                    <i class="bi bi-search"></i> Find Available Doctors
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Loading State -->
                    <div class="loading-state" id="loadingState" style="display: none;">
                        <div class="spinner"></div>
                        <p>Finding available doctors...</p>
                    </div>
                    
                    <!-- Doctors Section -->
                    <div class="doctors-section" id="doctorsSection">
                        <div class="doctors-header">
                            <h2><i class="bi bi-person-badge"></i> Available Doctors</h2>
                            <span class="doctors-count" id="doctorsCount" style="display: none;">0 found</span>
                        </div>
                        
                        <div id="filteredDoctors">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-calendar-plus"></i></div>
                                <h3 class="empty-state-text">Select a Date to Begin</h3>
                                <p class="empty-state-subtext">Choose a date to see available doctors for appointment</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Appointment Reason Modal -->
    <div class="modal fade" id="appointmentReasonModal" tabindex="-1" aria-labelledby="appointmentReasonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="appointmentReasonForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="appointmentReasonModalLabel">Book Appointment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Doctor</label>
                            <input type="text" class="form-control" id="modalDoctorName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="text" class="form-control" id="modalAppointmentDate" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Time</label>
                            <input type="text" class="form-control" id="modalAppointmentTime" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="modalAppointmentReason" class="form-label">Reason for Appointment</label>
                            <select class="form-control" id="modalAppointmentReason" required>
                                <option value="">Select a service</option>
                                <option value="Dental Consultation & Treatment">Dental Consultation & Treatment</option>
                                <option value="Oral Prophylaxis (Cleaning)">Oral Prophylaxis (Cleaning)</option>
                                <option value="Simple Tooth Extraction">Simple Tooth Extraction</option>
                                <option value="Dental Care Lectures">Dental Care Lectures</option>
                            </select>
                            <div class="invalid-feedback">Please select a service for your appointment.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Booking Result Modal -->
    <div class="modal fade" id="bookingResultModal" tabindex="-1" aria-labelledby="bookingResultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookingResultModalLabel">Appointment Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bookingResultMessage">
                    <!-- Message will be inserted here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
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
            const dateForm = document.getElementById('dateForm');
            const loadingState = document.getElementById('loadingState');
            const filteredDoctors = document.getElementById('filteredDoctors');
            const doctorsCount = document.getElementById('doctorsCount');
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
            
            // Date form submission
            dateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const selectedDate = document.getElementById('getDayWeek').value;
                if (!selectedDate) {
                    alert('Please select a date');
                    return;
                }
                
                // Show loading state
                loadingState.style.display = 'block';
                filteredDoctors.innerHTML = '';
                doctorsCount.style.display = 'none';
                
                // Fetch available doctors
                fetch('get_available_doctors.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'date=' + selectedDate
                })
                .then(response => response.text())
                .then(text => {
                    console.log(text); // See what is returned
                    const data = JSON.parse(text);
                    
                    // Hide loading state
                    loadingState.style.display = 'none';
                    
                    if (!data.success) {
                        filteredDoctors.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-exclamation-circle"></i></div>
                                <h3 class="empty-state-text">Error</h3>
                                <p class="empty-state-subtext">${data.error}</p>
                            </div>
                        `;
                        return;
                    }
                    
                    const doctors = data.doctors;
                    
                    // Update doctors count
                    doctorsCount.textContent = doctors.length + ' found';
                    doctorsCount.style.display = 'block';
                    
                    if (doctors.length === 0) {
                        filteredDoctors.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                                <h3 class="empty-state-text">No Doctors Available</h3>
                                <p class="empty-state-subtext">There are no doctors available on the selected date. Please try another date.</p>
                            </div>
                        `;
                        return;
                    }
                    
                    // Create doctors grid
                    const doctorsGrid = document.createElement('div');
                    doctorsGrid.className = 'doctors-grid';
                    
                    // Add each doctor to the grid
                    doctors.forEach(doctor => {
                        const doctorCard = document.createElement('div');
                        doctorCard.className = 'doctor-card';
                        doctorCard.innerHTML = `
                            <div class="doctor-header">
                                <div class="doctor-avatar">
                                    ${
                                        doctor.ProfilePhoto && doctor.ProfilePhoto.trim() !== ""
                                        ? `<img src="${doctor.ProfilePhoto}" alt="Doctor Photo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
                                        : `<i class="bi bi-person"></i>`
                                    }
                                </div>
                                <div class="doctor-info">
                                    <h3>Dr. ${doctor.FirstName} ${doctor.LastName}</h3>
                                    <div class="doctor-specialty">${doctor.Specialization || ''}</div>
                                </div>
                            </div>
                            <div class="doctor-body">
                                <div class="time-slots">
                                    <div class="time-slots-header">
                                        <div class="time-slots-title">
                                            <i class="bi bi-clock"></i> Available Time
                                        </div>
                                        <div class="available-tag">Available</div>
                                    </div>
                                    <div class="time-slot">
                                        <i class="bi bi-clock time-slot-icon"></i>
                                        <span class="time-slot-range">${to12HourRange(doctor.ScheduleTime)}</span>
                                    </div>
                                </div>
                                <button class="book-btn" onclick="bookAppointment('${doctor.DoctorID}', '${doctor.SlotID}', '${selectedDate}', '${doctor.ScheduleTime}')">
                                    <i class="bi bi-calendar-plus"></i> Book Appointment
                                </button>
                            </div>
                        `;
                        doctorsGrid.appendChild(doctorCard);
                    });
                    
                    filteredDoctors.innerHTML = '';
                    filteredDoctors.appendChild(doctorsGrid);
                })
                .catch(error => {
                    loadingState.style.display = 'none';
                    filteredDoctors.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-exclamation-triangle"></i></div>
                            <h3 class="empty-state-text">Something Went Wrong</h3>
                            <p class="empty-state-subtext">There was an error loading doctors. Please try again.</p>
                        </div>
                    `;
                    console.error('Error:', error);
                });
            });
            
            // Set minimum date for appointment selection
            const dateInput = document.getElementById('getDayWeek');
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            // Format the date as YYYY-MM-DD
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            // Set tomorrow as the minimum date (to ensure available slots)
            dateInput.min = formatDate(tomorrow);
            
            // Set default value to tomorrow
            dateInput.value = formatDate(tomorrow);
            
            // Add date validation
            dateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                selectedDate.setHours(0, 0, 0, 0);
                
                const todayDate = new Date();
                todayDate.setHours(0, 0, 0, 0);
                
                if (selectedDate < todayDate) {
                    alert('Please select a current or future date');
                    this.value = formatDate(tomorrow);
                }
            });
            
            // Set initial state
            setInitialState();
        });
        
        // Store booking info globally for modal use
        let bookingInfo = {};
        
        // Book appointment function
        function bookAppointment(doctorId, slotId, date, time) {
            // Find doctor name from the card
            const doctorCard = event.target.closest('.doctor-card');
            const doctorName = doctorCard.querySelector('.doctor-info h3').textContent;
            
            bookingInfo = { doctorId, slotId, date, time, doctorName };
            
            // Fill modal fields
            document.getElementById('modalDoctorName').value = doctorName;
            document.getElementById('modalAppointmentDate').value = date;
            document.getElementById('modalAppointmentTime').value = to12HourRange(time);
            document.getElementById('modalAppointmentReason').value = '';
            
            // Show modal
            var reasonModal = new bootstrap.Modal(document.getElementById('appointmentReasonModal'));
            reasonModal.show();
        }
        
        // Convert time to 12-hour format
        function to12HourRange(timeRange) {
            if (!timeRange) return '';
            const [start, end] = timeRange.split('-');
            return `${to12Hour(start)} - ${to12Hour(end)}`;
        }
        
        function to12Hour(time) {
            if (!time) return '';
            const [hour, minute] = time.split(':');
            let h = parseInt(hour, 10);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            if (h === 0) h = 12;
            return `${h}:${minute} ${ampm}`;
        }
        
        // Handle appointment reason form submission
        document.addEventListener('DOMContentLoaded', function() {
            const reasonForm = document.getElementById('appointmentReasonForm');
            
            if (reasonForm) {
                reasonForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const reasonInput = document.getElementById('modalAppointmentReason');
                    if (!reasonInput.value.trim()) {
                        reasonInput.classList.add('is-invalid');
                        return;
                    } else {
                        reasonInput.classList.remove('is-invalid');
                    }
                    
                    // Hide modal
                    var reasonModalEl = document.getElementById('appointmentReasonModal');
                    var reasonModal = bootstrap.Modal.getInstance(reasonModalEl);
                    reasonModal.hide();
                    
                    // Submit appointment
                    fetch('submit_appointment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            doctorID: bookingInfo.doctorId,
                            appointmentDate: bookingInfo.date,
                            appointmentTime: bookingInfo.time,
                            reason: reasonInput.value.trim()
                        }),
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        let message = '';
                        if (data.success) {
                            message = '<div class="alert alert-success mb-0">' + data.message + '</div>';
                            // Refresh available doctors
                            document.getElementById('dateForm').dispatchEvent(new Event('submit'));
                        } else {
                            message = '<div class="alert alert-danger mb-0">' + data.message + '</div>';
                        }
                        
                        document.getElementById('bookingResultMessage').innerHTML = message;
                        var bookingModal = new bootstrap.Modal(document.getElementById('bookingResultModal'));
                        bookingModal.show();
                    })
                    .catch(error => {
                        document.getElementById('bookingResultMessage').innerHTML =
                            '<div class="alert alert-danger mb-0">Error booking appointment. Please try again.</div>';
                        var bookingModal = new bootstrap.Modal(document.getElementById('bookingResultModal'));
                        bookingModal.show();
                        console.error('Error:', error);
                    });
                });
            }
        });
    </script>
</body>
</html>
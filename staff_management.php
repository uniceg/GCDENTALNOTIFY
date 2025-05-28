<?php
// Start session
session_start();

// Include database connection - FIXED
include('config.php'); // Changed from db_connection.php to config.php

// Add this function at the top of the file after the database connection
function generateDoctorID($conn) {
    $year = date('Y');
    $query = "SELECT MAX(CAST(SUBSTRING_INDEX(DoctorID, '-', -1) AS UNSIGNED)) as max_num 
              FROM doctors 
              WHERE DoctorID LIKE 'DOC-$year-%'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $next_num = ($row['max_num'] ?? 0) + 1;
    return sprintf("DOC-%s-%04d", $year, $next_num);
}

// Fetch staff (doctors) details from the doctors table
$staffQuery = "SELECT d.DoctorID, d.FirstName, d.LastName, d.Specialization, d.Email, d.ContactNumber as Phone, d.Status, d.ImageFile,
               a.AppointmentID, a.AppointmentDate, a.Reason, a.statusID, a.TestResultFile, s.StudentID, s.FirstName AS StudentFirstName, s.LastName AS StudentLastName
               FROM doctors d
               LEFT JOIN appointments a ON d.DoctorID = a.DoctorID
               LEFT JOIN students s ON a.StudentID = s.StudentID
               GROUP BY d.DoctorID
               ORDER BY d.DoctorID DESC";
$staffResult = $conn->query($staffQuery);

// Check if the query was successful
if ($staffResult === false) {
    echo "Error fetching staff details: " . $conn->error;
    exit();
}

// Fetch the total number of appointments for each doctor
$appointmentQuery = "SELECT DoctorID, COUNT(*) as TotalAppointments FROM appointments GROUP BY DoctorID";
$appointmentResult = $conn->query($appointmentQuery);

// Check if the appointment query was successful
if ($appointmentResult === false) {
    echo "Error fetching appointments: " . $conn->error;
    exit();
}

// Store the total appointments in an associative array (DoctorID => TotalAppointments)
$appointmentsByDoctor = [];
while ($appointment = $appointmentResult->fetch_assoc()) {
    $appointmentsByDoctor[$appointment['DoctorID']] = $appointment['TotalAppointments'];
}

// Add these queries after the existing queries
$specializationQuery = "SELECT DISTINCT Specialization FROM doctors WHERE Specialization IS NOT NULL";
$specializationResult = $conn->query($specializationQuery);

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_doctor':
                $firstName = $_POST['firstName'];
                $lastName = $_POST['lastName'];
                $specialization = $_POST['specialization'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $status = $_POST['status'];
                
                // Generate new doctor ID
                $doctorID = generateDoctorID($conn);

                $insertQuery = "INSERT INTO doctors (DoctorID, FirstName, LastName, Specialization, Email, ContactNumber, Status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("sssssss", $doctorID, $firstName, $lastName, $specialization, $email, $phone, $status);
                
                if ($stmt->execute()) {
                    $message = "Doctor added successfully!";
                    $messageType = "success";
                    // Refresh the page to show new data
                    header("Location: " . $_SERVER['PHP_SELF'] . "?message=added");
                    exit();
                } else {
                    $message = "Error adding doctor: " . $conn->error;
                    $messageType = "error";
                }
                break;

            case 'edit_doctor':
                $doctorID = $_POST['doctorID'];
                $firstName = $_POST['firstName'];
                $lastName = $_POST['lastName'];
                $specialization = $_POST['specialization'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $status = $_POST['status'];

                $updateQuery = "UPDATE doctors SET 
                              FirstName = ?, 
                              LastName = ?, 
                              Specialization = ?, 
                              Email = ?, 
                              ContactNumber = ?, 
                              Status = ? 
                              WHERE DoctorID = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("sssssss", $firstName, $lastName, $specialization, $email, $phone, $status, $doctorID);
                
                if ($stmt->execute()) {
                    $message = "Doctor updated successfully!";
                    $messageType = "success";
                    // Refresh the page to show updated data
                    header("Location: " . $_SERVER['PHP_SELF'] . "?message=updated");
                    exit();
                } else {
                    $message = "Error updating doctor: " . $conn->error;
                    $messageType = "error";
                }
                break;

            case 'delete_doctor':
                $doctorID = $_POST['doctorID'];
                
                // First check if doctor has any appointments
                $checkQuery = "SELECT COUNT(*) as appointment_count FROM appointments WHERE DoctorID = ?";
                $stmt = $conn->prepare($checkQuery);
                $stmt->bind_param("s", $doctorID);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['appointment_count'] > 0) {
                    $message = "Cannot delete doctor with existing appointments.";
                    $messageType = "error";
                } else {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // First delete associated timeslots
                        $deleteTimeslotsQuery = "DELETE FROM timeslots WHERE DoctorID = ?";
                        $stmt = $conn->prepare($deleteTimeslotsQuery);
                        $stmt->bind_param("s", $doctorID);
                        $stmt->execute();
                        
                        // Then delete the doctor
                        $deleteDoctorQuery = "DELETE FROM doctors WHERE DoctorID = ?";
                        $stmt = $conn->prepare($deleteDoctorQuery);
                        $stmt->bind_param("s", $doctorID);
                        $stmt->execute();
                        
                        // If both operations successful, commit transaction
                        $conn->commit();
                        
                        $message = "Doctor deleted successfully!";
                        $messageType = "success";
                        // Refresh the page to show updated data
                        header("Location: " . $_SERVER['PHP_SELF'] . "?message=deleted");
                        exit();
                    } catch (Exception $e) {
                        // If any operation fails, rollback changes
                        $conn->rollback();
                        $message = "Error deleting doctor: " . $e->getMessage();
                        $messageType = "error";
                    }
                }
                break;
        }
    }
}

// Handle URL messages
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'added':
            $message = "Doctor added successfully!";
            $messageType = "success";
            break;
        case 'updated':
            $message = "Doctor updated successfully!";
            $messageType = "success";
            break;
        case 'deleted':
            $message = "Doctor deleted successfully!";
            $messageType = "success";
            break;
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Medical Clinic</title>
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
            --admin-primary: #2e7d32;
            --admin-primary-light: #60ad5e;
            --admin-primary-dark: #1b5e20;
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
        
        /* Sidebar */
        .sidebar {
            grid-area: sidebar;
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

        .sidebar-menu a:hover i {
            transform: translateX(3px);
        }
        
        .sidebar-menu:last-child {
            margin-top: auto;
            padding-bottom: 20px;
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
        
        /* Main Content */
        .main-content {
            grid-area: main;
            margin-left: 0;
            padding: 30px;
            transition: all 0.3s ease;
            background-color: var(--surface-light);
        }
        
        .main-expanded {
            margin-left: 250px;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 25px;
            border-radius: var(--radius-md);
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary);
        }

        .page-header h1 {
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .page-header p {
            color: var(--text-medium);
            margin: 0;
            font-size: 1rem;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 24px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--admin-primary-light), var(--admin-primary));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:hover::after {
            transform: scaleX(1);
        }
        
        .stat-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-medium);
            margin: 0 0 16px;
            display: flex;
            align-items: center;
        }
        
        .stat-card h3 i {
            margin-right: 10px;
            color: var(--admin-primary);
            font-size: 1.5rem;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--admin-primary);
            margin-bottom: 10px;
        }
        
        .stat-card .description {
            color: var(--text-medium);
            font-size: 0.9rem;
        }

        /* Staff Management Specific Styles */
        .staff-management-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 24px;
            border: 1px solid rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }
        
        .staff-management-card:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .add-doctor-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .add-doctor-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
            color: white;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .table th {
            background-color: var(--primary);
            color: #fff;
            padding: 15px;
            font-weight: 500;
            text-align: left;
        }

        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: rgba(46, 125, 50, 0.05);
        }

        /* Status Badge */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 8px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .edit-btn {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .delete-btn {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .action-btn:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }

        /* Doctor Avatar */
        .doctor-avatar-initials {
            width: 40px !important;
            height: 40px !important;
            min-width: 40px !important;
            min-height: 40px !important;
            max-width: 40px !important;
            max-height: 40px !important;
            font-size: 1.1rem;
            font-weight: 600;
            text-align: center;
            line-height: 40px !important;
            border-radius: 50% !important;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(46, 125, 50, 0.08);
            padding: 0 !important;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.25rem 1.5rem;
            border: none;
        }

        .modal-header .modal-title {
            font-weight: 600;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* Form Styles */
        .form-floating > .form-control,
        .form-floating > .form-select {
            height: 48px;
            padding: 1rem 0.75rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            color: #000;
        }

        .form-floating > .form-control:focus,
        .form-floating > .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1);
            color: #000;
        }

        .form-floating > label {
            padding: 1rem 0.75rem;
            color: #666;
            font-size: 0.95rem;
        }

        .form-select {
            height: 48px;
            padding: 0.75rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #000;
            background-color: #fff;
            cursor: pointer;
        }

        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1);
        }

        /* Button Styles */
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
        }

        /* Sidebar overlay */
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

        /* Search Styles */
        .search-container {
            min-width: 240px;
            max-width: 400px;
            flex: 1 1 240px;
        }

        .search-card {
            background: #f6faff;
            border: 1px solid rgba(46, 125, 50, 0.1);
            border-radius: 8px;
            padding: 8px;
        }

        .input-group-text {
            background: white;
            border-color: #e0e0e0;
            color: var(--text-medium);
        }

        .form-control {
            border-color: #e0e0e0;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.15);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-250px);
            }
            
            .header, .main-content {
                margin-left: 0 !important;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .table th, .table td {
                padding: 10px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            .page-header,
            .staff-management-card {
                padding: 20px;
            }
            
            .table th, .table td {
                padding: 8px;
                font-size: 0.85rem;
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
                <li><a href="admin_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin_dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
                </a></li>
                <li><a href="admin_profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin_profile.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-circle"></i> <span>My Profile</span>
                </a></li>
                <li><a href="staff_management.php" class="<?= basename($_SERVER['PHP_SELF']) === 'staff_management.php' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i> <span>Staff Management</span>
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
        
        <!-- Sidebar overlay -->
        <div id="sidebarOverlay" class="sidebar-overlay"></div>

        <!-- Header -->
        <header class="header header-expanded" id="header">
            <div class="d-flex align-items-center">
                <button class="toggle-sidebar" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="header-title">Staff Management</h1>
            </div>
            
            <div class="header-actions">
                <!-- Removed dashboard and logout buttons -->
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content main-expanded" id="mainContent">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="bi bi-people-fill me-2"></i>Staff Management</h1>
                <p>Manage doctors and medical staff in the clinic system</p>
            </div>

            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3><i class="bi bi-people-fill"></i> Total Doctors</h3>
                    <div class="number"><?php echo $staffResult->num_rows; ?></div>
                    <div class="description">Registered medical staff</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="bi bi-calendar-check"></i> Total Appointments</h3>
                    <div class="number"><?php echo array_sum($appointmentsByDoctor); ?></div>
                    <div class="description">Appointments handled by staff</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="bi bi-exclamation-circle-fill"></i> Doctors with 0 Appointments</h3>
                    <div class="number"><?php echo $staffResult->num_rows - count(array_filter($appointmentsByDoctor)); ?></div>
                    <div class="description">Staff without appointments</div>
                </div>
            </div>

            <!-- Staff Management Card -->
            <div class="staff-management-card">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                    <h2 class="mb-0">Doctors</h2>
                    <div class="d-flex gap-2">
                        <button type="button" class="add-doctor-btn" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                            <i class="bi bi-plus-lg"></i> Add New Doctor
                        </button>
                        <div class="search-container">
                            <div class="search-card">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="doctorSearch" class="form-control border-start-0" placeholder="Search doctors...">
                                    <button id="clearSearch" class="btn btn-outline-secondary d-none" type="button"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($staffResult->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle" id="doctorsTable">
                            <thead class="sticky-top">
                                <tr>
                                    <th>Name</th>
                                    <th>Specialization</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Total Appointments</th>
                                    <th>Manage Timeslots</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $staffResult->data_seek(0);
                                while ($staff = $staffResult->fetch_assoc()):
                                    $doctorID = $staff['DoctorID'];
                                    $totalAppt = isset($appointmentsByDoctor[$doctorID]) ? $appointmentsByDoctor[$doctorID] : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center doctor-avatar-initials">
                                                    <?php echo strtoupper(substr($staff['FirstName'], 0, 1) . substr($staff['LastName'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']); ?></div>
                                                    <small class="text-muted">ID: <?php echo htmlspecialchars($doctorID); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($staff['Specialization'] ?? 'Not specified'); ?></td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <small><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($staff['Email'] ?? 'N/A'); ?></small>
                                                <small><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($staff['Phone'] ?? 'N/A'); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo ($staff['Status'] ?? '') == 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $staff['Status'] ?? 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $totalAppt; ?>
                                            <?php if ($totalAppt == 0): ?>
                                                <span class="badge bg-warning text-dark ms-2"><i class="bi bi-exclamation-circle"></i> None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-primary btn-sm manage-timeslot-btn" 
                                                    data-doctor-id="<?php echo $doctorID; ?>"
                                                    data-doctor-name="<?php echo htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']); ?>">
                                                <i class="bi bi-clock"></i> Manage
                                            </button>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn edit-btn" title="Edit" data-doctor="<?php echo htmlspecialchars(json_encode($staff)); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="action-btn delete-btn" title="Delete" data-doctor-id="<?php echo $doctorID; ?>" data-doctor-name="<?php echo htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="mt-3 text-muted">No doctors found in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Doctor Modal -->
    <div class="modal fade" id="addDoctorModal" tabindex="-1" aria-labelledby="addDoctorModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDoctorModalLabel">Add New Doctor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="addDoctorForm" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="add_doctor">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="firstName" name="firstName" required>
                                    <label for="firstName">First Name</label>
                                    <div class="invalid-feedback">Please enter first name</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="lastName" name="lastName" required>
                                    <label for="lastName">Last Name</label>
                                    <div class="invalid-feedback">Please enter last name</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="specialization" name="specialization" required>
                            <label for="specialization">Specialization</label>
                            <div class="invalid-feedback">Please enter specialization</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" required>
                            <label for="email">Email Address</label>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="phone" name="phone" required pattern="[0-9]{11}">
                            <label for="phone">Phone Number (11 digits)</label>
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number</div>
                        </div>

                        <div class="form-floating mb-3">
                            <select class="form-select" id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <label for="status">Status</label>
                            <div class="invalid-feedback">Please select a status</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Add Doctor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Doctor Modal -->
    <div class="modal fade" id="editDoctorModal" tabindex="-1" aria-labelledby="editDoctorModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDoctorModalLabel">Edit Doctor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="editDoctorForm" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="edit_doctor">
                        <input type="hidden" name="doctorID" id="edit_doctorID">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="edit_firstName" name="firstName" required>
                                    <label for="edit_firstName">First Name</label>
                                    <div class="invalid-feedback">Please enter first name</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="edit_lastName" name="lastName" required>
                                    <label for="edit_lastName">Last Name</label>
                                    <div class="invalid-feedback">Please enter last name</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="edit_specialization" name="specialization" required>
                            <label for="edit_specialization">Specialization</label>
                            <div class="invalid-feedback">Please enter specialization</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                            <label for="edit_email">Email Address</label>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="edit_phone" name="phone" required pattern="[0-9]{11}">
                            <label for="edit_phone">Phone Number (11 digits)</label>
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number</div>
                        </div>

                        <div class="form-floating mb-3">
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <label for="edit_status">Status</label>
                            <div class="invalid-feedback">Please select a status</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Doctor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteDoctorModal" tabindex="-1" aria-labelledby="deleteDoctorModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDoctorModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <span id="deleteDoctorName" class="fw-bold"></span>?</p>
                    <p class="text-danger"><i class="bi bi-exclamation-triangle"></i> This action cannot be undone.</p>
                    <form method="POST" action="" id="deleteDoctorForm">
                        <input type="hidden" name="action" value="delete_doctor">
                        <input type="hidden" name="doctorID" id="delete_doctorID">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary flex-grow-1" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger flex-grow-1">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeslot Management Modal -->
    <div class="modal fade" id="timeslotModal" tabindex="-1" aria-labelledby="timeslotModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="timeslotModalLabel">Manage Timeslots for <span id="modalDoctorName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addTimeslotForm" class="row g-3 mb-3">
                        <input type="hidden" name="doctorID" id="modalDoctorID">
                        <div class="col-md-4">
                            <select class="form-select" name="AvailableDay" required>
                                <option value="">Day</option>
                                <option>Monday</option>
                                <option>Tuesday</option>
                                <option>Wednesday</option>
                                <option>Thursday</option>
                                <option>Friday</option>
                                <option>Saturday</option>
                                <option>Sunday</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="time" class="form-control" name="StartTime" required>
                        </div>
                        <div class="col-md-3">
                            <input type="time" class="form-control" name="EndTime" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">Add</button>
                        </div>
                    </form>
                    <div id="timeslotList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const sidebar = document.getElementById('sidebar');
            const header = document.getElementById('header');
            const mainContent = document.querySelector('.main-content');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            // Create sidebar overlay if it doesn't exist
            let sidebarOverlay = document.getElementById('sidebarOverlay');
            if (!sidebarOverlay) {
                sidebarOverlay = document.createElement('div');
                sidebarOverlay.id = 'sidebarOverlay';
                sidebarOverlay.className = 'sidebar-overlay';
                document.body.appendChild(sidebarOverlay);
            }
            
            // Toggle Sidebar function to match admin_dashboard
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
            
            // Event listeners
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
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.add('sidebar-collapsed');
                    header.classList.remove('header-expanded');
                    mainContent.classList.remove('main-expanded');
                }
            });
            
            // Confirmation dialog for logout
            window.confirmLogout = function() {
                return confirm('Are you sure you want to logout?');
            };
            
            // Doctor search/filter
            const searchInput = document.getElementById('doctorSearch');
            const table = document.getElementById('doctorsTable');
            const clearBtn = document.getElementById('clearSearch');
            
            if (searchInput && table) {
                searchInput.addEventListener('keyup', function() {
                    const filter = searchInput.value.toLowerCase();
                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const nameCell = row.querySelector('td:first-child');
                        if (nameCell.textContent.toLowerCase().includes(filter)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    if (clearBtn) {
                        clearBtn.classList.toggle('d-none', searchInput.value === '');
                    }
                });
                
                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        searchInput.value = '';
                        searchInput.dispatchEvent(new Event('keyup'));
                        searchInput.focus();
                    });
                }
            }

            // Initialize Bootstrap modals
            const addDoctorModal = new bootstrap.Modal(document.getElementById('addDoctorModal'), {
                backdrop: 'static',
                keyboard: false
            });
            
            const editDoctorModal = new bootstrap.Modal(document.getElementById('editDoctorModal'), {
                backdrop: 'static',
                keyboard: false
            });
            
            const deleteDoctorModal = new bootstrap.Modal(document.getElementById('deleteDoctorModal'), {
                backdrop: 'static',
                keyboard: false
            });

            // Form validation
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, { passive: true });
            });

            // Clear forms when modals are closed
            document.getElementById('addDoctorModal').addEventListener('hidden.bs.modal', function () {
                const form = document.getElementById('addDoctorForm');
                form.reset();
                form.classList.remove('was-validated');
            });

            document.getElementById('editDoctorModal').addEventListener('hidden.bs.modal', function () {
                const form = document.getElementById('editDoctorForm');
                form.classList.remove('was-validated');
            });

            // Phone number validation
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) {
                        value = value.slice(0, 11);
                    }
                    e.target.value = value;
                });
            });

            // Edit Doctor Function
            window.editDoctor = function(doctor) {
                const editForm = document.getElementById('editDoctorForm');
                editForm.querySelector('#edit_doctorID').value = doctor.DoctorID;
                editForm.querySelector('#edit_firstName').value = doctor.FirstName;
                editForm.querySelector('#edit_lastName').value = doctor.LastName;
                editForm.querySelector('#edit_specialization').value = doctor.Specialization || '';
                editForm.querySelector('#edit_email').value = doctor.Email || '';
                editForm.querySelector('#edit_phone').value = doctor.Phone || '';
                editForm.querySelector('#edit_status').value = doctor.Status || 'Active';
                
                editDoctorModal.show();
            };

            // Delete Doctor Function
            window.deleteDoctor = function(doctorID, doctorName) {
                document.getElementById('delete_doctorID').value = doctorID;
                document.getElementById('deleteDoctorName').textContent = doctorName;
                deleteDoctorModal.show();
            };

            // Add click event listeners to the buttons
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const doctorData = JSON.parse(this.getAttribute('data-doctor'));
                    editDoctor(doctorData);
                });
            });

            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const doctorID = this.getAttribute('data-doctor-id');
                    const doctorName = this.getAttribute('data-doctor-name');
                    deleteDoctor(doctorID, doctorName);
                });
            });

            // Timeslot management
            document.querySelectorAll('.manage-timeslot-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const doctorID = this.getAttribute('data-doctor-id');
                    const doctorName = this.getAttribute('data-doctor-name');
                    document.getElementById('modalDoctorID').value = doctorID;
                    document.getElementById('modalDoctorName').textContent = doctorName;
                    loadTimeslots(doctorID);
                    new bootstrap.Modal(document.getElementById('timeslotModal')).show();
                });
            });

            // Add timeslot
            const addTimeslotForm = document.getElementById('addTimeslotForm');
            if (addTimeslotForm) {
                addTimeslotForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    fetch('manage_timeslot.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            loadTimeslots(formData.get('doctorID'));
                            this.reset();
                        } else {
                            alert(data.error || 'Failed to add timeslot.');
                        }
                    });
                });
            }
            
            setInitialState();
        });

        // Load timeslots for a doctor - FIXED
        function loadTimeslots(doctorID) {
            fetch('manage_timeslot.php?doctorID=' + encodeURIComponent(doctorID))
            .then(response => response.json()) // FIXED: Added missing opening parenthesis
            .then(data => {
                let html = '<div class="table-responsive"><table class="table table-sm table-striped"><thead class="table-dark"><tr><th>Day</th><th>Start Time</th><th>End Time</th><th>Action</th></tr></thead><tbody>';
                
                if (data.success && data.timeslots && data.timeslots.length > 0) {
                    data.timeslots.forEach(slot => {
                        // Format time for display
                        const startTime = new Date('1970-01-01T' + slot.StartTime).toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                        const endTime = new Date('1970-01-01T' + slot.EndTime).toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                        
                        html += `<tr>
                            <td><span class="badge bg-primary">${slot.AvailableDay}</span></td>
                            <td>${startTime}</td>
                            <td>${endTime}</td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="deleteTimeslot(${slot.SlotID}, '${doctorID}')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>`;
                    });
                } else {
                    html += '<tr><td colspan="4" class="text-center text-muted py-3"><i class="bi bi-clock"></i> No timeslots found for this doctor.</td></tr>';
                }
                html += '</tbody></table></div>';
                
                document.getElementById('timeslotList').innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading timeslots:', error);
                document.getElementById('timeslotList').innerHTML = 
                    '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error loading timeslots: ' + error.message + '</div>';
            });
        }

        // Delete timeslot - FIXED
        function deleteTimeslot(slotID, doctorID) {
            if (confirm('Are you sure you want to delete this timeslot?')) {
                fetch('manage_timeslot.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'delete=1&SlotID=' + encodeURIComponent(slotID)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadTimeslots(doctorID);
                        // Show success message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success alert-dismissible fade show';
                        alertDiv.innerHTML = '<i class="bi bi-check-circle"></i> Timeslot deleted successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                        document.querySelector('#timeslotModal .modal-body').insertBefore(alertDiv, document.getElementById('addTimeslotForm'));
                        
                        // Auto-hide after 3 seconds
                        setTimeout(() => {
                            if (alertDiv && alertDiv.parentNode) {
                                alertDiv.remove();
                            }
                        }, 3000);
                    } else {
                        alert('Error: ' + (data.error || 'Failed to delete timeslot'));
                    }
                })
                .catch(error => {
                    console.error('Error deleting timeslot:', error);
                    alert('Error deleting timeslot: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>

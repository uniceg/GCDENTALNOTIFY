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
$current_page = basename($_SERVER['PHP_SELF']);

// Get total appointments count - ONLY for this doctor
$totalAppointmentsQuery = "SELECT COUNT(*) as total FROM appointments WHERE DoctorID = ?";
$totalStmt = $conn->prepare($totalAppointmentsQuery);
$totalStmt->bind_param("s", $doctorID);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalAppointments = $totalResult->fetch_assoc()['total'];

// Get pending appointments count (status 1) - ONLY for this doctor
$pendingAppointmentsQuery = "SELECT COUNT(*) as pending FROM appointments WHERE DoctorID = ? AND statusID = 1";
$pendingStmt = $conn->prepare($pendingAppointmentsQuery);
$pendingStmt->bind_param("s", $doctorID);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$pendingAppointments = $pendingResult->fetch_assoc()['pending'];

// Get approved appointments count (status 2) - ONLY for this doctor
$approvedAppointmentsQuery = "SELECT COUNT(*) as approved FROM appointments WHERE DoctorID = ? AND statusID = 2";
$approvedStmt = $conn->prepare($approvedAppointmentsQuery);
$approvedStmt->bind_param("s", $doctorID);
$approvedStmt->execute();
$approvedResult = $approvedStmt->get_result();
$approvedAppointments = $approvedResult->fetch_assoc()['approved'];

// Get completed appointments count (status 3) - ONLY for this doctor
$completedAppointmentsQuery = "SELECT COUNT(*) as completed FROM appointments WHERE DoctorID = ? AND statusID = 3";
$completedStmt = $conn->prepare($completedAppointmentsQuery);
$completedStmt->bind_param("s", $doctorID);
$completedStmt->execute();
$completedResult = $completedStmt->get_result();
$completedAppointments = $completedResult->fetch_assoc()['completed'];

// Get cancelled appointments count (status 4) - ONLY for this doctor
$cancelledAppointmentsQuery = "SELECT COUNT(*) as cancelled FROM appointments WHERE DoctorID = ? AND statusID = 4";
$cancelledStmt = $conn->prepare($cancelledAppointmentsQuery);
$cancelledStmt->bind_param("s", $doctorID);
$cancelledStmt->execute();
$cancelledResult = $cancelledStmt->get_result();
$cancelledAppointments = $cancelledResult->fetch_assoc()['cancelled'];

// Get blocked dates count - ONLY for this doctor
$blockedDatesQuery = "SELECT COUNT(*) as blocked FROM blocked_dates WHERE DoctorID = ?";
$blockedStmt = $conn->prepare($blockedDatesQuery);
$blockedStmt->bind_param("s", $doctorID);
$blockedStmt->execute();
$blockedResult = $blockedStmt->get_result();
$blockedDatesCount = $blockedResult->fetch_assoc()['blocked'];

// Get recent appointments - ONLY for this doctor
$recentAppointmentsQuery = "SELECT a.*, s.status_name, st.FirstName, st.LastName 
                           FROM appointments a
                           JOIN status s ON a.statusID = s.statusID
                           JOIN students st ON a.StudentID = st.StudentID
                           WHERE a.DoctorID = ?
                           ORDER BY a.AppointmentDate DESC
                           LIMIT 10";
$recentStmt = $conn->prepare($recentAppointmentsQuery);
$recentStmt->bind_param("s", $doctorID);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();

// Get recent blocked dates - ONLY for this doctor
$recentBlockedDatesQuery = "SELECT * FROM blocked_dates 
                           WHERE DoctorID = ? 
                           ORDER BY BlockedDate DESC 
                           LIMIT 10";
$recentBlockedStmt = $conn->prepare($recentBlockedDatesQuery);
$recentBlockedStmt->bind_param("s", $doctorID);
$recentBlockedStmt->execute();
$recentBlockedResult = $recentBlockedStmt->get_result();

// Get most common cancellation reasons - ONLY for this doctor
$commonCancellationsQuery = "SELECT Reason, COUNT(*) as count 
                            FROM appointments 
                            WHERE DoctorID = ? AND statusID = 4 AND Reason IS NOT NULL AND Reason != ''
                            GROUP BY Reason 
                            ORDER BY count DESC 
                            LIMIT 5";
$commonCancellationsStmt = $conn->prepare($commonCancellationsQuery);
$commonCancellationsStmt->bind_param("s", $doctorID);
$commonCancellationsStmt->execute();
$commonCancellationsResult = $commonCancellationsStmt->get_result();

// Get appointment trends (last 6 months) - ONLY for this doctor
$trendsQuery = "SELECT 
                    DATE_FORMAT(AppointmentDate, '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN statusID = 3 THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN statusID = 4 THEN 1 ELSE 0 END) as cancelled
                FROM appointments 
                WHERE DoctorID = ? 
                AND AppointmentDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(AppointmentDate, '%Y-%m')
                ORDER BY month DESC";
$trendsStmt = $conn->prepare($trendsQuery);
$trendsStmt->bind_param("s", $doctorID);
$trendsStmt->execute();
$trendsResult = $trendsStmt->get_result();

// Get patient distribution - ONLY for this doctor
$patientDistributionQuery = "SELECT 
                                COUNT(DISTINCT a.StudentID) as unique_patients,
                                COUNT(*) as total_appointments,
                                AVG(CASE WHEN statusID = 3 THEN 1.0 ELSE 0.0 END) * 100 as completion_rate
                            FROM appointments a
                            WHERE a.DoctorID = ?";
$patientStmt = $conn->prepare($patientDistributionQuery);
$patientStmt->bind_param("s", $doctorID);
$patientStmt->execute();
$patientResult = $patientStmt->get_result();
$patientStats = $patientResult->fetch_assoc();

// Close all statements
$doctor_verify_stmt->close();
$totalStmt->close();
$pendingStmt->close();
$approvedStmt->close();
$completedStmt->close();
$cancelledStmt->close();
$blockedStmt->close();
$recentStmt->close();
$recentBlockedStmt->close();
$commonCancellationsStmt->close();
$trendsStmt->close();
$patientStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Reports - Dr. <?= htmlspecialchars($doctorInfo['FirstName']) ?> - Medical Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    /* Exact same CSS variables and styles as doctor_profile.php */
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
    
    /* Exact same sidebar styles as doctor_profile.php */
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

    /* Exact same header styles as doctor_profile.php */
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
        transition: transform 0.2s ease;
    }
    
    .card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
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

    .btn-light {
        background-color: #f8f9fa;
        border-color: #f8f9fa;
        color: var(--text-dark);
        border-radius: var(--radius-sm);
        font-weight: 500;
        padding: 8px 16px;
        transition: all 0.2s;
    }

    .btn-light:hover {
        background-color: #e9ecef;
        border-color: #e9ecef;
    }
    
    /* Statistics card specific styles */
    .stats-card {
        min-height: 140px;
        border: none;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }

    .icon-container {
        font-size: 2.5rem;
        padding: 10px;
        border-radius: 50%;
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
    }

    .bg-total {
        background-color: #e3f2fd;
        color: #0d6efd;
    }

    .bg-completed {
        background-color: #e8f5e9;
        color: #2e7d32;
    }

    .bg-cancelled {
        background-color: #ffebee;
        color: #c62828;
    }

    .bg-upcoming {
        background-color: #fff8e1;
        color: #ff8f00;
    }

    .bg-blocked {
        background-color: #f3e5f5;
        color: #7b1fa2;
    }

    .dash-count {
        font-size: 2rem;
        font-weight: bold;
        color: var(--text-dark);
        margin: 0;
    }

    /* Status badge styles */
    .status-badge {
        padding: 6px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-pending {
        background-color: #fff8e1;
        color: #ff8f00;
    }

    .status-approved {
        background-color: #e8f5e9;
        color: #2e7d32;
    }

    .status-completed {
        background-color: #e3f2fd;
        color: #0d6efd;
    }

    .status-cancelled {
        background-color: #ffebee;
        color: #c62828;
    }

    .status-requested {
        background-color: #f3e5f5;
        color: #7b1fa2;
    }
    
    /* Table styles */
    .table {
        margin-top: 10px;
    }
    
    .table th {
        background-color: #f8f9fa;
        color: var(--text-dark);
        font-weight: 600;
        border-bottom: 2px solid var(--surface-medium);
        font-size: 0.9rem;
    }
    
    .table td {
        vertical-align: middle;
        padding: 12px;
        font-size: 0.9rem;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(46, 125, 50, 0.05);
    }
    
    /* Progress bar styles */
    .reason-progress {
        height: 15px;
        border-radius: 8px;
        background-color: #f8f9fa;
    }

    .reason-progress .progress-bar {
        border-radius: 8px;
    }
    
    /* Print styles */
    @media print {
        .sidebar, 
        .toggle-sidebar, 
        .sidebar-overlay, 
        .header,
        .btn-outline-primary,
        .btn-outline-danger,
        .btn-light {
            display: none !important;
        }
        
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            break-inside: avoid !important;
        }
        
        .card:hover {
            transform: none !important;
        }
        
        body {
            font-size: 12pt;
            background-color: white !important;
        }
        
        .print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .page-break {
            page-break-before: always;
        }
    }
    
    .print-header {
        display: none;
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
        
        .card-body {
            padding: 15px;
        }
        
        .stats-card {
            min-height: 120px;
        }
        
        .icon-container {
            width: 60px;
            height: 60px;
            font-size: 2rem;
        }
        
        .dash-count {
            font-size: 1.5rem;
        }
    }
    
    @media (max-width: 576px) {
        .main-content {
            padding: 10px;
        }
        
        .page-header {
            padding: 15px;
        }
        
        .card-header,
        .card-body {
            padding: 15px;
        }
    }
  </style>
</head>
<body>
  <!-- Exact same sidebar as doctor_profile.php -->
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
        <h1 class="header-title">My Reports - Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?></h1>
    </div>
    
    <div class="header-actions">
        <!-- Removed all header action buttons -->
    </div>
  </header>

  <!-- Sidebar overlay -->
  <div id="sidebarOverlay" class="sidebar-overlay"></div>

  <!-- Main content with complete UI -->
  <main class="main-content main-expanded" id="mainContent">
    <div class="container-fluid">
        <!-- Print header (only visible when printing) -->
        <div class="print-header">
            <h2>Medical Clinic System</h2>
            <h3>Doctor Performance Report - <?= date('F d, Y') ?></h3>
            <p>Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?></p>
            <p><?= htmlspecialchars($doctorInfo['Specialization']) ?></p>
            <hr>
        </div>
        
        <!-- Page header -->
        <div class="page-header">
            <h1><i class="bi bi-graph-up me-2"></i>My Reports & Analytics</h1>
            <p>Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?> - Your comprehensive appointment and performance reports</p>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="icon-container bg-total mx-auto">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <h3 class="dash-count"><?= $totalAppointments ?></h3>
                        <p class="text-muted mb-0">Total Appointments</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="icon-container bg-completed mx-auto">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3 class="dash-count"><?= $completedAppointments ?></h3>
                        <p class="text-muted mb-0">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="icon-container bg-cancelled mx-auto">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <h3 class="dash-count"><?= $cancelledAppointments ?></h3>
                        <p class="text-muted mb-0">Cancelled</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="icon-container bg-upcoming mx-auto">
                            <i class="bi bi-calendar-plus"></i>
                        </div>
                        <h3 class="dash-count"><?= $pendingAppointments + $approvedAppointments ?></h3>
                        <p class="text-muted mb-0">Upcoming</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient Statistics -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="icon-container bg-total mx-auto">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="dash-count"><?= $patientStats['unique_patients'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Unique Patients</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="icon-container bg-completed mx-auto">
                            <i class="bi bi-percent"></i>
                        </div>
                        <h3 class="dash-count"><?= round($patientStats['completion_rate'] ?? 0) ?>%</h3>
                        <p class="text-muted mb-0">Completion Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="icon-container bg-blocked mx-auto">
                            <i class="bi bi-calendar-x"></i>
                        </div>
                        <h3 class="dash-count"><?= $blockedDatesCount ?></h3>
                        <p class="text-muted mb-0">Blocked Dates</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Trends -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Monthly Appointment Trends (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($trendsResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Total Appointments</th>
                                            <th>Completed</th>
                                            <th>Cancelled</th>
                                            <th>Success Rate</th>
                                            <th>Visual Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($trend = $trendsResult->fetch_assoc()): ?>
                                            <?php 
                                            $successRate = $trend['total'] > 0 ? ($trend['completed'] / $trend['total']) * 100 : 0;
                                            $monthName = date('F Y', strtotime($trend['month'] . '-01'));
                                            ?>
                                            <tr>
                                                <td><strong><?= $monthName ?></strong></td>
                                                <td><?= $trend['total'] ?></td>
                                                <td><span class="status-badge status-completed"><?= $trend['completed'] ?></span></td>
                                                <td><span class="status-badge status-cancelled"><?= $trend['cancelled'] ?></span></td>
                                                <td><?= round($successRate) ?>%</td>
                                                <td>
                                                    <div class="reason-progress">
                                                        <div class="progress-bar bg-success" style="width: <?= $successRate ?>%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">No appointment data available for the last 6 months</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Appointments -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Recent Appointments</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recentResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($recent = $recentResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($recent['FirstName'] . ' ' . $recent['LastName']) ?></td>
                                                <td><?= date('M d, Y', strtotime($recent['AppointmentDate'])) ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($recent['statusID']) {
                                                        case 1: $statusClass = 'status-pending'; break;
                                                        case 2: $statusClass = 'status-approved'; break;
                                                        case 3: $statusClass = 'status-completed'; break;
                                                        case 4: $statusClass = 'status-cancelled'; break;
                                                        case 5: $statusClass = 'status-requested'; break;
                                                    }
                                                    ?>
                                                    <span class="status-badge <?= $statusClass ?>">
                                                        <?= htmlspecialchars($recent['status_name']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2">No recent appointments</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recently Added Blocked Dates -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-x me-2"></i>Recent Blocked Dates</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recentBlockedResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Blocked Date</th>
                                            <th>Reason</th>
                                            <th>Added On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($blocked = $recentBlockedResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($blocked['BlockedDate'])) ?></td>
                                                <td><?= htmlspecialchars($blocked['Reason']) ?></td>
                                                <td><?= date('M d, Y', strtotime($blocked['CreatedAt'])) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-check text-muted" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2">No blocked dates</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Most Common Cancellation Reasons -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Most Common Cancellation Reasons</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($commonCancellationsResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Reason</th>
                                            <th>Count</th>
                                            <th>Frequency</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $maxCount = 0;
                                        $reasons = [];
                                        while ($reason = $commonCancellationsResult->fetch_assoc()) {
                                            $reasons[] = $reason;
                                            if ($reason['count'] > $maxCount) $maxCount = $reason['count'];
                                        }
                                        
                                        foreach ($reasons as $reason): 
                                            $percentage = $maxCount > 0 ? ($reason['count'] / $maxCount) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($reason['Reason']) ?></td>
                                                <td><span class="status-badge status-cancelled"><?= $reason['count'] ?></span></td>
                                                <td>
                                                    <div class="reason-progress">
                                                        <div class="progress-bar bg-danger" style="width: <?= $percentage ?>%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">Great! No cancellations recorded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="doctor_student.php" class="btn btn-outline-primary w-100 p-3">
                                    <i class="bi bi-calendar-check d-block mb-2" style="font-size: 1.5rem;"></i>
                                    View All Appointments
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_viewer.php" class="btn btn-outline-primary w-100 p-3">
                                    <i class="bi bi-people d-block mb-2" style="font-size: 1.5rem;"></i>
                                    View All Patients
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="doctor_schedule.php" class="btn btn-outline-primary w-100 p-3">
                                    <i class="bi bi-calendar3 d-block mb-2" style="font-size: 1.5rem;"></i>
                                    Manage Schedule
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button onclick="printPage()" class="btn btn-outline-primary w-100 p-3">
                                    <i class="bi bi-printer d-block mb-2" style="font-size: 1.5rem;"></i>
                                    Print Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Print timestamp footer -->
        <div class="d-none d-print-block mt-5">
            <hr>
            <div class="row">
                <div class="col-6">
                    <p class="small text-muted">Report generated: <?php echo date('Y-m-d H:i:s'); ?></p>
                </div>
                <div class="col-6 text-end">
                    <p class="small text-muted">Doctor ID: <?php echo htmlspecialchars($doctorID); ?></p>
                </div>
            </div>
        </div>
    </div>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- JavaScript with added logout confirmation -->
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
        // Prepare page for printing
        const originalTitle = document.title;
        document.title = "Doctor Report - " + new Date().toLocaleDateString();
        
        // Add print-specific classes
        document.body.classList.add('printing-active');
        
        // Enhance print view
        const charts = document.querySelectorAll('.progress');
        charts.forEach(chart => {
            chart.style.height = '20px';
        });
        
        // Initiate print dialog
        setTimeout(function() {
            window.print();
            
            // Clean up after print
            setTimeout(function() {
                document.title = originalTitle;
                document.body.classList.remove('printing-active');
                charts.forEach(chart => {
                    chart.style.height = '';
                });
            }, 1000);
        }, 300);
    }

    // Logout confirmation
    window.confirmLogout = function() {
        return confirm('Are you sure you want to logout?');
    }
    
    // Set initial state
    setInitialState();
});
  </script>
</body>
</html>
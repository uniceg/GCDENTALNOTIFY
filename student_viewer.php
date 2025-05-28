<?php
session_start();
include 'config.php';

// Check if doctor is logged in
if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit();
}

// Get the logged-in doctor's unique ID
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

$searchTerm = $_GET['search'] ?? '';
$studentID = $_GET['studentID'] ?? '';

// Get student list for search dropdown - ONLY students who have appointments with THIS doctor
$studentSql = "SELECT DISTINCT s.studentID, CONCAT(s.firstName, ' ', s.lastName) AS student_name 
               FROM students s
               INNER JOIN appointments a ON s.studentID = a.StudentID
               WHERE a.DoctorID = ?
               ORDER BY s.lastName, s.firstName";

$studentStmt = $conn->prepare($studentSql);
$studentStmt->bind_param("s", $doctorID);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();

// Check if query was successful
if (!$studentResult) {
    die("Database query failed: " . $conn->error);
}

$students = [];
while ($row = $studentResult->fetch_assoc()) {
    $students[] = $row;
}
$studentStmt->close();

// Get specific student's appointment history - ONLY with THIS doctor
$appointmentHistory = [];
if (!empty($studentID)) {
    // First verify this student has appointments with the logged-in doctor
    $verify_sql = "SELECT COUNT(*) as count FROM appointments WHERE StudentID = ? AND DoctorID = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("is", $studentID, $doctorID);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_count = $verify_result->fetch_assoc()['count'];
    $verify_stmt->close();
    
    if ($verify_count === 0) {
        // Redirect if trying to access unauthorized patient
        header("Location: student_viewer.php?error=unauthorized");
        exit();
    }
    
    // Get appointment history - ONLY appointments with THIS doctor
    $sql = "SELECT a.AppointmentID, a.AppointmentDate, a.Reason, a.notes,
                   ts.StartTime, ts.EndTime,
                   s.status_name AS status_name,
                   CONCAT(d.FirstName, ' ', d.LastName) as doctor_name
            FROM appointments a
            LEFT JOIN timeslots ts ON a.SlotID = ts.SlotID
            LEFT JOIN status s ON a.statusID = s.statusID
            LEFT JOIN doctors d ON a.DoctorID = d.DoctorID
            WHERE a.StudentID = ? AND a.DoctorID = ?
            ORDER BY a.AppointmentDate DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("is", $studentID, $doctorID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        die("Query execution failed: " . $stmt->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        // Format time and date
        $row['formatted_date'] = date("F d, Y", strtotime($row['AppointmentDate']));
        if ($row['StartTime'] && $row['EndTime']) {
            $row['time'] = date("h:i A", strtotime($row['StartTime'])) . ' - ' . date("h:i A", strtotime($row['EndTime']));
        } else {
            $row['time'] = 'Time not assigned';
        }
        $appointmentHistory[] = $row;
    }
    $stmt->close();
    
    // Get comprehensive student info
    $studentInfoSql = "SELECT CONCAT(firstName, ' ', lastName) AS full_name, 
                              studentID, email, contactNumber, dateOfBirth, course, year,
                              name, address, parentGuardian, gender, yearLevel,
                              firstName, lastName, parentContact,
                              emergencyContactName, emergencyContactRelationship, emergencyContactNumber,
                              bloodType, allergies, medicalConditions, medications
                       FROM students 
                       WHERE studentID = ?";
    
    $studentStmt = $conn->prepare($studentInfoSql);
    if (!$studentStmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $studentStmt->bind_param("i", $studentID);
    $studentStmt->execute();
    $studentInfoResult = $studentStmt->get_result();
    
    if (!$studentInfoResult) {
        die("Student info query failed: " . $studentStmt->error);
    }
    
    $studentInfo = $studentInfoResult->fetch_assoc();
    $studentStmt->close();
}

// Close doctor verification
$doctor_verify_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Patients - Dr. <?= htmlspecialchars($doctorInfo['FirstName']) ?> - Medical Clinic</title>
  <!-- Keep all existing CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    /* Simplified CSS variables */
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
    
    /* Simplified sidebar styles */
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

    /* Simplified header */
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
    
    /* Simplified main content */
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
    
    /* Simple page header */
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
    
    /* Simplified cards */
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
    
    /* Simple form styling */
    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 8px;
    }

    .form-select {
        border: 1px solid var(--surface-medium);
        border-radius: var(--radius-sm);
        padding: 10px 15px;
        font-size: 1rem;
        transition: border-color 0.2s;
    }

    .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.15);
        outline: none;
    }
    
    /* Simple buttons */
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
    
    /* Simple statistics */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        border-radius: var(--radius-sm);
        padding: 20px;
        text-align: center;
        box-shadow: var(--shadow-sm);
        border-top: 3px solid var(--primary);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 5px;
    }

    .stat-label {
        color: var(--text-medium);
        font-weight: 500;
        font-size: 0.9rem;
    }
    
    /* Simplified patient info */
    .patient-info {
        background: white;
        border: 1px solid var(--surface-medium);
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
    }

    .patient-info .card-header {
        background: #f8f9fa;
        border-bottom: 1px solid var(--surface-medium);
        font-weight: 600;
        color: var(--text-dark);
        padding: 15px 20px;
    }

    .patient-sections {
        padding: 20px;
    }

    .info-section {
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f0f0f0;
    }

    .info-section:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .section-title {
        color: var(--text-dark);
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-right: 8px;
        color: var(--primary);
    }

    .info-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
    }

    .info-item .label {
        font-weight: 600;
        color: var(--text-medium);
        font-size: 0.85rem;
        margin-bottom: 3px;
    }

    .info-item .value {
        color: var(--text-dark);
        font-size: 1rem;
        line-height: 1.4;
    }

    .info-item .value a {
        color: var(--primary);
        text-decoration: none;
    }

    .info-item .value a:hover {
        text-decoration: underline;
    }

    /* Simple medical indicators */
    .blood-type-simple {
        background: var(--primary);
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 0.9rem;
        display: inline-block;
    }

    .medical-warning {
        background: #fff3cd;
        color: #856404;
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid #ffeaa7;
        margin-top: 5px;
    }

    .medical-condition {
        background: #f8d7da;
        color: #721c24;
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid #f5c6cb;
        margin-top: 5px;
    }

    .medication-info {
        background: #e7f3ff;
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid #bee5eb;
        margin-top: 5px;
    }

    /* Enhanced table styling */
    .table-container {
        background: white;
        border-radius: var(--radius-sm);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        margin-bottom: 20px;
    }

    .table-container .header {
        background: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid var(--surface-medium);
        font-weight: 600;
        color: var(--text-dark);
        font-size: 1.1rem;
        display: flex;
        align-items: center;
    }

    .table {
        margin: 0;
        font-size: 0.95rem;
    }

    .table th {
        background: #f8f9fa;
        color: var(--text-dark);
        font-weight: 600;
        padding: 15px 20px;
        border: none;
        border-bottom: 2px solid var(--surface-medium);
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 15px 20px;
        vertical-align: middle;
        border: none;
        border-bottom: 1px solid #f8f9fa;
    }

    .table tbody tr {
        transition: background-color 0.2s ease;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Status badges */
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
        min-width: 80px;
        text-align: center;
    }

    .badge-pending {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .badge-approved {
        background: #cce5ff;
        color: #0056b3;
        border: 1px solid #99d3ff;
    }

    .badge-completed {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .badge-cancelled {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Notes button */
    .notes-btn {
        background: var(--primary);
        border: none;
        color: white;
        padding: 8px 16px;
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .notes-btn:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .notes-btn i {
        font-size: 0.9rem;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-medium);
    }

    .empty-state i {
        font-size: 4rem;
        color: var(--surface-medium);
        margin-bottom: 20px;
        display: block;
    }

    .empty-state h4 {
        color: var(--text-dark);
        margin-bottom: 10px;
        font-weight: 600;
        font-size: 1.3rem;
    }

    .empty-state p {
        color: var(--text-medium);
        margin: 0;
        font-size: 1rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .medical-history-text {
            max-height: 120px;
            padding: 10px;
        }
        
        .blood-type {
            font-size: 0.8rem;
            padding: 3px 10px;
        }
        
        .allergies-warning,
        .chronic-conditions {
            padding: 6px 10px;
            font-size: 0.9rem;
        }
        
        .table-responsive {
            font-size: 0.85rem;
        }
        
        .table th,
        .table td {
            padding: 10px 12px;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            min-width: 70px;
        }
        
        .notes-btn {
            font-size: 0.8rem;
            padding: 6px 12px;
        }
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
        .page-header h1 {
            font-size: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .info-grid {
            grid-template-columns: 1fr !important;
        }
        
        .table-responsive {
            font-size: 0.9rem;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .header {
            padding: 10px 15px;
        }
        
        .main-content {
            padding: 15px;
        }
    }

    @media (max-width: 576px) {
        /* Stack table for mobile */
        .table-responsive table,
        .table-responsive thead,
        .table-responsive tbody,
        .table-responsive th,
        .table-responsive td,
        .table-responsive tr {
            display: block;
        }
        
        .table-responsive thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        
        .table-responsive tr {
            border: 1px solid var(--surface-medium);
            margin-bottom: 15px;
            border-radius: var(--radius-sm);
            background: white;
            padding: 15px;
        }
        
        .table-responsive td {
            border: none;
            position: relative;
            padding: 8px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-responsive td:before {
            content: attr(data-label) ": ";
            font-weight: 600;
            color: var(--text-medium);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    }
  </style>
</head>
<body>
  <!-- Updated sidebar with logout button -->
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

<!-- Header -->
<header class="header header-expanded" id="header">
    <div class="d-flex align-items-center">
        <button class="toggle-sidebar" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="header-title">My Patients - Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?></h1>
    </div>
    
    <div class="header-actions">
        <!-- Removed all header action buttons -->
    </div>
</header>

<!-- Sidebar overlay -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Updated main content wrapper -->
<main class="main-content main-expanded" id="mainContent">
    <div class="container-fluid">
        <!-- Simple page header -->
        <div class="page-header">
            <h1><i class="bi bi-person-lines-fill me-2"></i>My Patients</h1>
            <p>Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?> - View patient records for appointments assigned to you</p>
        </div>
        
        <!-- Simple search form -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-search me-2"></i>Select My Patient
            </div>
            <div class="card-body">
                <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        You can only view patients who have appointments with you.
                    </div>
                <?php endif; ?>
                
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-8">
                        <label for="studentID" class="form-label">Patient Name</label>
                        <select name="studentID" id="studentID" class="form-select" style="height: 48px;">
                            <option value="">-- Select one of your patients --</option>
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['studentID'] ?>" <?= ($studentID == $student['studentID']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($student['student_name']) ?> (ID: <?= $student['studentID'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No patients found - appointments will appear here</option>
                            <?php endif; ?>
                        </select>
                        <?php if (count($students) === 0): ?>
                            <small class="text-muted">
                                You don't have any patients yet. Patients will appear here once they book appointments with you.
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label invisible">Button</label>
                        <button type="submit" class="btn btn-primary w-100 d-block" style="height: 48px;" <?= count($students) === 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-search me-2"></i>View Records
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($studentID) && isset($studentInfo)): ?>
        
        <!-- Simple statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($appointmentHistory) ?></div>
                <div class="stat-label">Total Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?= count(array_filter($appointmentHistory, function($apt) { 
                        return strtolower($apt['status_name']) === 'completed'; 
                    })) ?>
                </div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?= count(array_filter($appointmentHistory, function($apt) { 
                        return strtolower($apt['status_name']) === 'pending'; 
                    })) ?>
                </div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?= count(array_filter($appointmentHistory, function($apt) { 
                        return strtolower($apt['status_name']) === 'cancelled'; 
                    })) ?>
                </div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>
        
        <!-- Simplified patient information section -->
        <div class="patient-info">
            <div class="card-header">
                <i class="bi bi-person-badge me-2"></i>Patient Information
            </div>
            <div class="patient-sections">
                <!-- Personal Information -->
                <div class="info-section">
                    <h6 class="section-title">
                        <i class="bi bi-person"></i>Personal Details
                    </h6>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="label">Full Name</div>
                            <div class="value"><?= htmlspecialchars($studentInfo['full_name'] ?: $studentInfo['name'] ?: 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Student ID</div>
                            <div class="value"><?= htmlspecialchars($studentInfo['studentID']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Gender</div>
                            <div class="value"><?= htmlspecialchars($studentInfo['gender'] ?: 'Not specified') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Date of Birth</div>
                            <div class="value">
                                <?php if ($studentInfo['dateOfBirth'] && $studentInfo['dateOfBirth'] !== '0000-00-00'): ?>
                                    <?= date("F d, Y", strtotime($studentInfo['dateOfBirth'])) ?>
                                    <small class="text-muted d-block">(Age: <?= date_diff(date_create($studentInfo['dateOfBirth']), date_create('today'))->y ?> years)</small>
                                <?php else: ?>
                                    Not available
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="info-section">
                    <h6 class="section-title">
                        <i class="bi bi-telephone"></i>Contact Information
                    </h6>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="label">Email Address</div>
                            <div class="value">
                                <?php if ($studentInfo['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($studentInfo['email']) ?>">
                                        <?= htmlspecialchars($studentInfo['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Primary Phone</div>
                            <div class="value">
                                <?php if ($studentInfo['contactNumber']): ?>
                                    <a href="tel:<?= htmlspecialchars($studentInfo['contactNumber']) ?>">
                                        <?= htmlspecialchars($studentInfo['contactNumber']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Parent Contact</div>
                            <div class="value">
                                <?php if ($studentInfo['parentContact']): ?>
                                    <a href="tel:<?= htmlspecialchars($studentInfo['parentContact']) ?>">
                                        <?= htmlspecialchars($studentInfo['parentContact']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Home Address</div>
                            <div class="value">
                                <?php if ($studentInfo['address']): ?>
                                    <?= htmlspecialchars($studentInfo['address']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="info-section">
                    <h6 class="section-title">
                        <i class="bi bi-mortarboard"></i>Academic Details
                    </h6>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="label">Course/Program</div>
                            <div class="value">
                                <?= htmlspecialchars($studentInfo['course'] ?: 'Not specified') ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Year Level</div>
                            <div class="value">
                                <?= htmlspecialchars($studentInfo['year'] ?: $studentInfo['yearLevel'] ?: 'Not specified') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="info-section">
                    <h6 class="section-title">
                        <i class="bi bi-heart-pulse"></i>Medical Information
                    </h6>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="label">Blood Type</div>
                            <div class="value">
                                <?php if ($studentInfo['bloodType']): ?>
                                    <span class="blood-type-simple"><?= htmlspecialchars($studentInfo['bloodType']) ?></span>
                                <?php else: ?>
                                    <span class="text-warning">Not recorded</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Known Allergies</div>
                            <div class="value">
                                <?php if ($studentInfo['allergies']): ?>
                                    <div class="medical-warning">
                                        <?= nl2br(htmlspecialchars($studentInfo['allergies'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-success">No known allergies</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-item">
                            <div class="label">Medical Conditions</div>
                            <div class="value">
                                <?php if ($studentInfo['medicalConditions']): ?>
                                    <div class="medical-condition">
                                        <?= nl2br(htmlspecialchars($studentInfo['medicalConditions'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-success">No medical conditions reported</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Current Medications</div>
                            <div class="value">
                                <?php if ($studentInfo['medications']): ?>
                                    <div class="medication-info">
                                        <?= nl2br(htmlspecialchars($studentInfo['medications'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No medications reported</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="info-section">
                    <h6 class="section-title">
                        <i class="bi bi-person-plus"></i>Emergency Contact
                    </h6>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="label">Emergency Contact Name</div>
                            <div class="value">
                                <?= htmlspecialchars($studentInfo['emergencyContactName'] ?: $studentInfo['parentGuardian'] ?: 'Not provided') ?>
                            </div>
                        </div>
                        <?php if ($studentInfo['emergencyContactRelationship']): ?>
                        <div class="info-item">
                            <div class="label">Relationship</div>
                            <div class="value">
                                <?= htmlspecialchars($studentInfo['emergencyContactRelationship']) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <div class="label">Emergency Phone</div>
                            <div class="value">
                                <?php 
                                    $emergencyPhone = $studentInfo['emergencyContactNumber'] ?: $studentInfo['parentContact'] ?: null;
                                    if ($emergencyPhone) {
                                        echo '<a href="tel:' . htmlspecialchars($emergencyPhone) . '" class="text-danger">';
                                        echo htmlspecialchars($emergencyPhone);
                                        echo '</a>';
                                    } else {
                                        echo '<span class="text-muted">Not provided</span>';
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Enhanced appointment history -->
        <div class="table-container">
            <div class="header">
                <i class="bi bi-clock-history me-2"></i>Appointment History
                <?php if (count($appointmentHistory) > 0): ?>
                    <span class="ms-auto text-muted" style="font-size: 0.9rem; font-weight: 400;">
                        <?= count($appointmentHistory) ?> appointment<?= count($appointmentHistory) !== 1 ? 's' : '' ?> found
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (count($appointmentHistory) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Reason</th>
                                <th>Doctor</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointmentHistory as $appointment): ?>
                                <tr>
                                    <td data-label="Date">
                                        <div>
                                            <strong><?= date("M d, Y", strtotime($appointment['AppointmentDate'])) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= date("l", strtotime($appointment['AppointmentDate'])) ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Time">
                                        <span class="fw-semibold"><?= htmlspecialchars($appointment['time']) ?></span>
                                    </td>
                                    <td data-label="Reason">
                                        <?= htmlspecialchars($appointment['Reason']) ?>
                                    </td>
                                    <td data-label="Doctor">
                                        <?php if ($appointment['doctor_name']): ?>
                                            <div>
                                                <i class="bi bi-person-badge me-1"></i>
                                                <?= htmlspecialchars($appointment['doctor_name']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php
                                            $statusClass = '';
                                            $statusIcon = '';
                                            switch(strtolower($appointment['status_name'])) {
                                                case 'pending': 
                                                    $statusClass = 'badge-pending'; 
                                                    $statusIcon = 'bi-clock';
                                                    break;
                                                case 'approved': 
                                                    $statusClass = 'badge-approved'; 
                                                    $statusIcon = 'bi-check-circle';
                                                    break;
                                                case 'completed': 
                                                    $statusClass = 'badge-completed'; 
                                                    $statusIcon = 'bi-check-circle-fill';
                                                    break;
                                                case 'cancelled': 
                                                    $statusClass = 'badge-cancelled'; 
                                                    $statusIcon = 'bi-x-circle';
                                                    break;
                                                default:
                                                    $statusClass = 'badge-pending';
                                                    $statusIcon = 'bi-question-circle';
                                            }
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <i class="bi <?= $statusIcon ?> me-1"></i>
                                            <?= htmlspecialchars($appointment['status_name']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Notes">
                                        <?php if (!empty($appointment['notes'])): ?>
                                            <button type="button" class="notes-btn" data-bs-toggle="modal" data-bs-target="#notesModal<?= $appointment['AppointmentID'] ?>">
                                                <i class="bi bi-file-text"></i>
                                                View Notes
                                            </button>
                                            
                                            <!-- Enhanced Notes Modal -->
                                            <div class="modal fade" id="notesModal<?= $appointment['AppointmentID'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header" style="background: #f8f9fa;">
                                                            <h5 class="modal-title">
                                                                <i class="bi bi-file-text me-2"></i>
                                                                Appointment Notes
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <strong>Date:</strong> <?= htmlspecialchars($appointment['formatted_date']) ?>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <strong>Time:</strong> <?= htmlspecialchars($appointment['time']) ?>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <strong>Doctor:</strong> <?= htmlspecialchars($appointment['doctor_name'] ?: 'Not assigned') ?>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <strong>Status:</strong> 
                                                                    <span class="status-badge <?= $statusClass ?> ms-2">
                                                                        <?= htmlspecialchars($appointment['status_name']) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h6><strong>Medical Notes:</strong></h6>
                                                            <div class="p-3 bg-light rounded" style="max-height: 300px; overflow-y: auto;">
                                                                <?= nl2br(htmlspecialchars($appointment['notes'])) ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="bi bi-x-lg me-1"></i>Close
                                                            </button>
                                                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                                                <i class="bi bi-printer me-1"></i>Print Notes
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="bi bi-dash-circle me-1"></i>
                                                No notes
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <h4>No Appointment History</h4>
                    <p>This patient has no recorded appointments yet.</p>
                    <div class="mt-3">
                        <small class="text-muted">
                            Appointment records will appear here once the patient schedules their first visit.
                        </small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php elseif (!empty($studentID)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Patient Not Found!</strong> Please select a different patient.
            </div>
        <?php endif; ?>
    </div>
</main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  
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
                // Ensure expanded classes are applied on larger screens
                sidebar.classList.remove('sidebar-collapsed');
                header.classList.add('header-expanded');
                mainContent.classList.add('main-expanded');
            }
        }
        
        // Toggle sidebar event
        sidebarToggle.addEventListener('click', toggleSidebar);
        
        // Handle overlay click
        sidebarOverlay.addEventListener('click', function() {
            if (!sidebar.classList.contains('sidebar-collapsed')) {
                toggleSidebar();
            }
        });
        
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
    });
  </script>
</body>
</html>
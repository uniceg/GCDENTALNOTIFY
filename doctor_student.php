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

// Handle search
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchTermLike = '%' . $conn->real_escape_string($searchTerm) . '%';

// Get the selected filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get counts for each status - ONLY for this doctor
$countQuery = "SELECT a.StatusID, COUNT(*) as count 
               FROM appointments a 
               WHERE a.DoctorID = ?
               GROUP BY a.StatusID";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("s", $doctorID);
$countStmt->execute();
$countResult = $countStmt->get_result();

$statusCounts = [
    'Pending' => 0,
    'Approved' => 0,
    'Completed' => 0,
    'Cancelled' => 0,
    'Cancellation Requested' => 0
];

while ($row = $countResult->fetch_assoc()) {
    switch ($row['StatusID']) {
        case 1: $statusCounts['Pending'] = $row['count']; break;
        case 2: $statusCounts['Approved'] = $row['count']; break;
        case 3: $statusCounts['Completed'] = $row['count']; break;
        case 4: $statusCounts['Cancelled'] = $row['count']; break;
        case 5: $statusCounts['Cancellation Requested'] = $row['count']; break;
    }
}

// Modified main query to show ONLY this doctor's appointments
$query = "
    SELECT 
        students.StudentID,
        students.FirstName,
        students.LastName,
        students.ContactNumber,
        appointments.AppointmentID,
        appointments.StatusID,
        appointments.AppointmentDate,
        appointments.DoctorID,
        appointments.Reason,
        s.status_name,
        COALESCE(
            (SELECT cancellation_reason 
             FROM notifications 
             WHERE appointmentID = appointments.AppointmentID 
             AND cancellation_reason IS NOT NULL 
             ORDER BY notificationID DESC 
             LIMIT 1),
            'No reason provided'
        ) as cancellation_reason,
        d.FirstName AS DoctorFirstName,
        d.LastName AS DoctorLastName,
        ts.StartTime,
        ts.EndTime
    FROM students
    INNER JOIN appointments ON students.StudentID = appointments.StudentID
    LEFT JOIN status s ON appointments.StatusID = s.statusID
    LEFT JOIN doctors d ON appointments.DoctorID = d.DoctorID
    LEFT JOIN timeslots ts ON appointments.SlotID = ts.SlotID
    WHERE appointments.DoctorID = ?"; // Filter by logged-in doctor

$whereConditions = [];
$params = [$doctorID]; // Start with doctor ID
$types = "s";

if ($searchTerm) {
    $whereConditions[] = "(students.FirstName LIKE ? OR students.LastName LIKE ?)";
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $types .= "ss";
}

if ($statusFilter !== 'all') {
    $whereConditions[] = "appointments.StatusID = ?";
    switch ($statusFilter) {
        case 'Pending': $params[] = 1; break;
        case 'Approved': $params[] = 2; break;
        case 'Completed': $params[] = 3; break;
        case 'Cancelled': $params[] = 4; break;
        case 'Cancellation Requested': $params[] = 5; break;
    }
    $types .= "i";
}

if (!empty($whereConditions)) {
    $query .= " AND " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY 
    CASE 
        WHEN appointments.StatusID = 5 THEN 1  -- Cancellation Requested
        WHEN DATE(appointments.AppointmentDate) = CURDATE() THEN 2
        WHEN DATE(appointments.AppointmentDate) > CURDATE() THEN 3
        ELSE 4
    END,
    DATE(appointments.AppointmentDate) ASC,
    ts.StartTime ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointmentID = $_POST['appointment_id'];
    
    // Verify this appointment belongs to the logged-in doctor
    $verify_appointment_sql = "SELECT * FROM appointments WHERE AppointmentID = ? AND DoctorID = ?";
    $verify_appointment_stmt = $conn->prepare($verify_appointment_sql);
    $verify_appointment_stmt->bind_param("is", $appointmentID, $doctorID);
    $verify_appointment_stmt->execute();
    $verify_result = $verify_appointment_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $_SESSION['error_message'] = "You can only manage your own appointments.";
        header("Location: doctor_student.php");
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get appointment details
        $getDetails = $conn->prepare("SELECT StudentID, AppointmentDate, DoctorID, StatusID FROM appointments WHERE AppointmentID = ?");
        $getDetails->bind_param("i", $appointmentID);
        $getDetails->execute();
        $appointment_result = $getDetails->get_result();
        $appointment = $appointment_result->fetch_assoc();
        
        if ($appointment) {
            // Get student details
            $getStudent = $conn->prepare("SELECT email, FirstName, LastName FROM students WHERE StudentID = ?");
            $getStudent->bind_param("i", $appointment['StudentID']);
            $getStudent->execute();
            $student = $getStudent->get_result()->fetch_assoc();
            
            if (isset($_POST['action'])) {
                $action = $_POST['action'];
                
                if ($action === 'approve_appointment') {
                    // Update appointment status to Approved
                    $updateQuery = "UPDATE appointments SET StatusID = 2 WHERE AppointmentID = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("i", $appointmentID);
                    $updateStmt->execute();
                    
                    $message = "Your appointment with Dr. " . $doctorInfo['FirstName'] . " " . $doctorInfo['LastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been approved.";
                    
                    $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                    $insertNotification->bind_param("iis", $appointment['StudentID'], $appointmentID, $message);
                    $insertNotification->execute();
                    
                    $_SESSION['success_message'] = "Appointment has been approved successfully.";
                }
                else if ($action === 'complete') {
                    // Update appointment status to Completed
                    $updateQuery = "UPDATE appointments SET StatusID = 3 WHERE AppointmentID = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("i", $appointmentID);
                    $updateStmt->execute();
                    
                    $message = "Congratulations! Your appointment with Dr. " . $doctorInfo['FirstName'] . " " . $doctorInfo['LastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been completed. Please check for your results or follow-up instructions.";
                    
                    $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                    $insertNotification->bind_param("iis", $appointment['StudentID'], $appointmentID, $message);
                    $insertNotification->execute();
                    
                    $_SESSION['success_message'] = "Appointment has been marked as completed.";
                }
                else if ($action === 'cancel') {
                    // Update appointment status to Cancelled
                    $updateQuery = "UPDATE appointments SET StatusID = 4 WHERE AppointmentID = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("i", $appointmentID);
                    $updateStmt->execute();
                    
                    $message = "Your appointment with Dr. " . $doctorInfo['FirstName'] . " " . $doctorInfo['LastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been cancelled.";
                    
                    $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                    $insertNotification->bind_param("iis", $appointment['StudentID'], $appointmentID, $message);
                    $insertNotification->execute();
                    
                    $_SESSION['success_message'] = "Appointment has been cancelled.";
                }
                else if ($action === 'approve') {
                    // Approve cancellation request - Update to Cancelled
                    $updateQuery = "UPDATE appointments SET StatusID = 4 WHERE AppointmentID = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("i", $appointmentID);
                    $updateStmt->execute();
                    
                    $message = "Your cancellation request for the appointment with Dr. " . $doctorInfo['FirstName'] . " " . $doctorInfo['LastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been approved.";
                    
                    $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                    $insertNotification->bind_param("iis", $appointment['StudentID'], $appointmentID, $message);
                    $insertNotification->execute();
                    
                    $_SESSION['success_message'] = "Cancellation request has been approved.";
                }
                else if ($action === 'reject') {
                    // Reject cancellation request - Update back to Approved
                    $updateQuery = "UPDATE appointments SET StatusID = 2 WHERE AppointmentID = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("i", $appointmentID);
                    $updateStmt->execute();
                    
                    $message = "Your cancellation request for the appointment with Dr. " . $doctorInfo['FirstName'] . " " . $doctorInfo['LastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been rejected. The appointment is still scheduled.";
                    
                    $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                    $insertNotification->bind_param("iis", $appointment['StudentID'], $appointmentID, $message);
                    $insertNotification->execute();
                    
                    $_SESSION['success_message'] = "Cancellation request has been rejected.";
                }
            }
            
            // Commit transaction
            $conn->commit();
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    }
    
    // Redirect with current filter
    $redirectUrl = "doctor_student.php";
    if ($statusFilter !== 'all') {
        $redirectUrl .= "?status=" . urlencode($statusFilter);
    }
    if ($searchTerm) {
        $redirectUrl .= ($statusFilter !== 'all' ? '&' : '?') . "search=" . urlencode($searchTerm);
    }
    header("Location: " . $redirectUrl);
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Appointments - Dr. <?= htmlspecialchars($doctorInfo['FirstName']) ?> - Medical Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <!-- Your existing CSS styles -->
  <style>
    :root {
        --primary: #2e7d32;
        --primary-light: #60ad5e;
        --primary-dark: #1b5e20;
        --text-dark: #263238;
        --text-medium: #546e7a;
        --text-light: #78909c;
        --surface-light: #f5f7fa;
        --surface-medium: #e1e5eb;
        --surface-dark: #cfd8dc;
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
        overflow-x: hidden;
    }
    
    /* Ensure these styles match doctor_dashboard.php exactly */
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
        line-height: 1.3;
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
        transition: transform 0.2s;
    }

    .sidebar-menu a:hover i {
        transform: translateX(3px);
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
        margin-left: 0; /* Start with no margin like doctor_dashboard */
    }
    
    .header-expanded {
        margin-left: 250px;
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
    
    /* Toggle sidebar button */
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
        position: relative;
        z-index: 91;
    }
    
    .toggle-sidebar:active {
        transform: scale(0.95);
    }

    .toggle-sidebar i {
        font-size: 1.5rem;
    }
    
    /* Main content */
    .main-content {
        margin-left: 0; /* Start with no margin like doctor_dashboard */
        padding: 20px;
        transition: all 0.3s ease;
        background-color: var(--surface-light);
    }
    
    .main-expanded {
        margin-left: 250px;
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
    
    /* Keep your existing responsive styles but update these */
    @media (max-width: 1200px) {
        .main-content {
            padding: 18px;
        }
    }

    @media (max-width: 992px) {
        .main-content {
            padding: 15px;
        }
        
        .sidebar {
            transform: translateX(-250px);
        }
        
        .header, .main-content {
            margin-left: 0 !important;
        }
        
        .toggle-sidebar {
            display: flex;
        }
    }
    
    @media (max-width: 768px) {
        .header-title {
            font-size: 1.3rem;
        }
        
        .main-content {
            padding: 15px;
        }
    }
    
    @media (max-width: 576px) {
        .header {
            padding: 15px;
        }
        
        .header-title {
            font-size: 1.2rem;
        }
        
        .main-content {
            padding: 15px;
        }
    }
    
    /* Very small device optimizations */
    @media (max-width: 360px) {
        .header {
            padding: 10px;
        }
        
        .header-title {
            font-size: 1.1rem;
        }
    }

    /* Better touch interactions for mobile */
    @media (hover: none) {
        .sidebar-menu a:hover {
            background: var(--primary);
            padding-left: 18px;
        }
        
        .sidebar-menu a:active {
            background: var(--primary-light);
        }
    }

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
  </style>
</head>

<body>
  <!-- Your existing sidebar -->
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
        <button class="toggle-sidebar me-3" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="header-title">My Appointments - Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?></h1>
    </div>
    
    <div class="header-actions">
        <!-- Removed specialization and print button -->
    </div>
  </header>

  <!-- Sidebar overlay -->
  <div id="sidebarOverlay" class="sidebar-overlay"></div>

  <main class="main-content main-expanded" id="mainContent">
    <!-- Page header -->
    <div class="page-header">
        <h1><i class="bi bi-calendar-check me-2"></i>My Appointments</h1>
        <p class="mb-0 text-muted">
            Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?> 
            - Managing appointments assigned to you only
        </p>
    </div>
    
    <!-- Display success/error messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <!-- Statistics cards for THIS doctor only -->
    <div class="row g-3 mb-4">
        <?php
        // Calculate total patients for this doctor
        $totalPatients = $conn->prepare("SELECT COUNT(DISTINCT students.StudentID) as total FROM students INNER JOIN appointments ON students.StudentID = appointments.StudentID WHERE appointments.DoctorID = ?");
        $totalPatients->bind_param("s", $doctorID);
        $totalPatients->execute();
        $totalPatientsCount = $totalPatients->get_result()->fetch_assoc()['total'] ?? 0;
        ?>
        <div class="col-12 col-sm-6 col-lg-3 mb-2 mb-lg-0">
            <div class="card shadow-sm border-0 text-center py-3 h-100">
                <div class="mb-2"><i class="bi bi-people-fill" style="font-size:1.7rem;color:#1976d2;"></i></div>
                <div class="fw-bold" style="font-size:1.05rem;">My Patients</div>
                <div class="fs-5 text-primary"><?php echo $totalPatientsCount; ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 mb-2 mb-lg-0">
            <div class="card shadow-sm border-0 text-center py-3 h-100">
                <div class="mb-2"><i class="bi bi-hourglass-split" style="font-size:1.5rem;color:#f9a825;"></i></div>
                <div class="fw-bold" style="font-size:0.98rem;">My Pending</div>
                <div class="fs-6"><span class="badge bg-warning text-dark"><?php echo $statusCounts['Pending']; ?></span></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 mb-2 mb-lg-0">
            <div class="card shadow-sm border-0 text-center py-3 h-100">
                <div class="mb-2"><i class="bi bi-check-circle-fill" style="font-size:1.5rem;color:#43a047;"></i></div>
                <div class="fw-bold" style="font-size:0.98rem;">My Approved</div>
                <div class="fs-6"><span class="badge bg-success"><?php echo $statusCounts['Approved']; ?></span></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 mb-2 mb-lg-0">
            <div class="card shadow-sm border-0 text-center py-3 h-100">
                <div class="mb-2"><i class="bi bi-clipboard-check-fill" style="font-size:1.5rem;color:#1976d2;"></i></div>
                <div class="fw-bold" style="font-size:0.98rem;">My Completed</div>
                <div class="fs-6"><span class="badge bg-primary"><?php echo $statusCounts['Completed']; ?></span></div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm border-0 p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <h2 class="mb-0 flex-grow-1" style="font-weight:700; letter-spacing:0.5px; color:#011f4b;">My Appointment Management</h2>
            <form method="GET" action="doctor_student.php" class="search-form" style="min-width:220px; max-width:350px; width:100%;">
                <div class="input-group shadow-sm rounded-pill overflow-hidden">
                    <input type="text" class="form-control border-0" style="background:#f4f6fa; border-radius: 50px 0 0 50px;" placeholder="Search by Patient Name" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button class="btn btn-primary px-4 rounded-end-pill" type="submit" style="background: linear-gradient(90deg,#4a90e2,#357abd); border:none; font-weight:600;">Search</button>
                </div>
            </form>
        </div>

        <!-- Filter buttons -->
        <div class="filter-buttons mb-4">
            <a href="?status=all<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                All <span class="badge bg-light text-dark"><?php echo array_sum($statusCounts); ?></span>
            </a>
            <a href="?status=Pending<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                Pending <span class="badge bg-light text-dark"><?php echo $statusCounts['Pending']; ?></span>
            </a>
            <a href="?status=Approved<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Approved' ? 'btn-success' : 'btn-outline-success'; ?>">
                Approved <span class="badge bg-light text-dark"><?php echo $statusCounts['Approved']; ?></span>
            </a>
            <a href="?status=Completed<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Completed' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                Completed <span class="badge bg-light text-dark"><?php echo $statusCounts['Completed']; ?></span>
            </a>
            <a href="?status=Cancelled<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Cancelled' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                Cancelled <span class="badge bg-light text-dark"><?php echo $statusCounts['Cancelled']; ?></span>
            </a>
            <a href="?status=Cancellation Requested<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Cancellation Requested' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                Cancellation Requests <span class="badge bg-light text-dark"><?php echo $statusCounts['Cancellation Requested']; ?></span>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle table-responsive">
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Contact Information</th>
                        <th>Appointment Date</th>
                        <th>Service/Reason</th>
                        <th>Current Status</th>
                        <th>Actions</th>
                        <th>Upload Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['FirstName']) . ' ' . htmlspecialchars($row['LastName']); ?></td>
                            <td><?php echo htmlspecialchars($row['ContactNumber']); ?></td>
                            <td><?php echo htmlspecialchars(date('F j, Y', strtotime($row['AppointmentDate']))); ?></td>
                            <td>
                                <?php 
                                if ($row['StatusID'] == 5 || $row['StatusID'] == 4) {
                                    echo '<div class="text-danger">';
                                    echo '<strong>Cancellation Reason:</strong><br>';
                                    echo htmlspecialchars($row['cancellation_reason']);
                                    echo '</div>';
                                } else {
                                    echo '<div><strong>Service/Reason:</strong><br>' . htmlspecialchars($row['Reason']) . '</div>'; 
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $statusText = "Pending";
                                $statusBadge = "<span class='badge bg-warning text-dark'>Pending</span>";
                                switch ($row['StatusID']) {
                                    case 1: 
                                        $statusText = "Pending"; 
                                        $statusBadge = "<span class='badge bg-warning text-dark'>Pending</span>"; 
                                        break;
                                    case 2: 
                                        $statusText = "Approved"; 
                                        $statusBadge = "<span class='badge bg-success'>Approved</span>"; 
                                        break;
                                    case 3: 
                                        $statusText = "Completed"; 
                                        $statusBadge = "<span class='badge bg-primary'>Completed</span>"; 
                                        break;
                                    case 4: 
                                        $statusText = "Cancelled"; 
                                        $statusBadge = "<span class='badge bg-danger'>Cancelled</span>"; 
                                        break;
                                    case 5: 
                                        $statusText = "Cancellation Requested"; 
                                        $statusBadge = "<span class='badge bg-warning'>Cancellation Requested</span>"; 
                                        break;
                                }
                                echo $statusBadge;
                                ?>
                            </td>
                            <td>
                                <form method="POST" action="doctor_student.php" class="d-flex flex-column gap-1">
                                    <input type="hidden" name="appointment_id" value="<?php echo $row['AppointmentID']; ?>">
                                    <?php if ($row['StatusID'] == 5): // Cancellation Requested ?>
                                        <button type="submit" name="action" value="approve" class="btn btn-danger btn-sm">
                                            <i class="bi bi-check-lg"></i> Approve Cancellation
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-success btn-sm">
                                            <i class="bi bi-x-lg"></i> Reject Cancellation
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="approve_appointment" class="btn btn-success btn-sm <?php echo $row['StatusID'] == 2 || $row['StatusID'] == 3 ? 'disabled' : ''; ?>">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="complete" class="btn btn-primary btn-sm <?php echo $row['StatusID'] == 3 ? 'disabled' : ''; ?>">
                                            <i class="bi bi-check-circle"></i> Complete
                                        </button>
                                        <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm <?php echo $row['StatusID'] == 4 ? 'disabled' : ''; ?>">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td>
                                <?php
                                // Check if there's an uploaded file for this appointment
                                $fileQuery = "SELECT FilePath, FileName FROM test_results WHERE AppointmentID = ?";
                                $fileStmt = $conn->prepare($fileQuery);
                                $fileStmt->bind_param("i", $row['AppointmentID']);
                                $fileStmt->execute();
                                $fileResult = $fileStmt->get_result();
                                
                                if ($fileResult->num_rows > 0) {
                                    $fileData = $fileResult->fetch_assoc();
                                    echo '<div class="mb-2">';
                                    echo '<a href="' . htmlspecialchars($fileData['FilePath']) . '" target="_blank" class="btn btn-sm btn-info">';
                                    echo '<i class="bi bi-file-earmark-text"></i> View File';
                                    echo '</a>';
                                    echo '</div>';
                                }
                                ?>
                                <form action="upload_result.php" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2 flex-wrap">
                                    <input type="hidden" name="appointment_id" value="<?php echo $row['AppointmentID']; ?>">
                                    <input type="file" name="result_file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.png" required>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Upload</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">No appointments found for your account.</p>
                                <p class="text-muted">Patients need to book appointments with you to see them here.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </main>

  <!-- Add Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Your existing JavaScript for sidebar toggle -->
  <script>
    // Add your existing sidebar toggle JavaScript here
    window.printDashboard = function() {
        window.print();
    }
  </script>
</body>
</html>
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

// Process form submissions
$message = '';
$alertType = '';

// Add available time slots
if (isset($_POST['add_timeslot'])) {
    $day = $_POST['day'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';

    if (!empty($day) && !empty($startTime) && !empty($endTime)) {
        // Check if the start time is before end time
        if (strtotime($startTime) < strtotime($endTime)) {
            // Check for duplicate time slot
            $check_sql = "SELECT SlotID FROM timeslots WHERE DoctorID = ? AND AvailableDay = ? AND StartTime = ? AND EndTime = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ssss", $doctorID, $day, $startTime, $endTime);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = "This time slot already exists for the selected day.";
                $alertType = "warning";
            } else {
                $sql = "INSERT INTO timeslots (DoctorID, AvailableDay, StartTime, EndTime, IsAvailable) 
                        VALUES (?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $doctorID, $day, $startTime, $endTime);
                
                if ($stmt->execute()) {
                    $message = "Time slot added successfully!";
                    $alertType = "success";
                } else {
                    $message = "Error adding time slot: " . $conn->error;
                    $alertType = "danger";
                }
                $stmt->close();
            }
            $check_stmt->close();
        } else {
            $message = "Start time must be before end time.";
            $alertType = "warning";
        }
    } else {
        $message = "Please fill all fields.";
        $alertType = "warning";
    }
}

// Block off dates - ONLY for this doctor
if (isset($_POST['block_date'])) {
    $blockDate = $_POST['block_date'] ?? '';
    $reason = $_POST['block_reason'] ?? '';

    if (!empty($blockDate)) {
        // Check if date is not in the past
        if (strtotime($blockDate) >= strtotime(date('Y-m-d'))) {
            // Check if date is already blocked
            $check_sql = "SELECT BlockID FROM blocked_dates WHERE DoctorID = ? AND BlockedDate = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ss", $doctorID, $blockDate);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = "This date is already blocked.";
                $alertType = "warning";
            } else {
                $sql = "INSERT INTO blocked_dates (DoctorID, BlockedDate, Reason) 
                        VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $doctorID, $blockDate, $reason);
                
                if ($stmt->execute()) {
                    $message = "Date blocked successfully!";
                    $alertType = "success";
                } else {
                    $message = "Error blocking date: " . $conn->error;
                    $alertType = "danger";
                }
                $stmt->close();
            }
            $check_stmt->close();
        } else {
            $message = "Cannot block dates in the past.";
            $alertType = "warning";
        }
    } else {
        $message = "Please select a date to block.";
        $alertType = "warning";
    }
}

// Remove time slot - ONLY for this doctor
if (isset($_GET['remove_slot']) && is_numeric($_GET['remove_slot'])) {
    $slotID = $_GET['remove_slot'];

    // Verify the slot belongs to this doctor
    $verify_sql = "SELECT SlotID FROM timeslots WHERE SlotID = ? AND DoctorID = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("is", $slotID, $doctorID);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $message = "You can only remove your own time slots.";
        $alertType = "danger";
    } else {
        $sql = "DELETE FROM timeslots WHERE SlotID = ? AND DoctorID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $slotID, $doctorID);
        
        if ($stmt->execute()) {
            $message = "Time slot removed successfully!";
            $alertType = "success";
        } else {
            $message = "Error removing time slot: " . $conn->error;
            $alertType = "danger";
        }
        $stmt->close();
    }
    $verify_stmt->close();
}

// Remove blocked date - ONLY for this doctor
if (isset($_GET['remove_block']) && is_numeric($_GET['remove_block'])) {
    $blockID = $_GET['remove_block'];

    // Verify the blocked date belongs to this doctor
    $verify_sql = "SELECT BlockID FROM blocked_dates WHERE BlockID = ? AND DoctorID = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("is", $blockID, $doctorID);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $message = "You can only remove your own blocked dates.";
        $alertType = "danger";
    } else {
        $sql = "DELETE FROM blocked_dates WHERE BlockID = ? AND DoctorID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $blockID, $doctorID);
        
        if ($stmt->execute()) {
            $message = "Blocked date removed successfully!";
            $alertType = "success";
        } else {
            $message = "Error removing blocked date: " . $conn->error;
            $alertType = "danger";
        }
        $stmt->close();
    }
    $verify_stmt->close();
}

// Get current time slots - ONLY for this doctor
$currentTimeSlots = [];
$sql = "SELECT SlotID, AvailableDay, StartTime, EndTime 
        FROM timeslots 
        WHERE DoctorID = ? 
        ORDER BY FIELD(AvailableDay, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), StartTime";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $doctorID);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Format time for display
    $row['formatted_start'] = date("h:i A", strtotime($row['StartTime']));
    $row['formatted_end'] = date("h:i A", strtotime($row['EndTime']));
    $currentTimeSlots[] = $row;
}
$stmt->close();

// Get blocked dates - ONLY for this doctor
$blockedDates = [];
$sql = "SELECT BlockID, BlockedDate, Reason 
        FROM blocked_dates 
        WHERE DoctorID = ? AND BlockedDate >= CURDATE()
        ORDER BY BlockedDate";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $doctorID);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Format date for display
    $row['formatted_date'] = date("F d, Y", strtotime($row['BlockedDate']));
    $blockedDates[] = $row;
}
$stmt->close();

// Days of week array
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Schedule - Dr. <?= htmlspecialchars($doctorInfo['FirstName']) ?> - Medical Clinic</title>
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

    .form-control, .form-select {
        border: 1px solid var(--surface-medium);
        border-radius: var(--radius-sm);
        padding: 10px 15px;
        font-size: 1rem;
        transition: border-color 0.2s;
    }

    .form-control:focus, .form-select:focus {
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

    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        border-radius: var(--radius-sm);
        font-weight: 500;
        padding: 10px 20px;
        transition: all 0.2s;
    }

    .btn-danger:hover {
        background-color: #c82333;
        border-color: #c82333;
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
    
    /* Schedule-specific styles */
    .schedule-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .day-badge {
        background-color: #c8e6c9;
        color: var(--primary);
        padding: 6px 12px;
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
        font-weight: 600;
        min-width: 100px;
        display: inline-block;
        text-align: center;
    }
    
    .time-badge {
        background-color: #bbdefb;
        color: #1976d2;
        padding: 6px 12px;
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .badge-blocked {
        background-color: #ffcdd2;
        color: #d32f2f;
        padding: 6px 12px;
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
        font-weight: 500;
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
    }
    
    .table td {
        vertical-align: middle;
        padding: 12px;
    }
    
    /* Alert styles */
    .alert {
        border-radius: var(--radius-sm);
        padding: 15px 20px;
        margin-bottom: 20px;
    }
    
    /* Guidelines list styles */
    .card-body ul {
        padding-left: 20px;
    }
    
    .card-body ul li {
        margin-bottom: 8px;
        line-height: 1.5;
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
        
        .day-badge, .time-badge {
            display: block;
            margin-bottom: 5px;
            min-width: auto;
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
        
        .btn-primary,
        .btn-danger {
            width: 100%;
            margin-bottom: 10px;
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
        <h1 class="header-title">My Schedule - Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?></h1>
    </div>
    
    <div class="header-actions">
        <!-- Removed all header action buttons -->
    </div>
  </header>

  <!-- Sidebar overlay -->
  <div id="sidebarOverlay" class="sidebar-overlay"></div>

  <!-- Main content with exact same structure as doctor_profile.php -->
  <main class="main-content main-expanded" id="mainContent">
    <div class="container-fluid">
        <!-- Page header -->
        <div class="page-header">
            <h1><i class="bi bi-calendar3 me-2"></i>My Schedule Configuration</h1>
            <p>Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?> - Configure your available days and time slots for appointments</p>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $alertType ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="schedule-container">
            <div class="row">
                <!-- Add Available Time Slots -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-clock me-2"></i>Add Available Time Slots
                        </div>
                        <div class="card-body">
                            <form action="" method="POST">
                                <div class="mb-3">
                                    <label for="day" class="form-label">Day of Week</label>
                                    <select name="day" id="day" class="form-select" required>
                                        <option value="">-- Select Day --</option>
                                        <?php foreach ($daysOfWeek as $day): ?>
                                            <option value="<?= $day ?>"><?= $day ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="start_time" class="form-label">Start Time</label>
                                        <input type="time" class="form-control" id="start_time" name="start_time" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="end_time" class="form-label">End Time</label>
                                        <input type="time" class="form-control" id="end_time" name="end_time" required>
                                    </div>
                                </div>
                                <button type="submit" name="add_timeslot" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Add Time Slot
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Current Time Slots -->
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-calendar-week me-2"></i>Current Available Time Slots
                        </div>
                        <div class="card-body">
                            <?php if (count($currentTimeSlots) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Day</th>
                                                <th>Time</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($currentTimeSlots as $slot): ?>
                                                <tr>
                                                    <td>
                                                        <span class="day-badge"><?= htmlspecialchars($slot['AvailableDay']) ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="time-badge">
                                                            <?= htmlspecialchars($slot['formatted_start']) ?> - <?= htmlspecialchars($slot['formatted_end']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="?remove_slot=<?= $slot['SlotID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this time slot?')">
                                                            <i class="bi bi-trash"></i> Remove
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>No time slots have been added yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Block Off Dates -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-calendar-x me-2"></i>Block Off Unavailable Dates
                        </div>
                        <div class="card-body">
                            <form action="" method="POST">
                                <div class="mb-3">
                                    <label for="block_date" class="form-label">Select Date to Block</label>
                                    <input type="date" class="form-control" id="block_date" name="block_date" min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="block_reason" class="form-label">Reason (Optional)</label>
                                    <input type="text" class="form-control" id="block_reason" name="block_reason" placeholder="e.g., Vacation, Meeting, etc.">
                                </div>
                                <button type="submit" name="block_date" class="btn btn-danger">
                                    <i class="bi bi-x-circle me-2"></i>Block This Date
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Current Blocked Dates -->
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-calendar-minus me-2"></i>Currently Blocked Dates
                        </div>
                        <div class="card-body">
                            <?php if (count($blockedDates) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Reason</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($blockedDates as $date): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge-blocked">
                                                            <?= htmlspecialchars($date['formatted_date']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= !empty($date['Reason']) ? htmlspecialchars($date['Reason']) : '<em class="text-muted">No reason provided</em>' ?>
                                                    </td>
                                                    <td>
                                                        <a href="?remove_block=<?= $date['BlockID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to unblock this date?')">
                                                            <i class="bi bi-trash"></i> Remove
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>No dates are currently blocked.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Schedule Guidelines -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>Schedule Guidelines
                </div>
                <div class="card-body">
                    <ul>
                        <li>Add your regular weekly availability by creating time slots for each day you're available.</li>
                        <li>Use the "Block Off Unavailable Dates" section to mark specific dates when you're not available (vacations, meetings, etc.).</li>
                        <li>Students will only be able to book appointments during your available time slots and on days that aren't blocked.</li>
                        <li>You cannot select dates in the past to block off.</li>
                        <li>Make sure to keep your schedule updated to prevent scheduling conflicts.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Exact same JavaScript as doctor_profile.php -->
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
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bootstrapAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bootstrapAlert.close();
        }, 5000);
    });
    
    // Set minimum date for date picker
    const today = new Date().toISOString().split('T')[0];
    const blockDateInput = document.getElementById('block_date');
    if (blockDateInput) {
        blockDateInput.setAttribute('min', today);
    }
});
  </script>
</body>
</html>
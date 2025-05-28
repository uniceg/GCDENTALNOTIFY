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

$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Upcoming Appointments
$sql = "SELECT a.*, s.StartTime, s.EndTime, st.firstName, st.lastName 
        FROM appointments a
        JOIN timeslots s ON a.SlotID = s.SlotID
        JOIN students st ON a.StudentID = st.studentID
        WHERE a.DoctorID = ?
        AND a.AppointmentDate BETWEEN ? AND ?
        AND a.statusID IN (1, 2)
        ORDER BY a.AppointmentDate, s.StartTime";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $doctorID, $today, $week_end);
$stmt->execute();
$result = $stmt->get_result();
$appointments = [];
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();

// Today's Patients Count
$today_sql = "SELECT COUNT(*) AS count FROM appointments 
              WHERE DoctorID = ? 
              AND AppointmentDate = ? 
              AND statusID IN (2, 3)";
$today_stmt = $conn->prepare($today_sql);
$today_stmt->bind_param("ss", $doctorID, $today);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
$today_count = $today_result->fetch_assoc()['count'];
$today_stmt->close();

// This Week's Patients Count
$week_sql = "SELECT COUNT(*) AS count FROM appointments 
             WHERE DoctorID = ? 
             AND AppointmentDate BETWEEN ? AND ? 
             AND statusID IN (2, 3)";
$week_stmt = $conn->prepare($week_sql);
$week_stmt->bind_param("sss", $doctorID, $week_start, $week_end);
$week_stmt->execute();
$week_result = $week_stmt->get_result();
$week_count = $week_result->fetch_assoc()['count'];
$week_stmt->close();

// This Month's Patients Count
$month_sql = "SELECT COUNT(*) AS count FROM appointments 
              WHERE DoctorID = ? 
              AND AppointmentDate BETWEEN ? AND ? 
              AND statusID IN (2, 3)";
$month_stmt = $conn->prepare($month_sql);
$month_stmt->bind_param("sss", $doctorID, $month_start, $month_end);
$month_stmt->execute();
$month_result = $month_stmt->get_result();
$month_count = $month_result->fetch_assoc()['count'];
$month_stmt->close();

// Patients Handled Count (Total)
$count_sql = "SELECT COUNT(*) AS count FROM appointments WHERE DoctorID = ? AND statusID = 3";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("s", $doctorID);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$handled_count = $count_result->fetch_assoc()['count'];
$count_stmt->close();

// Close the doctor verification statement
$doctor_verify_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - Dr. <?= htmlspecialchars($doctorInfo['FirstName']) ?> - Medical Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  
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
        overflow-x: hidden;
    }
    
    .app-container {
        display: grid;
        min-height: 100vh;
        grid-template-columns: auto 1fr;
        grid-template-rows: auto 1fr;
        grid-template-areas: 
            "sidebar header"
            "sidebar main";
    }
    
    .sidebar {
        grid-area: sidebar;
        width: 250px;
        background: var(--primary);
        transition: all 0.3s ease;
        position: fixed;
        height: 100vh;
        z-index: 100;
        box-shadow: var(--shadow-md);
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
    }
    
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
    
    .toggle-sidebar:hover {
        background-color: var(--surface-light);
    }
    
    .toggle-sidebar:active {
        transform: scale(0.95);
    }
    
    .toggle-sidebar i {
        font-size: 1.5rem;
    }
    
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
    
    .welcome-banner {
        background: linear-gradient(145deg, #ffffff 0%, #f9fbff 100%);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(46, 125, 50, 0.08);
        padding: 32px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 24px;
        margin-bottom: 30px;
        border: 1px solid rgba(46, 125, 50, 0.1);
        position: relative;
    }

    .welcome-banner .avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        color: white;
        box-shadow: 0 8px 25px rgba(46, 125, 50, 0.25);
        border: 4px solid white;
        overflow: hidden;
        position: relative;
    }

    .welcome-banner h2 {
        margin: 0;
        font-size: 2rem;
        font-weight: 600;
        color: var(--primary);
    }

    .welcome-banner p {
        margin: 5px 0 0;
        color: var(--text-medium);
        font-size: 1rem;
    }
    
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }

    .stat-box {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .stat-box:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }

    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 15px;
        color: var(--primary);
    }

    .stat-value {
        font-size: 2.2rem;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-dark);
    }

    .stat-label {
        font-size: 1rem;
        color: var(--text-medium);
    }

    .card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 30px;
    }

    .card h4 {
        margin-top: 0;
        margin-bottom: 20px;
        color: var(--primary);
        font-weight: 600;
        font-size: 1.3rem;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--surface-medium);
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 15px;
        border-radius: 8px;
    }

    .table {
        min-width: 800px;
    }

    .doctor-photo {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 8px 25px rgba(46, 125, 50, 0.25);
    }
    
    @media (max-width: 992px) {
        .main-content {
            padding: 15px;
        }
        
        .stats-container {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-container {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .welcome-banner {
            flex-direction: column;
            text-align: center;
            padding: 20px;
        }
        
        .header-title {
            font-size: 1.3rem;
        }
    }

    @media (max-width: 576px) {
        .stats-container {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .stat-box {
            padding: 15px;
        }
    }
  </style>
</head>
<body>

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
    
    <div class="sidebar-divider" style="margin-top: auto;"></div>
    <ul class="sidebar-menu">
        <li><a href="doctor_login.php" class="logout-link" onclick="return confirmLogout()">
            <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
        </a></li>
    </ul>
</aside>

<header class="header header-expanded" id="header">
    <div class="d-flex align-items-center">
        <button class="toggle-sidebar me-3" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="header-title">Dashboard - Dr. <?= htmlspecialchars($doctorInfo['FirstName'] . ' ' . $doctorInfo['LastName']) ?></h1>
    </div>
    
    <div class="header-actions">
        <button onclick="printDashboard()" class="btn btn-sm btn-light no-print">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
</header>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<main class="main-content main-expanded" id="mainContent">
    <!-- Welcome banner with doctor photo -->
    <div class="welcome-banner">
        <div class="avatar">
            <?php if (!empty($doctorInfo['ProfilePhoto']) && file_exists($doctorInfo['ProfilePhoto'])): ?>
                <img src="<?= htmlspecialchars($doctorInfo['ProfilePhoto']) ?>" 
                     alt="Dr. <?= htmlspecialchars($doctorInfo['FirstName']) ?>" 
                     class="doctor-photo">
            <?php else: ?>
                <i class="bi bi-person-circle"></i>
            <?php endif; ?>
        </div>
        <div class="welcome-text">
            <h2>Welcome, Dr. <?= htmlspecialchars($doctorInfo['FirstName']) ?></h2>
            <p><?= htmlspecialchars($doctorInfo['Specialization']) ?> - Your personalized dashboard</p>
            <small class="text-muted">
                Last login: <?= isset($_SESSION['login_time']) ? date('M d, Y h:i A', strtotime($_SESSION['login_time'])) : 'Today' ?>
            </small>
        </div>
    </div>

    <!-- Stats cards with proper data display -->
    <div class="stats-container">
        <div class="stat-box today">
            <div class="stat-icon"><i class="bi bi-calendar-day"></i></div>
            <div class="stat-value"><?= (int)$today_count ?></div>
            <div class="stat-label">My Patients Today</div>
        </div>
        
        <div class="stat-box week">
            <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
            <div class="stat-value"><?= (int)$week_count ?></div>
            <div class="stat-label">My Patients This Week</div>
        </div>
        
        <div class="stat-box month">
            <div class="stat-icon"><i class="bi bi-calendar-month"></i></div>
            <div class="stat-value"><?= (int)$month_count ?></div>
            <div class="stat-label">My Patients This Month</div>
        </div>
        
        <div class="stat-box total">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= (int)$handled_count ?></div>
            <div class="stat-label">Total Completed</div>
        </div>
    </div>

    <!-- Appointments table -->
    <div class="card">
        <h4><i class="bi bi-calendar-check me-2"></i>My Upcoming Appointments</h4>
        <p class="text-muted mb-3">Appointments scheduled for you from today through this week</p>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Appointment ID</th>
                        <th>Patient Name</th>
                        <th>Date</th>
                        <th>Time Slot</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($appointments) > 0): ?>
                    <?php foreach ($appointments as $row): ?>
                        <tr>
                            <td data-label="Appointment ID"><?= $row['AppointmentID'] ?></td>
                            <td data-label="Patient Name"><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?></td>
                            <td data-label="Date"><?= date('M d, Y', strtotime($row['AppointmentDate'])) ?></td>
                            <td data-label="Time Slot"><?= date('h:i A', strtotime($row['StartTime'])) . ' - ' . date('h:i A', strtotime($row['EndTime'])) ?></td>
                            <td data-label="Reason"><?= htmlspecialchars($row['Reason']) ?></td>
                            <td data-label="Status">
                                <?php
                                switch ($row['statusID']) {
                                    case 1: echo '<span class="badge bg-warning">Pending</span>'; break;
                                    case 2: echo '<span class="badge bg-success">Approved</span>'; break;
                                    case 3: echo '<span class="badge bg-primary">Completed</span>'; break;
                                    case 4: echo '<span class="badge bg-danger">Cancelled</span>'; break;
                                    case 5: echo '<span class="badge bg-warning">Cancel Requested</span>'; break;
                                    default: echo '<span class="badge bg-secondary">Unknown</span>';
                                }
                                ?>
                            </td>
                            <td data-label="Actions">
                                <a href="doctor_student.php?appointment_id=<?= $row['AppointmentID'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Manage
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding: 40px;">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">No upcoming appointments scheduled for you</p>
                            <p class="text-muted">When patients book appointments with you, they'll appear here.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($appointments) > 0): ?>
            <div class="text-center mt-3">
                <a href="doctor_student.php" class="btn btn-primary">
                    <i class="bi bi-calendar-check me-2"></i>View All My Appointments
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <h4><i class="bi bi-lightning me-2"></i>Quick Actions</h4>
        <div class="row">
            <div class="col-md-3 mb-3">
                <a href="doctor_student.php" class="btn btn-outline-primary w-100 p-3">
                    <i class="bi bi-calendar-check d-block mb-2" style="font-size: 2rem;"></i>
                    Manage My Appointments
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="student_viewer.php" class="btn btn-outline-success w-100 p-3">
                    <i class="bi bi-people d-block mb-2" style="font-size: 2rem;"></i>
                    View My Patients
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="doctor_schedule.php" class="btn btn-outline-info w-100 p-3">
                    <i class="bi bi-calendar3 d-block mb-2" style="font-size: 2rem;"></i>
                    Update My Schedule
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="doctor_profile.php" class="btn btn-outline-warning w-100 p-3">
                    <i class="bi bi-person-gear d-block mb-2" style="font-size: 2rem;"></i>
                    Edit My Profile
                </a>
            </div>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const header = document.getElementById('header');
        const mainContent = document.querySelector('.main-content');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            if (sidebar.classList.contains('sidebar-collapsed')) {
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
        
        sidebarToggle.addEventListener('click', toggleSidebar);
        
        sidebarOverlay.addEventListener('click', function() {
            if (!sidebar.classList.contains('sidebar-collapsed')) {
                toggleSidebar();
            }
        });
        
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.add('sidebar-collapsed');
                header.classList.remove('header-expanded');
                mainContent.classList.remove('main-expanded');
            }
        });
        
        window.printDashboard = function() {
            window.print();
        }
        
        window.confirmLogout = function() {
            return confirm('Are you sure you want to logout?');
        }
        
        setInitialState();
    });
</script>
</body>
</html>

<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "medicalclinicnotify";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch daily appointments count
$queryDaily = "SELECT COUNT(*) AS total FROM appointments WHERE DATE(AppointmentDate) = CURDATE()";
$resultDaily = $conn->query($queryDaily);
$dailyTotal = $resultDaily->fetch_assoc()['total'] ?? 0;

// Fetch weekly appointments count
$queryWeekly = "SELECT COUNT(*) AS total FROM appointments WHERE YEARWEEK(AppointmentDate, 1) = YEARWEEK(CURDATE(), 1)";
$resultWeekly = $conn->query($queryWeekly);
$weeklyTotal = $resultWeekly->fetch_assoc()['total'] ?? 0;

// Fetch monthly appointments count
$queryMonthly = "SELECT COUNT(*) AS total FROM appointments WHERE MONTH(AppointmentDate) = MONTH(CURDATE()) AND YEAR(AppointmentDate) = YEAR(CURDATE())";
$resultMonthly = $conn->query($queryMonthly);
$monthlyTotal = $resultMonthly->fetch_assoc()['total'] ?? 0;

// Fetch cancellation count
$queryCancellations = "SELECT COUNT(*) AS total FROM appointments WHERE statusID = 4 AND MONTH(AppointmentDate) = MONTH(CURDATE()) AND YEAR(AppointmentDate) = YEAR(CURDATE())";
$resultCancellations = $conn->query($queryCancellations);
$cancellationsTotal = $resultCancellations->fetch_assoc()['total'] ?? 0;

// Fetch the most common reasons for appointments
$queryReasons = "SELECT Reason, COUNT(*) AS count FROM appointments GROUP BY Reason ORDER BY count DESC LIMIT 3";
$resultReasons = $conn->query($queryReasons);
$reasonsData = [];
while ($row = $resultReasons->fetch_assoc()) {
    $reasonsData[] = $row;
}

// Fetch the most common reasons for cancellation
$queryCancellationReasons = "SELECT n.cancellation_reason, COUNT(*) AS count 
                            FROM notifications n 
                            INNER JOIN appointments a ON n.appointmentID = a.AppointmentID 
                            WHERE n.cancellation_reason IS NOT NULL 
                            AND a.StatusID = 4 
                            GROUP BY n.cancellation_reason 
                            ORDER BY count DESC 
                            LIMIT 3";
$resultCancellationReasons = $conn->query($queryCancellationReasons);
$cancellationReasonsData = [];
while ($row = $resultCancellationReasons->fetch_assoc()) {
    $cancellationReasonsData[] = $row;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Medical Clinic</title>
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

        /* Report Specific Styles */
        .report-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            border: 1px solid rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }
        
        .report-container:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .summary-boxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .summary-box {
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
            text-align: center;
        }
        
        .summary-box::after {
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
        
        .summary-box:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .summary-box:hover::after {
            transform: scaleX(1);
        }

        .summary-box h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-medium);
            margin: 0 0 16px;
        }

        .summary-box p {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--admin-primary);
            margin: 0;
        }

        /* Different colors for different boxes */
        .summary-box:nth-child(1) p { color: #1976d2; }
        .summary-box:nth-child(2) p { color: #43a047; }
        .summary-box:nth-child(3) p { color: #f9a825; }
        .summary-box:nth-child(4) p { color: #dc3545; }

        .summary-box:nth-child(1)::after { background: linear-gradient(90deg, #5c9bd1, #1976d2); }
        .summary-box:nth-child(2)::after { background: linear-gradient(90deg, #7cb342, #43a047); }
        .summary-box:nth-child(3)::after { background: linear-gradient(90deg, #fdd835, #f9a825); }
        .summary-box:nth-child(4)::after { background: linear-gradient(90deg, #ef5350, #dc3545); }

        .reasons {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #eee;
            margin-bottom: 25px;
        }

        .reasons h3 {
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: var(--primary);
            font-weight: 600;
        }

        .reasons ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .reasons ul li {
            background-color: #f8f9fa;
            margin-bottom: 12px;
            padding: 15px 20px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .reasons ul li:hover {
            transform: translateX(5px);
            background-color: #e3f2fd;
            border-color: #1976d2;
        }

        .badge {
            font-size: 0.9rem;
            padding: 8px 12px;
        }

        /* Form Styles */
        .form-control {
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.15);
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
        }

        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            border-color: #6c757d;
            transform: translateY(-1px);
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
            
            .summary-boxes {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .summary-boxes {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .report-container {
                padding: 20px;
            }
            
            .summary-box {
                padding: 20px;
            }
            
            .summary-box h3 {
                font-size: 1.1rem;
            }
            
            .summary-box p {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .page-header,
            .report-container,
            .reasons {
                padding: 15px;
            }
            
            .summary-box {
                padding: 15px;
            }
            
            .summary-box h3 {
                font-size: 1rem;
                margin-bottom: 10px;
            }
            
            .summary-box p {
                font-size: 1.8rem;
            }
            
            .reasons h3 {
                font-size: 1.2rem;
            }
            
            .reasons ul li {
                padding: 12px 15px;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar, .header, .sidebar-overlay {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .report-container {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            
            .summary-box {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
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
                <h1 class="header-title">Reports</h1>
            </div>
            
            <div class="header-actions">
                <!-- Removed dashboard and logout buttons -->
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content main-expanded" id="mainContent">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="bi bi-graph-up me-2"></i>Appointment Reports</h1>
                <p>Comprehensive analytics and insights for clinic appointments and performance</p>
            </div>

            <!-- Report Container -->
            <div class="report-container">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
                    <h2 class="mb-0">Appointment Statistics</h2>
                    <div class="d-flex gap-2 flex-wrap">
                        <form class="d-flex align-items-center gap-2" id="dateRangeForm" method="GET" action="#">
                            <label class="form-label mb-0 me-1" for="dateFrom">From</label>
                            <input type="date" class="form-control form-control-sm" id="dateFrom" name="dateFrom" style="min-width:130px;">
                            <label class="form-label mb-0 ms-2 me-1" for="dateTo">To</label>
                            <input type="date" class="form-control form-control-sm" id="dateTo" name="dateTo" style="min-width:130px;">
                            <button type="submit" class="btn btn-primary btn-sm ms-2">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                        </form>
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()" data-bs-toggle="tooltip" title="Print or export this report">
                            <i class="bi bi-printer"></i> Export/Print
                        </button>
                    </div>
                </div>

                <div class="summary-boxes">
                    <div class="summary-box">
                        <h3><i class="bi bi-calendar-day me-2"></i>Daily Appointments</h3>
                        <p><?php echo $dailyTotal; ?></p>
                    </div>
                    <div class="summary-box">
                        <h3><i class="bi bi-calendar-week me-2"></i>Weekly Appointments</h3>
                        <p><?php echo $weeklyTotal; ?></p>
                    </div>
                    <div class="summary-box">
                        <h3><i class="bi bi-calendar-month me-2"></i>Monthly Appointments</h3>
                        <p><?php echo $monthlyTotal; ?></p>
                    </div>
                    <div class="summary-box">
                        <h3><i class="bi bi-x-circle me-2"></i>Monthly Cancellations</h3>
                        <p><?php echo $cancellationsTotal; ?></p>
                    </div>
                </div>

                <div class="reasons">
                    <div class="d-flex align-items-center mb-3 gap-2">
                        <h3 class="mb-0 flex-grow-1"><i class="bi bi-clipboard2-pulse me-2"></i>Most Common Appointment Reasons</h3>
                        <span class="text-muted small">
                            <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="Top 3 reasons for appointments in the selected period"></i>
                        </span>
                    </div>
                    <ul class="mb-0">
                        <?php if (empty($reasonsData)): ?>
                            <li>
                                <span class="text-muted">No appointment data available</span>
                            </li>
                        <?php else: ?>
                            <?php foreach ($reasonsData as $reason): ?>
                                <li>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">
                                        <i class="bi bi-clipboard2-pulse me-1"></i><?php echo htmlspecialchars($reason['Reason']); ?>
                                    </span>
                                    <span class="fw-semibold"><?php echo $reason['count']; ?> appointments</span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="reasons">
                    <div class="d-flex align-items-center mb-3 gap-2">
                        <h3 class="mb-0 flex-grow-1"><i class="bi bi-x-circle me-2"></i>Most Common Cancellation Reasons</h3>
                        <span class="text-muted small">
                            <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="Top 3 reasons for appointment cancellations"></i>
                        </span>
                    </div>
                    <ul class="mb-0">
                        <?php if (empty($cancellationReasonsData)): ?>
                            <li>
                                <span class="text-muted">No cancellation data available</span>
                            </li>
                        <?php else: ?>
                            <?php foreach ($cancellationReasonsData as $reason): ?>
                                <li>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">
                                        <i class="bi bi-x-circle me-1"></i><?php echo htmlspecialchars($reason['cancellation_reason']); ?>
                                    </span>
                                    <span class="fw-semibold"><?php echo $reason['count']; ?> cancellations</span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </main>
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
            
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Set today's date as default for date inputs
            const today = new Date().toISOString().split('T')[0];
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            
            if (dateFrom && dateTo) {
                // Set default date range to current month
                const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
                dateFrom.value = firstDayOfMonth;
                dateTo.value = today;
            }
            
            setInitialState();
        });
    </script>
</body>
</html>

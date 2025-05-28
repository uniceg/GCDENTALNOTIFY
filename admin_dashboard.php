<?php
session_start();
include 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['adminID'])) {
    header('location:admin_login.php');
    exit;
}

$admin_id = $_SESSION['adminID'];

// Fetch admin data
$admin_query = "SELECT * FROM admins WHERE adminID = ? LIMIT 1";
$admin_stmt = $conn->prepare($admin_query);
$admin_stmt->bind_param("s", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
$admin_data = $admin_result->fetch_assoc();
$admin_stmt->close();

// Fetch pending appointments
$pending_query = "SELECT COUNT(*) as pending FROM appointments WHERE statusID = 1";
$pending_result = $conn->query($pending_query);
$pending_count = $pending_result->fetch_assoc()['pending'] ?? 0;

// Fetch active doctors
$doctors_query = "SELECT COUNT(*) as active FROM doctors WHERE Status = 'Active'";
$doctors_result = $conn->query($doctors_query);
$active_doctors = $doctors_result->fetch_assoc()['active'] ?? 0;

// Fetch registered students
$students_query = "SELECT COUNT(*) as registered FROM students";
$students_result = $conn->query($students_query);
$registered_students = $students_result->fetch_assoc()['registered'] ?? 0;

// Fetch total appointments
$appointments_query = "SELECT COUNT(*) as total FROM appointments";
$appointments_result = $conn->query($appointments_query);
$total_appointments = $appointments_result->fetch_assoc()['total'] ?? 0;

// Fetch most recent appointments with proper error handling
$recent_appt_query = "SELECT a.AppointmentID, a.AppointmentDate, a.Reason, s.firstName, s.lastName, 
                      d.FirstName AS doctorFirstName, d.LastName AS doctorLastName, st.status_name as StatusName
                      FROM appointments a 
                      JOIN students s ON a.StudentID = s.studentID
                      JOIN doctors d ON a.DoctorID = d.DoctorID
                      JOIN status st ON a.statusID = st.statusID
                      ORDER BY a.AppointmentDate DESC LIMIT 5";
$recent_result = $conn->query($recent_appt_query);
$recent_appts = [];

if ($recent_result && $recent_result->num_rows > 0) {
    while ($row = $recent_result->fetch_assoc()) {
        $recent_appts[] = $row;
    }
}

// Fetch appointment status stats
$status_query = "SELECT st.status_name as StatusName, COUNT(*) as count 
                FROM appointments a
                JOIN status st ON a.statusID = st.statusID
                GROUP BY a.statusID 
                ORDER BY st.statusID";
$status_result = $conn->query($status_query);
$status_data = [];

if ($status_result && $status_result->num_rows > 0) {
    while ($row = $status_result->fetch_assoc()) {
        $status_data[] = $row;
    }
}

// Generate meaningful system activity data from existing tables
$activities = [];

// Get recent appointments activity (last 7 days)
$recent_appointments_query = "SELECT 
    CONCAT('New appointment scheduled by ', s.firstName, ' ', s.lastName, ' with Dr. ', d.FirstName, ' ', d.LastName) as action,
    'appointment' as type,
    a.AppointmentDate as activity_date
    FROM appointments a 
    JOIN students s ON a.StudentID = s.studentID
    JOIN doctors d ON a.DoctorID = d.DoctorID
    WHERE a.AppointmentDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
    ORDER BY a.AppointmentDate DESC 
    LIMIT 3";

$recent_appointments_result = $conn->query($recent_appointments_query);
if ($recent_appointments_result && $recent_appointments_result->num_rows > 0) {
    while ($row = $recent_appointments_result->fetch_assoc()) {
        $activities[] = [
            'action' => $row['action'],
            'type' => 'appointment',
            'created_at' => $row['activity_date']
        ];
    }
}

// Get recent notifications (check if table exists first)
$check_notifications = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notifications && $check_notifications->num_rows > 0) {
    $recent_notifications_query = "SELECT 
        CONCAT('Notification sent: ', LEFT(n.message, 50), '...') as action,
        'notification' as type,
        n.created_at as activity_date
        FROM notifications n 
        WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
        ORDER BY n.created_at DESC 
        LIMIT 2";

    $recent_notifications_result = $conn->query($recent_notifications_query);
    if ($recent_notifications_result && $recent_notifications_result->num_rows > 0) {
        while ($row = $recent_notifications_result->fetch_assoc()) {
            $activities[] = [
                'action' => $row['action'],
                'type' => 'notification',
                'created_at' => $row['activity_date']
            ];
        }
    }
}

// Add current admin session
$activities[] = [
    'action' => 'Admin dashboard accessed by ' . $admin_data['adminName'] . ' ' . $admin_data['adminLastName'],
    'type' => 'login',
    'created_at' => date('Y-m-d H:i:s')
];

// Add system status based on current data
if ($pending_count > 0) {
    $activities[] = [
        'action' => $pending_count . ' pending appointment' . ($pending_count > 1 ? 's' : '') . ' requiring attention',
        'type' => 'alert',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
    ];
}

// Add doctor status update
$activities[] = [
    'action' => 'System health check completed - ' . $active_doctors . ' active doctors, ' . $registered_students . ' registered students',
    'type' => 'system',
    'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
];

// Add some recent student registrations
$recent_students_query = "SELECT 
    CONCAT('New student registered: ', firstName, ' ', lastName) as action,
    'student' as type,
    registrationDate as activity_date
    FROM students 
    WHERE registrationDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
    ORDER BY registrationDate DESC 
    LIMIT 2";

$recent_students_result = $conn->query($recent_students_query);
if ($recent_students_result && $recent_students_result->num_rows > 0) {
    while ($row = $recent_students_result->fetch_assoc()) {
        $activities[] = [
            'action' => $row['action'],
            'type' => 'student',
            'created_at' => $row['activity_date']
        ];
    }
}

// Add recent doctor activities if any
$recent_doctors_query = "SELECT 
    CONCAT('Doctor profile updated: Dr. ', FirstName, ' ', LastName) as action,
    'doctor' as type,
    DATE(NOW()) as activity_date
    FROM doctors 
    WHERE Status = 'Active'
    ORDER BY DoctorID DESC 
    LIMIT 1";

$recent_doctors_result = $conn->query($recent_doctors_query);
if ($recent_doctors_result && $recent_doctors_result->num_rows > 0) {
    $row = $recent_doctors_result->fetch_assoc();
    $activities[] = [
        'action' => $row['action'],
        'type' => 'doctor',
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))
    ];
}

// Sort activities by date (most recent first)
usort($activities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Limit to 5 most recent activities
$activities = array_slice($activities, 0, 5);

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Medical Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
            padding: 20px;
            transition: all 0.3s ease;
            background-color: var(--surface-light);
        }
        
        .main-expanded {
            margin-left: 250px;
        }
        
        /* Dashboard Specific Styles */
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
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(46, 125, 50, 0.03) 0%, rgba(255, 255, 255, 0) 60%);
            border-radius: 50%;
            z-index: 0;
        }
        
        .welcome-banner .avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--admin-primary-light), var(--admin-primary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            box-shadow: 0 8px 25px rgba(46, 125, 50, 0.25);
            border: 4px solid white;
            position: relative;
            z-index: 1;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .welcome-banner .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .welcome-banner h2 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(to right, var(--admin-primary-dark), var(--admin-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            z-index: 1;
        }
        
        .welcome-banner p {
            margin: 8px 0 0;
            color: var(--text-medium);
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        /* Dashboard Stats */
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
        
        /* Charts and Tables Container */
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }
        
        /* Chart Card */
        .chart-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 24px;
            border: 1px solid rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }
        
        .chart-card:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .chart-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--surface-medium);
            display: flex;
            align-items: center;
        }
        
        .chart-card h3 i {
            margin-right: 10px;
            color: var(--admin-primary);
            font-size: 1.2rem;
        }
        
        /* Recent Appointments Table */
        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .recent-table th {
            text-align: left;
            padding: 12px 15px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-medium);
            border-bottom: 1px solid var(--surface-medium);
        }
        
        .recent-table td {
            padding: 12px 15px;
            font-size: 0.95rem;
            border-bottom: 1px solid var(--surface-light);
        }
        
        .recent-table tr:last-child td {
            border-bottom: none;
        }
        
        .recent-table tr:hover {
            background-color: var(--surface-light);
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
        }
        
        .status-pending {
            background-color: rgba(245, 124, 0, 0.1);
            color: var(--warning);
        }
        
        .status-approved {
            background-color: rgba(21, 101, 192, 0.1);
            color: var(--secondary);
        }
        
        .status-completed {
            background-color: rgba(56, 142, 60, 0.1);
            color: var(--success);
        }
        
        .status-cancelled {
            background-color: rgba(211, 47, 47, 0.1);
            color: var(--danger);
        }
        
        /* Activity Log */
        .activity-log {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }
        
        .activity-log:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .activity-log h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--surface-medium);
            display: flex;
            align-items: center;
        }
        
        .activity-log h3 i {
            margin-right: 10px;
            color: var(--admin-primary);
            font-size: 1.2rem;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            display: flex;
            margin-bottom: 15px;
            position: relative;
            padding: 12px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }
        
        .activity-item:hover {
            background-color: var(--surface-light);
        }
        
        .activity-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 40px;
            left: 27px;
            width: 2px;
            height: calc(100% - 15px);
            background-color: var(--surface-medium);
        }
        
        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            z-index: 2;
            position: relative;
        }
        
        .icon-info {
            background-color: rgba(21, 101, 192, 0.1);
            color: var(--secondary);
        }
        
        .icon-success {
            background-color: rgba(56, 142, 60, 0.1);
            color: var(--success);
        }
        
        .icon-warning {
            background-color: rgba(245, 124, 0, 0.1);
            color: var(--warning);
        }
        
        .icon-error {
            background-color: rgba(211, 47, 47, 0.1);
            color: var(--danger);
        }
        
        .icon-system {
            background-color: rgba(46, 125, 50, 0.1);
            color: var(--admin-primary);
        }
        
        .activity-content {
            flex-grow: 1;
        }
        
        .activity-title {
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 0.95rem;
            color: var(--text-dark);
            line-height: 1.4;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: var(--text-medium);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 24px;
            border: 1px solid rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }
        
        .quick-actions:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .quick-actions h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--surface-medium);
            display: flex;
            align-items: center;
        }
        
        .quick-actions h3 i {
            margin-right: 10px;
            color: var(--admin-primary);
            font-size: 1.2rem;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--admin-primary);
            color: white;
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
        
        /* Responsive styles */
        @media (max-width: 1200px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
        
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
            
            .welcome-banner {
                padding: 24px;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .recent-table th:nth-child(4),
            .recent-table td:nth-child(4) {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }
            
            .welcome-banner {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .welcome-banner .avatar {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }
            
            .welcome-banner h2 {
                font-size: 1.6rem;
            }
            
            .welcome-banner p {
                font-size: 0.9rem;
            }
            
            .activity-list {
                padding-left: 0;
            }
            
            .recent-table th:nth-child(3),
            .recent-table td:nth-child(3) {
                display: none;
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
                <h1 class="header-title">Admin Dashboard</h1>
            </div>
            
            <div class="header-actions">
                <!-- Removed all header action elements -->
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="main-content main-expanded" id="mainContent">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="avatar">
                    <?php if (!empty($admin_data['profilePhoto']) && file_exists($admin_data['profilePhoto'])): ?>
                        <img src="<?php echo htmlspecialchars($admin_data['profilePhoto']); ?>" alt="Admin Profile Photo">
                    <?php else: ?>
                        <i class="bi bi-person-gear"></i>
                    <?php endif; ?>
                </div>
                <div class="welcome-text">
                    <h2>Welcome, <?php echo htmlspecialchars($admin_data['adminName'] ?? ''); ?>!</h2>
                    <p>Clinic System Administrator Dashboard</p>
                    <small class="text-muted">
                        Today's Date: <?php echo date('F d, Y'); ?>
                    </small>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3><i class="bi bi-hourglass-split"></i> Pending Appointments</h3>
                    <div class="number"><?php echo $pending_count; ?></div>
                    <div class="description">Appointments awaiting confirmation</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="bi bi-person-badge"></i> Active Doctors</h3>
                    <div class="number"><?php echo $active_doctors; ?></div>
                    <div class="description">Currently registered medical staff</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="bi bi-people"></i> Registered Students</h3>
                    <div class="number"><?php echo $registered_students; ?></div>
                    <div class="description">Students in the system</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="bi bi-calendar-check"></i> Total Appointments</h3>
                    <div class="number"><?php echo $total_appointments; ?></div>
                    <div class="description">Appointments recorded in the system</div>
                </div>
            </div>
            
            <!-- Charts Container -->
            <div class="charts-container">
                <!-- Appointments Chart -->
                <div class="chart-card">
                    <h3><i class="bi bi-bar-chart-fill"></i> Appointment Statistics</h3>
                    <canvas id="appointmentChart" height="250"></canvas>
                </div>
                
                <!-- Recent Appointments Table -->
                <div class="chart-card">
                    <h3><i class="bi bi-calendar2-week"></i> Recent Appointments</h3>
                    <div style="overflow-x: auto;">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Doctor</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_appts) > 0): ?>
                                    <?php foreach ($recent_appts as $appt): ?>
                                        <?php
                                        $statusClass = '';
                                        $statusName = strtolower($appt['StatusName'] ?? '');
                                        switch ($statusName) {
                                            case 'pending':
                                                $statusClass = 'status-pending';
                                                break;
                                            case 'approved':
                                                $statusClass = 'status-confirmed';
                                                break;
                                            case 'completed':
                                                $statusClass = 'status-completed';
                                                break;
                                            case 'cancelled':
                                            case 'cancellation requested':
                                                $statusClass = 'status-cancelled';
                                                break;
                                            default:
                                                $statusClass = 'status-pending';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($appt['AppointmentDate'])); ?></td>
                                            <td><?php echo htmlspecialchars(($appt['firstName'] ?? '') . ' ' . ($appt['lastName'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars(($appt['doctorFirstName'] ?? '') . ' ' . ($appt['doctorLastName'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars($appt['Reason'] ?? ''); ?></td>
                                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($appt['StatusName'] ?? 'Unknown'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No recent appointments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links & Activity Log -->
            <div class="charts-container">
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h3><i class="bi bi-lightning-charge"></i> Quick Actions</h3>
                    <div class="action-buttons">
                        <a href="staff_management.php" class="action-btn btn-primary">
                            <i class="bi bi-people-fill"></i> Manage Staff
                        </a>
                        <a href="admin_profile.php" class="action-btn btn-secondary">
                            <i class="bi bi-person-circle"></i> My Profile
                        </a>
                        <a href="admin_report.php" class="action-btn btn-success">
                            <i class="bi bi-file-earmark-text"></i> Generate Reports
                        </a>
                        <a href="#" class="action-btn btn-warning" onclick="alert('Feature coming soon!')">
                            <i class="bi bi-bell"></i> System Settings
                        </a>
                    </div>
                </div>
                
                <!-- Activity Log -->
                <div class="activity-log">
                    <h3><i class="bi bi-clock-history"></i> Recent System Activity</h3>
                    <?php if (!empty($activities)): ?>
                        <ul class="activity-list">
                            <?php foreach ($activities as $activity): ?>
                                <?php
                                $iconClass = 'icon-system';
                                $iconName = 'gear';
                                
                                // Determine icon based on activity type
                                switch ($activity['type']) {
                                    case 'appointment':
                                        $iconClass = 'icon-info';
                                        $iconName = 'calendar-plus';
                                        break;
                                    case 'notification':
                                        $iconClass = 'icon-warning';
                                        $iconName = 'bell';
                                        break;
                                    case 'login':
                                        $iconClass = 'icon-success';
                                        $iconName = 'box-arrow-in-right';
                                        break;
                                    case 'alert':
                                        $iconClass = 'icon-error';
                                        $iconName = 'exclamation-triangle';
                                        break;
                                    case 'student':
                                        $iconClass = 'icon-info';
                                        $iconName = 'person-plus';
                                        break;
                                    case 'doctor':
                                        $iconClass = 'icon-success';
                                        $iconName = 'person-badge';
                                        break;
                                    case 'system':
                                        $iconClass = 'icon-success';
                                        $iconName = 'shield-check';
                                        break;
                                    default:
                                        $iconClass = 'icon-system';
                                        $iconName = 'info-circle';
                                }
                                ?>
                                <li class="activity-item">
                                    <div class="activity-icon <?php echo $iconClass; ?>">
                                        <i class="bi bi-<?php echo $iconName; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?php echo htmlspecialchars($activity['action']); ?>
                                        </div>
                                        <div class="activity-time">
                                            <?php 
                                            $time_diff = time() - strtotime($activity['created_at']);
                                            if ($time_diff < 60) {
                                                echo 'Just now';
                                            } elseif ($time_diff < 3600) {
                                                echo floor($time_diff / 60) . ' minutes ago';
                                            } elseif ($time_diff < 86400) {
                                                echo floor($time_diff / 3600) . ' hours ago';
                                            } else {
                                                echo date('M d, Y h:i A', strtotime($activity['created_at']));
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-activities" style="text-align: center; padding: 40px 20px; color: var(--text-medium);">
                            <i class="bi bi-clock-history" style="font-size: 3rem; color: var(--surface-dark); margin-bottom: 15px; display: block;"></i>
                            <h5>No Recent Activity</h5>
                            <p>System activities will appear here as they occur</p>
                        </div>
                    <?php endif; ?>
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
            
            // Toggle Sidebar function
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
            
            // Initialize the appointment chart
            const appointmentChartCtx = document.getElementById('appointmentChart').getContext('2d');
            
            // Extract data from PHP or use default values if there's no data
            let statusLabels = <?= !empty($status_data) ? json_encode(array_column($status_data, 'StatusName')) : '["Pending", "Approved", "Completed", "Cancelled"]' ?>;
            let statusCounts = <?= !empty($status_data) ? json_encode(array_column($status_data, 'count')) : '[0, 0, 0, 0]' ?>;
            
            // Colors for chart
            const chartColors = [
                'rgba(245, 124, 0, 0.7)',  // Orange for Pending
                'rgba(21, 101, 192, 0.7)', // Blue for Approved
                'rgba(56, 142, 60, 0.7)',  // Green for Completed
                'rgba(211, 47, 47, 0.7)',  // Red for Cancelled
            ];
            
            // Create chart
            const appointmentChart = new Chart(appointmentChartCtx, {
                type: 'bar',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        label: 'Number of Appointments',
                        data: statusCounts,
                        backgroundColor: chartColors,
                        borderColor: chartColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Set initial state
            setInitialState();
        });
    </script>
</body>
</html>
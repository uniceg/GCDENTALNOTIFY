<?php
include 'config.php';
session_start();

if (!isset($_SESSION['studentID'])) {
    header('location:login.php');
    exit;
}

$student_id = $_SESSION['studentID'];
// Fetch student data
$student_query = "SELECT * FROM students WHERE studentID = ? LIMIT 1";
$student_stmt = $conn->prepare($student_query);
$student_stmt->bind_param("s", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student_data = $student_result->fetch_assoc();
$student_stmt->close();

// Fetch next appointment
$appt_query = "SELECT * FROM appointments WHERE StudentID = ? AND AppointmentDate >= CURDATE() ORDER BY AppointmentDate, AppointmentID LIMIT 1";
$appt_stmt = $conn->prepare($appt_query);
$appt_stmt->bind_param("s", $student_id);
$appt_stmt->execute();
$appt_result = $appt_stmt->get_result();
$next_appt = $appt_result->fetch_assoc();
$appt_stmt->close();

// Fetch unread notifications
$notif_query = "SELECT * FROM notifications WHERE studentID = ? AND is_read = 0 ORDER BY created_at DESC";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("s", $student_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$unread_count = $notif_result->num_rows;
$latest_notifs = [];
while (($row = $notif_result->fetch_assoc()) && count($latest_notifs) < 3) {
    $latest_notifs[] = $row;
}
$notif_stmt->close();

// Fetch appointment summary
$summary_query = "SELECT statusID, COUNT(*) as count FROM appointments WHERE StudentID = ? GROUP BY statusID";
$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("s", $student_id);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$appt_summary = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
while ($row = $summary_result->fetch_assoc()) {
    $appt_summary[$row['statusID']] = $row['count'];
}
$summary_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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
        
        /* Main Content */
        .main-content {
            grid-area: main;
            padding: 30px;
            transition: all 0.3s ease;
            background-color: #f6f8fa;
        }
        
        .main-expanded {
            margin-left: 260px;
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
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
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
            transition: all 0.3s ease;
        }
        
        .welcome-banner .avatar:hover {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 0 12px 30px rgba(46, 125, 50, 0.3);
        }
        
        .welcome-banner h2 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(to right, var(--primary-dark), var(--primary));
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
        
        /* Dashboard Cards Grid Layout */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        /* Enhanced Dashboard Cards */
        .dashboard-card {
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
            height: 100%;
            min-height: 200px;
        }
        
        .dashboard-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-light), var(--primary));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-card:hover::after {
            transform: scaleX(1);
        }
        
        .dashboard-card h4 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0 0 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dashboard-card h4::before {
            content: '';
            display: inline-block;
            width: 24px;
            height: 24px;
            background-color: #f0f7f0;
            border-radius: 6px;
            background-position: center;
            background-repeat: no-repeat;
            background-size: 14px;
            flex-shrink: 0;
        }
        
        .dashboard-card:nth-child(1) h4::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%232e7d32' class='bi bi-calendar-check' viewBox='0 0 16 16'%3E%3Cpath d='M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z'/%3E%3Cpath d='M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z'/%3E%3C/svg%3E");
        }
        
        .dashboard-card:nth-child(2) h4::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%232e7d32' class='bi bi-bell' viewBox='0 0 16 16'%3E%3Cpath d='M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6'/%3E%3C/svg%3E");
        }
        
        .dashboard-card:nth-child(3) h4::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%232e7d32' class='bi bi-plus-circle' viewBox='0 0 16 16'%3E%3Cpath d='M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16'/%3E%3Cpath d='M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4'/%3E%3C/svg%3E");
        }
        
        .dashboard-card:nth-child(4) h4::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%232e7d32' class='bi bi-bar-chart' viewBox='0 0 16 16'%3E%3Cpath d='M4 11H2v3h2zm5-4H7v7h2zm5-5h-2v12h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM8 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zm-6 0a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z'/%3E%3C/svg%3E");
        }
        
        .dashboard-card .stat {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 16px;
            flex-grow: 1;
            line-height: 1.6;
        }
        
        .dashboard-card .btn {
            align-self: flex-start;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .dashboard-card .btn-outline-success {
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .dashboard-card .btn-outline-primary {
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }
        
        .dashboard-card .btn-outline-secondary {
            color: var(--text-medium);
            border: 1px solid var(--text-light);
        }
        
        .dashboard-card .btn-success {
            background: var(--primary);
            border: none;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-card .btn-success::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: transform 0.4s ease;
        }
        
        .dashboard-card .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-card .btn-success:hover::after {
            transform: translateX(100%);
        }
        
        /* Appointment Date Badge */
        .date-badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 6px;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Enhanced Info Cards */
        .latest-notifications {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }
        
        .latest-notifications:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }
        
        .latest-notifications h5 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0 0 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f7f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .latest-notifications h5::before {
            content: '';
            display: inline-block;
            width: 24px;
            height: 24px;
            background-color: #f0f7f0;
            border-radius: 6px;
            background-position: center;
            background-repeat: no-repeat;
            background-size: 14px;
        }
        
        .latest-notifications:nth-of-type(1) h5::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%232e7d32' class='bi bi-megaphone' viewBox='0 0 16 16'%3E%3Cpath d='M13 2.5a1.5 1.5 0 0 1 3 0v11a1.5 1.5 0 0 1-3 0zm-1 .724c-2.067.95-4.539 1.481-7 1.656v6.237a25.222 25.222 0 0 1 1.088.085c2.053.204 4.038.668 5.912 1.56zm-8 7.841V4.934c-.68.027-1.399.043-2.008.053A2.02 2.02 0 0 0 0 7v2c0 1.106.896 1.996 1.994 2.009a68.14 68.14 0 0 1 .496.008 64 64 0 0 1 1.51.048zm1.39 1.081c.285.021.569.047.85.078l.253 1.69a1 1 0 0 1-.983 1.187h-.548a1 1 0 0 1-.916-.599l-1.314-2.48a65.81 65.81 0 0 1 1.692.064c.327.017.65.037.966.06z'/%3E%3C/svg%3E");
        }
        
        .latest-notifications:nth-of-type(2) h5::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%232e7d32' class='bi bi-bell' viewBox='0 0 16 16'%3E%3Cpath d='M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6'/%3E%3C/svg%3E");
        }
        
        /* Enhanced Announcement List */
        .latest-notifications ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .latest-notifications li {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 3px solid var(--primary-light);
            background-color: #fafafa;
            transition: all 0.2s ease;
            font-size: 0.98rem;
            color: var(--text-dark);
        }
        
        .latest-notifications li:hover {
            background-color: #f0f7f0;
            transform: translateX(5px);
        }
        
        .latest-notifications li:last-child {
            margin-bottom: 0;
        }
        
        .latest-notifications li strong {
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        .latest-notifications li span {
            display: inline-block;
            float: right;
            color: var(--text-medium);
            background: #f0f7f0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Add these additional styles for the updated HTML elements */
        
        .welcome-text {
            position: relative;
            z-index: 1;
        }
        
        .notification-badge-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .notification-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .notification-preview-label {
            font-size: 0.9rem;
            color: var(--text-medium);
            margin-bottom: 5px;
        }
        
        .notification-preview {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .empty-notifications {
            text-align: center;
            padding: 20px 0;
        }
        
        .empty-notifications i {
            font-size: 2rem;
            color: var(--text-light);
            margin-bottom: 10px;
            display: block;
        }
        
        /* Responsive appointment summary items */
        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .status-label {
            flex: 1;
        }
        
        .status-badge {
            color: white;
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 0.9rem;
            min-width: 30px;
            text-align: center;
            font-weight: 500;
        }
        
        .status-badge.pending {
            background: var(--warning);
        }
        
        .status-badge.approved {
            background: var(--secondary);
        }
        
        .status-badge.completed {
            background: var(--success);
        }
        
        .status-badge.cancelled {
            background: var(--danger);
        }
        
        /* Fix notification dropdown positioning */
        .notifications {
            position: relative;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            min-width: 300px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1000;
            display: none;
            margin-top: 10px;
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-header {
            padding: 15px;
            border-bottom: 1px solid var(--surface-medium);
            color: var(--text-dark);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notification-list {
            padding: 8px 0;
        }

        .notification-item {
            display: flex;
            padding: 12px 15px;
            border-bottom: 1px solid var(--surface-light);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: var(--surface-light);
        }

        .notification-icon {
            background-color: var(--surface-light);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: var(--primary);
            flex-shrink: 0;
        }

        .notification-content {
            flex-grow: 1;
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .notification-date {
            font-size: 0.75rem;
            color: var(--text-medium);
        }

        .no-notifications {
            padding: 20px;
            text-align: center;
            color: var(--text-medium);
        }

        /* Fix notification button styling */
        .notification-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.2rem;
            position: relative;
            cursor: pointer;
            padding: 5px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Responsive tweaks */
        @media (max-width: 1200px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .welcome-banner {
                padding: 28px;
            }
            
            .welcome-banner h2 {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 992px) {
            .main-content {
                padding: 20px;
            }
            
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
            
            .welcome-banner {
                padding: 24px;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .welcome-banner::before {
                width: 200px;
                height: 200px;
                right: -10%;
            }
            
            .welcome-banner .avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
                margin-bottom: 10px;
            }
            
            .welcome-banner h2 {
                font-size: 1.6rem;
            }
            
            .welcome-banner p {
                font-size: 1rem;
            }
            
            .latest-notifications {
                padding: 20px;
            }
            
            .latest-notifications h5 {
                font-size: 1.1rem;
            }
            
            .latest-notifications li {
                padding: 10px 12px;
                font-size: 0.92rem;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .dashboard-card {
                padding: 20px;
            }
            
            .dashboard-card h4 {
                font-size: 1.1rem;
            }
            
            .dashboard-card .stat {
                font-size: 1.1rem;
            }
            
            .empty-appointment {
                padding: 20px 0;
            }
            
            .latest-notifications li span {
                float: none;
                display: block;
                margin-top: 5px;
                width: fit-content;
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
            }
            
            .welcome-banner h2 {
                font-size: 1.4rem;
            }
            
            .welcome-banner p {
                font-size: 0.9rem;
            }
            
            .dashboard-card {
                padding: 16px;
                min-height: auto;
            }
            
            .dashboard-card h4 {
                font-size: 1rem;
                margin-bottom: 12px;
            }
            
            .dashboard-card h4::before {
                width: 20px;
                height: 20px;
                background-size: 12px;
            }
            
            .dashboard-card .stat {
                font-size: 0.95rem;
                margin-bottom: 12px;
            }
            
            .dashboard-card .btn {
                font-size: 0.85rem;
                padding: 6px 12px;
            }
            
            .date-badge {
                font-size: 0.8rem;
                padding: 4px 8px;
            }
            
            .appointment-details {
                font-size: 0.85rem !important;
            }
            
            .latest-notifications {
                padding: 16px;
                margin-bottom: 20px;
            }
            
            .latest-notifications h5 {
                font-size: 1rem;
                margin-bottom: 15px;
                padding-bottom: 8px;
            }
            
            .latest-notifications h5::before {
                width: 20px;
                height: 20px;
            }
            
            .latest-notifications li {
                padding: 10px;
                font-size: 0.85rem;
                margin-bottom: 8px;
            }
            
            /* Improve status badges in appointment summary */
            .dashboard-card:nth-child(4) .stat > div {
                flex-wrap: wrap;
            }
            
            .dashboard-card:nth-child(4) .stat > div > span:first-child {
                width: 50%;
            }
            
            .dashboard-card:nth-child(4) .stat > div > span:last-child {
                font-size: 0.8rem;
                min-width: 25px;
            }
            
            /* Improve notification badge */
            .dashboard-card:nth-child(2) .stat > div:first-child {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .dashboard-card:nth-child(2) .stat > div:first-child > span:first-child {
                width: 22px;
                height: 22px;
                font-size: 0.8rem;
            }
        }
        
        /* Fix for very small screens */
        @media (max-width: 360px) {
            .welcome-banner {
                padding: 15px;
            }
            
            .welcome-banner .avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .welcome-banner h2 {
                font-size: 1.2rem;
            }
            
            .welcome-banner p {
                font-size: 0.8rem;
            }
            
            .dashboard-card {
                padding: 12px;
            }
            
            .latest-notifications {
                padding: 12px;
            }
            
            .latest-notifications li {
                padding: 8px;
            }
        }
        
        /* Improve appointments listing on mobile */
        @media (max-width: 768px) {
            .appointment-details {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            
            .empty-appointment {
                flex-direction: column;
                text-align: center;
            }
            
            .empty-appointment i {
                margin-bottom: 5px;
            }
        }
        
        /* Fix for responsive notification dropdown */
        @media (max-width: 576px) {
            .notification-dropdown {
                width: calc(100vw - 30px);
                right: -10px;
                max-height: 80vh;
                overflow-y: auto;
            }
            
            .notification-item {
                padding: 10px 15px;
            }
            
            .notification-icon {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            .notification-message {
                font-size: 0.85rem;
            }
            
            .notification-date {
                font-size: 0.75rem;
            }
        }
        
        /* Improve card hover effects for touch devices */
        @media (hover: none) {
            .dashboard-card:hover {
                transform: none;
            }
            
            .dashboard-card:hover::after {
                transform: scaleX(1);
            }
            
            .latest-notifications li:hover {
                transform: none;
            }
            
            .dashboard-card .btn:hover {
                transform: none;
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
        <li><a href="studentDashboard.php" class="active"><i class="bi bi-house"></i> Home</a></li>
        <li><a href="studentHome.php"><i class="bi bi-person"></i> Profile</a></li>
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
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-count"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <i class="bi bi-bell"></i> Notifications
                        </div>
                        <div class="notification-list">
                            <?php if (count($latest_notifs) > 0): ?>
                                <?php foreach ($latest_notifs as $notif): ?>
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
            <div class="welcome-banner">
                <div class="avatar">
                    <?php if (!empty($student_data['profilePhoto']) && file_exists($student_data['profilePhoto'])): ?>
                        <img src="<?php echo htmlspecialchars($student_data['profilePhoto']); ?>" alt="Profile Photo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <i class="bi bi-person-circle"></i>
                    <?php endif; ?>
                </div>
                <div class="welcome-text">
                    <h2>Welcome, <?php echo htmlspecialchars($student_data['firstName'] ?? ''); ?>!</h2>
                    <p>Here's what's happening with your clinic account today</p>
                </div>
            </div>
            
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <h4>Next Appointment</h4>
                    <div class="stat">
                        <?php if ($next_appt): ?>
                            <span class="date-badge">
                                <?php echo date('M d, Y', strtotime($next_appt['AppointmentDate'])); ?>
                            </span><br>
                            <?php echo htmlspecialchars($next_appt['Reason']); ?>
                            
                            <?php
                            // Get appointment details
                            $detailQuery = "SELECT d.FirstName, d.LastName, ts.StartTime, ts.EndTime 
                                            FROM appointments a
                                            LEFT JOIN doctors d ON a.DoctorID = d.DoctorID
                                            LEFT JOIN timeslots ts ON a.SlotID = ts.SlotID
                                            WHERE a.AppointmentID = ?";
                            $detailStmt = $conn->prepare($detailQuery);
                            $detailStmt->bind_param("i", $next_appt['AppointmentID']);
                            $detailStmt->execute();
                            $detailData = $detailStmt->get_result()->fetch_assoc();
                            
                            if ($detailData): ?>
                                <div class="appointment-details" style="margin-top:10px;font-size:0.9rem;color:var(--text-medium);">
                                    <div><i class="bi bi-person-badge me-1"></i> Dr. <?php echo htmlspecialchars($detailData['FirstName'] . ' ' . $detailData['LastName']); ?></div>
                                    <div><i class="bi bi-clock me-1"></i> <?php echo date('g:i A', strtotime($detailData['StartTime'])); ?> - <?php echo date('g:i A', strtotime($detailData['EndTime'])); ?></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-appointment">
                                <i class="bi bi-calendar-x" style="font-size:2rem;color:var(--text-light);margin-bottom:10px;"></i>
                                <span style="color:var(--text-medium);">No upcoming appointments</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <a href="schedule.php" class="btn btn-outline-success">View Details</a>
                </div>
                
                <div class="dashboard-card">
                    <h4>Notifications</h4>
                    <div class="stat">
                        <?php if ($unread_count > 0): ?>
                            <div class="notification-badge-container">
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                                <span>unread message<?php echo $unread_count > 1 ? 's' : ''; ?></span>
                            </div>
                            
                            <?php if (count($latest_notifs) > 0): ?>
                                <div class="notification-preview-label">Latest notification:</div>
                                <div class="notification-preview">
                                    <?php echo htmlspecialchars(substr($latest_notifs[0]['message'], 0, 60) . (strlen($latest_notifs[0]['message']) > 60 ? '...' : '')); ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-notifications">
                                <i class="bi bi-bell-slash"></i>
                                <span>No unread notifications</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <a href="#" class="btn btn-outline-primary" id="viewAllNotifications">View All</a>
                </div>
                
                <div class="dashboard-card">
                    <h4>Book Appointment</h4>
                    <div class="stat">
                        <p>Need to schedule a new appointment with one of our doctors?</p>
                        <div style="display:flex;align-items:center;gap:10px;margin:15px 0;color:var(--primary);font-size:0.9rem;">
                            <i class="bi bi-check-circle-fill"></i> 
                            <span>Check doctor availability</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:15px;color:var(--primary);font-size:0.9rem;">
                            <i class="bi bi-check-circle-fill"></i> 
                            <span>Select preferred time slot</span>
                        </div>
                    </div>
                    <a href="appointment.php" class="btn btn-success">Book Now</a>
                </div>
                
                <div class="dashboard-card">
                    <h4>Appointment Summary</h4>
                    <div class="stat">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <span>Pending</span>
                            <span style="background:var(--warning);color:white;border-radius:6px;padding:3px 10px;font-size:0.9rem;min-width:30px;text-align:center;font-weight:500;"><?php echo $appt_summary[1]; ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <span>Approved</span>
                            <span style="background:var(--secondary);color:white;border-radius:6px;padding:3px 10px;font-size:0.9rem;min-width:30px;text-align:center;font-weight:500;"><?php echo $appt_summary[2]; ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <span>Completed</span>
                            <span style="background:var(--success);color:white;border-radius:6px;padding:3px 10px;font-size:0.9rem;min-width:30px;text-align:center;font-weight:500;"><?php echo $appt_summary[3]; ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span>Cancelled</span>
                            <span style="background:var(--danger);color:white;border-radius:6px;padding:3px 10px;font-size:0.9rem;min-width:30px;text-align:center;font-weight:500;"><?php echo $appt_summary[4]; ?></span>
                        </div>
                    </div>
                    <a href="schedule.php" class="btn btn-outline-secondary">View All</a>
                </div>
            </div>
            
            <div class="latest-notifications">
                <h5>Clinic News & Announcements</h5>
                <ul>
                    <li>
                        <strong>May 2025:</strong> The clinic will be closed on June 1 for facility maintenance. Please plan your appointments accordingly.
                        <span>Important</span>
                    </li>
                    <li>
                        <strong>Oral Health Month:</strong> Free dental check-ups for all students every Friday this month!
                        <span>New</span>
                    </li>
                    <li>
                        <strong>New Service:</strong> We now offer digital dental records. Ask your dentist for more info!
                        <span>Info</span>
                    </li>
                </ul>
            </div>
            
            <div class="latest-notifications">
                <h5>Latest Notifications</h5>
                <ul>
                    <?php if (count($latest_notifs) > 0): ?>
                        <?php foreach ($latest_notifs as $notif): ?>
                            <li>
                                <?php echo htmlspecialchars($notif['message']); ?>
                                <span><?php echo date('M d, Y', strtotime($notif['created_at'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li style="color:var(--text-medium);border-left-color:var(--text-light);text-align:center;">
                            <i class="bi bi-bell-slash me-2"></i>No recent notifications
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </main>
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
                const isDisplayed = notificationDropdown.style.display === 'block';
                notificationDropdown.style.display = isDisplayed ? 'none' : 'block';
                
                // Position the dropdown correctly if needed
                if (!isDisplayed) {
                    const btnRect = notificationBtn.getBoundingClientRect();
                    const dropdownWidth = notificationDropdown.offsetWidth;
                    
                    // Ensure dropdown doesn't go off-screen on small screens
                    if (window.innerWidth < 576) {
                        notificationDropdown.style.right = '0';
                        notificationDropdown.style.left = 'auto';
                    } else {
                        // Position dropdown centered beneath button on larger screens
                        const leftPos = Math.max(0, btnRect.left + (btnRect.width/2) - (dropdownWidth/2));
                        notificationDropdown.style.left = 'auto';
                        notificationDropdown.style.right = '0';
                    }
                }
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
            
            // Set initial state
            setInitialState();
        });
    </script>
</body>
</html>
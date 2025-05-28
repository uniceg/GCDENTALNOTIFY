<?php
include 'config.php';
session_start();

if (!isset($_SESSION['studentID'])) {
    header('location:login.php');
    exit;
}

$student_id = $_SESSION['studentID'];

// Fetch all notifications for the student
$notificationQuery = "SELECT * FROM notifications WHERE studentID = ? ORDER BY created_at DESC";
$notificationStmt = $conn->prepare($notificationQuery);
$notificationStmt->bind_param("i", $student_id);
$notificationStmt->execute();
$notifications = $notificationStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Medical Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }

        .sidebar {
            width: 240px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            background-color: #024351;
            padding: 30px 20px;
            box-shadow: 2px 0px 5px rgba(0, 0, 0, 0.1);
        }

        .logo-section {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        .logo-section img {
            width: 180px;
            height: auto;
            margin-bottom: 15px;
        }

        .logo-section h3 {
            color: #fff;
            font-size: 20px;
            margin: 0;
            font-weight: 600;
        }

        .logo-section h3 span {
            display: block;
            color: #82f2fc;
            font-size: 16px;
            margin-top: 5px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            text-decoration: none;
            color: #fff;
            margin: 12px 0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .sidebar a i {
            margin-right: 10px;
            font-size: 18px;
        }

        .sidebar a.active,
        .sidebar a:hover {
            background-color: #03777e;
        }

        .main-content {
            margin-left: 240px;
            padding: 30px;
        }

        .notifications-header {
            background-color: #024351;
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            margin-bottom: 20px;
        }

        .notifications-header h1 {
            margin: 0;
            font-size: 24px;
        }

        .notifications-list {
            background-color: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item.unread {
            background-color: #e3f2fd;
        }

        .notification-content {
            flex-grow: 1;
        }

        .notification-time {
            color: #666;
            font-size: 14px;
            margin-left: 20px;
        }

        .mark-read-btn {
            background: none;
            border: none;
            color: #024351;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .mark-read-btn:hover {
            background-color: #f0f0f0;
        }

        .no-notifications {
            padding: 40px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo-section">
            <img src="MedicalClinicLogo.png" alt="Medical Clinic Logo">
            <h3>Medical Clinic<span>Notify+</span></h3>
        </div>
        
        <a href="studentHome.php"><i class="bi bi-house"></i>Home</a>
        <a href="doctors.php"><i class="bi bi-person-square"></i>Doctors</a>
        <a href="appointment.php"><i class="bi bi-journal-plus"></i>Schedule</a>
        <a href="schedule.php"><i class="bi bi-journal-arrow-down"></i>My Appointments</a>
        <a href="services.php"><i class="bi bi-journal-album"></i>Services</a>
        <a href="notifications.php" class="active">
            <i class="bi bi-bell"></i>Notifications
            <?php if ($notifications->num_rows > 0): ?>
                <span style="background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 12px; margin-left: 5px;">
                    <?php echo $notifications->num_rows; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </div>

    <div class="main-content">
        <div class="notifications-header">
            <h1>Notifications</h1>
        </div>
        
        <div class="notifications-list">
            <?php if ($notifications->num_rows > 0): ?>
                <?php while ($notification = $notifications->fetch_assoc()): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                        <div class="notification-content">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                        <div class="notification-time">
                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                        </div>
                        <?php if (!$notification['is_read']): ?>
                            <button class="mark-read-btn" onclick="markAsRead(<?php echo $notification['notificationID']; ?>, this)">
                                Mark as read
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-notifications">
                    No notifications to display
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function markAsRead(notificationId, button) {
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationItem = button.closest('.notification-item');
                notificationItem.classList.remove('unread');
                button.remove();
                
                // Update notification count in sidebar
                const countSpan = document.querySelector('.sidebar a[href="notifications.php"] span');
                if (countSpan) {
                    const currentCount = parseInt(countSpan.textContent);
                    if (currentCount > 1) {
                        countSpan.textContent = currentCount - 1;
                    } else {
                        countSpan.remove();
                    }
                }
            }
        });
    }
    </script>
</body>
</html> 
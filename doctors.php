<?php
include 'config.php';
session_start();

if (!isset($_SESSION['studentID'])) {
    header('location:login.php');
    exit;
}

$student_id = trim($_SESSION['studentID']);

// Fetch notifications
$notificationQuery = "SELECT * FROM notifications WHERE studentID = ? AND is_read = FALSE ORDER BY created_at DESC";
$notificationStmt = $conn->prepare($notificationQuery);
$notificationStmt->bind_param("s", $student_id);
$notificationStmt->execute();
$notifications = $notificationStmt->get_result();

$doctors = [];
$doctorQuery = "SELECT * FROM doctors";
$doctorResult = $conn->query($doctorQuery);
if ($doctorResult && $doctorResult->num_rows > 0) {
    while ($row = $doctorResult->fetch_assoc()) {
        $doctors[] = $row;
    }
}

// Fetch total appointments for each doctor
$appointmentsByDoctor = [];
$appointmentQuery = "SELECT DoctorID, COUNT(*) as TotalAppointments FROM appointments GROUP BY DoctorID";
$appointmentResult = $conn->query($appointmentQuery);
while ($appointment = $appointmentResult->fetch_assoc()) {
    $appointmentsByDoctor[$appointment['DoctorID']] = $appointment['TotalAppointments'];
}

// Fetch student data for welcome message
$student_first_name = '';
$student_query = "SELECT FirstName FROM students WHERE StudentID = ? LIMIT 1";
$student_stmt = $conn->prepare($student_query);
if ($student_stmt) {
    $student_stmt->bind_param("s", $student_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    if ($student_result && $student_result->num_rows > 0) {
        $student_row = $student_result->fetch_assoc();
        $student_first_name = $student_row['FirstName'];
    }
    $student_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General styles */
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }

        /* Sidebar DESIGN */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            background-color: #2e7d32 !important;
            color: white;
            padding-top: 15px;
            box-shadow: 4px 0 15px rgba(46, 125, 50, 0.15);
            transition: transform 0.3s ease;
            z-index: 2000;
            overflow-y: hidden;
            left: 0;
            top: 0;
            display: block;
        }

        .sidebar img {
            width: 65%;
            height: auto;
            margin: 0 auto 15px;
            display: block;
            filter: none;
            transition: transform 0.3s ease;
        }

        .sidebar img:hover {
            transform: scale(1.05);
        }

        .sidebar-divider {
            border-bottom: 1.5px solid #60ad5e;
            margin: 12px 20px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
            padding: 14px 25px;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .sidebar a i {
            margin-right: 12px;
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .sidebar a:hover {
            background: #60ad5e;
            color: #fff;
            padding-left: 30px;
        }

        .sidebar a:hover i {
            transform: translateX(3px);
        }

        .sidebar a.active {
            background: #60ad5e;
            color: #fff;
            border-right: 6px solid #388e3c;
        }

        /* Top Bar Part */
        .top-bar {
            width: calc(100% - 260px);
            height: 65px;
            background-color: #2e7d32;
            color: #fff;
            display: flex;
            align-items: center;
            padding: 0 30px;
            font-size: 1.4rem;
            font-weight: 600;
            margin-left: 260px;
            justify-content: space-between;
            transition: all 0.3s ease;
            box-shadow: 0 2px 15px rgba(46, 125, 50, 0.1);
            border-bottom: 2px solid #60ad5e;
            letter-spacing: 0.5px;
        }

        /* Notification Bell */
        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            margin-left: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-bell:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .notification-bell i {
            font-size: 1.3rem;
            color: #fff;
        }

        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(255, 68, 68, 0.3);
            animation: pulse 2s infinite;
        }

        /* Toggle Button */
        .toggle-btn {
            position: fixed;
            left: 260px;
            top: 20px;
            background: #fff;
            color: #1976d2;
            border: none;
            width: 35px;
            height: 35px;
            padding: 0;
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(1, 31, 75, 0.15);
            cursor: pointer;
            z-index: 1100;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-btn:hover {
            background: #e3f0fc;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            padding-top: 70px;
            transition: margin-left 0.3s ease;
        }

        /* Doctors Container */
        .doctors-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .doctor-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e3f0fc;
        }

        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .doctor-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .doctor-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid #1976d2;
        }

        .doctor-info h3 {
            margin: 0;
            color: #011f4b;
            font-size: 1.4rem;
        }

        .doctor-info p {
            margin: 5px 0;
            color: #666;
        }

        .doctor-specialty {
            background: #e3f0fc;
            color: #1976d2;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 5px;
        }

        .doctor-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .btn-primary {
            background: #1976d2;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #1565c0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.2);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-260px);
            }
            .sidebar.expanded {
                transform: translateX(0);
            }
            .toggle-btn {
                left: 20px;
            }
            .toggle-btn.expanded {
                left: 260px;
            }
            .top-bar {
                margin-left: 0;
                width: 100%;
            }
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .doctors-container {
                padding: 10px;
            }
            .doctor-card {
                padding: 15px;
            }
        }

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

        .notification-dropdown {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(1,31,75,0.18);
            max-height: 400px;
            min-width: 200px;
            width: 90vw;
            max-width: 270px;
            overflow-y: auto;
            padding: 0;
            border: 1.5px solid #e3f0fc;
            animation: fadeIn 0.2s;
            right: 0;
            left: auto;
            font-size: 1rem;
        }
        @media (max-width: 400px) {
            .notification-dropdown {
                min-width: 0;
                width: 98vw;
                max-width: 98vw;
                font-size: 0.95rem;
                padding: 0;
            }
            .notification-dropdown .dropdown-header {
                font-size: 1rem;
                padding: 10px 10px;
            }
            .notification-dropdown .dropdown-item {
                padding: 10px 10px;
                font-size: 0.93rem;
            }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px);}
            to { opacity: 1; transform: translateY(0);}
        }
        .notification-dropdown .dropdown-header {
            background: #1976d2;
            color: #fff;
            font-weight: 600;
            padding: 14px 18px;
            border-radius: 12px 12px 0 0;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }
        .notification-dropdown .dropdown-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 18px;
            border-bottom: 1px solid #f0f4fa;
            font-size: 0.98rem;
            background: #fff;
            transition: background 0.2s;
        }
        .notification-dropdown .dropdown-item:last-child {
            border-bottom: none;
        }
        .notification-dropdown .dropdown-item:hover {
            background: #f4f8fd;
        }
        .notification-dropdown .notif-icon {
            color: #1976d2;
            font-size: 1.3rem;
            margin-top: 2px;
        }
        .notification-dropdown .notif-message {
            flex: 1;
            color: #222;
            font-size: 0.92rem;
            font-weight: 500;
            line-height: 1.4;
            word-break: break-word;
        }
        .notification-dropdown .notif-date {
            color: #888;
            font-size: 0.82rem;
            margin-top: 2px;
            font-weight: 400;
        }
        .notification-dropdown .no-notif {
            text-align: center;
            color: #aaa;
            padding: 30px 0;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <button class="toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-chevron-double-right"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <img src="img/GCLINIC.png" alt="Logo">
        <div class="sidebar-divider"></div>
        <a href="studentDashboard.php"><i class="bi bi-house"></i> Home</a>
        <a href="studentHome.php"><i class="bi bi-person"></i> Profile</a>
        <a href="doctors.php" class="active"><i class="bi bi-person-square"></i> Doctors</a>
        <a href="appointment.php"><i class="bi bi-journal-plus"></i> Schedule Appointment</a>
        <a href="schedule.php"><i class="bi bi-journal-arrow-down"></i> My Appointments</a>
        <a href="services.php"><i class="bi bi-journal-album"></i> Service</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>

    <div class="top-bar">
        <span>Medical Clinic Notify+</span>
        <div class="d-flex align-items-center">
            <div class="welcome-text">
                <i class="bi bi-person-circle"></i>
                Welcome, <?php echo htmlspecialchars($student_first_name); ?>
            </div>
            <div class="notification-bell" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Notifications" style="position: relative;">
                <i class="bi bi-bell-fill"></i>
                <?php if ($notifications->num_rows > 0): ?>
                    <span class="notification-count"><?php echo $notifications->num_rows; ?></span>
                <?php endif; ?>
                <div class="dropdown-menu notification-dropdown" id="notificationDropdown" style="display: none; position: absolute; right: 0; top: 40px; z-index: 3000;">
                    <div class="dropdown-header">Notifications</div>
                    <?php if ($notifications->num_rows > 0): ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="dropdown-item notification-item" data-id="<?php echo $notif['notificationID']; ?>">
                                <span class="notif-icon"><i class="bi bi-info-circle-fill"></i></span>
                                <div class="notif-message">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                    <div class="notif-date"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'] ?? '')); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-notif">No new notifications.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="row mb-4">
            <div class="col-md-6 mb-2">
                <input type="text" id="doctorSearch" class="form-control" placeholder="Search by name...">
            </div>
            <div class="col-md-6 mb-2">
                <select id="specializationFilter" class="form-select">
                    <option value="">All Specializations</option>
                    <?php
                    // Get unique specializations
                    $specializations = [];
                    foreach ($doctors as $doctor) {
                        $spec = $doctor['Specialization'] ?? 'General';
                        if (!in_array($spec, $specializations)) $specializations[] = $spec;
                    }
                    foreach ($specializations as $spec) {
                        echo '<option value="' . htmlspecialchars($spec) . '">' . htmlspecialchars($spec) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="doctors-container">
            <?php if (count($doctors) > 0): ?>
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card"
                         data-name="<?php echo strtolower(htmlspecialchars($doctor['FirstName'] . ' ' . $doctor['LastName'])); ?>"
                         data-specialization="<?php echo strtolower(htmlspecialchars($doctor['Specialization'] ?? 'General')); ?>">
                        <div class="doctor-header">
                            <?php if (!empty($doctor['ImageFile']) && file_exists($doctor['ImageFile'])): ?>
                                <img src="<?php echo htmlspecialchars($doctor['ImageFile']); ?>" alt="Doctor" class="doctor-image">
                            <?php else: ?>
                                <div class="doctor-image" style="background:#e3f0fc;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#1976d2;">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                            <?php endif; ?>
                            <div class="doctor-info">
                                <h3><?php echo htmlspecialchars($doctor['FirstName'] . ' ' . $doctor['LastName']); ?></h3>
                                <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['Specialization'] ?? 'General'); ?></div>
                                <p class="mb-1"><strong>ID:</strong> <?php echo htmlspecialchars($doctor['DoctorID']); ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($doctor['Email'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($doctor['ContactNumber'] ?? 'N/A'); ?></p>
                                <p class="mb-1">
                                    <strong>Status:</strong>
                                    <span class="status-badge <?php echo ($doctor['Status'] ?? '') == 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $doctor['Status'] ?? 'Inactive'; ?>
                                    </span>
                                </p>
                                <p class="mb-0">
                                    <strong>Total Appointments:</strong>
                                    <?php
                                        $totalAppt = isset($appointmentsByDoctor[$doctor['DoctorID']]) ? $appointmentsByDoctor[$doctor['DoctorID']] : 0;
                                        echo $totalAppt;
                                        if ($totalAppt == 0) {
                                            echo ' <span class="badge bg-warning text-dark ms-2"><i class="bi bi-exclamation-circle"></i> None</span>';
                                        }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">No doctors found.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            const mainContent = document.querySelector('.main-content');
            const topBar = document.querySelector('.top-bar');

            // Function to update sidebar state
            function updateSidebarState(isCollapsed) {
                if (isCollapsed) {
                    sidebar.style.transform = 'translateX(-260px)';
                    mainContent.style.marginLeft = '0';
                    topBar.style.marginLeft = '0';
                    topBar.style.width = '100%';
                    toggleBtn.style.left = '20px';
                    toggleBtn.innerHTML = '<i class="bi bi-chevron-double-right"></i>';
                } else {
                    sidebar.style.transform = 'translateX(0)';
                    mainContent.style.marginLeft = '260px';
                    topBar.style.marginLeft = '260px';
                    topBar.style.width = 'calc(100% - 260px)';
                    toggleBtn.style.left = '260px';
                    toggleBtn.innerHTML = '<i class="bi bi-chevron-double-left"></i>';
                }
            }

            // Initial state based on screen size
            function setInitialState() {
                if (window.innerWidth <= 992) {
                    updateSidebarState(true);
                } else {
                    updateSidebarState(false);
                }
            }

            // Toggle button click handler
            toggleBtn.addEventListener('click', function() {
                const isCurrentlyCollapsed = sidebar.style.transform === 'translateX(-260px)';
                updateSidebarState(!isCurrentlyCollapsed);
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 992) {
                    updateSidebarState(true);
                } else {
                    updateSidebarState(false);
                }
            });

            // Set initial state
            setInitialState();

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            const searchInput = document.getElementById('doctorSearch');
            const specFilter = document.getElementById('specializationFilter');
            const doctorCards = document.querySelectorAll('.doctor-card');

            function filterDoctors() {
                const search = searchInput.value.toLowerCase();
                const spec = specFilter.value.toLowerCase();

                doctorCards.forEach(card => {
                    const name = card.getAttribute('data-name');
                    const specialization = card.getAttribute('data-specialization');
                    const matchName = name.includes(search);
                    const matchSpec = !spec || specialization === spec;
                    card.style.display = (matchName && matchSpec) ? '' : 'none';
                });
            }

            searchInput.addEventListener('input', filterDoctors);
            specFilter.addEventListener('change', filterDoctors);

            const bell = document.querySelector('.notification-bell');
            const dropdown = document.getElementById('notificationDropdown');
            const notifCount = document.querySelector('.notification-count');

            bell.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function() {
                dropdown.style.display = 'none';
            });

            // Mark notification as read on click
            document.querySelectorAll('.notification-item').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const notifId = this.getAttribute('data-id');
                    fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'notification_id=' + encodeURIComponent(notifId)
                    }).then(response => response.text()).then(data => {
                        // Remove the notification from the dropdown
                        this.remove();
                        // Update the count
                        let count = parseInt(notifCount.textContent, 10);
                        if (count > 1) {
                            notifCount.textContent = count - 1;
                        } else {
                            notifCount.remove();
                            dropdown.querySelector('.dropdown-header').insertAdjacentHTML('afterend', '<div class="no-notif">No new notifications.</div>');
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>

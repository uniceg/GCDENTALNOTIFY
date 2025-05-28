<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "medicalclinicnotify";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch pending appointments
$sql = "SELECT s.FirstName, s.LastName, a.AppointmentDate, d.FirstName as DoctorFirstName, d.LastName as DoctorLastName, st.status_name 
        FROM appointments a 
        JOIN students s ON a.StudentID = s.StudentID 
        JOIN doctors d ON a.DoctorID = d.DoctorID 
        JOIN status st ON a.statusID = st.statusID";
$result = $conn->query($sql);
if (!$result) {
    die("Error fetching appointments: " . $conn->error);
}

// Fetch total appointments stats
$stats_sql = "SELECT 
                COUNT(CASE WHEN st.status_name = 'Pending' THEN 1 END) AS pending,
                COUNT(CASE WHEN st.status_name = 'Approved' THEN 1 END) AS approved,
                COUNT(CASE WHEN st.status_name = 'Completed' THEN 1 END) AS completed
              FROM appointments a
              JOIN status st ON a.statusID = st.statusID";
$stats_result = $conn->query($stats_sql);
if (!$stats_result) {
    die("Error fetching stats: " . $conn->error);
}
$stats = $stats_result->fetch_assoc();

// Fetch today's appointments
$today_sql = "SELECT COUNT(*) AS today_appointments 
              FROM appointments 
              WHERE DATE(AppointmentDate) = CURDATE()";
$today_result = $conn->query($today_sql);
if (!$today_result) {
    die("Error fetching today's appointments: " . $conn->error);
}
$today_appointments = $today_result->fetch_assoc()['today_appointments'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clinic Appointment System</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    html {
      scroll-behavior: smooth;
    }
  </style>

  <!-- Firebase SDK -->
  <script src="https://www.gstatic.com/firebasejs/9.1.2/firebase-app.js"></script>
  <script src="https://www.gstatic.com/firebasejs/9.1.2/firebase-messaging.js"></script>
</head>
<body>
  <div class="sidebar">
    <div class="logo">
      <img src="logo.png" alt="Clinic Logo">
    </div>
    <nav>
      <ul>
        <li><a href="dashboard.php">DASHBOARD</a></li>
        <li><a href="appointment.php">APPOINTMENT</a></li>
        <li><a href="student_management.php">STUDENT MANAGEMENT</a></li>
        <li><a href="staff_management.php">STAFF MANAGEMENT</a></li>
        <li><a href="reports.php">REPORT</a></li>
      </ul>
    </nav>
  </div>
  
  <div class="main-content">
    <header>
      <h1>Clinic Appointment System</h1>
      <span class="login-info">name ng nakalogin</span>
    </header>
    
    <section id="dashboard">
      <h2>DASHBOARD</h2>
      <h3>View Pending Appointments</h3>
      <table>
        <thead>
          <tr>
            <th>Patient Name</th>
            <th>Scheduled Times</th>
            <th>Assigned Staff</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?></td>
                <td><?= htmlspecialchars(date("m/d/Y", strtotime($row['AppointmentDate']))) ?></td>
                <td><?= htmlspecialchars($row['DoctorFirstName'] . ' ' . $row['DoctorLastName']) ?></td>
                <td>
                  <select>
                    <option <?= $row['status_name'] === 'APPROVED' ? 'selected' : '' ?>>APPROVED</option>
                    <option <?= $row['status_name'] === 'DENIED' ? 'selected' : '' ?>>DENIED</option>
                    <option <?= $row['status_name'] === 'UNAVAILABLE' ? 'selected' : '' ?>>UNAVAILABLE</option>
                  </select>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="4">No appointments found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <footer>
      <div class="summary">
        <h3>Total Appointments</h3>
        <div class="stats">
          <span>Pending: <?= $stats['pending'] ?? 0 ?></span>
          <span>Approved: <?= $stats['approved'] ?? 0 ?></span>
          <span>Completed: <?= $stats['completed'] ?? 0 ?></span>
        </div>
      </div>
      <div class="schedule">
        <h3>Number of Students Scheduled for Today</h3>
        <p><?= $today_appointments ?? 0 ?></p>
        <p>Upcoming Appointments.</p>
      </div>
    </footer>
  </div>

  <script type="module">
  // Import the functions you need from the SDKs you need
  import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-app.js";
  import { getAnalytics } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-analytics.js";
  // TODO: Add SDKs for Firebase products that you want to use
  // https://firebase.google.com/docs/web/setup#available-libraries

  // Your web app's Firebase configuration
  // For Firebase JS SDK v7.20.0 and later, measurementId is optional
  const firebaseConfig = {
    apiKey: "AIzaSyDzmfrIS9TtKygAFOPw6PEwlG8NER3pgZU",
    authDomain: "medical-clinic-notify-6d018.firebaseapp.com",
    projectId: "medical-clinic-notify-6d018",
    storageBucket: "medical-clinic-notify-6d018.firebasestorage.app",
    messagingSenderId: "885466843892",
    appId: "1:885466843892:web:111057bc6ee216f482eca1",
    measurementId: "G-1ENT4SX289"
  };

  // Initialize Firebase
  const app = initializeApp(firebaseConfig);
  const analytics = getAnalytics(app);
</script>

</body>
</html>

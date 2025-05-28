<?php
session_start();
include 'config.php';

// TEMPORARY: Hardcoded DoctorID for testing (replace with a real one)
$doctorID = 'DOC-2025-0003'; // or just 5, 7, etc., based on your `doctors` table

// Get doctor details
$sql = "SELECT * FROM doctors WHERE DoctorID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $doctorID);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

// Handle form submission for adding/updating notes
$noteMessage = '';
$noteStatus = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_notes'])) {
    $appointmentID = $_POST['appointmentID'];
    $notes = $_POST['notes'];
    
    $update_sql = "UPDATE appointments SET 
                    Notes = ?
                  WHERE AppointmentID = ? AND DoctorID = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sss", $notes, $appointmentID, $doctorID);
    
    if ($update_stmt->execute()) {
        $noteMessage = "Notes updated successfully!";
        $noteStatus = "success";
    } else {
        $noteMessage = "Error updating notes: " . $conn->error;
        $noteStatus = "danger";
    }
}

// Get list of students assigned to this doctor (through appointments)
$students_sql = "SELECT a.AppointmentID, a.AppointmentDate, 
                 st.Status_Name, a.Notes, s.StudentID, s.FirstName, s.LastName  
                 FROM appointments a
                 JOIN students s ON a.StudentID = s.StudentID
                 JOIN status st ON a.StatusID = st.StatusID
                 WHERE a.DoctorID = ?
                 ORDER BY a.AppointmentDate DESC";
                 
$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("s", $doctorID);
$students_stmt->execute();
$students_result = $students_stmt->get_result();

// Get current page for navbar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Doctor Notes - Clinic Appointment System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      overflow-x: hidden;
    }
    .sidebar {
      width: 240px;
      height: 100vh;
      position: fixed;
      background-color: #2e7d32 !important;
      color: white;
      padding-top: 20px;
      box-shadow: 2px 0 12px rgba(46, 125, 50, 0.3);
      transition: transform 0.3s ease;
      z-index: 2000;
      overflow-y: auto;
      left: 0;
      top: 0;
      display: block;
    }
    .sidebar-divider {
      border-bottom: 1.5px solid #60ad5e;
      margin: 18px 0 12px 0;
    }
    .sidebar.collapsed {
      transform: translateX(-240px);
      background-color: #2e7d32 !important;
    }
    .toggle-btn {
      position: fixed;
      left: 240px;
      top: 24px;
      background-color: #fff;
      color: #2e7d32;
      border: none;
      width: 40px;
      height: 40px;
      padding: 0;
      border-radius: 50%;
      box-shadow: 0 2px 8px rgba(46, 125, 50, 0.3);
      cursor: pointer;
      z-index: 2100;
      transition: left 0.3s, background 0.2s, color 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .toggle-btn:hover {
      background: #dcedc8;
      color: #2e7d32;
    }
    .toggle-btn.collapsed {
      left: 16px;
    }
    .toggle-btn i {
      font-size: 20px;
      font-weight: bold;
      transition: transform 0.3s, color 0.2s;
    }
    .toggle-btn.collapsed i {
      transform: rotate(-90deg) scale(1.1);
      color: #2e7d32;
    }
    .toggle-btn.expanded i {
      transform: rotate(0deg) scale(1.1);
      color: #2e7d32;
    }
    .sidebar img {
      width: 80%;
      height: auto;
      margin: 0 auto 10px;
      display: block;
    }
    .sidebar a {
      display: flex;
      align-items: center;
      color: #fff;
      text-decoration: none;
      padding: 16px 24px;
      width: 100%;
      transition: background-color 0.2s, color 0.2s;
      font-size: 1.08rem;
      font-weight: 500;
    }
    .sidebar a i {
      margin-right: 14px;
      font-size: 1.25rem;
    }
    .sidebar a:hover,
    .sidebar a.active {
      background-color: #60ad5e;
      color: #fff;
      border-right: 6px solid #388e3c;
    }
    .sidebar-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: rgba(0,0,0,0.5);
      z-index: 1500;
      display: none;
      transition: opacity 0.3s ease;
    }
    .sidebar-overlay.active {
      display: block;
    }
    .top-bar {
      width: calc(100% - 240px);
      height: 60px;
      background-color: #2e7d32;
      color: #fff;
      display: flex;
      align-items: center;
      padding: 0 28px;
      font-size: 22px;
      font-weight: 600;
      margin-left: 240px;
      justify-content: space-between;
      transition: all 0.3s ease;
      box-shadow: 0 2px 10px rgba(46, 125, 50, 0.1);
      border-bottom: 2px solid #60ad5e;
      letter-spacing: 0.5px;
    }
    .main-content {
      margin-left: 240px;
      padding: 20px;
      padding-top: 70px;
      transition: all 0.3s ease;
    }
    h1,
    h2 {
      color: #2e7d32;
      margin-bottom: 1.5rem;
    }
    h1 {
      font-size: 2rem;
      font-weight: 600;
    }
    .notes-container {
      max-width: 100%;
      margin: 20px auto;
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(46, 125, 50, 0.1);
      overflow: hidden;
    }
    .notes-header {
      background-color: #f1f8e9;
      padding: 30px;
      border-bottom: 1px solid #e0e0e0;
    }
    .notes-content {
      padding: 30px;
    }
    .student-card {
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
      margin-bottom: 20px;
      transition: transform 0.3s, box-shadow 0.3s;
      overflow: hidden;
    }
    .student-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(46, 125, 50, 0.15);
    }
    .student-card-header {
      background-color: #f1f8e9;
      padding: 20px;
      border-bottom: 1px solid #e0e0e0;
    }
    .student-card-body {
      padding: 20px;
    }
    .student-name {
      font-size: 1.4rem;
      font-weight: 600;
      color: #2e7d32;
      margin-bottom: 5px;
    }
    .appointment-info {
      color: #666;
      margin-bottom: 15px;
    }
    .status-badge {
      padding: 5px 10px;
      border-radius: 15px;
      font-size: 0.8rem;
      font-weight: 500;
      display: inline-block;
      margin-left: 10px;
    }
    .status-pending {
      background-color: #FFF3CD;
      color: #856404;
    }
    .status-completed {
      background-color: #D4EDDA;
      color: #155724;
    }
    .status-canceled {
      background-color: #F8D7DA;
      color: #721C24;
    }
    .notes-form textarea {
      border-radius: 8px;
      padding: 15px;
      border: 1px solid #ddd;
      transition: all 0.3s;
      min-height: 120px;
      font-family: 'Poppins', sans-serif;
    }
    .notes-form textarea:focus {
      border-color: #4CAF50;
      box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
    }
    .btn-save-notes {
      background-color: #2e7d32;
      border-color: #2e7d32;
      padding: 10px 25px;
      font-weight: 500;
      border-radius: 8px;
      transition: all 0.3s;
    }
    .btn-save-notes:hover {
      background-color: #388e3c;
      border-color: #388e3c;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .notes-placeholder {
      font-style: italic;
      color: #999;
    }
    .search-container {
      margin-bottom: 30px;
    }
    .search-input {
      border-radius: 20px;
      padding: 10px 20px 10px 45px;
      border: 1px solid #ddd;
      width: 100%;
      font-size: 1.1rem;
      transition: all 0.3s;
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%23888" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>');
      background-repeat: no-repeat;
      background-position: 15px center;
      background-size: 20px;
    }
    .search-input:focus {
      border-color: #4CAF50;
      box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.15);
      outline: none;
    }
    .filter-container {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }
    .filter-btn {
      padding: 8px 20px;
      border-radius: 20px;
      background-color: #fff;
      border: 1px solid #ddd;
      font-size: 0.9rem;
      color: #666;
      transition: all 0.3s;
      cursor: pointer;
    }
    .filter-btn:hover, .filter-btn.active {
      background-color: #2e7d32;
      color: #fff;
      border-color: #2e7d32;
    }
    .alert {
      border-radius: 8px;
      padding: 15px 20px;
      margin-bottom: 25px;
    }
    .header {
      color: #2e7d32;
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 25px;
    }
    .no-students-message {
      text-align: center;
      padding: 40px 20px;
      color: #666;
      font-style: italic;
      background-color: #f9f9f9;
      border-radius: 10px;
      margin-top: 20px;
    }
    @media (max-width: 992px) {
      .sidebar {
        background-color: 0 0 20px rgba(46, 125, 50, 0.1) !important;
        left: 0;
        top: 0;
        display: block;
        z-index: 2000;
      }
      .top-bar {
        margin-left: 0;
        width: 100%;
        font-size: 18px;
        padding: 0 15px;
      }
      .main-content {
        margin-left: 0;
        padding: 15px;
      }
      .notes-container {
        margin: 15px auto;
      }
    }
    @media (max-width: 768px) {
      .top-bar {
        font-size: 16px;
        height: 50px;
      }
      h1 {
        font-size: 1.8rem;
      }
      .notes-header,
      .notes-content {
        padding: 20px;
      }
      .student-card-header,
      .student-card-body {
        padding: 15px;
      }
    }
    @media (max-width: 576px) {
      .top-bar {
        font-size: 14px;
        padding: 0 10px;
      }
      .main-content {
        padding: 10px;
      }
      h1 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
      }
      .notes-header,
      .notes-content {
        padding: 15px;
      }
      .btn-save-notes {
        width: 100%;
      }
    }
  </style>
</head>
<body>

<div class="sidebar" id="sidebar">
  <img src="MedicalClinicLogo.png" alt="Logo" />
  <a href="doctor_dashboard.php" class="<?= $current_page === 'doctor_dashboard.php' ? 'active' : '' ?>">
    <i class="bi bi-speedometer2"></i> Dashboard Overview
  </a>
  <a href="doctor_student.php" class="<?= $current_page === 'doctor_student.php' ? 'active' : '' ?>">
    <i class="bi bi-calendar-check"></i> Appointment Management
  </a>
  <a href="student_viewer.php" class="<?= $current_page === 'student_viewer.php' ? 'active' : '' ?>">
    <i class="bi bi-person-lines-fill"></i> Patient Records Viewer
  </a>
  <a href="doctor_notes.php" class="<?= $current_page === 'doctor_notes.php' ? 'active' : '' ?>">
    <i class="bi bi-journal-text"></i> Patient Notes
  </a>
  <a href="doctor_profile.php" class="<?= $current_page === 'doctor_profile.php' ? 'active' : '' ?>">
    <i class="bi bi-person-circle"></i> Doctor Profile
  </a>
  <a href="doctor_schedule.php" class="<?= $current_page === 'doctor_schedule.php' ? 'active' : '' ?>">
    <i class="bi bi-calendar3"></i> Schedule Configuration
  </a>
  <a href="doctor_report.php" class="<?= $current_page === 'doctor_report.php' ? 'active' : '' ?>">
    <i class="bi bi-graph-up"></i> Reports & Analytics
  </a>
</div>

<!-- Sidebar toggle button and overlay -->
<button id="sidebarToggle" class="toggle-btn expanded" aria-label="Collapse sidebar">
  <i class="bi bi-chevron-left"></i>
</button>
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="top-bar">
  Medical Clinic Notify+ - Patient Notes
</div>

<div class="main-content">
  <div class="header">Patient Notes Management</div>
  
  <?php if (!empty($noteMessage)): ?>
    <div class="alert alert-<?= $noteStatus ?> alert-dismissible fade show" role="alert">
      <?= $noteMessage ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  
  <div class="notes-container">
    <div class="notes-header">
      <div class="row">
        <div class="col-md-12">
          <h2><i class="bi bi-journal-medical me-2"></i>Your Patient Notes</h2>
          <p class="lead">Manage clinical notes for patients assigned to you</p>
          
          <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Search patients by name or ID...">
          </div>
          
          <div class="filter-container">
            <button class="filter-btn active" data-filter="all">All Appointments</button>
            <button class="filter-btn" data-filter="pending">Pending</button>
            <button class="filter-btn" data-filter="completed">Completed</button>
            <button class="filter-btn" data-filter="cancelled">Cancelled</button>
            <button class="filter-btn" data-filter="with-notes">With Notes</button>
            <button class="filter-btn" data-filter="no-notes">Without Notes</button>
          </div>
        </div>
      </div>
    </div>
    
    <div class="notes-content">
      <?php if ($students_result->num_rows > 0): ?>
        <div class="row" id="studentCards">
          <?php while ($student = $students_result->fetch_assoc()): ?>
            <div class="col-md-6 student-card-col" 
                 data-status="<?= strtolower($student['Status_Name']) ?>" 
                 data-has-notes="<?= !empty($student['Notes']) ? 'yes' : 'no' ?>">
              <div class="student-card">
                <div class="student-card-header">
                  <h3 class="student-name">
                    <?= htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']) ?>
                    <?php 
                      $statusClass = '';
                      switch(strtolower($student['Status_Name'])) {
                        case 'pending':
                          $statusClass = 'status-pending';
                          break;
                        case 'completed':
                          $statusClass = 'status-completed';
                          break;
                        case 'cancelled':
                        case 'canceled':
                          $statusClass = 'status-canceled';
                          break;
                      }
                    ?>
                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($student['Status_Name']) ?></span>
                  </h3>
                  <div class="appointment-info">
                    <i class="bi bi-calendar-date me-2"></i>
                    <?= date('F j, Y', strtotime($student['AppointmentDate'])) ?>
                  </div>
                  <div class="student-id">
                    <i class="bi bi-person-badge me-2"></i>Student ID: <?= htmlspecialchars($student['StudentID']) ?>
                  </div>
                </div>
                <div class="student-card-body">
                  <form class="notes-form" method="POST" action="">
                    <input type="hidden" name="appointmentID" value="<?= $student['AppointmentID'] ?>">
                    <div class="mb-3">
                      <label for="notes-<?= $student['AppointmentID'] ?>" class="form-label">Clinical Notes:</label>
                      <textarea class="form-control" id="notes-<?= $student['AppointmentID'] ?>" name="notes" rows="4" placeholder="Add your clinical notes for this patient here..."><?= htmlspecialchars($student['Notes'] ?? '') ?></textarea>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                      <button type="submit" class="btn btn-primary btn-save-notes" name="update_notes">
                        <i class="bi bi-save me-2"></i>Save Notes
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <div class="no-students-message">
          <i class="bi bi-exclamation-circle fs-1 d-block mb-3 text-muted"></i>
          <h4>No patients found</h4>
          <p>You currently don't have any patients assigned to you.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Sidebar toggle functionality
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  const overlay = document.getElementById('sidebarOverlay');
  const mainContent = document.querySelector('.main-content');
  const topBar = document.querySelector('.top-bar');

  function toggleSidebar() {
    sidebar.classList.toggle('collapsed');
    toggleBtn.classList.toggle('collapsed');
    overlay.classList.toggle('active');

    if(sidebar.classList.contains('collapsed')){
      toggleBtn.setAttribute('aria-label', 'Expand sidebar');
      mainContent.style.marginLeft = '0';
      if(topBar) {
        topBar.style.marginLeft = '0';
        topBar.style.width = '100%';
      }
    } else {
      toggleBtn.setAttribute('aria-label', 'Collapse sidebar');
      mainContent.style.marginLeft = '240px';
      if(topBar) {
        topBar.style.marginLeft = '240px';
        topBar.style.width = 'calc(100% - 240px)';
      }
    }
  }

  toggleBtn.addEventListener('click', toggleSidebar);

  overlay.addEventListener('click', () => {
    sidebar.classList.add('collapsed');
    toggleBtn.classList.add('collapsed');
    overlay.classList.remove('active');
    mainContent.style.marginLeft = '0';
    if(topBar) {
      topBar.style.marginLeft = '0';
      topBar.style.width = '100%';
    }
    toggleBtn.setAttribute('aria-label', 'Expand sidebar');
  });
  
  // Auto-dismiss alerts after 5 seconds
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      const bootstrapAlert = bootstrap.Alert.getOrCreateInstance(alert);
      bootstrapAlert.close();
    }, 5000);
  });
  
  // Search functionality
  const searchInput = document.getElementById('searchInput');
  const studentCards = document.getElementById('studentCards');
  const studentCardElements = document.querySelectorAll('.student-card-col');
  
  searchInput.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    
    studentCardElements.forEach(card => {
      const studentName = card.querySelector('.student-name').textContent.toLowerCase();
      const studentID = card.querySelector('.student-id').textContent.toLowerCase();
      
      if (studentName.includes(searchTerm) || studentID.includes(searchTerm)) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  });
  
  // Filter functionality
  const filterButtons = document.querySelectorAll('.filter-btn');
  
  filterButtons.forEach(button => {
    button.addEventListener('click', function() {
      // Remove active class from all buttons
      filterButtons.forEach(btn => btn.classList.remove('active'));
      
      // Add active class to clicked button
      this.classList.add('active');
      
      const filter = this.getAttribute('data-filter');
      
      studentCardElements.forEach(card => {
        const status = card.getAttribute('data-status');
        const hasNotes = card.getAttribute('data-has-notes');
        
        switch(filter) {
          case 'all':
            card.style.display = '';
            break;
          case 'pending':
          case 'completed':
          case 'cancelled':
            card.style.display = (status === filter) ? '' : 'none';
            break;
          case 'with-notes':
            card.style.display = (hasNotes === 'yes') ? '' : 'none';
            break;
          case 'no-notes':
            card.style.display = (hasNotes === 'no') ? '' : 'none';
            break;
        }
      });
    });
  });
</script>

</body>
</html>
<?php
include 'config.php';
include 'send_appointment_notification.php';
require_once 'session_helper.php';

// Validate session
if (!validateSession()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid. Please login again.']);
    exit;
}

// Get and validate student ID from session
$studentID = getStudentID();
if (empty($studentID)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid session. Please login again.']);
    exit;
}

// Verify student exists in database
$checkStudentQuery = "SELECT StudentID FROM students WHERE StudentID = ?";
$checkStudentStmt = $conn->prepare($checkStudentQuery);
$checkStudentStmt->bind_param("s", $studentID);
$checkStudentStmt->execute();
$studentResult = $checkStudentStmt->get_result();

if ($studentResult->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Student not found. Please login again.']);
    exit;
}

// Check if request is AJAX and has JSON content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input data
    if (!isset($data['doctorID'], $data['appointmentDate'], $data['appointmentTime'], $data['reason']) || 
        empty($data['doctorID']) || empty($data['appointmentDate']) || 
        empty($data['appointmentTime']) || empty($data['reason'])) {
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    $doctorID = $data['doctorID'];
    $appointmentDate = $data['appointmentDate'];
    $appointmentTime = $data['appointmentTime'];
    $reason = trim($data['reason']);
    $statusID = 1; // Pending status

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check if student already has a pending appointment for the same date
        $checkQuery = "SELECT COUNT(*) as count FROM appointments 
                      WHERE studentID = ? AND appointmentDate = ? AND statusID = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("ssi", $studentID, $appointmentDate, $statusID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $count = $checkResult->fetch_assoc()['count'];

        if ($count > 0) {
            throw new Exception('You already have a pending appointment for this date.');
        }

        // Get the SlotID based on the appointment time and doctor
        $slotQuery = "SELECT SlotID FROM timeslots 
                     WHERE DoctorID = ? AND StartTime = ? AND AvailableDay = DAYNAME(?)";
        $slotStmt = $conn->prepare($slotQuery);
        $slotStmt->bind_param("sss", $doctorID, $appointmentTime, $appointmentDate);
        $slotStmt->execute();
        $slotResult = $slotStmt->get_result();
        
        if ($slotResult->num_rows === 0) {
            throw new Exception('Selected time slot is no longer available.');
        }
        
        $slot = $slotResult->fetch_assoc();
        $slotID = $slot['SlotID'];

        // Check if slot is already booked for the same date
        $checkSlotQuery = "SELECT COUNT(*) as booked FROM appointments 
                          WHERE appointmentDate = ? AND slotID = ? AND statusID IN (1, 2)"; // Pending or Approved
        $checkSlotStmt = $conn->prepare($checkSlotQuery);
        $checkSlotStmt->bind_param("si", $appointmentDate, $slotID);
        $checkSlotStmt->execute();
        $slotCheckResult = $checkSlotStmt->get_result();
        
        if ($slotCheckResult->fetch_assoc()['booked'] > 0) {
            throw new Exception('This time slot has already been booked. Please select another time.');
        }

        // Insert appointment
        $insertQuery = "INSERT INTO appointments (studentID, doctorID, appointmentDate, slotID, reason, statusID) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("sssisi", $studentID, $doctorID, $appointmentDate, $slotID, $reason, $statusID);

        if (!$insertStmt->execute()) {
            throw new Exception('Failed to submit appointment: ' . $insertStmt->error);
        }

        $appointmentID = $insertStmt->insert_id;
        
        // Get doctor's name for notification
        $doctorQuery = "SELECT FirstName, LastName FROM doctors WHERE doctorID = ?";
        $doctorStmt = $conn->prepare($doctorQuery);
        $doctorStmt->bind_param("s", $doctorID);
        $doctorStmt->execute();
        $doctorResult = $doctorStmt->get_result();
        $doctor = $doctorResult->fetch_assoc();
        
        // Create notification message
        $message = "Your appointment request with Dr. " . $doctor['FirstName'] . " " . $doctor['LastName'] . 
                  " on " . date('F j, Y', strtotime($appointmentDate)) . 
                  " at " . date('g:i A', strtotime($appointmentTime)) . 
                  " has been submitted and is pending approval.";
        
        // Insert notification directly
        $notifQuery = "INSERT INTO notifications (studentID, appointmentID, message, is_read, created_at) 
                      VALUES (?, ?, ?, 0, NOW())";
        $notifStmt = $conn->prepare($notifQuery);
        $notifStmt->bind_param("sis", $studentID, $appointmentID, $message);
        
        if (!$notifStmt->execute()) {
            throw new Exception('Failed to create notification: ' . $notifStmt->error);
        }
        
        // Commit the transaction
        $conn->commit();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment submitted successfully! Please wait for approval.'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 
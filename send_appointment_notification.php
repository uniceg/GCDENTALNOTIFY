<?php
include 'config.php';

function sendAppointmentNotification($studentID, $message, $appointmentID = null) {
    global $conn;
    
    // Validate student ID format
    if (!preg_match('/^PT-\d{8}-\d{4}$/', $studentID)) {
        error_log("Invalid StudentID format: " . $studentID);
        return false;
    }
    
    // Verify student exists
    $checkStudentQuery = "SELECT StudentID FROM students WHERE StudentID = ?";
    $checkStmt = $conn->prepare($checkStudentQuery);
    $checkStmt->bind_param("s", $studentID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("Student not found: " . $studentID);
        return false;
    }
    
    // Log notification creation attempt
    error_log("Creating notification for StudentID: " . $studentID . " Message: " . $message);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $query = "INSERT INTO notifications (studentID, appointmentID, message, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sis", $studentID, $appointmentID, $message);
        
        $success = $stmt->execute();
        
        if ($success) {
            $conn->commit();
            error_log("Successfully created notification for StudentID: " . $studentID);
        } else {
            throw new Exception($stmt->error);
        }
        
        return $success;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Failed to create notification for StudentID: " . $studentID . " Error: " . $e->getMessage());
        return false;
    }
}

// Function to send notification when appointment is completed
function sendAppointmentCompletedNotification($appointmentID) {
    global $conn;
    
    // Get appointment details
    $query = "SELECT a.studentID, a.appointmentDate, a.appointmentTime, d.FirstName, d.LastName 
              FROM appointments a 
              JOIN doctors d ON a.doctorID = d.doctorID 
              WHERE a.appointmentID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $appointmentID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $message = "Your appointment with Dr. " . $row['FirstName'] . " " . $row['LastName'] . 
                  " on " . date('F j, Y', strtotime($row['appointmentDate'])) . 
                  " at " . date('g:i A', strtotime($row['appointmentTime'])) . 
                  " has been completed.";
        
        return sendAppointmentNotification($row['studentID'], $message);
    }
    
    return false;
}

// Function to send notification when appointment status changes
function sendAppointmentStatusNotification($appointmentID, $status) {
    global $conn;
    
    // Get appointment details
    $query = "SELECT a.studentID, a.appointmentDate, a.appointmentTime, d.FirstName, d.LastName 
              FROM appointments a 
              JOIN doctors d ON a.doctorID = d.doctorID 
              WHERE a.appointmentID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $appointmentID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $statusMessage = "";
        switch ($status) {
            case 'approved':
                $statusMessage = "has been approved";
                break;
            case 'rejected':
                $statusMessage = "has been rejected";
                break;
            case 'cancelled':
                $statusMessage = "has been cancelled";
                break;
            default:
                return false;
        }
        
        $message = "Your appointment with Dr. " . $row['FirstName'] . " " . $row['LastName'] . 
                  " on " . date('F j, Y', strtotime($row['appointmentDate'])) . 
                  " at " . date('g:i A', strtotime($row['appointmentTime'])) . 
                  " " . $statusMessage . ".";
        
        return sendAppointmentNotification($row['studentID'], $message);
    }
    
    return false;
}
?> 
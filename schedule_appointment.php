<?php
require_once 'send_notification.php';

// ... existing code ...

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentID = $_POST['studentID'];
    $appointmentDate = $_POST['appointment_date'];
    $appointmentTime = $_POST['appointment_time'];
    $reason = $_POST['reason'];
    
    // Generate a unique appointment ID
    $appointmentID = uniqid('APPT_');
    
    // Insert appointment into database
    $stmt = $conn->prepare("INSERT INTO appointments (appointment_id, studentID, appointment_date, appointment_time, reason, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("sssss", $appointmentID, $studentID, $appointmentDate, $appointmentTime, $reason);
    
    if ($stmt->execute()) {
        // Send notification
        $appointmentDetails = [
            'appointment_id' => $appointmentID,
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime
        ];
        
        sendAppointmentNotification($studentID, $appointmentDetails);
        
        echo json_encode(['success' => true, 'message' => 'Appointment scheduled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to schedule appointment']);
    }
    
    $stmt->close();
}

// ... existing code ... 
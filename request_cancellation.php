<?php
include 'config.php';
session_start();

error_log("Starting cancellation request process");

if (!isset($_SESSION['studentID'])) {
    error_log("Not logged in as student");
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointmentID = $_POST['appointment_id'];
    $studentID = $_SESSION['studentID'];
    $cancellationReason = isset($_POST['cancellation_reason']) ? trim($_POST['cancellation_reason']) : '';

    error_log("Processing cancellation request - Appointment ID: $appointmentID, Student ID: $studentID");
    error_log("Cancellation Reason: " . $cancellationReason);

    if (empty($cancellationReason)) {
        error_log("Error: No cancellation reason provided");
        $_SESSION['error_message'] = "Please provide a reason for cancellation.";
        header('Location: schedule.php');
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // First verify the appointment exists and belongs to the student
        $verifyQuery = "SELECT a.AppointmentID, a.AppointmentDate, a.StatusID, d.FirstName, d.LastName,
                              ts.StartTime, ts.EndTime 
                       FROM appointments a 
                       JOIN doctors d ON a.DoctorID = d.DoctorID 
                       LEFT JOIN timeslots ts ON a.SlotID = ts.SlotID
                       WHERE a.AppointmentID = ? AND a.StudentID = ? 
                       AND a.StatusID IN (1, 2)"; // Only Pending (1) or Approved (2) can be cancelled
        
        $stmt = $conn->prepare($verifyQuery);
        if (!$stmt) {
            throw new Exception("Failed to prepare verify statement: " . $conn->error);
        }

        $stmt->bind_param("is", $appointmentID, $studentID);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute verify statement: " . $stmt->error);
        }

        $result = $stmt->get_result();
        error_log("Verify query result count: " . $result->num_rows);

        if ($result->num_rows === 0) {
            throw new Exception("This appointment cannot be cancelled. It may be completed, already cancelled, or not belong to you.");
        }

        $appointment = $result->fetch_assoc();
        error_log("Current appointment status: " . $appointment['StatusID']);
        
        // Update appointment status to Cancellation Requested (statusID = 5)
        $updateQuery = "UPDATE appointments SET StatusID = 5 WHERE AppointmentID = ? AND StudentID = ? AND StatusID IN (1, 2)";
        $updateStmt = $conn->prepare($updateQuery);
        if (!$updateStmt) {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }

        $updateStmt->bind_param("is", $appointmentID, $studentID);
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update appointment status: " . $updateStmt->error);
        }

        error_log("Update affected rows: " . $updateStmt->affected_rows);

        if ($updateStmt->affected_rows === 0) {
            throw new Exception("Failed to update appointment status. No rows affected.");
        }

        // Verify the status was actually updated
        $checkStatus = "SELECT StatusID FROM appointments WHERE AppointmentID = ?";
        $checkStmt = $conn->prepare($checkStatus);
        $checkStmt->bind_param("i", $appointmentID);
        $checkStmt->execute();
        $currentStatus = $checkStmt->get_result()->fetch_assoc();
        error_log("New appointment status: " . $currentStatus['StatusID']);

        if ($currentStatus['StatusID'] != 5) {
            throw new Exception("Status update verification failed. Expected 5, got " . $currentStatus['StatusID']);
        }

        // Create notification for the student
        $message = "Your cancellation request for the appointment with Dr. " . $appointment['LastName'] . 
                  " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) .
                  " at " . date('g:i A', strtotime($appointment['StartTime'])) . 
                  " has been submitted and is pending approval.";
        
        $insertNotif = "INSERT INTO notifications (studentID, appointmentID, message, cancellation_reason, is_read) 
                       VALUES (?, ?, ?, ?, 0)";
        $notifStmt = $conn->prepare($insertNotif);
        if (!$notifStmt) {
            throw new Exception("Failed to prepare notification statement: " . $conn->error);
        }

        $notifStmt->bind_param("siss", $studentID, $appointmentID, $message, $cancellationReason);
        if (!$notifStmt->execute()) {
            throw new Exception("Failed to create notification: " . $notifStmt->error);
        }

        error_log("Notification created successfully");

        // Create notification for admin
        $adminMessage = "New cancellation request from Student ID: " . $studentID . 
                       " for appointment with Dr. " . $appointment['LastName'] . 
                       " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) .
                       " at " . date('g:i A', strtotime($appointment['StartTime'])) .
                       ". Reason: " . $cancellationReason;
        
        $adminNotif = "INSERT INTO admin_notifications (message, created_at) VALUES (?, NOW())";
        $adminStmt = $conn->prepare($adminNotif);
        if ($adminStmt) {
            $adminStmt->bind_param("s", $adminMessage);
            $adminStmt->execute();
            error_log("Admin notification created successfully");
        }

        // Commit all changes
        $conn->commit();
        error_log("All changes committed successfully");
        $_SESSION['success_message'] = "Your cancellation request has been submitted successfully. Please wait for admin approval.";

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error in request_cancellation.php: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
} else {
    error_log("Invalid request - POST data: " . print_r($_POST, true));
    $_SESSION['error_message'] = "Invalid request.";
}

header('Location: schedule.php');
exit();
?> 
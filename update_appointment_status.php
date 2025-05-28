<?php
include 'config.php';
session_start();

if (!isset($_SESSION['adminID'])) {
    header('Location: admin_login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id']) && isset($_POST['status'])) {
    $appointmentID = $_POST['appointment_id'];
    $newStatus = $_POST['status'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get appointment details
        $query = "SELECT a.*, s.FirstName AS StudentFirstName, s.LastName AS StudentLastName, s.email AS StudentEmail,
                         d.FirstName AS DoctorFirstName, d.LastName AS DoctorLastName,
                         ts.StartTime, ts.EndTime
                  FROM appointments a
                  JOIN students s ON a.StudentID = s.StudentID
                  JOIN doctors d ON a.DoctorID = d.DoctorID
                  JOIN timeslots ts ON a.SlotID = ts.SlotID
                  WHERE a.AppointmentID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $appointmentID);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        
        if ($appointment) {
            // Update appointment status
            $updateQuery = "UPDATE appointments SET StatusID = ? WHERE AppointmentID = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ii", $newStatus, $appointmentID);
            $updateStmt->execute();
            
            // If status is being changed to Approved (2), send email
            if ($newStatus == 2) {
                // Prepare email details
                $to = $appointment['StudentEmail'];
                $toName = $appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName'];
                $doctorName = $appointment['DoctorFirstName'] . ' ' . $appointment['DoctorLastName'];
                $appointmentDate = $appointment['AppointmentDate'];
                $appointmentTime = date('g:i A', strtotime($appointment['StartTime'])) . ' - ' . date('g:i A', strtotime($appointment['EndTime']));
                $reason = $appointment['Reason'];

                // Email template
                $subject = "Your Appointment Confirmation";
                $bodyHtml = "
                    <div style='font-family: Poppins, Arial, sans-serif; background: #f6faff; padding: 30px;'>
                        <div style='max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(25,118,210,0.10); border-left: 6px solid #1976d2; padding: 32px 28px;'>
                            <div style='text-align: center; margin-bottom: 24px;'>
                                <h2 style='color: #1976d2; margin: 0 0 8px 0;'>Appointment Confirmed</h2>
                            </div>
                            <p style='font-size: 1.08rem; color: #222; margin-bottom: 18px;'>
                                Dear <strong>$toName</strong>,
                            </p>
                            <p style='font-size: 1.05rem; color: #333; margin-bottom: 18px;'>
                                Your appointment has been <span style='color: #1976d2; font-weight: 600;'>approved</span>! Here are your appointment details:
                            </p>
                            <table style='width: 100%; font-size: 1rem; margin-bottom: 18px;'>
                                <tr>
                                    <td style='padding: 6px 0; color: #1976d2; font-weight: 600;'>Date:</td>
                                    <td style='padding: 6px 0;'>$appointmentDate</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #1976d2; font-weight: 600;'>Time:</td>
                                    <td style='padding: 6px 0;'>$appointmentTime</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #1976d2; font-weight: 600;'>Doctor:</td>
                                    <td style='padding: 6px 0;'>Dr. $doctorName</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #1976d2; font-weight: 600;'>Reason:</td>
                                    <td style='padding: 6px 0;'>$reason</td>
                                </tr>
                            </table>
                            <div style='background: #e3f0fc; border-radius: 8px; padding: 16px; margin-bottom: 18px; color: #1976d2; font-size: 1rem;'>
                                <strong>Important Reminders:</strong>
                                <ul style='margin: 8px 0 0 18px; color: #1976d2;'>
                                    <li>Please arrive at least <strong>10 minutes early</strong> for your appointment.</li>
                                    <li>Bring your valid ID and any necessary documents.</li>
                                    <li>If you need to reschedule or cancel, please do so at least 24 hours in advance.</li>
                                    <li>For any questions, contact us at <a href='mailto:medicalclinicnotify@gmail.com' style='color: #1976d2;'>medicalclinicnotify@gmail.com</a>.</li>
                                </ul>
                            </div>
                            <p style='font-size: 1.01rem; color: #444; margin-bottom: 0;'>
                                Thank you for choosing <strong>Medical Clinic Notify+</strong>.<br>
                                We look forward to seeing you!
                            </p>
                            <div style='text-align: center; margin-top: 28px; color: #aaa; font-size: 0.95rem;'>
                                &copy; " . date('Y') . " Medical Clinic Notify+
                            </div>
                        </div>
                    </div>
                ";

                require 'send_mail.php';
                $emailSent = sendAppointmentEmail($to, $toName, $subject, $bodyHtml);
                if (!$emailSent) {
                    error_log("Failed to send email to: " . $to);
                }
            }
            
            // Create notification for student
            $message = "";
            switch ($newStatus) {
                case 2: // Approved
                    $message = "Your appointment with Dr. " . $appointment['DoctorLastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been approved.";
                    break;
                case 3: // Completed
                    $message = "Your appointment with Dr. " . $appointment['DoctorLastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been marked as completed.";
                    break;
                case 4: // Cancelled
                    $message = "Your appointment with Dr. " . $appointment['DoctorLastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been cancelled.";
                    break;
            }
            
            if ($message) {
                $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                $insertNotification->bind_param("iis", $appointment['StudentID'], $appointmentID, $message);
                $insertNotification->execute();
            }
            
            $conn->commit();
            $_SESSION['success_message'] = "Appointment status has been updated successfully.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error updating appointment status: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Invalid request.";
}

// Redirect back to student management
header('Location: student_management.php');
exit();
?> 
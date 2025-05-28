<?php
ob_start();
include 'config.php';
require_once 'send_mail.php';
session_start();

if (!isset($_SESSION['adminID'])) {
    header('Location: admin_login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointmentID = $_POST['appointment_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get appointment details with all necessary information
        $getDetails = $conn->prepare("
            SELECT 
                a.StudentID, 
                a.AppointmentDate, 
                a.DoctorID,
                a.StatusID,
                a.Reason,
                s.FirstName as StudentFirstName,
                s.LastName as StudentLastName,
                s.email as StudentEmail,
                d.FirstName as DoctorFirstName,
                d.LastName as DoctorLastName,
                ts.StartTime,
                ts.EndTime,
                n.cancellation_reason
            FROM appointments a
            JOIN students s ON a.StudentID = s.StudentID
            JOIN doctors d ON a.DoctorID = d.DoctorID
            LEFT JOIN timeslots ts ON a.SlotID = ts.SlotID
            LEFT JOIN notifications n ON a.AppointmentID = n.appointmentID 
                AND n.cancellation_reason IS NOT NULL
            WHERE a.AppointmentID = ?
            ORDER BY n.notificationID DESC
            LIMIT 1
        ");
        
        $getDetails->bind_param("i", $appointmentID);
        $getDetails->execute();
        $result = $getDetails->get_result();
        $appointment = $result->fetch_assoc();
        
        if ($appointment) {
            if (isset($_POST['action'])) {
                $action = $_POST['action'];
                
                switch($action) {
                    case 'approve_appointment':
                        // Update status
                        $updateQuery = "UPDATE appointments SET StatusID = 2 WHERE AppointmentID = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param("i", $appointmentID);
                        $updateStmt->execute();
                        
                        // Create notification
                        $message = "Your appointment with Dr. " . $appointment['DoctorLastName'] . 
                                 " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                                 " has been approved.";
                        
                        $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                        $insertNotification->bind_param("sis", $appointment['StudentID'], $appointmentID, $message);
                        $insertNotification->execute();

                        // Common email template header and styles
                        $emailHeader = "
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <meta charset='UTF-8'>
                                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                                <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
                                <style>
                                    body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; }
                                    .email-container { max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 30px 20px; }
                                    .email-content { background-color: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); }
                                    .email-header { text-align: center; margin-bottom: 35px; }
                                    .email-title { color: #1976d2; font-size: 28px; font-weight: 700; margin-bottom: 12px; letter-spacing: -0.5px; }
                                    .email-subtitle { color: #666; font-size: 17px; margin: 0; font-weight: 500; }
                                    .greeting { font-size: 18px; color: #2c3e50; margin-bottom: 20px; font-weight: 600; }
                                    .message { font-size: 16px; color: #444; line-height: 1.6; margin-bottom: 25px; }
                                    .details-container { background-color: #f8f9fa; border-radius: 12px; padding: 25px; margin-bottom: 30px; border: 1px solid #e9ecef; }
                                    .details-table { width: 100%; border-collapse: collapse; }
                                    .details-table td { padding: 12px 0; }
                                    .details-label { color: #666; font-weight: 500; width: 35%; }
                                    .details-value { color: #2c3e50; font-weight: 600; }
                                    .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; }
                                    .status-approved { background-color: #28a745; color: white; }
                                    .status-completed { background-color: #1976d2; color: white; }
                                    .status-cancelled { background-color: #dc3545; color: white; }
                                    .info-box { background: #e3f0fc; border-radius: 12px; padding: 25px; margin-bottom: 30px; color: #1976d2; }
                                    .info-box-title { font-weight: 600; margin: 0 0 15px 0; font-size: 17px; }
                                    .info-box-list { margin: 0; padding-left: 20px; }
                                    .info-box-list li { margin-bottom: 8px; font-size: 15px; }
                                    .footer { text-align: center; margin-top: 35px; padding-top: 25px; border-top: 1px solid #eee; }
                                    .footer-text { color: #666; font-size: 14px; margin: 0; }
                                    .brand-name { color: #1976d2; font-size: 18px; font-weight: 700; margin: 8px 0; }
                                    .copyright { text-align: center; margin-top: 25px; color: #999; font-size: 13px; }
                                    @media only screen and (max-width: 480px) {
                                        .email-content { padding: 25px; }
                                        .email-title { font-size: 24px; }
                                        .email-subtitle { font-size: 16px; }
                                        .details-container { padding: 20px; }
                                        .status-badge { padding: 6px 12px; }
                                    }
                                </style>
                            </head>
                            <body>
                            <div class='email-container'>
                                <div class='email-content'>";

                        $emailFooter = "
                                    <div class='footer'>
                                        <p class='footer-text'>Thank you for choosing</p>
                                        <p class='brand-name'>Medical Clinic Notify+</p>
                                    </div>
                                </div>
                                <div class='copyright'>
                                    © " . date('Y') . " Medical Clinic Notify+. All rights reserved.
                                </div>
                            </div>
                            </body>
                            </html>";

                        // For Approval Email
                        if ($action === 'approve_appointment') {
                            $emailSubject = "Appointment Approved - Medical Clinic Notify+";
                            $emailBody = $emailHeader . "
                                <div class='email-header'>
                                    <h1 class='email-title'>Appointment Approved</h1>
                                    <p class='email-subtitle'>Your appointment has been confirmed</p>
                                </div>

                                <p class='greeting'>Dear " . htmlspecialchars($appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName']) . ",</p>
                                <p class='message'>Your appointment has been approved. Here are the details:</p>

                                <div class='details-container'>
                                    <table class='details-table'>
                                        <tr>
                                            <td class='details-label'>Doctor:</td>
                                            <td class='details-value'>Dr. " . htmlspecialchars($appointment['DoctorFirstName'] . ' ' . $appointment['DoctorLastName']) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Date:</td>
                                            <td class='details-value'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Time:</td>
                                            <td class='details-value'>" . date('g:i A', strtotime($appointment['StartTime'])) . " - " . date('g:i A', strtotime($appointment['EndTime'])) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Reason:</td>
                                            <td class='details-value'>" . htmlspecialchars($appointment['Reason']) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Status:</td>
                                            <td class='details-value'><span class='status-badge status-approved'>Approved</span></td>
                                        </tr>
                                    </table>
                                </div>

                                <div class='info-box'>
                                    <p class='info-box-title'>Important Reminders:</p>
                                    <ul class='info-box-list'>
                                        <li>Please arrive 10 minutes before your appointment</li>
                                        <li>Bring any relevant medical records</li>
                                        <li>Don't forget your valid ID</li>
                                        <li>Contact us if you need to reschedule</li>
                                    </ul>
                                </div>" . $emailFooter;

                        // For Completion Email
                        } else if ($action === 'complete') {
                            $emailSubject = "Appointment Completed - Medical Clinic Notify+";
                            $emailBody = $emailHeader . "
                                <div class='email-header'>
                                    <h1 class='email-title'>Appointment Completed</h1>
                                    <p class='email-subtitle'>Your appointment has been completed</p>
                                </div>

                                <p class='greeting'>Dear " . htmlspecialchars($appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName']) . ",</p>
                                <p class='message'>Your appointment has been marked as completed. Here are the details:</p>

                                <div class='details-container'>
                                    <table class='details-table'>
                                        <tr>
                                            <td class='details-label'>Doctor:</td>
                                            <td class='details-value'>Dr. " . htmlspecialchars($appointment['DoctorFirstName'] . ' ' . $appointment['DoctorLastName']) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Date:</td>
                                            <td class='details-value'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Time:</td>
                                            <td class='details-value'>" . date('g:i A', strtotime($appointment['StartTime'])) . " - " . date('g:i A', strtotime($appointment['EndTime'])) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Status:</td>
                                            <td class='details-value'><span class='status-badge status-completed'>Completed</span></td>
                                        </tr>
                                    </table>
                                </div>

                                <div class='info-box'>
                                    <p class='info-box-title'>Next Steps:</p>
                                    <ul class='info-box-list'>
                                        <li>Check your email for any test results or follow-up instructions</li>
                                        <li>Schedule follow-up appointments if recommended</li>
                                        <li>Contact us if you have any questions about your visit</li>
                                    </ul>
                                </div>" . $emailFooter;

                        // For Cancellation Email
                        } else if ($action === 'cancel') {
                            $emailSubject = "Appointment Cancelled - Medical Clinic Notify+";
                            $emailBody = $emailHeader . "
                                <div class='email-header'>
                                    <h1 class='email-title' style='color: #dc3545;'>Appointment Cancelled</h1>
                                    <p class='email-subtitle'>Your appointment has been cancelled</p>
                                </div>

                                <p class='greeting'>Dear " . htmlspecialchars($appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName']) . ",</p>
                                <p class='message'>Your appointment has been cancelled. Here are the details of the cancelled appointment:</p>

                                <div class='details-container'>
                                    <table class='details-table'>
                                        <tr>
                                            <td class='details-label'>Doctor:</td>
                                            <td class='details-value'>Dr. " . htmlspecialchars($appointment['DoctorFirstName'] . ' ' . $appointment['DoctorLastName']) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Date:</td>
                                            <td class='details-value'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Time:</td>
                                            <td class='details-value'>" . date('g:i A', strtotime($appointment['StartTime'])) . " - " . date('g:i A', strtotime($appointment['EndTime'])) . "</td>
                                        </tr>
                                        <tr>
                                            <td class='details-label'>Status:</td>
                                            <td class='details-value'><span class='status-badge status-cancelled'>Cancelled</span></td>
                                        </tr>
                                    </table>
                                </div>

                                <div class='info-box'>
                                    <p class='info-box-title'>Need to schedule a new appointment?</p>
                                    <ul class='info-box-list'>
                                        <li>Visit our website to book a new appointment</li>
                                        <li>Choose from available time slots</li>
                                        <li>Select your preferred doctor</li>
                                    </ul>
                                </div>" . $emailFooter;
                        }

                        error_log("Sending email to: " . $appointment['StudentEmail']);
                        $emailSent = sendAppointmentEmail(
                            $appointment['StudentEmail'],
                            $appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName'],
                            $emailSubject,
                            $emailBody
                        );
                        
                        if (!$emailSent) {
                            error_log("Failed to send email to: " . $appointment['StudentEmail']);
                            $_SESSION['warning_message'] = "Email notification failed.";
                        } else {
                            $_SESSION['success_message'] = "Email notification sent.";
                        }
                        break;

                    case 'complete':
                        // Update appointment status to Completed (3)
                        $updateQuery = "UPDATE appointments SET StatusID = 3 WHERE AppointmentID = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param("i", $appointmentID);
                        $updateStmt->execute();
                        
                        // Create notification for student
                        $message = "Your appointment with Dr. " . $appointment['DoctorLastName'] . 
                                 " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                                 " has been completed.";
                        
                        $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                        $insertNotification->bind_param("sis", $appointment['StudentID'], $appointmentID, $message);
                        $insertNotification->execute();

                        // Send completion email
                        $emailSubject = "Appointment Completed - Medical Clinic Notify+";
                        $emailBody = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 20px;'>
                                <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                    <div style='text-align: center; margin-bottom: 30px;'>
                                        <h2 style='color: #1976d2; font-size: 24px; margin-bottom: 10px;'>Appointment Completed</h2>
                                        <p style='color: #666; font-size: 16px; margin: 0;'>Your appointment has been completed</p>
                                    </div>

                                    <div style='margin-bottom: 25px;'>
                                        <p style='font-size: 16px; color: #444; margin-bottom: 15px;'>Dear " . htmlspecialchars($appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName']) . ",</p>
                                        <p style='font-size: 16px; color: #444; line-height: 1.5;'>Your appointment has been marked as completed. Here are the details:</p>
                                    </div>

                                    <div style='background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                                        <table style='width: 100%; border-collapse: collapse;'>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Doctor:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>Dr. " . htmlspecialchars($appointment['DoctorFirstName'] . ' ' . $appointment['DoctorLastName']) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Date:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Time:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('g:i A', strtotime($appointment['StartTime'])) . " - " . date('g:i A', strtotime($appointment['EndTime'])) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Status:</td>
                                                <td style='padding: 8px 0;'><span style='background-color: #28a745; color: white; padding: 5px 10px; border-radius: 15px; font-size: 14px;'>Completed</span></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div style='background: #e3f0fc; border-radius: 8px; padding: 16px; margin-bottom: 25px; color: #1976d2;'>
                                        <p style='margin: 0; font-weight: 500; margin-bottom: 10px;'>Next Steps:</p>
                                        <ul style='margin: 0; padding-left: 20px;'>
                                            <li style='margin-bottom: 5px;'>Check your email for any test results or follow-up instructions</li>
                                            <li style='margin-bottom: 5px;'>Schedule follow-up appointments if recommended</li>
                                            <li>Contact us if you have any questions about your visit</li>
                                        </ul>
                                    </div>

                                    <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                        <p style='color: #666; font-size: 14px; margin: 0;'>Thank you for choosing</p>
                                        <p style='color: #1976d2; font-size: 16px; font-weight: 600; margin: 5px 0;'>Medical Clinic Notify+</p>
                                    </div>
                                </div>
                                <div style='text-align: center; margin-top: 20px; color: #999; font-size: 14px;'>
                                    © " . date('Y') . " Medical Clinic Notify+. All rights reserved.
                                </div>
                            </div>
                        ";
                        
                        $emailSent = sendAppointmentEmail(
                            $appointment['StudentEmail'],
                            $appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName'],
                            $emailSubject,
                            $emailBody
                        );
                        
                        if (!$emailSent) {
                            error_log("Failed to send completion email to: " . $appointment['StudentEmail']);
                            $_SESSION['warning_message'] = "Appointment marked as completed but email notification failed.";
                        } else {
                            $_SESSION['success_message'] = "Appointment has been marked as completed and email notification sent.";
                        }
                        break;

                    case 'cancel':
                        // Update appointment status to Cancelled (4)
                        $updateQuery = "UPDATE appointments SET StatusID = 4 WHERE AppointmentID = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param("i", $appointmentID);
                        $updateStmt->execute();
                        
                        // Create notification for student
                        $message = "Your appointment with Dr. " . $appointment['DoctorLastName'] . 
                                 " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                                 " has been cancelled.";
                        
                        $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                        $insertNotification->bind_param("sis", $appointment['StudentID'], $appointmentID, $message);
                        $insertNotification->execute();

                        // Send cancellation email
                        $emailSubject = "Appointment Cancelled - Medical Clinic Notify+";
                        $emailBody = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 20px;'>
                                <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                    <div style='text-align: center; margin-bottom: 30px;'>
                                        <h2 style='color: #dc3545; font-size: 24px; margin-bottom: 10px;'>Appointment Cancelled</h2>
                                        <p style='color: #666; font-size: 16px; margin: 0;'>Your appointment has been cancelled</p>
                                    </div>

                                    <div style='margin-bottom: 25px;'>
                                        <p style='font-size: 16px; color: #444; margin-bottom: 15px;'>Dear " . htmlspecialchars($appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName']) . ",</p>
                                        <p style='font-size: 16px; color: #444; line-height: 1.5;'>Your appointment has been cancelled. Here are the details of the cancelled appointment:</p>
                                    </div>

                                    <div style='background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                                        <table style='width: 100%; border-collapse: collapse;'>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Doctor:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>Dr. " . htmlspecialchars($appointment['DoctorFirstName'] . ' ' . $appointment['DoctorLastName']) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Date:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Time:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('g:i A', strtotime($appointment['StartTime'])) . " - " . date('g:i A', strtotime($appointment['EndTime'])) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Status:</td>
                                                <td style='padding: 8px 0;'><span style='background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 15px; font-size: 14px;'>Cancelled</span></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div style='background: #e3f0fc; border-radius: 8px; padding: 16px; margin-bottom: 25px; color: #1976d2;'>
                                        <p style='margin: 0; font-weight: 500; margin-bottom: 10px;'>Need to schedule a new appointment?</p>
                                        <ul style='margin: 0; padding-left: 20px;'>
                                            <li style='margin-bottom: 5px;'>Visit our website to book a new appointment</li>
                                            <li style='margin-bottom: 5px;'>Choose from available time slots</li>
                                            <li>Select your preferred doctor</li>
                                        </ul>
                                    </div>

                                    <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                        <p style='color: #666; font-size: 14px; margin: 0;'>Thank you for choosing</p>
                                        <p style='color: #1976d2; font-size: 16px; font-weight: 600; margin: 5px 0;'>Medical Clinic Notify+</p>
                                    </div>
                                </div>
                                <div style='text-align: center; margin-top: 20px; color: #999; font-size: 14px;'>
                                    © " . date('Y') . " Medical Clinic Notify+. All rights reserved.
                                </div>
                            </div>
                        ";
                        
                        error_log("Sending cancellation email to: " . $appointment['StudentEmail']);
                        $emailSent = sendAppointmentEmail(
                            $appointment['StudentEmail'],
                            $appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName'],
                            $emailSubject,
                            $emailBody
                        );
                        
                        if (!$emailSent) {
                            error_log("Failed to send cancellation email to: " . $appointment['StudentEmail']);
                            $_SESSION['warning_message'] = "Appointment cancelled but email notification failed.";
                        } else {
                            $_SESSION['success_message'] = "Appointment has been cancelled and email notification sent.";
                        }
                        break;

                    case 'approve':
                        // Update appointment status to Cancelled
                        $updateQuery = "UPDATE appointments SET StatusID = 4 WHERE AppointmentID = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param("i", $appointmentID);
                        $updateStmt->execute();
                        
                        // Create notification for student
                        $message = "Your cancellation request for the appointment with Dr. " . $appointment['DoctorLastName'] . 
                                 " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                                 " has been approved.";
                        
                        $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                        $insertNotification->bind_param("sis", $appointment['StudentID'], $appointmentID, $message);
                        $insertNotification->execute();

                        // Send email notification
                        $emailSubject = "Appointment Cancellation Request Approved - Medical Clinic Notify+";
                        $emailBody = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 20px;'>
                                <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                    <div style='text-align: center; margin-bottom: 30px;'>
                                        <h2 style='color: #1976d2; font-size: 24px; margin-bottom: 10px;'>Cancellation Request Approved</h2>
                                        <p style='color: #666; font-size: 16px; margin: 0;'>Your appointment has been successfully cancelled</p>
                                    </div>

                                    <div style='margin-bottom: 25px;'>
                                        <p style='font-size: 16px; color: #444; margin-bottom: 15px;'>Dear " . htmlspecialchars($appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName']) . ",</p>
                                        <p style='font-size: 16px; color: #444; line-height: 1.5;'>Your request to cancel your appointment has been approved. Here are the details of the cancelled appointment:</p>
                                    </div>

                                    <div style='background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                                        <table style='width: 100%; border-collapse: collapse;'>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Doctor:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>Dr. " . htmlspecialchars($appointment['DoctorFirstName'] . ' ' . $appointment['DoctorLastName']) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Date:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Time:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('g:i A', strtotime($appointment['StartTime'])) . " - " . date('g:i A', strtotime($appointment['EndTime'])) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Status:</td>
                                                <td style='padding: 8px 0;'><span style='background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 15px; font-size: 14px;'>Cancelled</span></td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Cancellation Reason:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . htmlspecialchars($appointment['cancellation_reason']) . "</td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div style='background: #e3f0fc; border-radius: 8px; padding: 16px; margin-bottom: 25px; color: #1976d2;'>
                                        <p style='margin: 0; font-weight: 500; margin-bottom: 10px;'>Need to schedule a new appointment?</p>
                                        <ul style='margin: 0; padding-left: 20px;'>
                                            <li style='margin-bottom: 5px;'>Visit our website to book a new appointment</li>
                                            <li style='margin-bottom: 5px;'>Choose from available time slots</li>
                                            <li>Select your preferred doctor</li>
                                        </ul>
                                    </div>

                                    <p style='font-size: 16px; color: #444; margin-bottom: 25px;'>If you have any questions or need assistance, please don't hesitate to contact us.</p>

                                    <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                        <p style='color: #666; font-size: 14px; margin: 0;'>Thank you for choosing</p>
                                        <p style='color: #1976d2; font-size: 16px; font-weight: 600; margin: 5px 0;'>Medical Clinic Notify+</p>
                                    </div>
                                </div>
                                <div style='text-align: center; margin-top: 20px; color: #999; font-size: 14px;'>
                                    © " . date('Y') . " Medical Clinic Notify+. All rights reserved.
                                </div>
                            </div>
                        ";
                        
                        $emailSent = sendAppointmentEmail($appointment['StudentEmail'], $appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName'], $emailSubject, $emailBody);
                        
                        if (!$emailSent) {
                            error_log("[Handle Cancellation] Failed to send cancellation approval email to student: " . $appointment['StudentEmail']);
                            // Still proceed with the cancellation, but add a warning message
                            $_SESSION['warning_message'] = "Cancellation request has been approved, but there was an issue sending the email notification.";
                        } else {
                            $_SESSION['success_message'] = "Cancellation request has been approved and email notification sent.";
                        }
                        break;

                    case 'reject':
                        // Update appointment status back to Approved
                        $updateQuery = "UPDATE appointments SET StatusID = 2 WHERE AppointmentID = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param("i", $appointmentID);
                        $updateStmt->execute();
                        
                        // Create notification for student
                        $message = "Your cancellation request for the appointment with Dr. " . $appointment['DoctorLastName'] . 
                                 " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                                 " has been rejected. The appointment is still scheduled.";
                        
                        $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                        $insertNotification->bind_param("sis", $appointment['StudentID'], $appointmentID, $message);
                        $insertNotification->execute();

                        // Send rejection email
                        $emailSubject = "Appointment Cancellation Request Rejected - Medical Clinic Notify+";
                        $emailBody = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 20px;'>
                                <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                    <div style='text-align: center; margin-bottom: 30px;'>
                                        <h2 style='color: #1976d2; font-size: 24px; margin-bottom: 10px;'>Cancellation Request Rejected</h2>
                                        <p style='color: #666; font-size: 16px; margin: 0;'>Your appointment is still scheduled</p>
                                    </div>

                                    <div style='margin-bottom: 25px;'>
                                        <p style='font-size: 16px; color: #444; margin-bottom: 15px;'>Dear " . htmlspecialchars($appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName']) . ",</p>
                                        <p style='font-size: 16px; color: #444; line-height: 1.5;'>Your request to cancel your appointment has been rejected. The appointment will proceed as scheduled. Here are your appointment details:</p>
                                    </div>

                                    <div style='background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                                        <table style='width: 100%; border-collapse: collapse;'>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Doctor:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>Dr. " . htmlspecialchars($appointment['DoctorFirstName'] . ' ' . $appointment['DoctorLastName']) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Date:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Time:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('g:i A', strtotime($appointment['StartTime'])) . " - " . date('g:i A', strtotime($appointment['EndTime'])) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Status:</td>
                                                <td style='padding: 8px 0;'><span style='background-color: #28a745; color: white; padding: 5px 10px; border-radius: 15px; font-size: 14px;'>Scheduled</span></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div style='background: #e3f0fc; border-radius: 8px; padding: 16px; margin-bottom: 25px; color: #1976d2;'>
                                        <p style='margin: 0; font-weight: 500; margin-bottom: 10px;'>Important Reminders:</p>
                                        <ul style='margin: 0; padding-left: 20px;'>
                                            <li style='margin-bottom: 5px;'>Please arrive 10 minutes before your appointment</li>
                                            <li style='margin-bottom: 5px;'>Bring any relevant medical records</li>
                                            <li style='margin-bottom: 5px;'>Don't forget your valid ID</li>
                                            <li>Contact us if you have any questions</li>
                                        </ul>
                                    </div>

                                    <p style='font-size: 16px; color: #444; margin-bottom: 25px;'>If you have any concerns or need to discuss this further, please don't hesitate to contact us.</p>

                                    <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                        <p style='color: #666; font-size: 14px; margin: 0;'>Thank you for choosing</p>
                                        <p style='color: #1976d2; font-size: 16px; font-weight: 600; margin: 5px 0;'>Medical Clinic Notify+</p>
                                    </div>
                                </div>
                                <div style='text-align: center; margin-top: 20px; color: #999; font-size: 14px;'>
                                    © " . date('Y') . " Medical Clinic Notify+. All rights reserved.
                                </div>
                            </div>
                        ";

                        // Send the email
                        sendAppointmentEmail(
                            $appointment['StudentEmail'],
                            $appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName'],
                            $emailSubject,
                            $emailBody
                        );
                        
                        $_SESSION['success_message'] = "Cancellation request has been rejected.";
                        break;
                }
            }
            
            // Commit transaction
            $conn->commit();
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error in handle_cancellation.php: " . $e->getMessage());
        $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    }
    
    // Redirect back to student management page
    header("Location: student_management.php");
    exit();
}

// If we get here, there was no valid POST request
$_SESSION['error_message'] = "Invalid request.";
header("Location: student_management.php");
exit();

ob_end_flush();
?> 
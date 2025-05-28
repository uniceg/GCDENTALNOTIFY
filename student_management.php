<?php 
session_start();
include 'db_connection.php';

// Redirect if admin not logged in
if (!isset($_SESSION['adminID'])) {
    header('Location: admin_login.php');
    exit();
}

// Handle search
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchTermLike = '%' . $conn->real_escape_string($searchTerm) . '%';

// Get the selected filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get counts for each status
$countQuery = "SELECT a.StatusID, COUNT(*) as count 
               FROM appointments a 
               LEFT JOIN status s ON a.StatusID = s.statusID
               GROUP BY a.StatusID";
$countResult = $conn->query($countQuery);

// Debug log the count query
error_log("Count Query: " . $countQuery);

$statusCounts = [
    'Pending' => 0,
    'Approved' => 0,
    'Completed' => 0,
    'Cancelled' => 0,
    'Cancellation Requested' => 0
];

while ($row = $countResult->fetch_assoc()) {
    error_log("Status ID: " . $row['StatusID'] . ", Count: " . $row['count']);
    switch ($row['StatusID']) {
        case 1: $statusCounts['Pending'] = $row['count']; break;
        case 2: $statusCounts['Approved'] = $row['count']; break;
        case 3: $statusCounts['Completed'] = $row['count']; break;
        case 4: $statusCounts['Cancelled'] = $row['count']; break;
        case 5: $statusCounts['Cancellation Requested'] = $row['count']; break;
    }
}

// Debug log the status counts
error_log("Status Counts: " . print_r($statusCounts, true));

// Debug query to check cancellation requests
$debugQuery = "SELECT COUNT(*) as cancellation_count 
               FROM appointments a 
               WHERE a.StatusID = 5";
$debugResult = $conn->query($debugQuery);
$cancellationCount = $debugResult->fetch_assoc()['cancellation_count'];
error_log("Debug - Cancellation requests count: " . $cancellationCount);

// Modify the main query to properly handle status filtering
$query = "
    SELECT 
        students.StudentID,
        students.FirstName,
        students.LastName,
        students.ContactNumber,
        appointments.AppointmentID,
        appointments.StatusID,
        appointments.AppointmentDate,
        appointments.DoctorID,
        appointments.Reason,
        s.status_name,
        COALESCE(
            (SELECT cancellation_reason 
             FROM notifications 
             WHERE appointmentID = appointments.AppointmentID 
             AND cancellation_reason IS NOT NULL 
             ORDER BY notificationID DESC 
             LIMIT 1),
            'No reason provided'
        ) as cancellation_reason,
        d.FirstName AS DoctorFirstName,
        d.LastName AS DoctorLastName,
        ts.StartTime,
        ts.EndTime
    FROM students
    INNER JOIN appointments ON students.StudentID = appointments.StudentID
    LEFT JOIN status s ON appointments.StatusID = s.statusID
    LEFT JOIN doctors d ON appointments.DoctorID = d.DoctorID
    LEFT JOIN timeslots ts ON appointments.SlotID = ts.SlotID";

$whereConditions = [];
$params = [];
$types = "";

if ($searchTerm) {
    $whereConditions[] = "(students.FirstName LIKE ? OR students.LastName LIKE ?)";
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $types .= "ss";
}

if ($statusFilter !== 'all') {
    $whereConditions[] = "appointments.StatusID = ?";
    switch ($statusFilter) {
        case 'Pending': $params[] = 1; break;
        case 'Approved': $params[] = 2; break;
        case 'Completed': $params[] = 3; break;
        case 'Cancelled': $params[] = 4; break;
        case 'Cancellation Requested': $params[] = 5; break;
    }
    $types .= "i";
}

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY 
    CASE 
        WHEN appointments.StatusID = 5 THEN 1  -- Cancellation Requested
        WHEN DATE(appointments.AppointmentDate) = CURDATE() THEN 2
        WHEN DATE(appointments.AppointmentDate) > CURDATE() THEN 3
        ELSE 4
    END,
    DATE(appointments.AppointmentDate) ASC,
    ts.StartTime ASC";

// Debug output
error_log("Debug - Status Filter: " . $statusFilter);
error_log("Debug - Query: " . $query);
if (!empty($params)) {
    error_log("Debug - Params: " . implode(", ", $params));
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Debug log the number of results
error_log("Number of results: " . $result->num_rows);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointmentID = $_POST['appointment_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get appointment details
        $getDetails = $conn->prepare("SELECT StudentID, AppointmentDate, DoctorID, StatusID FROM appointments WHERE AppointmentID = ?");
        $getDetails->bind_param("i", $appointmentID);
        $getDetails->execute();
        $result = $getDetails->get_result();
        $appointment = $result->fetch_assoc();
        
        if ($appointment) {
            // Get doctor details
            $getDoctor = $conn->prepare("SELECT FirstName, LastName FROM doctors WHERE DoctorID = ?");
            $getDoctor->bind_param("i", $appointment['DoctorID']);
            $getDoctor->execute();
            $doctor = $getDoctor->get_result()->fetch_assoc();
            
            if (isset($_POST['update_status'])) {
                $newStatus = $_POST['update_status'];
                
                // Update appointment status
                $updateQuery = "UPDATE appointments SET StatusID = ? WHERE AppointmentID = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ii", $newStatus, $appointmentID);
                $updateStmt->execute();
                
                // Create notification based on status change
                $message = "";
                switch ($newStatus) {
                    case 2: // Approved
                        $message = "Your appointment with Dr. " . $doctor['LastName'] . 
                                 " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                                 " has been approved.";
                        break;
                    case 3: // Completed
                        $message = "Congratulations! Your appointment with Dr. " . $doctor['LastName'] . 
                                 " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                                 " has been completed. Please check for your results or follow-up instructions.";
                        
                        // Get test results/attachments for this appointment
                        $fileQuery = "SELECT FilePath, FileName FROM test_results WHERE AppointmentID = ?";
                        $fileStmt = $conn->prepare($fileQuery);
                        $fileStmt->bind_param("i", $appointmentID);
                        $fileStmt->execute();
                        $fileResult = $fileStmt->get_result();
                        $fileData = $fileResult->fetch_assoc();

                        // Send completion email
                        require_once 'send_mail.php';
                        $emailSubject = "Appointment Completed - Medical Clinic Notify+";
                        $emailBody = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 20px;'>
                                <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                    <div style='text-align: center; margin-bottom: 30px;'>
                                        <h2 style='color: #1976d2; font-size: 24px; margin-bottom: 10px;'>Appointment Completed</h2>
                                        <p style='color: #666; font-size: 16px; margin: 0;'>Thank you for visiting Medical Clinic Notify+</p>
                                    </div>

                                    <div style='margin-bottom: 25px;'>
                                        <p style='font-size: 16px; color: #444; margin-bottom: 15px;'>Dear " . htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']) . ",</p>
                                        <p style='font-size: 16px; color: #444; line-height: 1.5;'>Your appointment has been successfully completed. Here are the details of your visit:</p>
                                    </div>

                                    <div style='background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                                        <table style='width: 100%; border-collapse: collapse;'>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Doctor:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>Dr. " . htmlspecialchars($doctor['FirstName'] . ' ' . $doctor['LastName']) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Date:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Purpose:</td>
                                                <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . htmlspecialchars($appointment['Reason']) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #666;'>Status:</td>
                                                <td style='padding: 8px 0;'><span style='background-color: #1976d2; color: white; padding: 5px 10px; border-radius: 15px; font-size: 14px;'>Completed</span></td>
                                            </tr>
                                        </table>
                                    </div>";

                        // Add test results section if available
                        if ($fileData) {
                            $emailBody .= "
                                    <div style='background-color: #dff0d8; border-radius: 8px; padding: 16px; margin-bottom: 25px; color: #3c763d;'>
                                        <p style='margin: 0; font-weight: 500;'>Test Results Available</p>
                                        <p style='margin: 10px 0 0 0;'>Your test results have been attached to this email. Please review them carefully and keep them for your records.</p>
                                        <p style='margin: 10px 0 0 0; font-size: 14px;'>Attached file: " . htmlspecialchars($fileData['FileName']) . "</p>
                                    </div>";
                        }

                        $emailBody .= "
                                    <div style='background: #e3f0fc; border-radius: 8px; padding: 16px; margin-bottom: 25px; color: #1976d2;'>
                                        <p style='margin: 0; font-weight: 500; margin-bottom: 10px;'>Next Steps:</p>
                                        <ul style='margin: 0; padding-left: 20px;'>
                                            <li style='margin-bottom: 5px;'>Review your attached test results (if any)</li>
                                            <li style='margin-bottom: 5px;'>Follow any prescribed treatment plan</li>
                                            <li style='margin-bottom: 5px;'>Schedule follow-up appointments if recommended</li>
                                            <li>Keep this email and attachments for your records</li>
                                        </ul>
                                    </div>

                                    <div style='background-color: #fff3cd; border-radius: 8px; padding: 16px; margin-bottom: 25px; color: #856404;'>
                                        <p style='margin: 0; font-weight: 500;'>Important Note:</p>
                                        <p style='margin: 10px 0 0 0;'>If you have any questions about your results or need to schedule a follow-up appointment, please don't hesitate to contact us.</p>
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
                        
                        // Send email with attachment if available
                        if ($fileData) {
                            sendAppointmentEmailWithAttachment(
                                $student['email'],
                                $student['FirstName'] . ' ' . $student['LastName'],
                                $emailSubject,
                                $emailBody,
                                $fileData['FilePath'],
                                $fileData['FileName']
                            );
                        } else {
                            sendAppointmentEmail(
                                $student['email'],
                                $student['FirstName'] . ' ' . $student['LastName'],
                                $emailSubject,
                                $emailBody
                            );
                        }
                        break;
                    case 4: // Cancelled
                        $message = "Your appointment with Dr. " . $doctor['LastName'] . 
                                 " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                                 " has been cancelled.";
                        break;
                }
                
                if ($message) {
                    $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                    $insertNotification->bind_param("iis", $appointment['StudentID'], $appointmentID, $message);
                    $insertNotification->execute();
                }
                
                $_SESSION['success_message'] = "Appointment status has been updated successfully.";
            } 
            else if (isset($_POST['action'])) {
                $action = $_POST['action'];
                
                if ($action === 'approve') {
                    // Update appointment status to Cancelled
                    $updateQuery = "UPDATE appointments SET StatusID = 4 WHERE AppointmentID = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("i", $appointmentID);
                    $updateStmt->execute();
                    
                    // Create notification for student
                    $message = "Your cancellation request for the appointment with Dr. " . $doctor['LastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been approved.";
                    
                    $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                    $insertNotification->bind_param("iis", $appointment['StudentID'], $appointmentID, $message);
                    $insertNotification->execute();

                    // Get student email
                    $studentQuery = $conn->prepare("SELECT email, FirstName, LastName FROM students WHERE StudentID = ?");
                    $studentQuery->bind_param("i", $appointment['StudentID']);
                    $studentQuery->execute();
                    $student = $studentQuery->get_result()->fetch_assoc();

                    // Send email notification
                    require_once 'send_mail.php';
                    $emailSubject = "Appointment Cancellation Request Approved - Medical Clinic Notify+";
                    $emailBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 20px;'>
                            <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                <div style='text-align: center; margin-bottom: 30px;'>
                                    <h2 style='color: #1976d2; font-size: 24px; margin-bottom: 10px;'>Cancellation Request Approved</h2>
                                    <p style='color: #666; font-size: 16px; margin: 0;'>Your appointment has been successfully cancelled</p>
                                </div>

                                <div style='margin-bottom: 25px;'>
                                    <p style='font-size: 16px; color: #444; margin-bottom: 15px;'>Dear " . htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']) . ",</p>
                                    <p style='font-size: 16px; color: #444; line-height: 1.5;'>Your request to cancel your appointment has been approved. Here are the details of the cancelled appointment:</p>
                                </div>

                                <div style='background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                                    <table style='width: 100%; border-collapse: collapse;'>
                                        <tr>
                                            <td style='padding: 8px 0; color: #666;'>Doctor:</td>
                                            <td style='padding: 8px 0; color: #333; font-weight: 500;'>Dr. " . htmlspecialchars($doctor['LastName']) . "</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 8px 0; color: #666;'>Date:</td>
                                            <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
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
                    
                    $emailSent = sendAppointmentEmail($student['email'], $student['FirstName'] . ' ' . $student['LastName'], $emailSubject, $emailBody);
                    
                    if (!$emailSent) {
                        error_log("[Student Management] Failed to send cancellation approval email to student: " . $student['email']);
                        // Still proceed with the cancellation, but add a warning message
                        $_SESSION['warning_message'] = "Cancellation request has been approved, but there was an issue sending the email notification.";
                    } else {
                        $_SESSION['success_message'] = "Cancellation request has been approved and email notification sent.";
                    }
                } 
                else if ($action === 'reject') {
                    // Update appointment status back to Approved
                    $updateQuery = "UPDATE appointments SET StatusID = 2 WHERE AppointmentID = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("i", $appointmentID);
                    $updateStmt->execute();
                    
                    // Create notification for student
                    $message = "Your cancellation request for the appointment with Dr. " . $doctor['LastName'] . 
                             " on " . date('F j, Y', strtotime($appointment['AppointmentDate'])) . 
                             " has been rejected. The appointment is still scheduled.";
                    
                    $insertNotification = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message) VALUES (?, ?, ?)");
                    $insertNotification->bind_param("iis", $appointment['StudentID'], $appointmentID, $message);
                    $insertNotification->execute();

                    // Get student email
                    $studentQuery = $conn->prepare("SELECT email, FirstName, LastName FROM students WHERE StudentID = ?");
                    $studentQuery->bind_param("i", $appointment['StudentID']);
                    $studentQuery->execute();
                    $student = $studentQuery->get_result()->fetch_assoc();

                    // Send email notification
                    require_once 'send_mail.php';
                    $emailSubject = "Appointment Cancellation Request Rejected - Medical Clinic Notify+";
                    $emailBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 20px;'>
                            <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                <div style='text-align: center; margin-bottom: 30px;'>
                                    <h2 style='color: #1976d2; font-size: 24px; margin-bottom: 10px;'>Cancellation Request Rejected</h2>
                                    <p style='color: #666; font-size: 16px; margin: 0;'>Your appointment is still scheduled</p>
                                </div>

                                <div style='margin-bottom: 25px;'>
                                    <p style='font-size: 16px; color: #444; margin-bottom: 15px;'>Dear " . htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']) . ",</p>
                                    <p style='font-size: 16px; color: #444; line-height: 1.5;'>Your request to cancel your appointment has been rejected. The appointment will proceed as scheduled. Here are your appointment details:</p>
                                </div>

                                <div style='background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                                    <table style='width: 100%; border-collapse: collapse;'>
                                        <tr>
                                            <td style='padding: 8px 0; color: #666;'>Doctor:</td>
                                            <td style='padding: 8px 0; color: #333; font-weight: 500;'>Dr. " . htmlspecialchars($doctor['FirstName'] . ' ' . $doctor['LastName']) . "</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 8px 0; color: #666;'>Date:</td>
                                            <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('F j, Y', strtotime($appointment['AppointmentDate'])) . "</td>
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
                    sendAppointmentEmail($student['email'], $student['FirstName'] . ' ' . $student['LastName'], $emailSubject, $emailBody);
                    
                    $_SESSION['success_message'] = "Cancellation request has been rejected.";
                }
            }
            
            // Commit transaction
            $conn->commit();
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    }
    
    // Redirect with current filter
    $redirectUrl = "student_management.php";
    if ($statusFilter !== 'all') {
        $redirectUrl .= "?status=" . urlencode($statusFilter);
    }
    if ($searchTerm) {
        $redirectUrl .= ($statusFilter !== 'all' ? '&' : '?') . "search=" . urlencode($searchTerm);
    }
    header("Location: " . $redirectUrl);
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }
        /* Sidebar DESIGN */
        .sidebar {
            width: 240px;
            height: 100vh;
            position: fixed;
            background-color: #011f4b !important;
            color: white;
            padding-top: 20px;
            box-shadow: 2px 0 12px rgba(1, 31, 75, 0.10);
            transition: transform 0.3s ease;
            z-index: 2000;
            overflow-y: auto;
            left: 0;
            top: 0;
            display: block;
        }
        .sidebar-divider {
            border-bottom: 1.5px solid #23406a;
            margin: 18px 0 12px 0;
        }
        .sidebar.collapsed {
            transform: translateX(-240px);
            background-color: #011f4b !important;
        }
        .toggle-btn {
            position: fixed;
            left: 240px;
            top: 24px;
            background-color: #fff;
            color: #1976d2;
            border: none;
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(1,31,75,0.10);
            cursor: pointer;
            z-index: 1100;
            transition: left 0.3s, background 0.2s, color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-btn:hover {
            background: #e3f0fc;
            color: #011f4b;
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
            color: #011f4b;
        }
        .toggle-btn.expanded i {
            transform: rotate(0deg) scale(1.1);
            color: #1976d2;
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
        .sidebar a:hover, .sidebar a.active {
            background-color: #e3f0fc;
            color: #1976d2;
            border-right: 6px solid #1976d2;
        }

        /* Top Bar Part */
        .top-bar {
            width: calc(100% - 240px);
            height: 60px;
            background-color: #011f4b;
            color: #fff;
            display: flex;
            align-items: center;
            padding: 0 28px;
            font-size: 22px;
            font-weight: 600;
            margin-left: 240px;
            justify-content: space-between;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(1, 31, 75, 0.08);
            border-bottom: 2px solid #23406a;
            letter-spacing: 0.5px;
        }

        .main-content {
            margin-left: 240px;
            padding: 20px;
            padding-top: 70px;
            transition: all 0.3s ease;
        }

        h1, h2 {
            color: #011f4b;
            margin-bottom: 1.5rem;
        }

        h2 {
            font-weight: 600;
            font-size: 1.8rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(1, 31, 75, 0.1);
            margin-bottom: 2rem;
        }

        .table th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f9f87a;
            color: #011f4b;
            padding: 18px 16px;
            font-weight: 700;
            font-size: 1.05rem;
            border-bottom: 2px solid #eaf6fb;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: rgba(37, 129, 196, 0.1);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        .form-control-sm {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }

        .input-group {
            max-width: 500px;
        }

        /* Modal Styles */
        .modal-dialog {
            max-width: 90%;
            width: 90%;
            margin: 1.75rem auto;
        }

        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
            background-color: #fff;
            border-radius: 10px;
        }

        .file-preview {
            width: 100%;
            height: 70vh;
            min-height: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .file-preview iframe {
            width: 100%;
            height: 100%;
            border: none;
            background-color: #fff;
        }

        .file-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .modal-header {
            background-color: #011f4b;
            color: white;
            border-bottom: none;
        }

        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .modal-footer {
            background-color: #f8f9fa;
            border-top: none;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                background-color: #011f4b !important;
                left: 0;
                top: 0;
                display: block;
                z-index: 2000;
            }
            .sidebar.expanded {
                transform: translateX(0);
            }
            .toggle-btn {
                left: 16px;
                top: 24px;
            }
            .toggle-btn.expanded {
                left: 240px;
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
            .input-group {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                font-size: 16px;
                height: 50px;
            }
            h2 {
                font-size: 1.5rem;
            }
            .table th, .table td {
                padding: 10px;
                font-size: 0.9rem;
            }
            .btn-sm {
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }
            .file-preview {
                min-height: 250px;
            }
            .table-responsive {
                overflow-x: auto;
            }
            .table thead {
                display: none;
            }
            .table, .table tbody, .table tr, .table td {
                display: block;
                width: 100%;
            }
            .table tr {
                margin-bottom: 1rem;
                border-bottom: 2px solid #eee;
            }
            .table td {
                text-align: right;
                padding-left: 50%;
                position: relative;
            }
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 50%;
                padding-left: 1rem;
                font-weight: bold;
                text-align: left;
                color: #333;
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
            h2 {
                font-size: 1.3rem;
                margin-bottom: 1rem;
            }
            .table th, .table td {
                padding: 8px;
                font-size: 0.85rem;
            }
            .sidebar a {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
            .sidebar img {
                width: 70%;
            }
            .btn-sm {
                width: 100%;
                margin-bottom: 0.3rem;
            }
            .form-control-sm {
                margin-bottom: 0.5rem;
            }
            .file-preview {
                min-height: 200px;
            }
        }

        /* Table responsive styles */
        @media (max-width: 480px) {
            .table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                box-shadow: none;
            }
            .table th, .table td {
                min-width: 120px;
            }
            .modal-dialog {
                margin: 0.5rem;
                max-width: 100%;
            }
            .file-preview {
                min-height: 150px;
            }
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.25);
            z-index: 1500;
        }
        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <button class="toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-chevron-double-right"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="sidebar" id="sidebar">
        <img src="MedicalClinicLogo.png" alt="Logo">
        <div class="sidebar-divider"></div>
        <a href="admin_profile.php" class="<?php echo $currentPage == 'admin_profile.php' ? 'active' : ''; ?>"><i class="bi bi-person-vcard"></i> Profile</a>
        <a href="staff_management.php" class="<?php echo $currentPage == 'staff_management.php' ? 'active' : ''; ?>"><i class="bi bi-person-lines-fill"></i> Staff Management</a>
        <a href="student_management.php" class="<?php echo $currentPage == 'student_management.php' ? 'active' : ''; ?>"><i class="bi bi-journal-text"></i> Users Management</a>
        <a href="admin_report.php" class="<?php echo $currentPage == 'admin_report.php' ? 'active' : ''; ?>"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="adminLogout.php" class="mt-auto d-flex align-items-center"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>

    <div class="top-bar">
        <span>Appointment Management System</span>
    </div>

    <div class="main-content">
        <div class="row g-3 mb-4">
            <?php
            // Calculate stats
            $totalUsers = $conn->query("SELECT COUNT(DISTINCT students.StudentID) as total FROM students INNER JOIN appointments ON students.StudentID = appointments.StudentID")->fetch_assoc()['total'] ?? 0;
            ?>
            <div class="col-12 col-sm-6 col-lg-3 mb-2 mb-lg-0">
                <div class="card shadow-sm border-0 text-center py-3 h-100">
                    <div class="mb-2"><i class="bi bi-people-fill" style="font-size:1.7rem;color:#1976d2;"></i></div>
                    <div class="fw-bold" style="font-size:1.05rem;">Total Users</div>
                    <div class="fs-5 text-primary"><?php echo $totalUsers; ?></div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3 mb-2 mb-lg-0">
                <div class="card shadow-sm border-0 text-center py-3 h-100">
                    <div class="mb-2"><i class="bi bi-hourglass-split" style="font-size:1.5rem;color:#f9a825;"></i></div>
                    <div class="fw-bold" style="font-size:0.98rem;">Pending</div>
                    <div class="fs-6"><span class="badge bg-warning text-dark"><?php echo $statusCounts['Pending']; ?></span></div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3 mb-2 mb-lg-0">
                <div class="card shadow-sm border-0 text-center py-3 h-100">
                    <div class="mb-2"><i class="bi bi-check-circle-fill" style="font-size:1.5rem;color:#43a047;"></i></div>
                    <div class="fw-bold" style="font-size:0.98rem;">Approved</div>
                    <div class="fs-6"><span class="badge bg-success"><?php echo $statusCounts['Approved']; ?></span></div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3 mb-2 mb-lg-0">
                <div class="card shadow-sm border-0 text-center py-3 h-100">
                    <div class="mb-2"><i class="bi bi-clipboard-check-fill" style="font-size:1.5rem;color:#1976d2;"></i></div>
                    <div class="fw-bold" style="font-size:0.98rem;">Completed</div>
                    <div class="fs-6"><span class="badge bg-primary"><?php echo $statusCounts['Completed']; ?></span></div>
                </div>
            </div>
        </div>
        <div class="card shadow-sm border-0 p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                <h2 class="mb-0 flex-grow-1" style="font-weight:700; letter-spacing:0.5px; color:#011f4b;">Manage Users</h2>
                <form method="GET" action="student_management.php" class="search-form" style="min-width:220px; max-width:350px; width:100%;">
                    <div class="input-group shadow-sm rounded-pill overflow-hidden">
                        <input type="text" class="form-control border-0" style="background:#f4f6fa; border-radius: 50px 0 0 50px;" placeholder="Search by Name" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button class="btn btn-primary px-4 rounded-end-pill" type="submit" style="background: linear-gradient(90deg,#4a90e2,#357abd); border:none; font-weight:600;">Search</button>
                    </div>
                </form>
            </div>

            <!-- Add filter buttons -->
            <div class="filter-buttons mb-4">
                <a href="?status=all<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    All <span class="badge bg-light text-dark"><?php echo array_sum($statusCounts); ?></span>
                </a>
                <a href="?status=Pending<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                    Pending <span class="badge bg-light text-dark"><?php echo $statusCounts['Pending']; ?></span>
                </a>
                <a href="?status=Approved<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Approved' ? 'btn-success' : 'btn-outline-success'; ?>">
                    Approved <span class="badge bg-light text-dark"><?php echo $statusCounts['Approved']; ?></span>
                </a>
                <a href="?status=Completed<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Completed' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                    Completed <span class="badge bg-light text-dark"><?php echo $statusCounts['Completed']; ?></span>
                </a>
                <a href="?status=Cancelled<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Cancelled' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                    Cancelled <span class="badge bg-light text-dark"><?php echo $statusCounts['Cancelled']; ?></span>
                </a>
                <a href="?status=Cancellation Requested<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" class="btn <?php echo $statusFilter === 'Cancellation Requested' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                    Cancellation Requests <span class="badge bg-light text-dark"><?php echo $statusCounts['Cancellation Requested']; ?></span>
                </a>
            </div>

            <?php if (isset($_GET['upload']) && $_GET['upload'] === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    File uploaded successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle table-responsive">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Contact Information</th>
                            <th>Appointment Date</th>
                            <th>Service/Reason</th>
                            <th>Doctor</th>
                            <th>Current Appointment Status</th>
                            <th>Status Actions</th>
                            <th>Upload Result <i class="bi bi-info-circle text-primary" data-bs-toggle="tooltip" title="Allowed: PDF, DOC, DOCX, JPG, PNG"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="User Name"><?php echo htmlspecialchars($row['FirstName']) . ' ' . htmlspecialchars($row['LastName']); ?></td>
                                <td data-label="Contact Information"><?php echo htmlspecialchars($row['ContactNumber']); ?></td>
                                <td data-label="Appointment Date"><?php echo htmlspecialchars(date('F j, Y', strtotime($row['AppointmentDate']))); ?></td>
                                <td data-label="Service/Reason">
                                    <?php 
                                    if ($row['StatusID'] == 5 || $row['StatusID'] == 4) {
                                        echo '<div class="text-danger">';
                                        echo '<strong>Cancellation Reason:</strong><br>';
                                        echo htmlspecialchars($row['cancellation_reason']);
                                        echo '</div>';
                                    } else {
                                        echo '<div><strong>Service/Reason:</strong><br>' . htmlspecialchars($row['Reason']) . '</div>'; 
                                    }
                                    ?>
                                </td>
                                <td data-label="Doctor">
                                    <?php 
                                        echo 'Dr. ' . htmlspecialchars($row['DoctorFirstName'] . ' ' . $row['DoctorLastName']); 
                                    ?>
                                </td>
                                <td data-label="Current Appointment Status">
                                    <?php
                                    $statusText = "Pending";
                                    $statusBadge = "<span class='badge bg-warning text-dark'>Pending</span>";
                                    switch ($row['StatusID']) {
                                        case 1: 
                                            $statusText = "Pending"; 
                                            $statusBadge = "<span class='badge bg-warning text-dark'>Pending</span>"; 
                                            break;
                                        case 2: 
                                            $statusText = "Approved"; 
                                            $statusBadge = "<span class='badge bg-success'>Approved</span>"; 
                                            break;
                                        case 3: 
                                            $statusText = "Completed"; 
                                            $statusBadge = "<span class='badge bg-primary'>Completed</span>"; 
                                            break;
                                        case 4: 
                                            $statusText = "Cancelled"; 
                                            $statusBadge = "<span class='badge bg-danger'>Cancelled</span>"; 
                                            break;
                                        case 5: 
                                            $statusText = "Cancellation Requested"; 
                                            $statusBadge = "<span class='badge bg-warning'>Cancellation Requested</span>"; 
                                            break;
                                    }
                                    echo $statusBadge;
                                    ?>
                                </td>
                                <td data-label="Status Actions">
                                    <form method="POST" action="handle_cancellation.php" class="d-flex flex-column gap-1">
                                        <input type="hidden" name="appointment_id" value="<?php echo $row['AppointmentID']; ?>">
                                        <?php if ($row['StatusID'] == 5): // Cancellation Requested ?>
                                            <button type="submit" name="action" value="approve" class="btn btn-danger btn-sm">
                                                <i class="bi bi-check-lg"></i> Approve Cancellation
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn btn-success btn-sm">
                                                <i class="bi bi-x-lg"></i> Reject Cancellation
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="action" value="approve_appointment" class="btn btn-success btn-sm <?php echo $row['StatusID'] == 2 ? 'disabled' : ''; ?>">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </button>
                                            <button type="submit" name="action" value="complete" class="btn btn-primary btn-sm <?php echo $row['StatusID'] == 3 ? 'disabled' : ''; ?>">
                                                <i class="bi bi-check-circle"></i> Complete
                                            </button>
                                            <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm <?php echo $row['StatusID'] == 4 ? 'disabled' : ''; ?>">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                                <td data-label="Upload Result">
                                    <?php
                                    // Check if there's an uploaded file for this appointment
                                    $fileQuery = "SELECT FilePath, FileName FROM test_results WHERE AppointmentID = ?";
                                    $fileStmt = $conn->prepare($fileQuery);
                                    $fileStmt->bind_param("i", $row['AppointmentID']);
                                    $fileStmt->execute();
                                    $fileResult = $fileStmt->get_result();
                                    
                                    if ($fileResult->num_rows > 0) {
                                        $fileData = $fileResult->fetch_assoc();
                                        echo '<div class="mb-2">';
                                        echo '<button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#fileModal' . $row['AppointmentID'] . '">';
                                        echo '<i class="bi bi-file-earmark-text"></i> View Uploaded File';
                                        echo '</button>';
                                        echo '</div>';
                                        // Add modal for this file
                                        echo '<div class="modal fade" id="fileModal' . $row['AppointmentID'] . '" tabindex="-1" aria-labelledby="fileModalLabel' . $row['AppointmentID'] . '" aria-hidden="true">';
                                        echo '<div class="modal-dialog modal-xl">';
                                        echo '<div class="modal-content">';
                                        echo '<div class="modal-header">';
                                        echo '<h5 class="modal-title" id="fileModalLabel' . $row['AppointmentID'] . '">' . htmlspecialchars($fileData['FileName']) . '</h5>';
                                        echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
                                        echo '</div>';
                                        echo '<div class="modal-body file-preview">';
                                        // Check file type and display accordingly
                                        $fileExtension = strtolower(pathinfo($fileData['FilePath'], PATHINFO_EXTENSION));
                                        if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
                                            echo '<img src="' . htmlspecialchars($fileData['FilePath']) . '" alt="Uploaded File" class="img-fluid">';
                                        } else if ($fileExtension === 'pdf') {
                                            echo '<iframe src="' . htmlspecialchars($fileData['FilePath']) . '" class="pdf-viewer"></iframe>';
                                        } else {
                                            echo '<div class="text-center">';
                                            echo '<p class="mb-3">File type not supported for preview. Please download the file to view it.</p>';
                                            echo '<a href="' . htmlspecialchars($fileData['FilePath']) . '" class="btn btn-primary" download>Download File</a>';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                        echo '<div class="modal-footer">';
                                        echo '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
                                        echo '</div>';
                                        echo '</div>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                    ?>
                                    <form action="upload_result.php" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2 flex-wrap">
                                        <input type="hidden" name="appointment_id" value="<?php echo $row['AppointmentID']; ?>">
                                        <input type="file" name="result_file" id="fileInput<?php echo $row['AppointmentID']; ?>" class="d-none" accept=".pdf,.doc,.docx,.jpg,.png" required>
                                        <label for="fileInput<?php echo $row['AppointmentID']; ?>" class="btn btn-outline-primary btn-sm mb-0"><i class="bi bi-paperclip"></i> Choose File</label>
                                        <span class="file-chosen text-muted small d-none" id="fileName<?php echo $row['AppointmentID']; ?>"></span>
                                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Upload</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No patients with appointments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Firebase Messaging Integration -->
    <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-app.js";
    import { getMessaging, getToken, onMessage } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-messaging.js";

    const firebaseConfig = {
      apiKey: "AIzaSyDzmfrIS9TtKygAFOPw6PEwlG8NER3pgZU",
      authDomain: "medical-clinic-notify-6d018.firebaseapp.com",
      projectId: "medical-clinic-notify-6d018",
      storageBucket: "medical-clinic-notify-6d018.firebasestorage.app",
      messagingSenderId: "885466843892",
      appId: "1:885466843892:web:111057bc6ee216f482eca1",
      measurementId: "G-1ENT4SX289"
    };

    const app = initializeApp(firebaseConfig);
    const messaging = getMessaging(app);

    getToken(messaging, { vapidKey: "BArAmBHOAwLmsR4p0CYSCrwQoMa6TVB_Z2w8aaM0iEOgQ92B2WdccCUcHKuuFnpz-zUymr212MmjJIIi2HHlj5k" }).then((currentToken) => {
        if (currentToken) {
            console.log("Token:", currentToken);
            // TODO: send token to server
        } else {
            console.warn("No registration token available.");
        }
    }).catch((err) => {
        console.error("An error occurred while retrieving token.", err);
    });

    onMessage(messaging, (payload) => {
        console.log("Message received. ", payload);
        alert(payload.notification.title + "\n" + payload.notification.body);
    });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            const mainContent = document.querySelector('.main-content');
            const topBar = document.querySelector('.top-bar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function setSidebarState() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.add('collapsed');
                    toggleBtn.classList.remove('expanded');
                    mainContent.style.marginLeft = '0';
                    topBar.style.marginLeft = '0';
                    topBar.style.width = '100%';
                    sidebarOverlay.classList.remove('active');
                } else {
                    sidebar.classList.remove('collapsed');
                    toggleBtn.classList.add('expanded');
                    mainContent.style.marginLeft = '240px';
                    topBar.style.marginLeft = '240px';
                    topBar.style.width = 'calc(100% - 240px)';
                    sidebarOverlay.classList.remove('active');
                }
            }

            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                toggleBtn.classList.toggle('collapsed');
                toggleBtn.classList.toggle('expanded');
                if (sidebar.classList.contains('collapsed')) {
                    mainContent.style.marginLeft = '0';
                    topBar.style.marginLeft = '0';
                    topBar.style.width = '100%';
                    sidebarOverlay.classList.remove('active');
                } else {
                    if (window.innerWidth <= 992) {
                        sidebarOverlay.classList.add('active');
                    }
                    mainContent.style.marginLeft = window.innerWidth > 992 ? '240px' : '0';
                    topBar.style.marginLeft = window.innerWidth > 992 ? '240px' : '0';
                    topBar.style.width = window.innerWidth > 992 ? 'calc(100% - 240px)' : '100%';
                }
            });

            sidebarOverlay.addEventListener('click', function() {
                if (!sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                    toggleBtn.classList.remove('expanded');
                    toggleBtn.classList.add('collapsed');
                    sidebarOverlay.classList.remove('active');
                    mainContent.style.marginLeft = '0';
                    topBar.style.marginLeft = '0';
                    topBar.style.width = '100%';
                }
            });

            window.addEventListener('resize', setSidebarState);
            setSidebarState();
        });
    </script>
    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($result->num_rows > 0): ?>
                <?php $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
                    var fileInput<?php echo $row['AppointmentID']; ?> = document.getElementById('fileInput<?php echo $row['AppointmentID']; ?>');
                    var fileName<?php echo $row['AppointmentID']; ?> = document.getElementById('fileName<?php echo $row['AppointmentID']; ?>');
                    if (fileInput<?php echo $row['AppointmentID']; ?>) {
                        fileInput<?php echo $row['AppointmentID']; ?>.addEventListener('change', function() {
                            if (this.files && this.files.length > 0) {
                                fileName<?php echo $row['AppointmentID']; ?>.textContent = this.files[0].name;
                                fileName<?php echo $row['AppointmentID']; ?>.classList.remove('d-none');
                            } else {
                                fileName<?php echo $row['AppointmentID']; ?>.textContent = '';
                                fileName<?php echo $row['AppointmentID']; ?>.classList.add('d-none');
                            }
                        });
                    }
                <?php endwhile; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php
include 'config.php';
require 'send_mail.php';

$dateToday = date('Y-m-d');
$successCount = 0;
$failCount = 0;

echo "Checking for appointments on date: " . $dateToday . "\n\n";

// Get all approved appointments for today
$query = "SELECT a.*, s.FirstName AS StudentFirstName, s.LastName AS StudentLastName, s.email AS StudentEmail,
                 d.FirstName AS DoctorFirstName, d.LastName AS DoctorLastName,
                 ts.StartTime, ts.EndTime
          FROM appointments a
          JOIN students s ON a.StudentID = s.StudentID
          JOIN doctors d ON a.DoctorID = d.DoctorID
          JOIN timeslots ts ON a.SlotID = ts.SlotID
          WHERE a.AppointmentDate = ? AND a.StatusID = 2"; // Only approved

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error . "\n");
}

$stmt->bind_param("s", $dateToday);
if (!$stmt->execute()) {
    die("Error executing statement: " . $stmt->error . "\n");
}

$result = $stmt->get_result();
echo "SQL Query executed successfully.\n";
echo "Found " . $result->num_rows . " approved appointments for today.\n\n";

while ($appointment = $result->fetch_assoc()) {
    $to = $appointment['StudentEmail'];
    $toName = $appointment['StudentFirstName'] . ' ' . $appointment['StudentLastName'];
    $doctorName = $appointment['DoctorFirstName'] . ' ' . $appointment['DoctorLastName'];
    $appointmentDate = $appointment['AppointmentDate'];
    $appointmentTime = date('g:i A', strtotime($appointment['StartTime'])) . ' - ' . date('g:i A', strtotime($appointment['EndTime']));
    $reason = $appointment['Reason'];

    echo "Attempting to send reminder to: " . $to . "\n";

    $subject = "Your Appointment is Today! - Medical Clinic Notify+";
    $bodyHtml = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; padding: 20px;'>
            <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h2 style='color: #1976d2; font-size: 24px; margin-bottom: 10px;'>Today's Appointment Reminder</h2>
                    <p style='color: #666; font-size: 16px; margin: 0;'>Your appointment is scheduled for today!</p>
                </div>

                <div style='margin-bottom: 25px;'>
                    <p style='font-size: 16px; color: #444; margin-bottom: 15px;'>Dear " . htmlspecialchars($toName) . ",</p>
                    <p style='font-size: 16px; color: #444; line-height: 1.5;'>This is a friendly reminder about your appointment scheduled for today. Here are your appointment details:</p>
                </div>

                <div style='background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #666;'>Doctor:</td>
                            <td style='padding: 8px 0; color: #333; font-weight: 500;'>Dr. " . htmlspecialchars($doctorName) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #666;'>Date:</td>
                            <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . date('F j, Y', strtotime($appointmentDate)) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #666;'>Time:</td>
                            <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . $appointmentTime . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #666;'>Purpose:</td>
                            <td style='padding: 8px 0; color: #333; font-weight: 500;'>" . htmlspecialchars($reason) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #666;'>Status:</td>
                            <td style='padding: 8px 0;'><span style='background-color: #28a745; color: white; padding: 5px 10px; border-radius: 15px; font-size: 14px;'>Confirmed</span></td>
                        </tr>
                    </table>
                </div>

                <div style='background: #e3f0fc; border-radius: 8px; padding: 16px; margin-bottom: 25px; color: #1976d2;'>
                    <p style='margin: 0; font-weight: 500; margin-bottom: 10px;'>Important Reminders:</p>
                    <ul style='margin: 0; padding-left: 20px;'>
                        <li style='margin-bottom: 5px;'>Please arrive 10-15 minutes before your scheduled time</li>
                        <li style='margin-bottom: 5px;'>Bring a valid ID and your medical records (if any)</li>
                        <li style='margin-bottom: 5px;'>Wear a face mask inside the clinic</li>
                        <li style='margin-bottom: 5px;'>If you're feeling unwell, please inform the staff upon arrival</li>
                        <li>Contact us immediately if you need to reschedule</li>
                    </ul>
                </div>

                <div style='background-color: #fff3cd; border-radius: 8px; padding: 16px; margin-bottom: 25px; color: #856404;'>
                    <p style='margin: 0; font-weight: 500;'>Health & Safety Protocol:</p>
                    <ul style='margin: 10px 0 0 0; padding-left: 20px;'>
                        <li>Temperature check upon entry</li>
                        <li>Hand sanitization required</li>
                        <li>Social distancing measures in place</li>
                    </ul>
                </div>

                <p style='font-size: 16px; color: #444; margin-bottom: 25px;'>If you have any questions or need to contact us, please don't hesitate to reach out.</p>

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

    if (sendAppointmentEmail($to, $toName, $subject, $bodyHtml)) {
        $successCount++;
        echo "Successfully sent reminder to: " . $to . "\n";
    } else {
        $failCount++;
        echo "Failed to send reminder to: " . $to . "\n";
    }
}

echo "\nSummary:\n";
echo "Total appointments processed: " . ($successCount + $failCount) . "\n";
echo "Successfully sent: " . $successCount . "\n";
echo "Failed to send: " . $failCount . "\n";
?>

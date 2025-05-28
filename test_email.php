<?php
require_once 'send_mail.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Email Functionality</h2>";

try {
    $to = "medicalclinicnotify@gmail.com"; // Replace with your email
    $toName = "Test User";
    $subject = "Test Email from Medical Clinic System";
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px;'>
            <h2>Test Email</h2>
            <p>This is a test email to verify the email sending functionality.</p>
            <p>Time sent: " . date('Y-m-d H:i:s') . "</p>
        </div>
    ";

    echo "<p>Attempting to send test email...</p>";
    
    $result = sendAppointmentEmail($to, $toName, $subject, $body);
    
    if ($result) {
        echo "<p style='color: green;'>Email sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>Failed to send email.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Display PHP mail configuration
echo "<h3>PHP Mail Configuration:</h3>";
echo "<pre>";
print_r(ini_get_all('mail'));
echo "</pre>";

// Display SMTP settings (excluding password)
echo "<h3>SMTP Settings:</h3>";
echo "<pre>";
echo "Host: smtp.gmail.com\n";
echo "Port: 465\n";
echo "Security: SMTPS\n";
echo "Username: medicalclinicnotify@gmail.com\n";
echo "</pre>";
?> 
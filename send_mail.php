<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php'; // or require 'PHPMailerAutoload.php';

function sendAppointmentEmail($to, $toName, $subject, $bodyHtml) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };
        
        // Detailed logging
        error_log("=== Starting Email Send Process ===");
        error_log("To: " . $to);
        error_log("Name: " . $toName);
        error_log("Subject: " . $subject);
        
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'medicalclinicnotify@gmail.com';
        $mail->Password = 'owqsmmcggbhwnxgs'; // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SSL instead of TLS
        $mail->Port = 465; // SSL port
        $mail->Timeout = 60; // Increased timeout
        $mail->CharSet = 'UTF-8';
        
        // Log SMTP settings
        error_log("SMTP Settings:");
        error_log("Host: " . $mail->Host);
        error_log("Port: " . $mail->Port);
        error_log("SMTPSecure: " . $mail->SMTPSecure);
        error_log("Username: " . $mail->Username);
        
        // Recipients
        $mail->setFrom('medicalclinicnotify@gmail.com', 'Medical Clinic Notify+');
        $mail->addAddress($to, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        
        // Send email
        $result = $mail->send();
        error_log("Email sent successfully!");
        error_log("=== End Email Send Process ===");
        return true;
        
    } catch (Exception $e) {
        error_log("=== Email Send Error ===");
        error_log("Error Message: " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        error_log("Stack Trace: " . $e->getTraceAsString());
        error_log("SMTP Debug Output: " . print_r($mail->SMTPDebug, true));
        error_log("=== End Email Error ===");
        return false;
    }
}

function sendAppointmentEmailWithAttachment($to, $toName, $subject, $bodyHtml, $attachmentPath, $attachmentName = '') {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Disable debug output in production
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'medicalclinicnotify@gmail.com';
        $mail->Password = 'owqsmmcggbhwnxgs'; // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SSL
        $mail->Port = 465; // SSL port
        $mail->Timeout = 60; // Increased timeout
        $mail->CharSet = 'UTF-8';
        
        // Detailed logging to error_log
        error_log("=== Starting Email with Attachment Send Process ===");
        error_log("To: " . $to);
        error_log("Name: " . $toName);
        error_log("Subject: " . $subject);
        error_log("Attachment Path: " . $attachmentPath);
        
        // Recipients
        $mail->setFrom('medicalclinicnotify@gmail.com', 'Medical Clinic Notify+');
        $mail->addAddress($to, $toName);
        
        // Attachment
        if (file_exists($attachmentPath)) {
            if (empty($attachmentName)) {
                $attachmentName = basename($attachmentPath);
            }
            $mail->addAttachment($attachmentPath, $attachmentName);
            error_log("Attachment added successfully: " . $attachmentPath);
        } else {
            error_log("Warning: Attachment file not found: " . $attachmentPath);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        
        // Send email
        $result = $mail->send();
        error_log("Email with attachment sent successfully!");
        error_log("=== End Email with Attachment Send Process ===");
        return true;
        
    } catch (Exception $e) {
        error_log("=== Email with Attachment Send Error ===");
        error_log("Error Message: " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        error_log("Stack Trace: " . $e->getTraceAsString());
        error_log("=== End Email Error ===");
        return false;
    }
}

// Test the email functionality (only run once when needed)
function testEmailConnection() {
    error_log("=== Testing Email Connection ===");
    try {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Disable debug output
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'medicalclinicnotify@gmail.com';
        $mail->Password = 'owqsmmcggbhwnxgs';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        
        // Try to send a test email
        $mail->setFrom('medicalclinicnotify@gmail.com', 'Medical Clinic Notify+');
        $mail->addAddress('medicalclinicnotify@gmail.com');
        $mail->Subject = 'SMTP Connection Test';
        $mail->Body = 'This is a test email to verify SMTP connection.';
        
        $mail->send();
        error_log("SMTP Connection Test: Success");
        return true;
    } catch (Exception $e) {
        error_log("SMTP Connection Test: Failed");
        error_log("Error: " . $e->getMessage());
        return false;
    }
}

// Comment out the test when not needed
// testEmailConnection();
?>

<?php
session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php'; // Include your database connection

// Get OTP from request
$data = json_decode(file_get_contents("php://input"), true);
$otp = $data['otp'] ?? '';

if (!$otp) {
    echo json_encode(["success" => false, "message" => "Missing OTP."]);
    exit();
}

// Check OTP
$stmt = $conn->prepare("SELECT email, otp_expiry FROM otp_verification WHERE otp = ?");
$stmt->bind_param("s", $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $email = $row['email'];
    $otpExpiry = strtotime($row['otp_expiry']);

    if (time() > $otpExpiry) {
        echo json_encode(["success" => false, "message" => "OTP has expired."]);
        exit();
    }

    // Remove used OTP
    $stmt = $conn->prepare("DELETE FROM otp_verification WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    // Store admin session
    $_SESSION['adminEmail'] = $email;
    
    // Get adminID for dashboard
    $stmt2 = $conn->prepare("SELECT adminID FROM admins WHERE adminEmail = ?");
    $stmt2->bind_param("s", $email);
    $stmt2->execute();
    $stmt2->bind_result($adminID);
    $stmt2->fetch();
    $stmt2->close();
    $_SESSION['adminID'] = $adminID;

    // Return success response
    echo json_encode([
        "success" => true,
        "message" => "OTP verified successfully.",
        "redirect" => "admin_profile.php"
    ]);
    exit();
} else {
    echo json_encode(["success" => false, "message" => "Invalid OTP."]);
    exit();
}
?> 
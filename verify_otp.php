<?php
require_once 'session_helper.php';

header('Content-Type: application/json');

// Check if session is valid
if (!validateSession()) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

// Check if OTP data exists in session
if (!isset($_SESSION['otp_data'])) {
    echo json_encode(['success' => false, 'message' => 'OTP session expired. Please request a new OTP.']);
    exit();
}

// Get OTP from request
$data = json_decode(file_get_contents("php://input"), true);
$submitted_otp = $data['otp'] ?? '';

if (!$submitted_otp) {
    echo json_encode(['success' => false, 'message' => 'Missing OTP.']);
    exit();
}

// Verify OTP
$stored_otp = $_SESSION['otp_data']['otp'];
$otp_expiry = strtotime($_SESSION['otp_data']['expiry']);

if (time() > $otp_expiry) {
    echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
    exit();
}

if ($submitted_otp === $stored_otp) {
    // OTP is valid, mark user as verified
    $_SESSION['is_verified'] = true;
    
    // Clear OTP data from session
    unset($_SESSION['otp_data']);
    
    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully.',
        'redirect' => 'studentHome.php'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
}
?> 
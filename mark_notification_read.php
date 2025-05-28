<?php
include 'config.php';
session_start();

if (!isset($_SESSION['studentID'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_POST['notification_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Notification ID not provided']);
    exit;
}

$notification_id = $_POST['notification_id'];
$student_id = $_SESSION['studentID'];

// Verify that the notification belongs to the student
$verifyQuery = "SELECT notificationID FROM notifications WHERE notificationID = ? AND studentID = ?";
$verifyStmt = $conn->prepare($verifyQuery);
$verifyStmt->bind_param("ii", $notification_id, $student_id);
$verifyStmt->execute();
$result = $verifyStmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Notification not found or unauthorized']);
    exit;
}

// Mark the notification as read
$updateQuery = "UPDATE notifications SET is_read = 1 WHERE notificationID = ?";
$updateStmt = $conn->prepare($updateQuery);
$updateStmt->bind_param("i", $notification_id);

if ($updateStmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
}
?> 
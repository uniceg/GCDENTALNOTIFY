<?php
include 'config.php';
session_start();

if (!isset($_SESSION['studentID']) || !isset($_POST['notification_ids'])) {
    exit;
}

$student_id = $_SESSION['studentID'];
$notification_ids = json_decode($_POST['notification_ids'], true);

if (!is_array($notification_ids) || empty($notification_ids)) {
    exit;
}

// Convert the array to a comma-separated string of IDs
$id_list = implode(',', array_map('intval', $notification_ids));

// Update all the notifications to mark them as read
$query = "UPDATE notifications SET is_read = 1 WHERE notificationID IN ($id_list) AND studentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();

echo "success";
?>
<?php
session_start();
include('includes/config.php');

// Make sure the student is logged in
if (isset($_SESSION['student_id']) && isset($_POST['token'])) {
    $studentID = $_SESSION['student_id'];
    $token = $_POST['token'];

    $stmt = $con->prepare("UPDATE students SET firebase_token = ? WHERE ID = ?");
    $stmt->bind_param("si", $token, $studentID);
    $stmt->execute();

    echo "Token saved.";
} else {
    echo "Invalid request.";
}
?>

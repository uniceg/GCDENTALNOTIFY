<?php
session_start();
header('Content-Type: application/json');

// Check if the studentID session variable is set
if (isset($_SESSION['studentID'])) {
    echo json_encode(['user_id' => $_SESSION['studentID']]);
} else {
    echo json_encode(['user_id' => null]);
}
?>

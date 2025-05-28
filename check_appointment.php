<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "Database connection successful.\n\n";

$appointmentID = 67;
$query = "SELECT a.*, s.email, s.FirstName, s.LastName 
          FROM appointments a 
          JOIN students s ON a.StudentID = s.StudentID 
          WHERE a.AppointmentID = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error . "\n");
}

$stmt->bind_param("i", $appointmentID);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error . "\n");
}

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "Appointment Details:\n";
    echo "ID: " . $row['AppointmentID'] . "\n";
    echo "Date: " . $row['AppointmentDate'] . "\n";
    echo "Status: " . $row['StatusID'] . "\n";
    echo "Student Email: " . $row['email'] . "\n";
    echo "Student Name: " . $row['FirstName'] . " " . $row['LastName'] . "\n";
} else {
    echo "Appointment not found.\n";
}
?> 
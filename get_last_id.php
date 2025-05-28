<?php
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("localhost", "root", "", "medicalclinicnotify");

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Get the current year and month
$year = date('y');
$month = date('m');

// Query to get the last ID for the current year and month
$query = "SELECT StudentID FROM students 
          WHERE StudentID LIKE '$year$month-%' 
          ORDER BY StudentID DESC 
          LIMIT 1";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(['lastId' => $row['StudentID']]);
} else {
    echo json_encode(['lastId' => null]);
}

$conn->close();
?> 
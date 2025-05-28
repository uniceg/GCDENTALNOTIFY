<?php
include 'config.php';

// Check students table structure
$query = "DESCRIBE students";
$result = $conn->query($query);

if ($result) {
    echo "Students table structure:\n";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
}

// Check some sample data
$dataQuery = "SELECT StudentID, FirstName, LastName FROM students LIMIT 5";
$result = $conn->query($dataQuery);

if ($result) {
    echo "\nSample student data:\n";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
}

$conn->close();
?> 
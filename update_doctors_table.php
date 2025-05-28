<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "medicalclinicnotify";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL to add new columns
$sql = "ALTER TABLE doctors 
        ADD COLUMN IF NOT EXISTS Specialization VARCHAR(100) AFTER LastName,
        ADD COLUMN IF NOT EXISTS Status VARCHAR(20) DEFAULT 'Active' AFTER Email";

if ($conn->query($sql) === TRUE) {
    echo "Table doctors updated successfully";
} else {
    echo "Error updating table: " . $conn->error;
}

$conn->close();
?> 
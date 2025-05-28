<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "medicalclinicnotify";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add reset_token and reset_expires columns to students table
$sql = "ALTER TABLE students 
        ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS reset_expires DATETIME DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Columns added successfully";
} else {
    echo "Error adding columns: " . $conn->error;
}

$conn->close();
?> 
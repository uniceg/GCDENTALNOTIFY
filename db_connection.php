<?php
// Database connection configuration
$servername = "localhost"; // Database server (usually localhost)
$username = "root";        // Database username (default: root)
$password = "";            // Database password (default: none for localhost)
$dbname = "medicalclinicnotify";           // Database name (replace with your database name)

// Create a new mysqli connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

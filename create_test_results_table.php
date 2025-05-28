<?php
include 'db_connection.php';

$sql = "CREATE TABLE IF NOT EXISTS test_results (
    ResultID INT AUTO_INCREMENT PRIMARY KEY,
    AppointmentID INT NOT NULL,
    FilePath VARCHAR(255) NOT NULL,
    FileName VARCHAR(255) NOT NULL,
    UploadDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (AppointmentID) REFERENCES appointments(AppointmentID)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table test_results created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?> 
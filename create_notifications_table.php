<?php
include 'db_connection.php';

$sql = "CREATE TABLE IF NOT EXISTS notifications (
    notificationID INT AUTO_INCREMENT PRIMARY KEY,
    studentID INT NOT NULL,
    appointmentID INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (studentID) REFERENCES students(StudentID),
    FOREIGN KEY (appointmentID) REFERENCES appointments(AppointmentID)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table notifications created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?> 
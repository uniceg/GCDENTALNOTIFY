<?php
include 'config.php';

try {
    // Start transaction
    $conn->begin_transaction();

    // 1. First, update existing notifications with correct student IDs from appointments
    $updateQuery = "
        UPDATE notifications n
        JOIN appointments a ON n.appointmentID = a.AppointmentID
        SET n.studentID = a.StudentID
        WHERE n.studentID = '0' OR n.studentID IS NULL";
    
    if ($conn->query($updateQuery)) {
        echo "Updated existing notifications with correct student IDs\n";
    }

    // 2. Modify the notifications table to enforce foreign key constraint
    $alterQuery = "
        ALTER TABLE notifications 
        MODIFY COLUMN studentID VARCHAR(20) NOT NULL,
        ADD CONSTRAINT fk_notifications_student 
        FOREIGN KEY (studentID) REFERENCES students(StudentID)";
    
    if ($conn->query($alterQuery)) {
        echo "Modified notifications table structure\n";
    }

    // Commit the changes
    $conn->commit();
    echo "Changes committed successfully\n";

    // Verify the changes
    $verifyQuery = "SELECT DISTINCT n.studentID, s.FirstName, s.LastName 
                   FROM notifications n 
                   JOIN students s ON n.studentID = s.StudentID";
    $result = $conn->query($verifyQuery);
    
    echo "\nVerifying notifications:\n";
    while ($row = $result->fetch_assoc()) {
        echo "StudentID: " . $row['studentID'] . ", Student: " . $row['FirstName'] . " " . $row['LastName'] . "\n";
    }

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}
?> 
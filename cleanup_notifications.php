<?php
include 'config.php';

try {
    $conn->begin_transaction();

    // 1. First drop existing constraint if it exists
    try {
        $conn->query("ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_student");
        echo "Dropped existing foreign key constraint\n";
    } catch (Exception $e) {
        // Ignore error if constraint doesn't exist
    }

    // 2. Delete any notifications with invalid student IDs
    $deleteInvalidQuery = "DELETE FROM notifications WHERE studentID = '0' OR studentID IS NULL";
    $conn->query($deleteInvalidQuery);
    echo "Deleted notifications with invalid student IDs\n";

    // 3. Modify the column to NOT NULL
    $alterColumnQuery = "ALTER TABLE notifications MODIFY COLUMN studentID VARCHAR(20) NOT NULL";
    $conn->query($alterColumnQuery);
    echo "Modified studentID column\n";

    // 4. Create new notifications for PT-20250513-2289
    $insertQuery1 = "
        INSERT INTO notifications (studentID, appointmentID, message, created_at, is_read) 
        SELECT 
            'PT-20250513-2289',
            a.AppointmentID,
            CONCAT(
                'Your appointment with Dr. ', d.FirstName, ' ', d.LastName,
                ' on ', DATE_FORMAT(a.AppointmentDate, '%M %d, %Y'),
                ' has been scheduled.'
            ),
            NOW(),
            0
        FROM appointments a
        JOIN doctors d ON a.doctorID = d.doctorID
        WHERE a.StudentID = 'PT-20250513-2289'";
    $conn->query($insertQuery1);
    echo "Created notifications for PT-20250513-2289\n";

    // 5. Create new notifications for PT-20250513-7498
    $insertQuery2 = "
        INSERT INTO notifications (studentID, appointmentID, message, created_at, is_read) 
        SELECT 
            'PT-20250513-7498',
            a.AppointmentID,
            CONCAT(
                'Your appointment with Dr. ', d.FirstName, ' ', d.LastName,
                ' on ', DATE_FORMAT(a.AppointmentDate, '%M %d, %Y'),
                ' has been scheduled.'
            ),
            NOW(),
            0
        FROM appointments a
        JOIN doctors d ON a.doctorID = d.doctorID
        WHERE a.StudentID = 'PT-20250513-7498'";
    $conn->query($insertQuery2);
    echo "Created notifications for PT-20250513-7498\n";

    // 6. Add the foreign key constraint
    $addConstraintQuery = "
        ALTER TABLE notifications 
        ADD CONSTRAINT fk_notifications_student 
        FOREIGN KEY (studentID) REFERENCES students(StudentID)";
    $conn->query($addConstraintQuery);
    echo "Added foreign key constraint\n";

    $conn->commit();
    echo "Changes committed successfully\n";

    // Verify the changes
    $verifyQuery = "
        SELECT 
            n.studentID, 
            s.FirstName,
            s.LastName,
            COUNT(*) as notification_count 
        FROM notifications n 
        JOIN students s ON n.studentID = s.StudentID
        GROUP BY n.studentID, s.FirstName, s.LastName";
    $result = $conn->query($verifyQuery);
    
    echo "\nNotification counts by student:\n";
    while ($row = $result->fetch_assoc()) {
        echo "StudentID: " . $row['studentID'] . 
             " (" . $row['FirstName'] . " " . $row['LastName'] . ")" .
             ", Count: " . $row['notification_count'] . "\n";
    }

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}
?> 
<?php
include 'config.php';

echo "Fixing database structure...\n\n";

// Start transaction
$conn->begin_transaction();

try {
    // 1. Add Cancellation Requested status if it doesn't exist
    $checkStatus = "SELECT COUNT(*) as count FROM status WHERE statusID = 5";
    $result = $conn->query($checkStatus);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $addStatus = "INSERT INTO status (statusID, status_name) VALUES (5, 'Cancellation Requested')";
        $conn->query($addStatus);
        echo "Added Cancellation Requested status\n";
    }

    // 2. Add cancellation_reason column to notifications if it doesn't exist
    $checkColumn = "SHOW COLUMNS FROM notifications LIKE 'cancellation_reason'";
    $result = $conn->query($checkColumn);
    
    if ($result->num_rows == 0) {
        $addColumn = "ALTER TABLE notifications ADD COLUMN cancellation_reason TEXT";
        $conn->query($addColumn);
        echo "Added cancellation_reason column to notifications table\n";
    }

    // 3. Add is_read column if it doesn't exist
    $checkIsRead = "SHOW COLUMNS FROM notifications LIKE 'is_read'";
    $result = $conn->query($checkIsRead);
    
    if ($result->num_rows == 0) {
        $addIsRead = "ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0";
        $conn->query($addIsRead);
        echo "Added is_read column to notifications table\n";
    }

    // 4. Verify status table contents
    echo "\nCurrent status table contents:\n";
    $statusQuery = "SELECT * FROM status ORDER BY statusID";
    $result = $conn->query($statusQuery);
    while ($row = $result->fetch_assoc()) {
        echo "StatusID: " . $row['statusID'] . ", Name: " . $row['status_name'] . "\n";
    }

    // 5. Check for any pending cancellation requests with wrong status
    $checkRequests = "SELECT a.AppointmentID, a.StatusID, n.cancellation_reason 
                     FROM appointments a 
                     JOIN notifications n ON a.AppointmentID = n.appointmentID 
                     WHERE n.cancellation_reason IS NOT NULL 
                     AND a.StatusID != 5";
    
    $result = $conn->query($checkRequests);
    if ($result->num_rows > 0) {
        echo "\nFixing " . $result->num_rows . " appointments with incorrect status:\n";
        while ($row = $result->fetch_assoc()) {
            $updateStatus = "UPDATE appointments SET StatusID = 5 WHERE AppointmentID = " . $row['AppointmentID'];
            $conn->query($updateStatus);
            echo "Fixed AppointmentID: " . $row['AppointmentID'] . "\n";
        }
    }

    // Commit all changes
    $conn->commit();
    echo "\nDatabase structure fixed successfully!\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

?> 
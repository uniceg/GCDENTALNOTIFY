<?php
include 'config.php';

echo "Fixing status table and verifying data integrity...\n\n";

$conn->begin_transaction();

try {
    // 1. Ensure all required statuses exist
    $requiredStatuses = [
        1 => 'Pending',
        2 => 'Approved',
        3 => 'Completed',
        4 => 'Cancelled',
        5 => 'Cancellation Requested'
    ];

    foreach ($requiredStatuses as $id => $name) {
        $stmt = $conn->prepare("INSERT INTO status (statusID, status_name) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE status_name = VALUES(status_name)");
        $stmt->bind_param("is", $id, $name);
        $stmt->execute();
    }

    echo "Status table updated.\n\n";

    // 2. Check for any appointments with invalid status IDs
    $invalidStatus = "SELECT AppointmentID, StatusID FROM appointments 
                     WHERE StatusID NOT IN (1, 2, 3, 4, 5)";
    $result = $conn->query($invalidStatus);
    
    if ($result->num_rows > 0) {
        echo "Found " . $result->num_rows . " appointments with invalid status. Fixing...\n";
        while ($row = $result->fetch_assoc()) {
            echo "Appointment ID: " . $row['AppointmentID'] . " had invalid status: " . $row['StatusID'] . "\n";
            // Set to Pending by default
            $updateStmt = $conn->prepare("UPDATE appointments SET StatusID = 1 WHERE AppointmentID = ?");
            $updateStmt->bind_param("i", $row['AppointmentID']);
            $updateStmt->execute();
        }
    }

    // 3. Check for cancellation requests without notifications
    $missingNotifs = "SELECT a.AppointmentID, a.StudentID 
                      FROM appointments a 
                      LEFT JOIN notifications n ON a.AppointmentID = n.appointmentID 
                      WHERE a.StatusID = 5 
                      AND (n.cancellation_reason IS NULL OR n.appointmentID IS NULL)";
    $result = $conn->query($missingNotifs);
    
    if ($result->num_rows > 0) {
        echo "\nFound " . $result->num_rows . " cancellation requests without proper notifications. Fixing...\n";
        while ($row = $result->fetch_assoc()) {
            // Create a notification for the missing cancellation request
            $message = "Cancellation requested for appointment";
            $reason = "System automated fix - No reason provided";
            $notifStmt = $conn->prepare("INSERT INTO notifications (studentID, appointmentID, message, cancellation_reason, is_read) 
                                       VALUES (?, ?, ?, ?, 0)");
            $notifStmt->bind_param("siss", $row['StudentID'], $row['AppointmentID'], $message, $reason);
            $notifStmt->execute();
            echo "Created missing notification for Appointment ID: " . $row['AppointmentID'] . "\n";
        }
    }

    // 4. Display current status counts
    echo "\nCurrent appointment status counts:\n";
    $statusCounts = "SELECT s.status_name, COUNT(a.AppointmentID) as count 
                    FROM status s 
                    LEFT JOIN appointments a ON s.statusID = a.StatusID 
                    GROUP BY s.statusID, s.status_name 
                    ORDER BY s.statusID";
    $result = $conn->query($statusCounts);
    while ($row = $result->fetch_assoc()) {
        echo $row['status_name'] . ": " . $row['count'] . "\n";
    }

    $conn->commit();
    echo "\nAll fixes applied successfully!\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

?> 
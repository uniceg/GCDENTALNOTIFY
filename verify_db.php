<?php
include 'config.php';

echo "Verifying database structure...\n\n";

$conn->begin_transaction();

try {
    // 1. Check status table
    $statusQuery = "SHOW TABLES LIKE 'status'";
    $result = $conn->query($statusQuery);
    
    if ($result->num_rows === 0) {
        echo "Creating status table...\n";
        $createStatus = "CREATE TABLE status (
            statusID INT PRIMARY KEY,
            status_name VARCHAR(50) NOT NULL
        )";
        $conn->query($createStatus);
    }

    // 2. Verify status entries
    $checkStatus = "SELECT * FROM status";
    $result = $conn->query($checkStatus);
    $existingStatuses = [];
    while ($row = $result->fetch_assoc()) {
        $existingStatuses[$row['statusID']] = $row['status_name'];
    }

    $requiredStatuses = [
        1 => 'Pending',
        2 => 'Approved',
        3 => 'Completed',
        4 => 'Cancelled',
        5 => 'Cancellation Requested'
    ];

    foreach ($requiredStatuses as $id => $name) {
        if (!isset($existingStatuses[$id])) {
            echo "Adding missing status: $name (ID: $id)\n";
            $insertStatus = "INSERT INTO status (statusID, status_name) VALUES (?, ?)";
            $stmt = $conn->prepare($insertStatus);
            $stmt->bind_param("is", $id, $name);
            $stmt->execute();
        } else if ($existingStatuses[$id] !== $name) {
            echo "Updating status name for ID $id: {$existingStatuses[$id]} -> $name\n";
            $updateStatus = "UPDATE status SET status_name = ? WHERE statusID = ?";
            $stmt = $conn->prepare($updateStatus);
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();
        }
    }

    // 3. Check notifications table
    $notifQuery = "SHOW TABLES LIKE 'notifications'";
    $result = $conn->query($notifQuery);
    
    if ($result->num_rows === 0) {
        echo "Creating notifications table...\n";
        $createNotif = "CREATE TABLE notifications (
            notificationID INT AUTO_INCREMENT PRIMARY KEY,
            studentID VARCHAR(20) NOT NULL,
            appointmentID INT NOT NULL,
            message TEXT NOT NULL,
            cancellation_reason TEXT,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($createNotif);
    } else {
        // Check if cancellation_reason column exists
        $checkColumn = "SHOW COLUMNS FROM notifications LIKE 'cancellation_reason'";
        $result = $conn->query($checkColumn);
        if ($result->num_rows === 0) {
            echo "Adding cancellation_reason column to notifications table...\n";
            $addColumn = "ALTER TABLE notifications ADD COLUMN cancellation_reason TEXT";
            $conn->query($addColumn);
        }

        // Check if is_read column exists
        $checkColumn = "SHOW COLUMNS FROM notifications LIKE 'is_read'";
        $result = $conn->query($checkColumn);
        if ($result->num_rows === 0) {
            echo "Adding is_read column to notifications table...\n";
            $addColumn = "ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0";
            $conn->query($addColumn);
        }
    }

    // 4. Check admin_notifications table
    $adminNotifQuery = "SHOW TABLES LIKE 'admin_notifications'";
    $result = $conn->query($adminNotifQuery);
    
    if ($result->num_rows === 0) {
        echo "Creating admin_notifications table...\n";
        $createAdminNotif = "CREATE TABLE admin_notifications (
            notificationID INT AUTO_INCREMENT PRIMARY KEY,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($createAdminNotif);
    }

    // 5. Verify foreign key constraints
    $checkFK = "SELECT COUNT(*) as count FROM information_schema.KEY_COLUMN_USAGE 
                WHERE REFERENCED_TABLE_NAME = 'appointments' 
                AND TABLE_NAME = 'notifications' 
                AND COLUMN_NAME = 'appointmentID'";
    $result = $conn->query($checkFK);
    $row = $result->fetch_assoc();
    
    if ($row['count'] === 0) {
        echo "Adding foreign key constraint for notifications.appointmentID...\n";
        $addFK = "ALTER TABLE notifications 
                 ADD CONSTRAINT fk_notification_appointment 
                 FOREIGN KEY (appointmentID) REFERENCES appointments(AppointmentID)
                 ON DELETE CASCADE";
        $conn->query($addFK);
    }

    // 6. Check existing appointments with status 5
    $checkAppointments = "SELECT COUNT(*) as count FROM appointments WHERE StatusID = 5";
    $result = $conn->query($checkAppointments);
    $row = $result->fetch_assoc();
    echo "\nAppointments with Cancellation Requested status: " . $row['count'] . "\n";

    // Commit all changes
    $conn->commit();
    echo "\nDatabase structure verified and fixed successfully!\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

// Display current status table contents
echo "\nCurrent status table contents:\n";
$result = $conn->query("SELECT * FROM status ORDER BY statusID");
while ($row = $result->fetch_assoc()) {
    echo "StatusID: " . $row['statusID'] . ", Name: " . $row['status_name'] . "\n";
}

?> 
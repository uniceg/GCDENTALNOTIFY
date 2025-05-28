<?php
include 'config.php';

echo "Checking Database Structure...\n\n";

// Check notifications table structure
$checkNotificationsTable = "DESCRIBE notifications";
$result = $conn->query($checkNotificationsTable);

if ($result) {
    echo "Notifications Table Structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . "\n";
    }
} else {
    echo "Error checking notifications table: " . $conn->error . "\n";
}

// Check if cancellation_reason column exists
$checkColumn = "SHOW COLUMNS FROM notifications LIKE 'cancellation_reason'";
$result = $conn->query($checkColumn);

if ($result->num_rows == 0) {
    // Add cancellation_reason column if it doesn't exist
    $addColumn = "ALTER TABLE notifications ADD COLUMN cancellation_reason TEXT";
    if ($conn->query($addColumn)) {
        echo "\nAdded missing column: cancellation_reason\n";
    } else {
        echo "\nError adding column: " . $conn->error . "\n";
    }
}

// Check recent cancellation requests
echo "\nRecent Cancellation Requests:\n";
$recentRequests = "SELECT n.notificationID, n.studentID, n.appointmentID, n.message, n.cancellation_reason, 
                   a.StatusID, s.status_name
                   FROM notifications n
                   LEFT JOIN appointments a ON n.appointmentID = a.AppointmentID
                   LEFT JOIN status s ON a.StatusID = s.statusID
                   WHERE n.cancellation_reason IS NOT NULL
                   ORDER BY n.notificationID DESC LIMIT 5";

$result = $conn->query($recentRequests);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "\nNotification ID: " . $row['notificationID'];
        echo "\nAppointment ID: " . $row['appointmentID'];
        echo "\nStatus: " . $row['status_name'] . " (ID: " . $row['StatusID'] . ")";
        echo "\nReason: " . $row['cancellation_reason'];
        echo "\n------------------------";
    }
} else {
    echo "Error checking recent requests: " . $conn->error . "\n";
}

// Check status table
echo "\n\nStatus Table Contents:\n";
$statusQuery = "SELECT * FROM status ORDER BY statusID";
$result = $conn->query($statusQuery);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "StatusID: " . $row['statusID'] . ", Name: " . $row['status_name'] . "\n";
    }
} else {
    echo "Error checking status table: " . $conn->error . "\n";
}

// Add missing status if needed
$checkStatus5 = "SELECT COUNT(*) as count FROM status WHERE statusID = 5";
$result = $conn->query($checkStatus5);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $addStatus = "INSERT INTO status (statusID, status_name) VALUES (5, 'Cancellation Requested')";
    if ($conn->query($addStatus)) {
        echo "\nAdded missing status: Cancellation Requested (ID: 5)\n";
    } else {
        echo "\nError adding status: " . $conn->error . "\n";
    }
}

?> 
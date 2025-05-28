<?php
include 'config.php';

// Check status table
$statusQuery = "SELECT * FROM status ORDER BY statusID";
$result = $conn->query($statusQuery);

if ($result) {
    echo "Current Status Table Contents:\n";
    while ($row = $result->fetch_assoc()) {
        echo "StatusID: " . $row['statusID'] . ", Name: " . $row['status_name'] . "\n";
    }
} else {
    echo "Error checking status table: " . $conn->error;
}

// Check if status 5 exists
$checkStatus5 = "SELECT COUNT(*) as count FROM status WHERE statusID = 5";
$result = $conn->query($checkStatus5);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Add status 5 if it doesn't exist
    $addStatus = "INSERT INTO status (statusID, status_name) VALUES (5, 'Cancellation Requested')";
    if ($conn->query($addStatus)) {
        echo "\nAdded missing status: Cancellation Requested (ID: 5)";
    } else {
        echo "\nError adding status: " . $conn->error;
    }
}

// Check appointments with status 5
$checkAppointments = "SELECT COUNT(*) as count FROM appointments WHERE StatusID = 5";
$result = $conn->query($checkAppointments);
$row = $result->fetch_assoc();
echo "\n\nAppointments with Cancellation Requested status: " . $row['count'];

?> 
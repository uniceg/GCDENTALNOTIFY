<?php
require_once 'config.php';

// Check if the status already exists
$checkQuery = "SELECT statusID FROM status WHERE status_name = 'Cancellation Requested'";
$result = $conn->query($checkQuery);

if ($result->num_rows == 0) {
    // Add the new status
    $insertQuery = "INSERT INTO status (statusID, status_name) VALUES (5, 'Cancellation Requested')";
    try {
        $conn->query($insertQuery);
        echo "Successfully added 'Cancellation Requested' status to the status table.";
    } catch (Exception $e) {
        echo "Error adding status: " . $e->getMessage();
    }
} else {
    echo "Status 'Cancellation Requested' already exists.";
}
?> 
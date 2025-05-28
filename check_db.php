<?php
include 'config.php';

// First, let's check if the studentID is being set correctly in a new appointment
$testQuery = "INSERT INTO appointments (studentID, doctorID, appointmentDate, slotID, reason, statusID) 
              VALUES (1, 1, '2024-05-20', 1, 'Test appointment', 1)";
$conn->query($testQuery);
$insertId = $conn->insert_id;

// Now check what was actually inserted
$checkQuery = "SELECT * FROM appointments WHERE AppointmentID = $insertId";
$result = $conn->query($checkQuery);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Test appointment data:\n";
    print_r($row);
}

// Clean up the test data
$conn->query("DELETE FROM appointments WHERE AppointmentID = $insertId");

// Check the column type
$columnQuery = "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = 'medicalclinicnotify' 
                AND TABLE_NAME = 'appointments'
                ORDER BY ORDINAL_POSITION";
$result = $conn->query($columnQuery);
if ($result) {
    echo "Column information:\n";
    while ($row = $result->fetch_assoc()) {
        echo "Column: {$row['COLUMN_NAME']}\n";
        echo "Type: {$row['COLUMN_TYPE']}\n";
        echo "Nullable: {$row['IS_NULLABLE']}\n";
        echo "Default: " . ($row['COLUMN_DEFAULT'] === NULL ? "NULL" : $row['COLUMN_DEFAULT']) . "\n";
        echo "Extra: {$row['EXTRA']}\n";
        echo "------------------------\n";
    }
}

$conn->close();
?> 
<?php
require_once 'config.php';

// Add cancellation_reason column to appointments table if it doesn't exist
$alterQuery = "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS cancellation_reason TEXT DEFAULT NULL";

try {
    $conn->query($alterQuery);
    echo "Successfully added cancellation_reason column to the appointments table.";
} catch (Exception $e) {
    echo "Error adding column: " . $e->getMessage();
}
?> 
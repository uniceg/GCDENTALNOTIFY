<?php
require_once 'config.php';

// Remove cancellation_reason column from appointments table if it exists
$alterQuery = "ALTER TABLE appointments DROP COLUMN IF EXISTS cancellation_reason";

try {
    $conn->query($alterQuery);
    echo "Successfully removed cancellation_reason column from the appointments table.";
} catch (Exception $e) {
    echo "Error removing column: " . $e->getMessage();
}
?> 
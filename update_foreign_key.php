<?php
require_once 'db_connection.php';

try {
    // Disable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=0");

    // Drop the existing foreign key
    $conn->query("ALTER TABLE test_results DROP FOREIGN KEY test_results_ibfk_1");

    // Add the new foreign key with CASCADE options
    $conn->query("ALTER TABLE test_results ADD CONSTRAINT test_results_ibfk_1 
                 FOREIGN KEY (AppointmentID) 
                 REFERENCES appointments(AppointmentID) 
                 ON DELETE CASCADE 
                 ON UPDATE CASCADE");

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");

    echo "Foreign key constraint updated successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 
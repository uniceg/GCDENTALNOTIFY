<?php
include 'config.php';

try {
    // Add index to notifications table
    $sql = "ALTER TABLE notifications ADD INDEX idx_student_notifications (studentID, is_read, created_at DESC)";
    if ($conn->query($sql)) {
        echo "Successfully added index to notifications table\n";
    } else {
        echo "Error adding index: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 
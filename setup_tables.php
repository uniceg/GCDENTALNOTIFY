<?php
include 'config.php';

// Read the SQL file
$sql = file_get_contents('create_tables.sql');

// Execute multi query
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

if ($conn->error) {
    echo "Error creating tables: " . $conn->error;
} else {
    echo "Tables created successfully!";
}
?> 
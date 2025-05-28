<?php
include 'db_connection.php';

// Check if test_results table exists, if not create it
$tableCheck = $conn->query("SHOW TABLES LIKE 'test_results'");
if ($tableCheck->num_rows == 0) {
    $createTable = "CREATE TABLE test_results (
        ResultID INT AUTO_INCREMENT PRIMARY KEY,
        AppointmentID INT NOT NULL,
        FilePath VARCHAR(255) NOT NULL,
        FileName VARCHAR(255) NOT NULL,
        UploadDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (AppointmentID) REFERENCES appointments(AppointmentID)
    )";
    
    if (!$conn->query($createTable)) {
        die("Error creating test_results table: " . $conn->error);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['appointment_id']) || !isset($_FILES['result_file'])) {
        die("Missing required parameters");
    }

    $appointment_id = $_POST['appointment_id'];
    $file = $_FILES['result_file'];
    
    // Validate file
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($file['type'], $allowed_types)) {
        die("Invalid file type. Only PDF, JPEG, and PNG files are allowed.");
    }

    // Create upload directory if it doesn't exist
    $upload_dir = "uploads/results/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        die("Error uploading file");
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert into test_results table
        $stmt = $conn->prepare("INSERT INTO test_results (AppointmentID, FilePath, FileName) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }

        $stmt->bind_param("iss", $appointment_id, $upload_path, $file['name']);
        
        if (!$stmt->execute()) {
            throw new Exception("Error uploading file: " . $stmt->error);
        }

        // Update appointments table
        $updateStmt = $conn->prepare("UPDATE appointments SET TestResultFile = ? WHERE AppointmentID = ?");
        if (!$updateStmt) {
            throw new Exception("Error preparing update statement: " . $conn->error);
        }

        $updateStmt->bind_param("si", $upload_path, $appointment_id);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Error updating appointment: " . $updateStmt->error);
        }

        // Commit transaction
        $conn->commit();
        
        // Redirect back to student management page with success message
        header("Location: student_management.php?upload=success&appointment=" . $appointment_id);
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }

    $stmt->close();
    $updateStmt->close();
    $conn->close();
}
?>

<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $doctor_id = $_POST['doctor_id'];
    $target_dir = "uploads/doctor_photos/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($_FILES["doctor_photo"]["name"], PATHINFO_EXTENSION));
    $target_file = $target_dir . "doctor_" . $doctor_id . "." . $file_extension;
    
    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES["doctor_photo"]["tmp_name"]);
    if($check === false) {
        die("File is not an image.");
    }
    
    // Allow certain file formats
    if($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
        die("Sorry, only JPG, JPEG & PNG files are allowed.");
    }
    
    // Upload file
    if (move_uploaded_file($_FILES["doctor_photo"]["tmp_name"], $target_file)) {
        // Update database with new photo path
        $photo_path = $target_file;
        $sql = "UPDATE doctors SET PhotoUrl = ? WHERE DoctorID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $photo_path, $doctor_id);
        
        if ($stmt->execute()) {
            header("Location: doctors.php?success=1");
        } else {
            echo "Error updating database: " . $conn->error;
        }
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}
?>

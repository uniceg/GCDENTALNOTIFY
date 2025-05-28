<?php
// Include your database connection file
include 'config.php';

// Start session if not started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array('success' => false, 'message' => '');

    // Get form data
    $adminID = $_POST['adminID'];
    $adminName = $_POST['adminName'];
    $adminLastName = $_POST['adminLastName'];
    $adminMiddleInitial = $_POST['adminMiddleInitial'];
    $adminEmail = $_POST['adminEmail'];
    $position = "Admin"; // Always set position to Admin
    $contactNumber = $_POST['contactNumber'];

    $query = "UPDATE admins SET 
                adminName = ?, 
                adminLastName = ?, 
                adminMiddleInitial = ?, 
                adminEmail = ?, 
                position = ?, 
                contactNumber = ?
              WHERE adminID = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssi", $adminName, $adminLastName, $adminMiddleInitial, $adminEmail, $position, $contactNumber, $adminID);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Profile updated successfully!";
        // Update session data if needed
        $_SESSION['adminName'] = $adminName;
        $_SESSION['adminLastName'] = $adminLastName;
        $_SESSION['adminMiddleInitial'] = $adminMiddleInitial;
    } else {
        $response['message'] = "Failed to update profile.";
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// If not POST request, redirect to profile page
header('Location: admin_profile.php');
exit();
?>

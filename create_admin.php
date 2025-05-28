<?php
require_once 'config.php';

// Admin credentials
$admin_email = "admin@yourinstitution.com"; // Change this to your admin email
$admin_password = "admin123"; // Change this to your desired password
$admin_role = "admin";

// Hash the password
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

// Check if admin already exists
$check_sql = "SELECT * FROM users WHERE email = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $admin_email);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows == 0) {
    // Insert admin user
    $sql = "INSERT INTO users (email, password, role, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $admin_email, $hashed_password, $admin_role);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully!<br>";
        echo "Email: " . $admin_email . "<br>";
        echo "Password: " . $admin_password . "<br>";
        echo "<a href='institutional_login.php'>Go to Login</a>";
    } else {
        echo "Error creating admin user: " . $conn->error;
    }
} else {
    echo "Admin user already exists!<br>";
    echo "<a href='institutional_login.php'>Go to Login</a>";
}
?> 
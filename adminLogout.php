<?php
session_start();

// Destroy all session variables
session_unset();

// Destroy the session itself
session_destroy();

// Clear the remember me cookie
setcookie('admin_remember', '', time() - 3600, '/');

// Redirect to the login page
header('Location: admin_login.php');
exit;
?>

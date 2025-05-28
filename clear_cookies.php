<?php
// Start the session to be able to destroy it
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear all cookies by setting them to expire in the past
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-3600, '/');
    }
}

// Clear the specific cookie we set in index.html
setcookie('cookies_accepted', '', time()-3600, '/');

echo "All cookies and session data have been cleared!";
echo "<br><br>";
echo "<a href='index.html'>Go back to homepage</a>";
?> 
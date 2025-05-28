<?php
function validateSession() {
    session_start();
    
    // Check if all required session variables are set
    if (!isset($_SESSION['studentID']) || !isset($_SESSION['last_activity']) || !isset($_SESSION['user_agent'])) {
        session_destroy();
        return false;
    }
    
    // Check for session hijacking
    if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        return false;
    }
    
    // Check session timeout (30 minutes)
    $timeout = 1800; // 30 minutes in seconds
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_destroy();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

function initializeSession($studentID, $userData) {
    // Start a new session and regenerate ID to prevent session fixation
    session_start();
    session_regenerate_id(true);
    
    // Store user data in session
    $_SESSION['studentID'] = $studentID;
    $_SESSION['last_activity'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_data'] = $userData;
}

function destroySession() {
    session_start();
    
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
}

function getStudentID() {
    if (isset($_SESSION['studentID'])) {
        return $_SESSION['studentID'];
    }
    return null;
}

function getUserData() {
    if (isset($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }
    return null;
}
?> 
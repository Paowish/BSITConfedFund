<?php
session_start();

// After logout, go back to the main page of the system
$redirect = '../index.php';

// Unset ALL session variables
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session completely
session_destroy();

header('Location: ' . $redirect);
exit;
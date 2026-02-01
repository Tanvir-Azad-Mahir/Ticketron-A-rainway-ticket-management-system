<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the session name before destroying
$session_name = session_name();

// Destroy all session data
session_unset();
session_destroy();

// Clear the session cookie
if (ini_get("session.use_cookies")) {
    $cookie_params = session_get_cookie_params();
    setcookie($session_name, '', time() - 3600, $cookie_params["path"], $cookie_params["domain"], $cookie_params["secure"], $cookie_params["httponly"]);
}

// Also clear any remember me cookies
setcookie('remember_login', '', time() - 3600, '/');
setcookie('remembered_input', '', time() - 3600, '/');

// Force no cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login with success message
header("Location: login.php?logout=success", true, 302);
exit();
?>

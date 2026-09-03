<?php
// 1. Start the session
// This is necessary to access session variables like $_SESSION['client_id']
session_start();

// 2. Unset all of the session variables
$_SESSION = array();

// 3. Destroy the session.
// This deletes the session file on the server.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 4. Redirect the user to the client login page
header("Location: login_client.php");
exit();
?>
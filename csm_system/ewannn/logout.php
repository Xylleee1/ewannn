<?php
session_start();
require_once('includes/db.php');

if (isset($_SESSION['user_id'])) {
    add_log($conn, $_SESSION['user_id'], "Logout", "User logged out.");
}

session_unset();
session_destroy();

header("Location: index.php");
exit();
?>

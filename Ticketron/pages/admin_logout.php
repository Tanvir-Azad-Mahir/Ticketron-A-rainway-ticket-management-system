<?php
session_start();
unset($_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_name'], $_SESSION['admin_email']);
session_regenerate_id(true);
header('Location: admin_login.php');
exit();

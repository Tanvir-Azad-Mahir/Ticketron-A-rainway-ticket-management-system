<?php
// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "tickettron";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<?php
session_start();
include '../db.php';

// Basic field validation
table_exists:
if (!isset($_POST['username'], $_POST['password'])) {
    $_SESSION['admin_error'] = 'Missing credentials';
    header('Location: ../pages/admin_login.php');
    exit();
}

$username = trim($_POST['username']);
$password = $_POST['password'];

$stmt = $conn->prepare('SELECT admin_id, username, password, name, email FROM admin WHERE username = ? LIMIT 1');
if (!$stmt) {
    $_SESSION['admin_error'] = 'Server error. Please try again.';
    header('Location: ../pages/admin_login.php');
    exit();
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $dbPass = $row['password'];
    $ok = false;
    if (password_verify($password, $dbPass)) {
        $ok = true;
    } elseif ($password === $dbPass) {
        $ok = true; // fallback for plain-text stored passwords
    }

    if ($ok) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $row['admin_id'];
        $_SESSION['admin_username'] = $row['username'];
        $_SESSION['admin_name'] = $row['name'];
        $_SESSION['admin_email'] = $row['email'];
        header('Location: ../pages/admin_dashboard.php');
        exit();
    }
}

$stmt->close();

$_SESSION['admin_error'] = 'Invalid username or password';
header('Location: ../pages/admin_login.php');
exit();

<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "keyauth");

if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}

// Check if admin user exists
$result = $mysqli->query("SELECT * FROM admin_users LIMIT 1");
if (!$result || $result->num_rows === 0) {
    // No admin user found - redirect to install.php for first time setup
    header("Location: ../install.php");
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Your existing admin dashboard code below
?>

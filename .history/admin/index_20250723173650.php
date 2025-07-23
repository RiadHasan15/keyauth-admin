<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "keyauth");

if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}

// Check if admin exists
$result = $mysqli->query("SELECT * FROM settings LIMIT 1");
if (!$result || $result->num_rows === 0) {
    // No admin user found - redirect to install.php
    header("Location: ../install.php");
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// ... your existing admin dashboard code below ...
?>

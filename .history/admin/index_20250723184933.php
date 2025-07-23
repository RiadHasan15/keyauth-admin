<?php
session_start();
require_once __DIR__ . '/../db.php';

// Check if admin_users table exists
$tableCheck = $mysqli->query("SHOW TABLES LIKE 'admin_users'");
if ($tableCheck->num_rows === 0) {
    header("Location: ../install.php");
    exit();
}

// Check if any admin exists
$result = $mysqli->query("SELECT COUNT(*) as total FROM admin_users");
$data = $result->fetch_assoc();
if ($data['total'] == 0) {
    header("Location: ../install.php");
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// ... your actual dashboard content here ...
echo "Welcome Admin!";

?>

<?php
require_once __DIR__ . '/config.php';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Check if admin already exists
$result = $mysqli->query("SELECT * FROM admin_users LIMIT 1");
if ($result && $result->num_rows > 0) {
    header("Location: admin/login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);
    $email = trim($_POST['email']);
    $site_url = trim($_POST['site_url']);

    // Create admin_users table if not exists
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL
        )
    ");

    // Create settings table if not exists
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_url VARCHAR(255) NOT NULL
        )
    ");

    // Insert admin user
    $stmt = $mysqli->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $password, $email);
    $stmt->execute();

    // Insert site URL into settings
    $stmt2 = $mysqli->prepare("INSERT INTO settings (site_url) VALUES (?)");
    $stmt2->bind_param("s", $site_url);
    $stmt2->execute();

    // Redirect to login
    header("Location: admin/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>KeyAuth Installation</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 40px; }
        form { background: white; padding: 20px; border-radius: 10px; width: 400px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px;
        }
        button {
            background: #2e86de; color: white; border: none; padding: 10px 20px;
            border-radius: 5px; cursor: pointer; font-size: 16px;
        }
    </style>
</head>
<body>
    <h2 style="text-align:center;">KeyAuth Panel Installation</h2>
    <form method="POST">
        <label>Admin Username:</label>
        <input type="text" name="username" required>

        <label>Admin Password:</label>
        <input type="password" name="password" required>

        <label>Admin Email:</label>
        <input type="email" name="email" required>

        <label>Site URL:</label>
        <input type="text" name="site_url" value="http://localhost/keyauth_panel" required>

        <button type="submit">Install</button>
    </form>
</body>
</html>

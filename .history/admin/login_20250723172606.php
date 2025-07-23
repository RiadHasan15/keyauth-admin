<?php
session_start();
require_once __DIR__ . '/../db.php';

$error = '';
$installed = false;

// Check if admin table exists and admin is set
$check = $mysqli->query("SHOW TABLES LIKE 'admin_users'");
if ($check->num_rows === 0) {
    // Create admin_users table
    $mysqli->query("
        CREATE TABLE admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL
        )
    ");
} else {
    // Check if admin is already installed
    $result = $mysqli->query("SELECT COUNT(*) as total FROM admin_users");
    $data = $result->fetch_assoc();
    if ($data['total'] > 0) {
        $installed = true;
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$installed) {
        // First-time setup: insert admin
        if ($username && $password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashed);
            $stmt->execute();
            $stmt->close();
            $_SESSION['admin'] = true;
            header("Location: index.php");
            exit;
        } else {
            $error = "Please enter a username and password";
        }
    } else {
        // Normal login
        $stmt = $mysqli->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $admin = $res->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin'] = true;
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid credentials";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $installed ? 'Admin Login' : 'Install Admin' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container mt-5">
    <div class="card p-4 mx-auto" style="max-width:400px;">
        <h3 class="text-center mb-3">
            <?= $installed ? 'Admin Login' : 'Admin Setup (First Time Installation)' ?>
        </h3>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input name="username" class="form-control mb-2" placeholder="Username" required>
            <input name="password" type="password" class="form-control mb-3" placeholder="Password" required>
            <button class="btn btn-primary w-100"><?= $installed ? 'Login' : 'Install Admin' ?></button>
        </form>
    </div>
</div>
</body>
</html>

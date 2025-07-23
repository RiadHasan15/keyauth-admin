<?php
require_once __DIR__ . '/db.php';

$error = '';
$installed = false;

// Check if already installed
$tableCheck = $mysqli->query("SHOW TABLES LIKE 'admin_users'");
if ($tableCheck->num_rows > 0) {
    $result = $mysqli->query("SELECT COUNT(*) as total FROM admin_users");
    $data = $result->fetch_assoc();
    if ($data['total'] > 0) {
        // Already installed
        header("Location: admin/login.php");
        exit();
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $site_url = trim($_POST['site_url']);

    if ($username && $password && $email && $site_url) {
        // Create admin_users
        $mysqli->query("
            CREATE TABLE IF NOT EXISTS admin_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL
            )
        ");

        // Create settings
        $mysqli->query("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_url VARCHAR(255) NOT NULL,
                admin_email VARCHAR(255) NOT NULL
            )
        ");

        // Save admin
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed, $email);
        $stmt->execute();
        $stmt->close();

        // Save settings
        $stmt = $mysqli->prepare("INSERT INTO settings (site_url, admin_email) VALUES (?, ?)");
        $stmt->bind_param("ss", $site_url, $email);
        $stmt->execute();
        $stmt->close();

        header("Location: admin/login.php");
        exit;
    } else {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Install KeyAuth Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container mt-5">
    <div class="card p-4 mx-auto" style="max-width:500px;">
        <h3 class="text-center mb-3">KeyAuth Panel Installation</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input name="site_url" class="form-control mb-2" placeholder="Site URL (e.g. http://localhost/keyauth_panel)" required>
            <input name="email" type="email" class="form-control mb-2" placeholder="Admin Email" required>
            <input name="username" class="form-control mb-2" placeholder="Admin Username" required>
            <input name="password" type="password" class="form-control mb-3" placeholder="Admin Password" required>
            <button class="btn btn-success w-100">Install</button>
        </form>
    </div>
</div>
</body>
</html>

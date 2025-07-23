<?php
$installed_file = __DIR__ . '/.installed';

if (file_exists($installed_file)) {
    die("Script is already installed. Delete .installed file to reinstall.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_password = trim($_POST['admin_password'] ?? '');

    if (!$admin_username || !$admin_password) {
        $error = "Username and password are required!";
    } else {
        require_once __DIR__ . '/db.php';

        // Create admin_users table if not exists
        $create = "CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $mysqli->query($create);

        // Check if admin already exists
        $stmt = $mysqli->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $admin_username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Admin user already exists.";
        } else {
            $hashed = password_hash($admin_password, PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $admin_username, $hashed);
            $stmt->execute();

            file_put_contents($installed_file, 'installed');
            header("Location: admin_login.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Install Admin Panel</title>
</head>
<body>
    <h2>Install Admin Panel</h2>
    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="post">
        <label>Admin Username:</label><br>
        <input type="text" name="admin_username" required><br><br>
        <label>Admin Password:</label><br>
        <input type="password" name="admin_password" required><br><br>
        <button type="submit">Install</button>
    </form>
</body>
</html>

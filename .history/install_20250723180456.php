<?php
session_start();

$configPath = __DIR__ . '/config.php';

if (file_exists($configPath)) {
    header("Location: admin/login.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = trim($_POST['db_pass'] ?? '');
    $db_name = trim($_POST['db_name'] ?? '');
    $site_url = trim($_POST['site_url'] ?? '');

    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = trim($_POST['admin_pass'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');

    if (!$db_host || !$db_user || !$db_name || !$site_url || !$admin_user || !$admin_pass || !$admin_email) {
        $error = "All fields are required.";
    } else {
        $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($mysqli->connect_error) {
            $error = "Database connection failed: " . $mysqli->connect_error;
        } else {
            // Create admin_users table if not exists
            $mysqli->query("
                CREATE TABLE IF NOT EXISTS admin_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL
                )
            ");

            // Insert admin account
            $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $admin_user, $hashed, $admin_email);
            $stmt->execute();
            $stmt->close();

            // Write config.php
            $config = "<?php\n";
            $config .= "\$db_host = '" . addslashes($db_host) . "';\n";
            $config .= "\$db_user = '" . addslashes($db_user) . "';\n";
            $config .= "\$db_pass = '" . addslashes($db_pass) . "';\n";
            $config .= "\$db_name = '" . addslashes($db_name) . "';\n";
            $config .= "\$site_url = '" . addslashes($site_url) . "';\n";

            file_put_contents($configPath, $config);

            $_SESSION['admin'] = true;
            header("Location: admin/index.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>KeyAuth Panel - First Time Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container mt-5">
    <div class="card p-4 mx-auto" style="max-width: 500px;">
        <h3 class="text-center mb-3">First-Time Installation</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <h5>Database Info</h5>
            <input name="db_host" class="form-control mb-2" placeholder="DB Host" value="localhost" required>
            <input name="db_user" class="form-control mb-2" placeholder="DB User" value="root" required>
            <input name="db_pass" type="password" class="form-control mb-2" placeholder="DB Password">
            <input name="db_name" class="form-control mb-3" placeholder="DB Name" value="keyauth" required>

            <h5>Site Info</h5>
            <input name="site_url" class="form-control mb-3" placeholder="Site URL (e.g., http://localhost/keyauth_panel)" value="http://localhost/keyauth_panel" required>

            <h5>Admin Account</h5>
            <input name="admin_user" class="form-control mb-2" placeholder="Admin Username" required>
            <input name="admin_pass" type="password" class="form-control mb-2" placeholder="Admin Password" required>
            <input name="admin_email" type="email" class="form-control mb-3" placeholder="Admin Email" required>

            <button class="btn btn-primary w-100">Install Now</button>
        </form>
    </div>
</div>
</body>
</html>

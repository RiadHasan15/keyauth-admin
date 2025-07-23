<?php
session_start();

$error = '';

// Check if admin already installed — prevent re-install
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        $error = "Cannot connect to DB with existing config: " . $mysqli->connect_error;
    } else {
        $result = $mysqli->query("SELECT COUNT(*) as total FROM admin_users");
        $data = $result ? $result->fetch_assoc() : null;
        if ($data && $data['total'] > 0) {
            header("Location: admin/login.php");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = $_POST['db_name'] ?? '';
    $admin_user = $_POST['admin_user'] ?? '';
    $admin_pass = $_POST['admin_pass'] ?? '';

    if (!$db_host || !$db_user || !$db_name || !$admin_user || !$admin_pass) {
        $error = 'Please fill in all required fields.';
    } else {
        // Try DB connection
        $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);

        if ($mysqli->connect_error) {
            $error = 'Database connection failed: ' . $mysqli->connect_error;
        } else {
            // Create tables
            $queries = [
                "CREATE TABLE IF NOT EXISTS admin_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL
                )",
                "CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    app_id INT NOT NULL,
                    hwid VARCHAR(255),
                    banned BOOLEAN DEFAULT 0,
                    hwid_banned BOOLEAN DEFAULT 0,
                    expires_at DATETIME DEFAULT NULL
                )",
                "CREATE TABLE IF NOT EXISTS apps (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    api_token VARCHAR(255) NOT NULL UNIQUE
                )",
                // Add more tables as needed here
            ];

            foreach ($queries as $q) {
                if (!$mysqli->query($q)) {
                    $error = 'Failed to create tables: ' . $mysqli->error;
                    break;
                }
            }

            if (!$error) {
                // Insert admin user
                $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $admin_user, $hashed);
                $stmt->execute();
                $stmt->close();

                // Save DB config
                $config = "<?php\n";
                $config .= "\$db_host = '$db_host';\n";
                $config .= "\$db_user = '$db_user';\n";
                $config .= "\$db_pass = '$db_pass';\n";
                $config .= "\$db_name = '$db_name';\n";
                file_put_contents(__DIR__ . '/config.php', $config);

                $_SESSION['admin'] = true;
                header('Location: admin/index.php');
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Install - Setup KeyAuth Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light text-dark">
<div class="container mt-5" style="max-width: 500px;">
    <h3 class="mb-4">First Time Setup</h3>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label>Database Host</label>
            <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
        </div>
        <div class="mb-3">
            <label>Database Username</label>
            <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label>Database Password</label>
            <input type="password" name="db_pass" class="form-control" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label>Database Name</label>
            <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
        </div>
        <hr>
        <div class="mb-3">
            <label>Admin Username</label>
            <input type="text" name="admin_user" class="form-control" value="<?= htmlspecialchars($_POST['admin_user'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label>Admin Password</label>
            <input type="password" name="admin_pass" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Install</button>
    </form>
</div>
</body>
</html>

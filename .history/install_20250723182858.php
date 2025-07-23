<?php
$error = '';
$step = 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get DB values
    $db_host = trim($_POST['db_host']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $db_name = trim($_POST['db_name']);

    // Get Site & Admin info
    $site_url = trim($_POST['site_url']);
    $admin_email = trim($_POST['email']);
    $admin_username = trim($_POST['username']);
    $admin_password = trim($_POST['password']);

    if ($db_host && $db_user && $db_name && $site_url && $admin_email && $admin_username && $admin_password) {
        // Try connecting to DB
        $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

        if ($mysqli->connect_error) {
            $error = "Database connection failed: " . $mysqli->connect_error;
        } else {
            // Create admin_users table
            // Force fresh admin_users table for first-time setup
$mysqli->query("DROP TABLE IF EXISTS admin_users");
$mysqli->query("
    CREATE TABLE admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL
    )
");


            // Create settings table
            $mysqli->query("
                CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    site_url VARCHAR(255) NOT NULL,
                    admin_email VARCHAR(255) NOT NULL
                )
            ");

            // Insert admin account
            $hashed = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $admin_username, $hashed, $admin_email);
            $stmt->execute();
            $stmt->close();

            // Insert settings
            $stmt = $mysqli->prepare("INSERT INTO settings (site_url, admin_email) VALUES (?, ?)");
            $stmt->bind_param("ss", $site_url, $admin_email);
            $stmt->execute();
            $stmt->close();

            // Generate config.php
            $configContent = "<?php\n"
                . "\$db_host = '$db_host';\n"
                . "\$db_user = '$db_user';\n"
                . "\$db_pass = '$db_pass';\n"
                . "\$db_name = '$db_name';\n"
                . "\$site_url = '$site_url';\n";

            file_put_contents(__DIR__ . '/config.php', $configContent);

            // Done - go to login
            header("Location: admin/login.php");
            exit();
        }
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
    <div class="card p-4 mx-auto bg-light text-dark" style="max-width:600px;">
        <h3 class="text-center mb-3">KeyAuth Panel Installation</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <h5>Database Details</h5>
            <input name="db_host" class="form-control mb-2" placeholder="Database Host (e.g. localhost)" required>
            <input name="db_user" class="form-control mb-2" placeholder="Database Username" required>
            <input name="db_pass" class="form-control mb-2" placeholder="Database Password">
            <input name="db_name" class="form-control mb-3" placeholder="Database Name" required>

            <h5>Site & Admin Info</h5>
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

<?php
session_start();
require_once __DIR__ . '/../db.php';
$error = '';

// Check if admin table exists
$check = $mysqli->query("SHOW TABLES LIKE 'admin_users'");
if ($check->num_rows === 0) {
    // No admin_users table exists - redirect to install
    header("Location: ../install.php");
    exit;
}

// Check if any admin users exist
$result = $mysqli->query("SELECT COUNT(*) as total FROM admin_users");
$data = $result->fetch_assoc();
if ($data['total'] == 0) {
    // No admin users exist - redirect to install
    header("Location: ../install.php");
    exit;
}

// If we reach here, admin exists and this is a normal login page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username && $password) {
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
    } else {
        $error = "Please enter username and password";
    }
}
header("Location: /keyauth_panel/admin/login.php");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container mt-5">
    <div class="card p-4 mx-auto" style="max-width:400px;">
        <h3 class="text-center mb-3">Admin Login</h3>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input name="username" class="form-control mb-2" placeholder="Username" required>
            <input name="password" type="password" class="form-control mb-3" placeholder="Password" required>
            <button class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</div>
</body>
</html>
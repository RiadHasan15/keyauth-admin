<?php
session_start();
require_once __DIR__ . '/../db.php';

// Debug information
echo "<h3>Debug Info:</h3>";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Document Root: " . __DIR__ . "<br>";

$error = '';
$installed = false;

// Check if admin table exists
$check = $mysqli->query("SHOW TABLES LIKE 'admin_users'");
echo "Table exists: " . ($check->num_rows > 0 ? 'YES' : 'NO') . "<br>";

if ($check->num_rows === 0) {
    echo "No admin_users table - should redirect to install<br>";
    echo "Redirecting to: ../install.php<br>";
    // Comment out redirect for debugging
    // header("Location: ../install.php");
    // exit;
} else {
    // Check if admin is already installed
    $result = $mysqli->query("SELECT COUNT(*) as total FROM admin_users");
    $data = $result->fetch_assoc();
    echo "Admin users count: " . $data['total'] . "<br>";
    
    if ($data['total'] === 0) {
        echo "No admin users - should redirect to install<br>";
        echo "Redirecting to: ../install.php<br>";
        // Comment out redirect for debugging
        // header("Location: ../install.php");
        // exit;
    } else {
        $installed = true;
        echo "Admin exists - showing login form<br>";
    }
}

// Handle POST - only for login now since install is handled elsewhere
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username && $password) {
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
    } else {
        $error = "Please enter username and password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container mt-5">
    <div class="card p-4 mx-auto" style="max-width:600px;">
        <h3 class="text-center mb-3">Admin Login - Debug Mode</h3>
        
        <div class="alert alert-info">
            <strong>Manual Links for Testing:</strong><br>
            <a href="../install.php" class="btn btn-sm btn-warning">Go to Install</a>
            <a href="index.php" class="btn btn-sm btn-info">Go to Index</a>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$installed): ?>
            <div class="alert alert-warning">
                <strong>Should redirect to install.php</strong><br>
                <a href="../install.php" class="btn btn-warning">Click here to install</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <input name="username" class="form-control mb-2" placeholder="Username" required>
                <input name="password" type="password" class="form-control mb-3" placeholder="Password" required>
                <button class="btn btn-primary w-100">Login</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
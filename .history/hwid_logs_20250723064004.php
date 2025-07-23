












<?php
require_once 'functions.php';
requireLogin();
require_once 'db.php';

// Fetch HWID logs from database
$result = $conn->query("SELECT hl.*, lk.license_key FROM hwid_logs hl
                        JOIN license_keys lk ON hl.key_id = lk.id
                        ORDER BY hl.created_at DESC");

?>

<!DOCTYPE html>
<html>
<head>
    <title>HWID Usage Logs - KeyAuth Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>HWID Usage Logs</h2>
        <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>License Key</th>
                <th>App</th>
                <th>HWID</th>
                <th>IP Address</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($log = $res->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($log['license_key'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($log['app_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($log['hwid']) ?></td>
                <td><?= htmlspecialchars($log['ip']) ?></td>
                <td><?= htmlspecialchars($log['logged_at']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>

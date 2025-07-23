<?php
require_once 'functions.php';
requireLogin();
require_once 'db.php'; // Your MySQL connection ($mysqli)

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add app
    if (isset($_POST['add_app'])) {
        $name = $mysqli->real_escape_string(trim($_POST['app_name']));
        $api_token = bin2hex(random_bytes(16));
        $stmt = $mysqli->prepare("INSERT INTO apps (name, api_token) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $api_token);
        $stmt->execute();
        logAction("App Added: $name");
    }

    // Delete app
    if (isset($_POST['delete_app'])) {
        $id = intval($_POST['app_id']);
        $stmt = $mysqli->prepare("DELETE FROM apps WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        logAction("App Deleted: $id");
    }

    // Regenerate API token
    if (isset($_POST['regen_token'])) {
        $id = intval($_POST['app_id']);
        $new_token = bin2hex(random_bytes(16));
        $stmt = $mysqli->prepare("UPDATE apps SET api_token = ? WHERE id = ?");
        $stmt->bind_param("si", $new_token, $id);
        $stmt->execute();
        logAction("API Token Regenerated: App ID $id");
    }

    // Add license key
    if (isset($_POST['add_key'])) {
        $key = $mysqli->real_escape_string(trim($_POST['key_value']));
        $app_id = intval($_POST['key_app_id']);
        $expires = !empty($_POST['key_expiry']) ? $_POST['key_expiry'] : null;
        $stmt = $mysqli->prepare("INSERT INTO license_keys (license_key, app_id, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $key, $app_id, $expires);
        $stmt->execute();
        logAction("Key Added: $key");
    }

    // Delete license key
    if (isset($_POST['delete_key'])) {
        $id = intval($_POST['key_id']);
        $stmt = $mysqli->prepare("DELETE FROM license_keys WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        logAction("Key Deleted: $id");
    }

    header("Location: index.php");
    exit();
}

// Fetch apps
$appsRes = $mysqli->query("SELECT * FROM apps ORDER BY id DESC");
$apps = $appsRes->fetch_all(MYSQLI_ASSOC);

// Fetch keys with app name join
$keysRes = $mysqli->query("
    SELECT license_keys.*, apps.name as app_name
    FROM license_keys
    LEFT JOIN apps ON license_keys.app_id = apps.id
    ORDER BY license_keys.id DESC
");
$keys = $keysRes->fetch_all(MYSQLI_ASSOC);

// Load logs (JSON file, same as before)
$logs = loadJson(__DIR__ . '/data/logs.json');

?>

<!DOCTYPE html>
<html>
<head>
    <title>KeyAuth Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-actions form {
            display: inline-block;
            margin: 0 2px;
        }
        code {
            user-select: all;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>KeyAuth Admin Panel</h2>
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

    <!-- Add App -->
    <div class="card p-3 mb-4">
        <h5>Add New App</h5>
        <form method="POST" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="app_name" class="form-control" placeholder="App Name" required>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" name="add_app">Add App</button>
            </div>
        </form>
    </div>

    <!-- Apps Table -->
    <div class="card p-3 mb-4">
        <h5>Apps</h5>
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>API Token</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($apps as $app): ?>
                <tr>
                    <td><?= htmlspecialchars($app['name']) ?></td>
                    <td><code><?= htmlspecialchars($app['api_token']) ?></code></td>
                    <td class="table-actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                            <button class="btn btn-sm btn-secondary" name="regen_token" title="Regenerate API Token">Regenerate Token</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete app?');">
                            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                            <button class="btn btn-sm btn-danger" name="delete_app">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add License Key -->
    <div class="card p-3 mb-4">
        <h5>Add New License Key</h5>
        <form method="POST" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="key_value" class="form-control" placeholder="License Key" required>
            </div>
            <div class="col-md-4">
                <select name="key_app_id" class="form-select" required>
                    <option value="">Select App</option>
                    <?php foreach ($apps as $app): ?>
                        <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" name="key_expiry" class="form-control" placeholder="Expires At (optional)">
            </div>
            <div class="col-auto">
                <button class="btn btn-success" name="add_key">Add Key</button>
            </div>
        </form>
    </div>

    <!-- License Keys Table -->
    <div class="card p-3 mb-4">
        <h5>License Keys</h5>
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>App</th>
                    <th>Expires At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($keys as $key): ?>
                <tr>
                    <td><?= htmlspecialchars($key['license_key']) ?></td>
                    <td><?= htmlspecialchars($key['app_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($key['expires_at'] ?? 'Never') ?></td>
                    <td class="table-actions">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete key?');">
                            <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                            <button class="btn btn-sm btn-danger" name="delete_key">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Logs Table -->
    <div class="card p-3 mb-4">
        <h5>Activity Logs</h5>
        <table class="table table-bordered table-hover">
            <thead><tr><th>Event</th><th>IP</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach (array_reverse($logs) as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['event']) ?></td>
                        <td><?= htmlspecialchars($log['ip']) ?></td>
                        <td><?= htmlspecialchars($log['time']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

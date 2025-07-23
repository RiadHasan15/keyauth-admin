<?php
require_once 'functions.php';
requireLogin();
require_once 'db.php'; // mysqli connection $mysqli

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_app'])) {
        $name = $mysqli->real_escape_string(trim($_POST['app_name']));
        $api_token = bin2hex(random_bytes(16));
        $stmt = $mysqli->prepare("INSERT INTO apps (name, api_token) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $api_token);
        $stmt->execute();
        logAction("App Added: $name");
    }

    if (isset($_POST['delete_app'])) {
        $id = intval($_POST['app_id']);
        $stmt = $mysqli->prepare("DELETE FROM apps WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        logAction("App Deleted: $id");
    }

    if (isset($_POST['regen_token'])) {
        $id = intval($_POST['app_id']);
        $new_token = bin2hex(random_bytes(16));
        $stmt = $mysqli->prepare("UPDATE apps SET api_token = ? WHERE id = ?");
        $stmt->bind_param("si", $new_token, $id);
        $stmt->execute();
        logAction("API Token Regenerated: App ID $id");
    }

    if (isset($_POST['add_key'])) {
        $key = $mysqli->real_escape_string(trim($_POST['key_value']));
        $app_id = intval($_POST['key_app_id']);
        $stmt = $mysqli->prepare("INSERT INTO license_keys (license_key, app_id, status) VALUES (?, ?, 'Active')");
        $stmt->bind_param("si", $key, $app_id);
        $stmt->execute();
        logAction("Key Added: $key");
    }

    if (isset($_POST['delete_key'])) {
        $id = intval($_POST['key_id']);
        $stmt = $mysqli->prepare("DELETE FROM license_keys WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        logAction("Key Deleted: $id");
    }

    if (isset($_POST['reset_hwid'])) {
        $id = intval($_POST['key_id']);
        $stmt = $mysqli->prepare("UPDATE license_keys SET hwid = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        logAction("HWID Reset: Key ID $id");
    }

    if (isset($_POST['toggle_ban'])) {
        $id = intval($_POST['key_id']);
        $res = $mysqli->query("SELECT banned FROM license_keys WHERE id = $id");
        $row = $res->fetch_assoc();
        $new_status = !$row['banned'];
        $stmt = $mysqli->prepare("UPDATE license_keys SET banned = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        $stmt->execute();
        logAction(($new_status ? 'Banned' : 'Unbanned') . " Key ID $id");
    }

    if (isset($_POST['toggle_hwid_ban'])) {
        $id = intval($_POST['key_id']);
        $res = $mysqli->query("SELECT hwid_banned FROM license_keys WHERE id = $id");
        $row = $res->fetch_assoc();
        $new_status = !$row['hwid_banned'];
        $stmt = $mysqli->prepare("UPDATE license_keys SET hwid_banned = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        $stmt->execute();
        logAction(($new_status ? 'HWID Banned' : 'HWID Unbanned') . " Key ID $id");
    }

    header("Location: index.php");
    exit();
}

// Fetch apps
$appResult = $mysqli->query("SELECT * FROM apps ORDER BY id DESC");
$apps = $appResult->fetch_all(MYSQLI_ASSOC);

// Fetch license keys with app names
$keyResult = $mysqli->query("
    SELECT lk.*, a.name AS app_name 
    FROM license_keys lk 
    LEFT JOIN apps a ON lk.app_id = a.id
    ORDER BY lk.id DESC
");
$keys = $keyResult->fetch_all(MYSQLI_ASSOC);

// Load logs (assumed JSON file for activity logs)
$logs = loadJson(__DIR__ . '/data/logs.json');

function paginateArray($array, $perPage = 10) {
    $total = count($array);
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $start = ($page - 1) * $perPage;
    return [
        'items' => array_slice($array, $start, $perPage),
        'total_pages' => ceil($total / $perPage),
        'current_page' => $page
    ];
}

$paginatedApps = paginateArray($apps);
$paginatedKeys = paginateArray($keys);
?>

<!DOCTYPE html>
<html>
<head>
    <title>KeyAuth Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
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
                <input type="text" name="app_name" class="form-control" placeholder="App Name" required />
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
            <?php foreach ($paginatedApps['items'] as $app): ?>
                <tr>
                    <td><?= htmlspecialchars($app['name']) ?></td>
                    <td><code><?= htmlspecialchars($app['api_token'] ?? 'No token') ?></code></td>
                    <td class="table-actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="app_id" value="<?= $app['id'] ?>" />
                            <button class="btn btn-sm btn-secondary" name="regen_token" title="Regenerate API Token">Regenerate Token</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete app?');">
                            <input type="hidden" name="app_id" value="<?= $app['id'] ?>" />
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
                <input type="text" name="key_value" class="form-control" placeholder="License Key" required />
            </div>
            <div class="col-md-4">
                <select name="key_app_id" class="form-select" required>
                    <option value="">Select App</option>
                    <?php foreach ($apps as $app): ?>
                        <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-success" name="add_key">Add Key</button>
            </div>
        </form>
    </div>

    <!-- License Keys Table -->
    <div class="card p-3 mb-4">
        <h5>License Keys</h5>
        <input type="text" id="keySearch" class="form-control mb-3" placeholder="Search Keys..." />
        <table class="table table-bordered table-hover" id="keysTable">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>App</th>
                    <th>HWID</th>
                    <th>Status</th>
                    <th>HWID Banned</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paginatedKeys['items'] as $key): ?>
                <tr>
                    <td><?= htmlspecialchars($key['license_key']) ?></td>
                    <td><?= htmlspecialchars($key['app_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($key['hwid'] ?? 'N/A') ?></td>
                    <td><?= $key['banned'] ? '<span class="text-danger">Banned</span>' : '<span class="text-success">Active</span>' ?></td>
                    <td><?= $key['hwid_banned'] ? '<span class="

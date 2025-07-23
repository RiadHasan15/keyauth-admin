<?php
// Data files
$appsFile = __DIR__ . '/data/apps.json';
$keysFile = __DIR__ . '/data/keys.json';
$logsFile = __DIR__ . '/data/logs.json';

// Load JSON
$apps = file_exists($appsFile) ? json_decode(file_get_contents($appsFile), true) : [];
$keys = file_exists($keysFile) ? json_decode(file_get_contents($keysFile), true) : [];
$logs = file_exists($logsFile) ? json_decode(file_get_contents($logsFile), true) : [];

// Handle Actions
function save($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function logAction($event) {
    global $logs, $logsFile;
    $logs[] = [
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'time' => date('Y-m-d H:i:s'),
    ];
    save($logsFile, $logs);
}

// Add/Edit/Delete Apps
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_app'])) {
        $apps[] = ['id' => uniqid(), 'name' => $_POST['app_name']];
        save($appsFile, $apps);
        logAction("App Added: {$_POST['app_name']}");
    }
    if (isset($_POST['edit_app'])) {
        foreach ($apps as &$app) {
            if ($app['id'] === $_POST['app_id']) {
                $app['name'] = $_POST['app_name'];
                logAction("App Edited: {$_POST['app_name']}");
            }
        }
        save($appsFile, $apps);
    }
    if (isset($_POST['delete_app'])) {
        $apps = array_filter($apps, fn($app) => $app['id'] !== $_POST['app_id']);
        save($appsFile, $apps);
        logAction("App Deleted: {$_POST['app_id']}");
    }

    // Add/Edit/Delete Keys
    if (isset($_POST['add_key'])) {
        $keys[] = [
            'id' => uniqid(),
            'app' => $_POST['key_app'],
            'key' => $_POST['key_value'],
            'hwid' => null,
            'banned' => false,
        ];
        save($keysFile, $keys);
        logAction("Key Added: {$_POST['key_value']}");
    }
    if (isset($_POST['edit_key'])) {
        foreach ($keys as &$key) {
            if ($key['id'] === $_POST['key_id']) {
                $key['key'] = $_POST['key_value'];
                $key['app'] = $_POST['key_app'];
                logAction("Key Edited: {$_POST['key_value']}");
            }
        }
        save($keysFile, $keys);
    }
    if (isset($_POST['delete_key'])) {
        $keys = array_filter($keys, fn($key) => $key['id'] !== $_POST['key_id']);
        save($keysFile, $keys);
        logAction("Key Deleted: {$_POST['key_id']}");
    }

    // HWID Reset
    if (isset($_POST['reset_hwid'])) {
        foreach ($keys as &$key) {
            if ($key['id'] === $_POST['key_id']) {
                $key['hwid'] = null;
                logAction("HWID Reset for: {$key['key']}");
            }
        }
        save($keysFile, $keys);
    }

    // Ban/Unban Key
    if (isset($_POST['toggle_ban'])) {
        foreach ($keys as &$key) {
            if ($key['id'] === $_POST['key_id']) {
                $key['banned'] = !$key['banned'];
                logAction(($key['banned'] ? "Banned" : "Unbanned") . " Key: {$key['key']}");
            }
        }
        save($keysFile, $keys);
    }

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KeyAuth Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">🔐 KeyAuth Admin Panel</h2>

    <!-- Add App -->
    <form method="POST" class="mb-3 p-3 border rounded bg-white">
        <h4>Add New App</h4>
        <div class="input-group">
            <input name="app_name" class="form-control" placeholder="App Name" required>
            <button name="add_app" class="btn btn-primary">Add App</button>
        </div>
    </form>

    <!-- Add Key -->
    <form method="POST" class="mb-3 p-3 border rounded bg-white">
        <h4>Add License Key</h4>
        <div class="row">
            <div class="col-md-5 mb-2">
                <input name="key_value" class="form-control" placeholder="Key" required>
            </div>
            <div class="col-md-5 mb-2">
                <select name="key_app" class="form-select">
                    <?php foreach ($apps as $app): ?>
                        <option value="<?= $app['name'] ?>"><?= $app['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button name="add_key" class="btn btn-success w-100">Add Key</button>
            </div>
        </div>
    </form>

    <!-- Apps Table -->
    <div class="mb-5">
        <h4>📱 Apps</h4>
        <table class="table table-bordered bg-white">
            <thead><tr><th>Name</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($apps as $app): ?>
                    <tr>
                        <td><?= htmlspecialchars($app['name']) ?></td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                <input type="text" name="app_name" value="<?= $app['name'] ?>" required>
                                <button name="edit_app" class="btn btn-sm btn-warning">Edit</button>
                                <button name="delete_app" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- Keys Table -->
    <div class="mb-5">
        <h4>🔑 License Keys</h4>
        <input class="form-control mb-2" id="searchKeys" placeholder="Search keys...">
        <table class="table table-bordered bg-white" id="keysTable">
            <thead><tr><th>Key</th><th>App</th><th>HWID</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($keys as $key): ?>
                    <tr>
                        <td><?= htmlspecialchars($key['key']) ?></td>
                        <td><?= htmlspecialchars($key['app']) ?></td>
                        <td><?= $key['hwid'] ?: '<i>None</i>' ?></td>
                        <td><?= $key['banned'] ? '❌ Banned' : '✅ Active' ?></td>
                        <td>
                            <form method="POST" class="d-flex gap-1 flex-wrap">
                                <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                <input type="text" name="key_value" value="<?= $key['key'] ?>" required>
                                <select name="key_app" class="form-select">
                                    <?php foreach ($apps as $app): ?>
                                        <option value="<?= $app['name'] ?>" <?= $key['app'] == $app['name'] ? 'selected' : '' ?>>
                                            <?= $app['name'] ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                                <button name="edit_key" class="btn btn-sm btn-warning">Edit</button>
                                <button name="delete_key" class="btn btn-sm btn-danger">Delete</button>
                                <button name="reset_hwid" class="btn btn-sm btn-secondary">Reset HWID</button>
                                <button name="toggle_ban" class="btn btn-sm <?= $key['banned'] ? 'btn-success' : 'btn-dark' ?>">
                                    <?= $key['banned'] ? 'Unban' : 'Ban' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- Logs -->
    <div class="bg-white border p-3 rounded">
        <h4>🧾 Logs</h4>
        <ul class="list-group">
            <?php foreach (array_reverse($logs) as $log): ?>
                <li class="list-group-item">
                    <b>[<?= $log['time'] ?>]</b> <?= htmlspecialchars($log['event']) ?> (IP: <?= $log['ip'] ?>)
                </li>
            <?php endforeach ?>
        </ul>
    </div>
</div>

<script>
    // Search filtering
    document.getElementById("searchKeys").addEventListener("keyup", function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll("#keysTable tbody tr");
        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
        });
    });
</script>
</body>
</html>

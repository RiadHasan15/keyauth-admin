<?php
require_once 'functions.php';
requireLogin();

$dataDir = __DIR__ . '/data';
$appsFile = $dataDir . '/apps.json';
$keysFile = $dataDir . '/keys.json';

$apps = loadJson($appsFile);
$keys = loadJson($keysFile);

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add app
    if (isset($_POST['add_app'])) {
        $apps[] = ['id' => uniqid(), 'name' => $_POST['app_name']];
        saveJson($appsFile, $apps);
        logAction("App Added: " . $_POST['app_name']);
    }

    // Delete app
    if (isset($_POST['delete_app'])) {
        $apps = array_filter($apps, fn($a) => $a['id'] !== $_POST['app_id']);
        saveJson($appsFile, $apps);
        logAction("App Deleted: " . $_POST['app_id']);
    }

    // Add key
    if (isset($_POST['add_key'])) {
        $keys[] = [
            'id' => uniqid(),
            'key' => $_POST['key_value'],
            'app_id' => $_POST['key_app_id'],
            'hwid' => null,
            'banned' => false,
        ];
        saveJson($keysFile, $keys);
        logAction("Key Added: " . $_POST['key_value']);
    }

    // Delete key
    if (isset($_POST['delete_key'])) {
        $keys = array_filter($keys, fn($k) => $k['id'] !== $_POST['key_id']);
        saveJson($keysFile, $keys);
        logAction("Key Deleted: " . $_POST['key_id']);
    }

    // Reset HWID
    if (isset($_POST['reset_hwid'])) {
        foreach ($keys as &$key) {
            if ($key['id'] === $_POST['key_id']) {
                $key['hwid'] = null;
                logAction("HWID Reset for key: " . $key['key']);
            }
        }
        saveJson($keysFile, $keys);
    }

    // Toggle ban/unban
    if (isset($_POST['toggle_ban'])) {
        foreach ($keys as &$key) {
            if ($key['id'] === $_POST['key_id']) {
                $key['banned'] = !$key['banned'];
                logAction(($key['banned'] ? 'Banned' : 'Unbanned') . " key: " . $key['key']);
            }
        }
        saveJson($keysFile, $keys);
    }

    header("Location: index.php");
    exit();
}

$paginatedApps = paginate($apps, 10);
$paginatedKeys = paginate($keys, 10);
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
            <thead><tr><th>Name</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($paginatedApps['items'] as $app): ?>
                <tr>
                    <td><?= htmlspecialchars($app['name']) ?></td>
                    <td class="table-actions">
                        <form method="POST" onsubmit="return confirm('Delete app?');" style="display:inline;">
                            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                            <button class="btn btn-sm btn-danger" name="delete_app">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $paginatedApps['total_pages']; $i++): ?>
                    <li class="page-item <?= $i == $paginatedApps['current_page'] ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>

    <!-- Add Key -->
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
            <div class="col-auto">
                <button class="btn btn-success" name="add_key">Add Key</button>
            </div>
        </form>
    </div>

    <!-- Keys Table -->
    <div class="card p-3 mb-4">
        <h5>License Keys</h5>
        <input type="text" id="keySearch" class="form-control mb-3" placeholder="Search Keys..." />
        <table class="table table-bordered table-hover" id="keysTable">
            <thead><tr><th>Key</th><th>App</th><th>HWID</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($paginatedKeys['items'] as $key): ?>
                <?php
                $appName = 'Unknown';
                foreach ($apps as $a) {
                    if ($a['id'] === $key['app_id']) {
                        $appName = $a['name'];
                        break;
                    }
                }
                ?>
                <tr>
                    <td><?= htmlspecialchars($key['key']) ?></td>
                    <td><?= htmlspecialchars($appName) ?></td>
                    <td><?= htmlspecialchars($key['hwid'] ?? 'N/A') ?></td>
                    <td><?= $key['banned'] ? '<span class="text-danger">Banned</span>' : '<span class="text-success">Active</span>' ?></td>
                    <td class="table-actions">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete key?');">
                            <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                            <button class="btn btn-sm btn-danger" name="delete_key">Delete</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                            <button class="btn btn-sm btn-warning" name="reset_hwid">Reset HWID</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                            <button class="btn btn-sm btn-info" name="toggle_ban">
                                <?= $key['banned'] ? 'Unban' : 'Ban' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $paginatedKeys['total_pages']; $i++): ?>
                    <li class="page-item <?= $i == $paginatedKeys['current_page'] ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
</div>

<script>
document.getElementById('keySearch').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#keysTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>

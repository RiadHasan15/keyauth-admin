<?php
require_once 'functions.php';
requireLogin();
require_once 'db.php';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add app
    if (isset($_POST['add_app'])) {
        $name = $mysqli->real_escape_string(trim($_POST['app_name']));
        $api_token = bin2hex(random_bytes(16));
        $stmt = $mysqli->prepare("INSERT INTO apps (name, api_token) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $name, $api_token);
            $stmt->execute();
            $stmt->close();
            logAction("App Added: $name");
        }
    }

    // Delete app (delete related license keys first)
    if (isset($_POST['delete_app'])) {
        $id = intval($_POST['app_id']);

        // Delete license keys related to this app first
        $stmtKeys = $mysqli->prepare("DELETE FROM license_keys WHERE app_id = ?");
        if ($stmtKeys) {
            $stmtKeys->bind_param("i", $id);
            $stmtKeys->execute();
            $stmtKeys->close();
        }

        // Now delete the app
        $stmtApp = $mysqli->prepare("DELETE FROM apps WHERE id = ?");
        if ($stmtApp) {
            $stmtApp->bind_param("i", $id);
            $stmtApp->execute();
            $stmtApp->close();
        }

        logAction("App Deleted: $id");
    }

    // Regenerate API token
    if (isset($_POST['regen_token'])) {
        $id = intval($_POST['app_id']);
        $new_token = bin2hex(random_bytes(16));
        $stmt = $mysqli->prepare("UPDATE apps SET api_token = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_token, $id);
            $stmt->execute();
            $stmt->close();
            logAction("API Token Regenerated: App ID $id");
        }
    }

    // Add key
    if (isset($_POST['add_key'])) {
        $key = $mysqli->real_escape_string(trim($_POST['key_value']));
        $app_id = intval($_POST['key_app_id']);
        // Fix: replaced 'status' with 'banned' (0 = active)
        $stmt = $mysqli->prepare("INSERT INTO license_keys (`license_key`, app_id, banned, activation_limit) VALUES (?, ?, 0, 0)");
        if ($stmt) {
            $stmt->bind_param("si", $key, $app_id);
            $stmt->execute();
            $stmt->close();
            logAction("Key Added: $key");
        }
    }

    // Delete key
    if (isset($_POST['delete_key'])) {
        $id = intval($_POST['key_id']);
        $stmt = $mysqli->prepare("DELETE FROM license_keys WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            logAction("Key Deleted: $id");
        }
    }

    // Reset HWID
    if (isset($_POST['reset_hwid'])) {
        $id = intval($_POST['key_id']);
        $stmt = $mysqli->prepare("UPDATE license_keys SET hwid = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            logAction("HWID Reset: Key ID $id");
        }
    }

    // Toggle ban/unban license key
    if (isset($_POST['toggle_ban'])) {
        $id = intval($_POST['key_id']);
        $stmt = $mysqli->prepare("SELECT banned FROM license_keys WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if ($row) {
                $new_status = $row['banned'] ? 0 : 1;
                $stmtUpdate = $mysqli->prepare("UPDATE license_keys SET banned = ? WHERE id = ?");
                if ($stmtUpdate) {
                    $stmtUpdate->bind_param("ii", $new_status, $id);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                    logAction(($new_status ? 'Banned' : 'Unbanned') . " Key ID $id");
                }
            }
        }
    }

    // Toggle HWID ban/unban license key
    if (isset($_POST['toggle_hwid_ban'])) {
        $id = intval($_POST['key_id']);
        $stmt = $mysqli->prepare("SELECT hwid_banned FROM license_keys WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if ($row) {
                $new_status = $row['hwid_banned'] ? 0 : 1;
                $stmtUpdate = $mysqli->prepare("UPDATE license_keys SET hwid_banned = ? WHERE id = ?");
                if ($stmtUpdate) {
                    $stmtUpdate->bind_param("ii", $new_status, $id);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                    logAction(($new_status ? 'HWID Banned' : 'HWID Unbanned') . " Key ID $id");
                }
            }
        }
    }

    header("Location: index.php");
    exit();
}

// Load apps
$appRes = $mysqli->query("SELECT * FROM apps ORDER BY id DESC");
$apps = $appRes ? $appRes->fetch_all(MYSQLI_ASSOC) : [];

// Load keys with app names
$keyRes = $mysqli->query("SELECT license_keys.*, apps.name AS app_name FROM license_keys LEFT JOIN apps ON license_keys.app_id = apps.id ORDER BY license_keys.id DESC");
$keys = $keyRes ? $keyRes->fetch_all(MYSQLI_ASSOC) : [];

// Load logs
$logs = loadJson(__DIR__ . '/data/logs.json');

// Pagination helper
function paginateArray($array, $perPage = 10, $pageParam = 'page') {
    $total = count($array);
    $page = isset($_GET[$pageParam]) ? max(1, intval($_GET[$pageParam])) : 1;
    $start = ($page - 1) * $perPage;
    return [
        'items' => array_slice($array, $start, $perPage),
        'total_pages' => ceil($total / $perPage),
        'current_page' => $page
    ];
}

$paginatedApps = paginateArray($apps, 10, 'app_page');
$paginatedKeys = paginateArray($keys, 10, 'key_page');

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
        input.activation-limit-input {
            max-width: 80px;
            display: inline-block;
        }
        span.update-status {
            font-weight: 600;
            margin-left: 8px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>KeyAuth Admin Panel</h2>
        <a href="hwid_logs.php" class="btn btn-info">View HWID Logs</a>
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
            <?php foreach ($paginatedApps['items'] as $app): ?>
                <tr>
                    <td><?= htmlspecialchars($app['name']) ?></td>
                    <td><code><?= htmlspecialchars($app['api_token']) ?></code></td>
                    <td class="table-actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                            <button class="btn btn-sm btn-secondary" name="regen_token" title="Regenerate API Token">Regenerate Token</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete app and all related license keys?');">
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
                        <a class="page-link" href="?app_page=<?= $i ?>"><?= $i ?></a>
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
            <thead>
                <tr>
                    <th>Key</th>
                    <th>App</th>
                    <th>HWID</th>
                    <th>Status</th>
                    <th>HWID Status</th>
                    <th>Activation Limit</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paginatedKeys['items'] as $key): ?>
                <tr data-id="<?= $key['id'] ?>">
                    <td><?= htmlspecialchars($key['license_key']) ?></td>
                    <td><?= htmlspecialchars($key['app_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($key['hwid'] ?? 'N/A') ?></td>
                    <td><?= $key['banned'] ? '<span class="text-danger">Banned</span>' : '<span class="text-success">Active</span>' ?></td>
                    <td><?= $key['hwid_banned'] ? '<span class="text-danger">HWID Banned</span>' : '<span class="text-success">HWID Allowed</span>' ?></td>
                    <td>
    <input type="number" 
           class="form-control activation-limit-input" 
           value="<?= (int)$key['activation_limit'] ?>" 
           min="0" 
           style="max-width:100px; display:inline-block;" 
    />
    <small class="text-muted">0 = unlimited</small>
    <button class="btn btn-sm btn-primary btn-update-limit" style="margin-left:6px;">Update</button>
    <span class="update-status"></span>
</td>

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
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                            <button class="btn btn-sm btn-danger" name="toggle_hwid_ban">
                                <?= $key['hwid_banned'] ? 'Unban HWID' : 'Ban HWID' ?>
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
                        <a class="page-link" href="?key_page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Simple search filter for keys table
document.getElementById('keySearch').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('#keysTable tbody tr').forEach(row => {
        const keyText = row.querySelector('td').textContent.toLowerCase();
        row.style.display = keyText.includes(filter) ? '' : 'none';
    });
});

// AJAX update for activation limit
$(document).ready(function() {
    $('.btn-update-limit').click(function() {
        const $row = $(this).closest('tr');
        const id = $row.data('id');
        const newLimit = $row.find('input.activation-limit-input').val();
        const $status = $row.find('.update-status');

        if (newLimit === '' || isNaN(newLimit) || newLimit < 0) {
            alert("Please enter a valid non-negative number for activation limit.");
            return;
        }

        $status.text('Saving...').css('color', 'black');

        $.ajax({
            url: 'update_activation_limit.php',
            method: 'POST',
            dataType: 'json',
            data: {
                license_key_id: id,
                activation_limit: newLimit
            },
            success: function(response) {
                if (response.success) {
                    $status.text('Updated').css('color', 'green');
                } else {
                    $status.text('Error: ' + response.message).css('color', 'red');
                }
            },
            error: function() {
                $status.text('Request failed').css('color', 'red');
            }
        });
    });
});
</script>
</body>
</html>

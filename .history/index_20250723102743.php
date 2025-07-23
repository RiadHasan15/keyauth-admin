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
        $expires_at = !empty($_POST['key_expires']) ? $_POST['key_expires'] : null;
        
        $stmt = $mysqli->prepare("INSERT INTO license_keys (`license_key`, app_id, banned, activation_limit, expires_at) VALUES (?, ?, 0, 0, ?)");
        if ($stmt) {
            $stmt->bind_param("sis", $key, $app_id, $expires_at);
            $stmt->execute();
            $stmt->close();
            logAction("Key Added: $key" . ($expires_at ? " (Expires: $expires_at)" : ""));
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

// Helper function to check if key is expired
function isKeyExpired($expires_at) {
    if (!$expires_at) return false;
    return strtotime($expires_at) < time();
}

// Helper function to format expiration date
function formatExpirationDate($expires_at) {
    if (!$expires_at) return 'Never';
    $timestamp = strtotime($expires_at);
    $now = time();
    
    if ($timestamp < $now) {
        return '<span class="text-danger">Expired (' . date('Y-m-d H:i', $timestamp) . ')</span>';
    } else {
        $diff = $timestamp - $now;
        $days = floor($diff / (60 * 60 * 24));
        $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
        
        if ($days > 0) {
            $timeLeft = $days . ' day' . ($days > 1 ? 's' : '');
        } else {
            $timeLeft = $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        
        return '<span class="text-success">' . date('Y-m-d H:i', $timestamp) . '</span><br><small class="text-muted">' . $timeLeft . ' left</small>';
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>KeyAuth Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        input.expires-input {
            max-width: 200px;
            display: inline-block;
        }
        span.update-status {
            font-weight: 600;
            margin-left: 8px;
        }
        .expired-key {
            background-color: #f8d7da !important;
        }
        .expiring-soon {
            background-color: #fff3cd !important;
        }
        /* Hamburger styles */
        .navbar-toggler {
            border: none;
        }
        .navbar-toggler:focus {
            outline: none;
            box-shadow: none;
        }
        @media (max-width: 991.98px) {
            .container {
                padding-left: 8px;
                padding-right: 8px;
            }
            .table-responsive {
                margin-bottom: 1rem;
            }
        }
        @media (max-width: 767.98px) {
            h2 {
                font-size: 1.3rem;
            }
            .card {
                padding: 1rem !important;
            }
            .table th, .table td {
                font-size: 0.95rem;
            }
            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-3">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#">KeyAuth Admin Panel</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="mainNavbar">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle btn btn-secondary text-white mx-1 my-1 my-lg-0 d-flex align-items-center gap-1"
                       href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Users
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-caret-down-fill ms-1" viewBox="0 0 16 16">
                            <path d="M7.247 11.14l-4.796-5.481C1.451 5.223 1.889 4.5 2.646 4.5h10.708c.757 0 1.195.723.195 1.159l-4.796 5.481a1 1 0 0 1-1.506 0z"/>
                        </svg>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="users.php">Manage Users</a></li>
                        <li><a class="dropdown-item" href="hwid_logs.php">View HWID Logs</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-danger text-white mx-1 my-1 my-lg-0" style="color:#fff !important;">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-2 mb-4">
    <!-- Add App -->
    <div class="card p-3 mb-4">
        <h5>Add New App</h5>
        <form method="POST" class="row g-2">
            <div class="col-md-6 col-12">
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
        <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
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
        </div>
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
            <div class="col-md-3 col-12">
                <input type="text" name="key_value" class="form-control" placeholder="License Key" required>
            </div>
            <div class="col-md-3 col-12">
                <select name="key_app_id" class="form-select" required>
                    <option value="">Select App</option>
                    <?php foreach ($apps as $app): ?>
                        <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-12">
                <input type="datetime-local" name="key_expires" class="form-control" title="Leave empty for no expiration">
                <small class="text-muted">Optional expiration date</small>
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
        <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle" id="keysTable">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>App</th>
                    <th>HWID</th>
                    <th>Status</th>
                    <th>HWID Status</th>
                    <th>Activation Limit</th>
                    <th>Expires At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paginatedKeys['items'] as $key): 
                $isExpired = isKeyExpired($key['expires_at']);
                $isExpiringSoon = !$isExpired && $key['expires_at'] && strtotime($key['expires_at']) - time() <= 86400; // 24 hours
                $rowClass = $isExpired ? 'expired-key' : ($isExpiringSoon ? 'expiring-soon' : '');
            ?>
                <tr data-id="<?= $key['id'] ?>" class="<?= $rowClass ?>">
                    <td><?= htmlspecialchars($key['license_key']) ?></td>
                    <td><?= htmlspecialchars($key['app_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($key['hwid'] ?? 'N/A') ?></td>
                    <td>
                        <?php if ($isExpired): ?>
                            <span class="text-danger">Expired</span>
                        <?php else: ?>
                            <?= $key['banned'] ? '<span class="text-danger">Banned</span>' : '<span class="text-success">Active</span>' ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $key['hwid_banned'] ? '<span class="text-danger">HWID Banned</span>' : '<span class="text-success">HWID Allowed</span>' ?></td>
                    <td>
                        <input type="number" class="form-control activation-limit-input" 
                               value="<?php echo isset($key['activation_limit']) ? (int)$key['activation_limit'] : 0; ?>" 
                               min="0" style="max-width:100px; display:inline-block;" />
                        <small class="text-muted">0 = unlimited</small>
                        <button class="btn btn-sm btn-primary btn-update-limit" style="margin-left:6px;">Update</button>
                        <span class="update-status"></span>
                    </td>
                    <td>
                        <input type="datetime-local" class="form-control expires-input" 
                               value="<?php echo $key['expires_at'] ? date('Y-m-d\TH:i', strtotime($key['expires_at'])) : ''; ?>" 
                               style="max-width:200px; display:inline-block;" />
                        <button class="btn btn-sm btn-primary btn-update-expires" style="margin-left:6px;">Update</button>
                        <div style="margin-top:5px;">
                            <?= formatExpirationDate($key['expires_at']) ?>
                        </div>
                        <span class="update-expires-status"></span>
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
        </div>
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
        <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
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
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

    // AJAX update for expiration date
    $('.btn-update-expires').click(function() {
        const $row = $(this).closest('tr');
        const id = $row.data('id');
        const newExpires = $row.find('input.expires-input').val();
        const $status = $row.find('.update-expires-status');

        $status.text('Saving...').css('color', 'black');

        $.ajax({
            url: 'update_expiration.php',
            method: 'POST',
            dataType: 'json',
            data: {
                license_key_id: id,
                expires_at: newExpires || null
            },
            success: function(response) {
                if (response.success) {
                    $status.text('Updated').css('color', 'green');
                    // Refresh the page to update the status colors and display
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
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
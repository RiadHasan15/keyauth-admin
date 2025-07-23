<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "keyauth_panel");

// Load logs from JSON
function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

// Save logs to JSON
function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Pagination helper
function paginateArray($array, $perPage = 5) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $total = count($array);
    $totalPages = ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    return [
        'items' => array_slice($array, $offset, $perPage),
        'totalPages' => $totalPages,
        'currentPage' => $page
    ];
}

// Handle app creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_app'])) {
    $name = $_POST['app_name'];
    $desc = $_POST['description'];
    $mysqli->query("INSERT INTO apps (name, description) VALUES ('$name', '$desc')");
    $_SESSION['success'] = "App created successfully!";
    header("Location: index.php");
    exit;
}

// Handle license key creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_key'])) {
    $key = $_POST['license_key'];
    $appId = $_POST['app_id'];
    $expires = $_POST['expires_at'];
    $mysqli->query("INSERT INTO license_keys (license_key, app_id, expires_at) VALUES ('$key', $appId, '$expires')");
    $_SESSION['success'] = "License key created successfully!";
    header("Location: index.php");
    exit;
}

// Load apps from database
$apps = [];
$result = $mysqli->query("SELECT * FROM apps");
while ($row = $result->fetch_assoc()) {
    $apps[] = $row;
}

// Load keys with app names using JOIN
$keys = [];
$result = $mysqli->query("SELECT license_keys.*, apps.name AS app_name 
                          FROM license_keys 
                          JOIN apps ON license_keys.app_id = apps.id");
while ($row = $result->fetch_assoc()) {
    $keys[] = $row;
}

// Load logs
$logs = loadJson(__DIR__ . '/data/logs.json');

// Paginate
$paginatedApps = paginateArray($apps);
$paginatedKeys = paginateArray($keys);
?>

<!DOCTYPE html>
<html>
<head>
    <title>KeyAuth Dashboard</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f4f4f4; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
        h2 { border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        .success { color: green; }
        .form-group { margin-bottom: 15px; }
        .form-inline input, select { margin-right: 10px; }
        .pagination a { padding: 4px 10px; background: #ddd; margin: 2px; text-decoration: none; border-radius: 4px; }
        .pagination .current { background: #444; color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <h1>KeyAuth Dashboard</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <p class="success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
    <?php endif; ?>

    <h2>Create New App</h2>
    <form method="POST">
        <div class="form-group">
            <input type="text" name="app_name" placeholder="App Name" required>
            <input type="text" name="description" placeholder="Description">
            <button type="submit" name="create_app">Create App</button>
        </div>
    </form>

    <h2>Create License Key</h2>
    <form method="POST">
        <div class="form-inline">
            <input type="text" name="license_key" placeholder="Key" required>
            <select name="app_id" required>
                <option value="">Select App</option>
                <?php foreach ($apps as $app): ?>
                    <option value="<?= $app['id'] ?>"><?= $app['name'] ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="expires_at" required>
            <button type="submit" name="create_key">Create Key</button>
        </div>
    </form>

    <h2>Apps</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Name</th><th>Description</th></tr>
        </thead>
        <tbody>
            <?php foreach ($paginatedApps['items'] as $app): ?>
                <tr>
                    <td><?= $app['id'] ?></td>
                    <td><?= $app['name'] ?></td>
                    <td><?= $app['description'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="pagination">
        <?php for ($i = 1; $i <= $paginatedApps['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= $i == $paginatedApps['currentPage'] ? 'current' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>

    <h2>License Keys</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Key</th><th>App</th><th>Expires At</th></tr>
        </thead>
        <tbody>
            <?php foreach ($paginatedKeys['items'] as $key): ?>
                <tr>
                    <td><?= $key['id'] ?></td>
                    <td><?= $key['license_key'] ?></td>
                    <td><?= $key['app_name'] ?></td>
                    <td><?= $key['expires_at'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="pagination">
        <?php for ($i = 1; $i <= $paginatedKeys['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= $i == $paginatedKeys['currentPage'] ? 'current' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>

    <h2>Logs</h2>
    <pre><?php print_r($logs); ?></pre>
</div>
</body>
</html>

<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "keyauth");

// Generate random API token
function generateApiToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Load logs from JSON
function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
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

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create app
    if (isset($_POST['create_app'])) {
        $name = $mysqli->real_escape_string($_POST['app_name']);
        $desc = $mysqli->real_escape_string($_POST['description']);
        $api_token = generateApiToken(32);
        $stmt = $mysqli->prepare("INSERT INTO apps (name, description, api_token) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $desc, $api_token);
        $stmt->execute();
        $_SESSION['success'] = "App created successfully!";
        header("Location: index.php");
        exit;
    }

    // Regenerate API token
    if (isset($_POST['regen_token'])) {
        $appId = intval($_POST['app_id']);
        $new_token = generateApiToken(32);
        $stmt = $mysqli->prepare("UPDATE apps SET api_token = ? WHERE id = ?");
        $stmt->bind_param("si", $new_token, $appId);
        $stmt->execute();
        $_SESSION['success'] = "API token regenerated!";
        header("Location: index.php");
        exit;
    }

    // Create license key
    if (isset($_POST['create_key'])) {
        $key = $mysqli->real_escape_string($_POST['license_key']);
        $appId = intval($_POST['app_id']);
        $expires = $mysqli->real_escape_string($_POST['expires_at']);
        $stmt = $mysqli->prepare("INSERT INTO license_keys (license_key, app_id, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $key, $appId, $expires);
        $stmt->execute();
        $_SESSION['success'] = "License key created successfully!";
        header("Location: index.php");
        exit;
    }
}

// Load apps from database
$apps = [];
$result = $mysqli->query("SELECT * FROM apps ORDER BY id DESC");
while ($row = $result->fetch_assoc()) {
    $apps[] = $row;
}

// Load keys with app names using JOIN
$keys = [];
$result = $mysqli->query("SELECT license_keys.*, apps.name AS app_name 
                          FROM license_keys 
                          JOIN apps ON license_keys.app_id = apps.id
                          ORDER BY license_keys.id DESC");
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
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>KeyAuth Dashboard</title>
<style>
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #eef2f7;
    margin: 0; padding: 0;
  }
  .container {
    max-width: 1100px;
    margin: 40px auto;
    background: #fff;
    padding: 30px 40px;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgb(0 0 0 / 0.1);
  }
  h1, h2 {
    color: #222;
    margin-bottom: 20px;
  }
  form {
    margin-bottom: 40px;
  }
  input[type=text], input[type=date], select {
    padding: 10px 15px;
    font-size: 16px;
    border-radius: 6px;
    border: 1.5px solid #ccc;
    margin-right: 15px;
    width: 250px;
    transition: border-color 0.3s;
  }
  input[type=text]:focus, input[type=date]:focus, select:focus {
    outline: none;
    border-color: #4a90e2;
  }
  button {
    background: #4a90e2;
    border: none;
    color: white;
    padding: 11px 25px;
    font-size: 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s;
  }
  button:hover {
    background: #357abd;
  }
  .success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 30px;
    border: 1px solid #c3e6cb;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 35px;
  }
  th, td {
    padding: 12px 15px;
    border-bottom: 1px solid #ddd;
    text-align: left;
    vertical-align: middle;
  }
  th {
    background: #4a90e2;
    color: white;
  }
  tr:hover {
    background-color: #f1f7ff;
  }
  code {
    background: #f1f1f1;
    padding: 4px 7px;
    border-radius: 4px;
    font-size: 14px;
    user-select: all;
  }
  .regen-btn {
    background: #28a745;
    padding: 6px 12px;
    font-size: 13px;
    border-radius: 4px;
    border: none;
    color: white;
    cursor: pointer;
    margin-left: 10px;
  }
  .regen-btn:hover {
    background: #1e7e34;
  }
  .pagination {
    text-align: center;
    margin-bottom: 40px;
  }
  .pagination a {
    display: inline-block;
    padding: 8px 14px;
    margin: 0 4px;
    background: #ddd;
    border-radius: 5px;
    text-decoration: none;
    color: #333;
    font-weight: 600;
    transition: background-color 0.3s;
  }
  .pagination a.current, .pagination a:hover {
    background: #4a90e2;
    color: white;
  }
  pre {
    background: #f6f8fa;
    padding: 15px;
    border-radius: 6px;
    overflow-x: auto;
    max-height: 200px;
  }
  @media (max-width: 768px) {
    input[type=text], input[type=date], select {
      width: 100%;
      margin: 8px 0;
    }
    button {
      width: 100%;
      margin-top: 10px;
    }
  }
</style>
</head>
<body>
<div class="container">

    <h1>KeyAuth Dashboard</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <h2>Create New App</h2>
    <form method="POST">
        <input type="text" name="app_name" placeholder="App Name" required />
        <input type="text" name="description" placeholder="Description (optional)" />
        <button type="submit" name="create_app">Create App</button>
    </form>

    <h2>Apps</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>API Token</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paginatedApps['items'] as $app): ?>
                <tr>
                    <td><?= $app['id'] ?></td>
                    <td><?= htmlspecialchars($app['name']) ?></td>
                    <td><?= htmlspecialchars($app['description']) ?></td>
                    <td><code><?= htmlspecialchars($app['api_token'] ?? 'N/A') ?></code></td>
                    <td>
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                        <button type="submit" name="regen_token" class="regen-btn" title="Regenerate API Token">Regenerate Token</button>
                      </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="pagination">
        <?php for ($i = 1; $i <= $paginatedApps['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= $i == $paginatedApps['currentPage'] ? 'current' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>

    <h2>Create License Key</h2>
    <form method="POST">
        <input type="text" name="license_key" placeholder="License Key" required />
        <select name="app_id" required>
            <option value="">Select App</option>
            <?php foreach ($apps as $app): ?>
                <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="expires_at" required />
        <button type="submit" name="create_key">Create Key</button>
    </form>

    <h2>License Keys</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Key</th><th>App</th><th>Expires At</th></tr>
        </thead>
        <tbody>
            <?php foreach ($paginatedKeys['items'] as $key): ?>
                <tr>
                    <td><?= $key['id'] ?></td>
                    <td><?= htmlspecialchars($key['license_key']) ?></td>
                    <td><?= htmlspecialchars($key['app_name']) ?></td>
                    <td><?= htmlspecialchars($key['expires_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="pagination">
        <?php for ($i = 1; $i <= $paginatedKeys['totalPages']; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= $i == $paginatedKeys['currentPage'] ? 'current' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>

    <h2>Activity Logs</h2>
    <pre><?php if($logs) echo htmlspecialchars(print_r($logs, true)); else echo "No logs found."; ?></pre>

</div>
</body>
</html>

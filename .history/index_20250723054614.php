<?php include 'db.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>KeyAuth Panel</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        form { margin-bottom: 30px; }
        input, select { padding: 5px; margin: 5px; }
        table { border-collapse: collapse; margin-top: 20px; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background-color: #eee; }
    </style>
</head>
<body>

<h2>Create New App</h2>
<form method="POST">
    <input type="text" name="app_name" placeholder="App Name" required>
    <input type="text" name="api_token" placeholder="API Token (optional)">
    <button type="submit" name="create_app">Create App</button>
</form>

<h2>Create New License Key</h2>
<form method="POST">
    <input type="text" name="key" placeholder="License Key" required>
    <select name="app_name" required>
        <option value="">Select App</option>
        <?php
        $res = $mysqli->query("SELECT name FROM apps");
        while ($row = $res->fetch_assoc()) {
            echo "<option value='{$row['name']}'>{$row['name']}</option>";
        }
        ?>
    </select>
    <select name="status">
        <option value="Active">Active</option>
        <option value="Expired">Expired</option>
    </select>
    <button type="submit" name="create_license">Create License</button>
</form>

<?php
// Insert App
if (isset($_POST['create_app'])) {
    $name = $mysqli->real_escape_string($_POST['app_name']);
    $token = $_POST['api_token'] ?: bin2hex(random_bytes(16)); // generate if empty
    $stmt = $mysqli->prepare("INSERT INTO apps (name, api_token) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $token);
    if ($stmt->execute()) {
        echo "<p style='color:green'>✅ App created successfully.</p>";
    } else {
        echo "<p style='color:red'>❌ Failed to create app: " . $stmt->error . "</p>";
    }
}

// Insert License
if (isset($_POST['create_license'])) {
    $key = $mysqli->real_escape_string($_POST['key']);
    $app = $mysqli->real_escape_string($_POST['app_name']);
    $status = $_POST['status'];
    $stmt = $mysqli->prepare("INSERT INTO license_keys (`key`, app_name, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $key, $app, $status);
    if ($stmt->execute()) {
        echo "<p style='color:green'>✅ License key added.</p>";
    } else {
        echo "<p style='color:red'>❌ Failed to add license: " . $stmt->error . "</p>";
    }
}
?>

<h2>All Apps</h2>
<table>
    <tr><th>Name</th><th>API Token</th></tr>
    <?php
    $res = $mysqli->query("SELECT * FROM apps");
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['name']}</td><td>{$row['api_token']}</td></tr>";
    }
    ?>
</table>

<h2>All License Keys</h2>
<table>
    <tr><th>Key</th><th>App</th><th>Status</th></tr>
    <?php
    $res = $mysqli->query("SELECT * FROM license_keys");
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['key']}</td><td>{$row['app_name']}</td><td>{$row['status']}</td></tr>";
    }
    ?>
</table>

</body>
</html>

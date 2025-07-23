<?php
require_once 'functions.php';
requireLogin();
require_once 'db.php'; // Use your MySQL DB instead of JSON

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

    // Add key
    if (isset($_POST['add_key'])) {
        $key = $mysqli->real_escape_string(trim($_POST['key_value']));
        $app_id = intval($_POST['key_app_id']);
        $stmt = $mysqli->prepare("INSERT INTO license_keys (`key`, app_id, status) VALUES (?, ?, 'Active')");
        $stmt->bind_param("si", $key, $app_id);
        $stmt->execute();
        logAction("Key Added: $key");
    }

    // Delete key
    if (isset($_POST['delete_key'])) {
        $id = intval($_POST['key_id']);
        $stmt = $mysqli->prepare("DELETE FROM license_keys WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        logAction("Key Deleted: $id");
    }

    // Reset HWID
    if (isset($_POST['reset_hwid'])) {
        $id = intval($_POST['key_id']);
        $stmt = $mysqli->prepare("UPDATE license_keys SET hwid = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        logAction("HWID Reset: Key ID $id");
    }

    // Toggle ban/unban
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

    header("Location: index.php");
    exit();
}

// Load data
$sql = "SELECT license_keys.*, apps.name 
        FROM license_keys 
        JOIN apps ON license_keys.app_name = apps.name";

$logs = loadJson(__DIR__ . '/data/logs.json');

// Helper: Pagination if needed
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

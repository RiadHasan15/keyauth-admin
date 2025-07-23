<?php
// Fixed functions.php
session_start(); // FIXED: Uncommented session_start

function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function logAction($event) {
    $logFile = __DIR__ . '/../data/logs.json'; // FIXED: Changed **DIR** to __DIR__
    $logs = loadJson($logFile);
    $logs[] = [
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'time' => date('Y-m-d H:i:s'),
    ];
    saveJson($logFile, $logs);
}

function paginate($items, $perPage = 10) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $total = count($items);
    $offset = ($page - 1) * $perPage;
    $pagedItems = array_slice($items, $offset, $perPage);
    $totalPages = ceil($total / $perPage);
    return [
        'items' => $pagedItems,
        'total_pages' => $totalPages,
        'current_page' => $page,
    ];
}

function requireLogin() {
    // FIXED: Session already started at top of file
    if (!isset($_SESSION['admin'])) {
        header("Location: login.php");
        exit();
    }
}

// Generate random API token
function generateApiToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Validate API token for given app_id, returns app data or false
function validateApiToken($app_id, $token) {
    $apps = loadJson(__DIR__ . '/../data/apps.json'); // FIXED: Changed **DIR** to __DIR__
    foreach ($apps as $app) {
        if ($app['id'] === $app_id && isset($app['api_token']) && hash_equals($app['api_token'], $token)) {
            return $app;
        }
    }
    return false;
}

// ===================================
// Fixed update_activation_limit.php
// ===================================

<?php
// update_activation_limit.php
header('Content-Type: application/json');
require_once __DIR__ . '/functions.php'; // FIXED: Changed **DIR** to __DIR__
requireLogin();
require_once __DIR__ . '/db.php'; // FIXED: Changed **DIR** to __DIR__

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$licenseKeyId = isset($_POST['license_key_id']) ? intval($_POST['license_key_id']) : 0;
$activationLimit = isset($_POST['activation_limit']) ? intval($_POST['activation_limit']) : -1;

if ($licenseKeyId <= 0 || $activationLimit < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$stmt = $mysqli->prepare("UPDATE license_keys SET activation_limit = ? WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}

$stmt->bind_param("ii", $activationLimit, $licenseKeyId);

if ($stmt->execute()) {
    logAction("Activation limit updated: Key ID $licenseKeyId to $activationLimit");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
}

$stmt->close();
exit;
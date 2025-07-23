<?php
// validate_key.php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
ob_clean();

// Log raw input for debugging
$rawInput = file_get_contents('php://input');
file_put_contents(__DIR__ . '/log_validate.txt', $rawInput);

// Decode input
$data = json_decode($rawInput, true);

// Validate input
if (!isset($data['app_id'], $data['api_token'], $data['license_key'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing parameters'
    ]);
    exit;
}

// Config
$db = new SQLite3(__DIR__ . '/../database.sqlite');

// Get app info
$stmt = $db->prepare("SELECT * FROM apps WHERE name = :name AND api_token = :token");
$stmt->bindValue(':name', $data['app_id']);
$stmt->bindValue(':token', $data['api_token']);
$app = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$app) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid API token or App ID'
    ]);
    exit;
}

// Validate license key
$stmt = $db->prepare("SELECT * FROM licenses WHERE key = :key AND app = :app");
$stmt->bindValue(':key', $data['license_key']);
$stmt->bindValue(':app', $data['app_id']);
$license = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$license) {
    echo json_encode([
        'success' => false,
        'message' => 'License key not found'
    ]);
    exit;
}

if ($license['status'] !== 'Active') {
    echo json_encode([
        'success' => false,
        'message' => 'License key is not active'
    ]);
    exit;
}

// All good
echo json_encode([
    'success' => true,
    'message' => 'License key is valid'
]);

<?php
require_once '../functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Parse JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (
    !$data ||
    !isset($data['app_id'], $data['api_token'], $data['license_key'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Validate API token for app
$app = validateApiToken($data['app_id'], $data['api_token']);
if (!$app) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API token']);
    exit;
}

// Load keys
$keys = loadJson(__DIR__ . '/../data/keys.json');

// Find the key with matching license_key and app_id
$keyData = null;
foreach ($keys as $key) {
    if ($key['key'] === $data['license_key'] && $key['app_id'] === $data['app_id']) {
        $keyData = $key;
        break;
    }
}

if (!$keyData) {
    echo json_encode(['success' => false, 'message' => 'Key not found']);
    exit;
}

if (($keyData['banned'] ?? false) === true) {
    echo json_encode(['success' => false, 'message' => 'Key is banned']);
    exit;
}

echo json_encode([
    'success' => true,
    'key' => $keyData['key'],
    'hwid' => $keyData['hwid'] ?? null,
    'banned' => $keyData['banned'] ?? false,
]);




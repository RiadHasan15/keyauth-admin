<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$api_token = isset($_POST['api_token']) ? trim($_POST['api_token']) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$hwid = isset($_POST['hwid']) ? trim($_POST['hwid']) : '';

if (!$api_token || !$username || !$password || !$hwid) {
    echo json_encode(['success' => false, 'message' => 'Missing API token, username, password, or HWID']);
    exit;
}

// Step 1: Validate the API token and get app id
$stmt = $mysqli->prepare("SELECT id FROM apps WHERE api_token = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $mysqli->error]);
    exit;
}
$stmt->bind_param('s', $api_token);
$stmt->execute();
$res = $stmt->get_result();
$app = $res->fetch_assoc();
$stmt->close();

if (!$app) {
    echo json_encode(['success' => false, 'message' => 'Invalid API token']);
    exit;
}

$app_id = $app['id'];

// Step 2: Lookup user by username and app_id
$stmt = $mysqli->prepare("SELECT id, password_hash, hwid, banned, hwid_banned FROM users WHERE username = ? AND app_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $mysqli->error]);
    exit;
}
$stmt->bind_param('si', $username, $app_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

if ($user['banned']) {
    echo json_encode(['success' => false, 'message' => 'User is banned']);
    exit;
}

if ($user['hwid_banned']) {
    echo json_encode(['success' => false, 'message' => 'User HWID is banned']);
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit;
}

if (empty($user['hwid'])) {
    // Save HWID if not set
    $updateStmt = $mysqli->prepare("UPDATE users SET hwid = ? WHERE id = ?");
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $mysqli->error]);
        exit;
    }
    $updateStmt->bind_param('si', $hwid, $user['id']);
    $updateStmt->execute();
    $updateStmt->close();
} else {
    if ($user['hwid'] !== $hwid) {
        echo json_encode(['success' => false, 'message' => 'HWID mismatch. Access denied.']);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'data' => [
        'user_id' => $user['id'],
        'username' => $username,
        'hwid' => $hwid,
        'app_id' => $app_id
    ]
]);

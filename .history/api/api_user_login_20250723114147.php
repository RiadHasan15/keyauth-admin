<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$app_api_token = $_POST['api_token'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// You said users won't send HWID, so we don't get it from POST

if (!$app_api_token || !$username || !$password) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// 1. Verify app by api_token
$stmt = $mysqli->prepare("SELECT id FROM apps WHERE api_token = ?");
$stmt->bind_param("s", $app_api_token);
$stmt->execute();
$res = $stmt->get_result();
$app = $res->fetch_assoc();
$stmt->close();

if (!$app) {
    echo json_encode(['success' => false, 'message' => 'Invalid API token']);
    exit;
}
$app_id = $app['id'];

// 2. Find user by username and app_id
$stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ? AND app_id = ?");
$stmt->bind_param("si", $username, $app_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

// 3. Check banned or expired
if ($user['banned']) {
    echo json_encode(['success' => false, 'message' => 'User is banned']);
    exit;
}
if ($user['expires_at'] !== null && strtotime($user['expires_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'User subscription expired']);
    exit;
}

// 4. Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

// 5. HWID logic modified: generate or retrieve HWID on server side
// For example, generate HWID based on IP + User-Agent (just an example, not 100% reliable)

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown_agent';
$generated_hwid = hash('sha256', $ip . $user_agent);

// Now check stored HWID
if (empty($user['hwid'])) {
    // First login, store generated HWID
    $stmt = $mysqli->prepare("UPDATE users SET hwid = ? WHERE id = ?");
    $stmt->bind_param("si", $generated_hwid, $user['id']);
    $stmt->execute();
    $stmt->close();
} else {
    // Subsequent login: check if generated HWID matches stored HWID
    if ($user['hwid'] !== $generated_hwid) {
        echo json_encode(['success' => false, 'message' => 'HWID mismatch: login from different device or browser']);
        exit;
    }
}

// Success response
echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'data' => [
        'username' => $user['username'],
        'expires_at' => $user['expires_at'],
        'banned' => (bool)$user['banned'],
        'hwid' => $user['hwid']
    ]
]);
exit;

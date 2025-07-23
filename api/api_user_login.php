<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$headers = getallheaders();
$app_api_token = $headers['X-API-TOKEN'] ?? '';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$hwid = $_POST['hwid'] ?? '';

if (!$app_api_token || !$username || !$password || !$hwid) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// 1. Verify app by API token
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

// 3. Check banned or expired (admins bypass expiry)
if ($user['banned']) {
    echo json_encode(['success' => false, 'message' => 'User is banned']);
    exit;
}

$is_admin = isset($user['role']) && $user['role'] === 'admin';
if (!$is_admin && $user['expires_at'] !== null && strtotime($user['expires_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'User subscription expired']);
    exit;
}

// 4. Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

// 5. HWID logic (admins bypass HWID lock)
if (!$is_admin) {
    if (empty($user['hwid'])) {
        $stmt = $mysqli->prepare("UPDATE users SET hwid = ? WHERE id = ?");
        $stmt->bind_param("si", $hwid, $user['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        if ($user['hwid'] !== $hwid) {
            echo json_encode(['success' => false, 'message' => 'HWID mismatch: login from different device denied']);
            exit;
        }
    }
}

// Success
echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'data' => [
        'username' => $user['username'],
        'expires_at' => $user['expires_at'],
        'banned' => (bool)$user['banned'],
        'hwid' => $user['hwid'],
        'is_admin' => $is_admin
    ]
]);
exit;

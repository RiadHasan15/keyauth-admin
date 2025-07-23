<?php
require_once '../db.php'; // database connection

// Secret admin key (keep this secure!)
$admin_secret = 'YOUR_SECRET_ADMIN_KEY'; // replace with strong key

// Check for required POST fields
if (!isset($_POST['username'], $_POST['app_token'], $_POST['admin_secret'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$username = $_POST['username'];
$app_token = $_POST['app_token'];
$admin_secret_input = $_POST['admin_secret'];

// Check admin secret
if ($admin_secret_input !== $admin_secret) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid admin secret']);
    exit;
}

// Get app ID from token
$app_stmt = $conn->prepare("SELECT id FROM apps WHERE api_token = ?");
$app_stmt->bind_param("s", $app_token);
$app_stmt->execute();
$app_result = $app_stmt->get_result();

if ($app_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid app token']);
    exit;
}

$app = $app_result->fetch_assoc();
$app_id = $app['id'];

// Ensure user belongs to this app
$user_stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND app_id = ?");
$user_stmt->bind_param("si", $username, $app_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found in this app']);
    exit;
}

// Reset HWID
$reset_stmt = $conn->prepare("UPDATE users SET hwid = NULL WHERE username = ? AND app_id = ?");
$reset_stmt->bind_param("si", $username, $app_id);
$reset_stmt->execute();

echo json_encode(['success' => true, 'message' => 'HWID reset successfully']);

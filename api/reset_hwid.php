<?php
// Set proper headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    require_once __DIR__ . '/../db.php'; // database connection
    
    // Secret admin key (keep this secure!)
    $admin_secret = '12345'; // replace with strong key
    
    // Check for required POST fields
    if (!isset($_POST['username'], $_POST['app_token'], $_POST['admin_secret'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Missing required parameters',
            'required' => ['username', 'app_token', 'admin_secret']
        ]);
        exit;
    }
    
    $username = trim($_POST['username']);
    $app_token = trim($_POST['app_token']);
    $admin_secret_input = $_POST['admin_secret'];
    
    // Validate input
    if (empty($username) || empty($app_token) || empty($admin_secret_input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Parameters cannot be empty']);
        exit;
    }
    
    // Check admin secret
    if ($admin_secret_input !== $admin_secret) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid admin secret']);
        exit;
    }
    
    // Use the correct database connection variable ($mysqli instead of $conn)
    if (!isset($mysqli)) {
        throw new Exception('Database connection not available');
    }
    
    // Get app ID from token
    $app_stmt = $mysqli->prepare("SELECT id, name FROM apps WHERE api_token = ?");
    if (!$app_stmt) {
        throw new Exception('Database prepare failed: ' . $mysqli->error);
    }
    
    $app_stmt->bind_param("s", $app_token);
    $app_stmt->execute();
    $app_result = $app_stmt->get_result();
    
    if ($app_result->num_rows === 0) {
        $app_stmt->close();
        http_response_code(404);
        echo json_encode(['error' => 'Invalid app token']);
        exit;
    }
    
    $app = $app_result->fetch_assoc();
    $app_id = $app['id'];
    $app_name = $app['name'];
    $app_stmt->close();
    
    // Check if user exists and belongs to this app
    $user_stmt = $mysqli->prepare("SELECT id, username, hwid FROM users WHERE username = ? AND app_id = ?");
    if (!$user_stmt) {
        throw new Exception('Database prepare failed: ' . $mysqli->error);
    }
    
    $user_stmt->bind_param("si", $username, $app_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        $user_stmt->close();
        http_response_code(404);
        echo json_encode([
            'error' => 'User not found in this app',
            'username' => $username,
            'app' => $app_name
        ]);
        exit;
    }
    
    $user = $user_result->fetch_assoc();
    $user_id = $user['id'];
    $current_hwid = $user['hwid'];
    $user_stmt->close();
    
    // Check if HWID is already null
    if (empty($current_hwid)) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'HWID was already empty for this user',
            'username' => $username,
            'app' => $app_name
        ]);
        exit;
    }
    
    // Reset HWID
    $reset_stmt = $mysqli->prepare("UPDATE users SET hwid = NULL WHERE id = ? AND app_id = ?");
    if (!$reset_stmt) {
        throw new Exception('Database prepare failed: ' . $mysqli->error);
    }
    
    $reset_stmt->bind_param("ii", $user_id, $app_id);
    $success = $reset_stmt->execute();
    
    if (!$success) {
        $reset_stmt->close();
        throw new Exception('Failed to reset HWID: ' . $mysqli->error);
    }
    
    $affected_rows = $mysqli->affected_rows;
    $reset_stmt->close();
    
    if ($affected_rows === 0) {
        http_response_code(500);
        echo json_encode([
            'error' => 'No rows affected - HWID reset may have failed',
            'username' => $username
        ]);
        exit;
    }
    
    // Log the action if logging function exists
    if (function_exists('logAction')) {
        logAction("HWID Reset via API - User: $username, App: $app_name");
    }
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'HWID reset successfully',
        'data' => [
            'username' => $username,
            'app' => $app_name,
            'app_id' => $app_id,
            'previous_hwid' => $current_hwid,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("HWID Reset API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'An unexpected error occurred while processing your request'
    ]);
}
?>
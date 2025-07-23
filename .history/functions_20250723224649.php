<?php
// Auto-start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Load data from a JSON file
 */
function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

/**
 * Save data to a JSON file
 */
function saveJson($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Log actions with IP and timestamp to data/logs.json
 */
function logAction($event) {
    $logFile = __DIR__ . '/data/logs.json';
    $logs = loadJson($logFile);
    $logs[] = [
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
        'time' => date('Y-m-d H:i:s'),
    ];
    saveJson($logFile, $logs);
}

/**
 * Require login — supports both page and AJAX requests
 */
function requireLogin() {
    if (!isset($_SESSION['admin'])) {
        // If AJAX request, return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit;
        } else {
            // Else redirect to login page
            header("Location: login.php");
            exit;
        }
    }
}

/**
 * Paginate an array of items
 */
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

/**
 * Generate a secure random API token (default: 32 characters)
 */
function generateApiToken($length = 32) {
    return bin2hex(random_bytes($length / 2)); // 32 hex chars from 16 bytes
}

/**
 * Validate API token for a given app_id
 */
function validateApiToken($app_id, $token) {
    $apps = loadJson(__DIR__ . '/data/apps.json');
    foreach ($apps as $app) {
        if ($app['id'] === $app_id && isset($app['api_token']) && hash_equals($app['api_token'], $token)) {
            return $app;
        }
    }
    return false;
}

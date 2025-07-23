<?php
require_once __DIR__ . '/admin/functions.php';

//session_start();

function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function logAction($event) {
    $logFile = __DIR__ . '/../data/logs.json';
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
    //session_start();
    if (!isset($_SESSION['admin'])) {
        header("Location: admin/login.php"); // <-- use this for root scripts
        exit();
    }
}

// Generate random API token
function generateApiToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Validate API token for given app_id, returns app data or false
function validateApiToken($app_id, $token) {
    $apps = loadJson(__DIR__ . '/../data/apps.json');
    foreach ($apps as $app) {
        if ($app['id'] === $app_id && isset($app['api_token']) && hash_equals($app['api_token'], $token)) {
            return $app;
        }
    }
    return false;
}

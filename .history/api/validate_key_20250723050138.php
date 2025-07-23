<?php
header('Content-Type: application/json');

// Write raw input to a log file to see if request comes
$input = file_get_contents('php://input');
file_put_contents(__DIR__ . '/../logs/api_log.txt', "Received: " . $input . "\n", FILE_APPEND);

echo json_encode(["success" => true, "message" => "Received your request"]);
exit;

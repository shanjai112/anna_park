<?php
header("Content-Type: application/json");

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

$source = $data['source'] ?? 'UNKNOWN';
$error  = $data['error'] ?? 'No error message';

// Log file path
$logFile = __DIR__ . "/error_log.txt";

// Log format
$logMessage = sprintf(
    "[%s] [%s] %s\n",
    date("Y-m-d H:i:s"),
    $source,
    $error
);

// Write to TXT file
file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

// Response back to JS
echo json_encode(["success" => true]);
exit;

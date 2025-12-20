<?php
// Archivo de prueba simple sin dependencias de Dolibarr
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$logFile = __DIR__ . '/../backups/test_simple.log';
$data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'script' => basename(__FILE__),
    'get_params' => $_GET,
    'post_params' => $_POST,
    'server_info' => [
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'N/A',
        'php_self' => $_SERVER['PHP_SELF'] ?? 'N/A',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'http_host' => $_SERVER['HTTP_HOST'] ?? 'N/A',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    ],
    'working_directory' => getcwd(),
    'script_directory' => __DIR__,
    'file_exists' => file_exists($logFile),
];

file_put_contents($logFile, json_encode($data, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => 'Test simple ejecutado correctamente',
    'timestamp' => date('Y-m-d H:i:s'),
    'log_file_created' => file_exists($logFile)
]);

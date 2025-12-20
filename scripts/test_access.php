<?php
// Archivo de prueba para verificar acceso
$logFile = __DIR__ . '/../backups/test_access.log';
$data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'script' => basename(__FILE__),
    'get' => $_GET,
    'post' => $_POST,
    'server' => [
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
        'php_self' => $_SERVER['PHP_SELF'] ?? '',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
    ]
];

file_put_contents($logFile, json_encode($data, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Test access logged', 'timestamp' => date('Y-m-d H:i:s')]);

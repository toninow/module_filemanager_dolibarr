<?php
/**
 * Script de prueba para eliminación de chunks
 */

// Headers para JSON y CORS
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

echo json_encode([
    'success' => true,
    'message' => 'Test script funcionando correctamente',
    'timestamp' => time(),
    'method' => $_SERVER['REQUEST_METHOD'],
    'post_data' => $_POST,
    'get_data' => $_GET
]);
?>

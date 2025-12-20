<?php
// Obtener hora del servidor para sincronización de tiempo
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

echo json_encode([
    'success' => true,
    'server_time' => time(),
    'server_time_formatted' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get()
]);
?>



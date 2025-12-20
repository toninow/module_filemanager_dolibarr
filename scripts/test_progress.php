<?php
// Script de prueba para verificar el archivo de progreso
header('Content-Type: application/json; charset=utf-8');

$tempDir = sys_get_temp_dir();
$sessionId = session_id();
$progressFile = $tempDir . '/analysis_progress_' . $sessionId . '.json';

$result = [
    'temp_dir' => $tempDir,
    'session_id' => $sessionId,
    'progress_file' => $progressFile,
    'file_exists' => file_exists($progressFile),
    'file_readable' => is_readable($progressFile),
    'file_writable' => is_writable($tempDir)
];

if (file_exists($progressFile)) {
    $content = @file_get_contents($progressFile);
    $result['file_size'] = strlen($content);
    $result['file_content'] = json_decode($content, true);
}

echo json_encode($result, JSON_PRETTY_PRINT);

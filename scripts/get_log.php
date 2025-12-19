<?php
// Obtener contenido del log de backup
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$backupId = $_GET['backup_id'] ?? '';

if (empty($backupId) || !preg_match('/^[0-9]+$/', $backupId)) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$backupDir = __DIR__ . '/../backups';
$logFile = $backupDir . '/backup_' . $backupId . '.log';

if (!file_exists($logFile)) {
    echo json_encode(['success' => false, 'error' => 'Log no encontrado']);
    exit;
}

$content = @file_get_contents($logFile);

echo json_encode([
    'success' => true,
    'log' => $content,
    'size' => strlen($content)
]);



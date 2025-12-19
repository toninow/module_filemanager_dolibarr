<?php
/**
 * Limpia los archivos de lock para permitir que un backup se reanude
 * Este script NO borra los checkpoints ni el ZIP incompleto
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Detectar directorio de backups
$backupDir = __DIR__ . '/../backups';

$cleaned = array();
$errors = array();

// Solo limpiar locks, NO checkpoints ni ZIPs incompletos
$lockFiles = array(
    'manual_backup.lock',
    'auto_backup.lock'
);

foreach ($lockFiles as $lockFile) {
    $fullPath = $backupDir . '/' . $lockFile;
    if (file_exists($fullPath)) {
        if (@unlink($fullPath)) {
            $cleaned[] = $lockFile;
        } else {
            $errors[] = "No se pudo eliminar: $lockFile";
        }
    }
}

// También limpiar heartbeats antiguos (más de 5 minutos)
$heartbeatFiles = glob($backupDir . '/heartbeat_*.txt');
foreach ($heartbeatFiles as $hbFile) {
    $fileAge = time() - filemtime($hbFile);
    if ($fileAge > 300) { // Más de 5 minutos
        if (@unlink($hbFile)) {
            $cleaned[] = basename($hbFile);
        }
    }
}

echo json_encode(array(
    'success' => empty($errors),
    'cleaned' => $cleaned,
    'errors' => $errors,
    'message' => empty($errors) 
        ? 'Locks limpiados: ' . count($cleaned) . ' archivos'
        : 'Errores: ' . implode(', ', $errors)
));

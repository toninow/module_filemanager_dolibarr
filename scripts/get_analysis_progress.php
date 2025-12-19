<?php
// Obtener progreso del análisis de archivos en tiempo real

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Archivo de progreso
$progressFile = __DIR__ . '/../backups/analysis_progress.json';
// DESHABILITADO: Archivo de debug que causaba crecimiento infinito
// $debugLogFile = __DIR__ . '/../backups/debug_get_progress.log';

if (!file_exists($progressFile)) {
    // LOG DESHABILITADO PARA EVITAR CRECIMIENTO INFINITO
    // @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " [GET_PROGRESS] Archivo no existe: $progressFile\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'running' => false,
        'message' => 'No hay análisis en progreso'
    ]);
    exit;
}

$progressContent = @file_get_contents($progressFile);
$progress = @json_decode($progressContent, true);

if (!$progress) {
    // LOG DESHABILITADO PARA EVITAR CRECIMIENTO INFINITO
    // @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " [GET_PROGRESS] Error leyendo progreso. Contenido: " . substr($progressContent, 0, 200) . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'running' => false,
        'message' => 'Error leyendo progreso'
    ]);
    exit;
}

// LOG DESHABILITADO PARA EVITAR CRECIMIENTO INFINITO
// @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " [GET_PROGRESS] Progreso leído - files: " . ($progress['total_files'] ?? 0) . ", folders: " . ($progress['total_folders'] ?? 0) . ", last_update: " . ($progress['last_update'] ?? 0) . "\n", FILE_APPEND);

// Verificar si el análisis sigue activo (última actualización hace menos de 2 minutos)
// Aumentado a 2 minutos para dar más margen en entornos restringidos donde puede tardar más
$lastUpdate = $progress['last_update'] ?? 0;
$timeSinceUpdate = time() - $lastUpdate;
$isRunning = $timeSinceUpdate < 120;

// Si está marcado como completado, no está corriendo PERO mantener los valores
if (isset($progress['completed']) && $progress['completed']) {
    $isRunning = false;
    // LOG DESHABILITADO PARA EVITAR CRECIMIENTO INFINITO
    // @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " [GET_PROGRESS] Marcado como completado - files: " . ($progress['total_files'] ?? 0) . ", folders: " . ($progress['total_folders'] ?? 0) . "\n", FILE_APPEND);
}

// Si el archivo es muy antiguo (más de 5 minutos), considerarlo obsoleto y no correr
if ($timeSinceUpdate > 300) {
    $isRunning = false;
    // LOG DESHABILITADO PARA EVITAR CRECIMIENTO INFINITO
    // @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " [GET_PROGRESS] Archivo obsoleto (más de 5 minutos), marcando como no corriendo\n", FILE_APPEND);
}

// Si el archivo existe pero tiene valores 0 y fue actualizado recientemente, está corriendo
if ($isRunning && ($progress['total_files'] == 0 && $progress['total_folders'] == 0)) {
    // El análisis acaba de comenzar, mantener como corriendo
    $isRunning = true;
    // LOG DESHABILITADO PARA EVITAR CRECIMIENTO INFINITO
    // @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " [GET_PROGRESS] Análisis recién iniciado (valores en 0)\n", FILE_APPEND);
}

// LOG DESHABILITADO PARA EVITAR CRECIMIENTO INFINITO
// @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " [GET_PROGRESS] Tiempo desde última actualización: {$timeSinceUpdate}s, running: " . ($isRunning ? 'true' : 'false') . "\n", FILE_APPEND);

echo json_encode([
    'success' => true,
    'running' => $isRunning,
    'stats' => [
        'total_files' => $progress['total_files'] ?? 0,
        'total_folders' => $progress['total_folders'] ?? 0,
        'total_size_mb' => $progress['total_size_mb'] ?? 0
    ],
    'current_path' => $progress['current_path'] ?? '',
    'recent_files' => $progress['recent_files'] ?? [],
    'last_update' => $lastUpdate
]);


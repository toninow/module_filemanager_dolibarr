<?php
/**
 * Limpiar progreso del análisis de archivos
 */

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Incluir entorno de Dolibarr
require_once '../../main.inc.php';

// Verificar permisos
if (!$user->admin) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Función para limpiar archivos temporales de análisis
function cleanupAnalysisFiles() {
    $filesCleaned = 0;
    $errors = [];

    // Archivos a limpiar
    $tempFiles = [
        DOL_DOCUMENT_ROOT . '/documents/admin/temp/analysis_progress.json',
        DOL_DOCUMENT_ROOT . '/documents/admin/temp/backup_analysis_progress.json',
        DOL_DATA_ROOT . '/admin/temp/analysis_progress.json',
        DOL_DATA_ROOT . '/admin/temp/backup_analysis_progress.json'
    ];

    foreach ($tempFiles as $file) {
        if (file_exists($file)) {
            if (@unlink($file)) {
                $filesCleaned++;
            } else {
                $errors[] = "No se pudo eliminar: " . basename($file);
            }
        }
    }

    return ['files_cleaned' => $filesCleaned, 'errors' => $errors];
}

try {
    $result = cleanupAnalysisFiles();

    echo json_encode([
        'success' => true,
        'message' => 'Limpieza completada',
        'files_cleaned' => $result['files_cleaned'],
        'errors' => $result['errors']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en limpieza: ' . $e->getMessage()
    ]);
}
?>
